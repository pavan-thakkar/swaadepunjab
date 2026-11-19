'use client';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export interface RazorpayCheckout {
  key: string;
  order_id: string;
  amount: number;
  currency: string;
  name: string;
  description: string;
  prefill: { name?: string; email?: string; contact?: string };
  test_mode?: boolean;
}

export type PayResult =
  | { status: 'paid'; order: any }
  | { status: 'dismissed' }
  | { status: 'failed'; message: string };

let loading: Promise<boolean> | null = null;

/** Load Razorpay Checkout once. */
export function loadRazorpay(): Promise<boolean> {
  if (typeof window === 'undefined') return Promise.resolve(false);
  if ((window as any).Razorpay) return Promise.resolve(true);
  if (!loading) {
    loading = new Promise(resolve => {
      const s = document.createElement('script');
      s.src = 'https://checkout.razorpay.com/v1/checkout.js';
      s.async = true;
      s.onload = () => resolve(!!(window as any).Razorpay);
      s.onerror = () => { loading = null; resolve(false); };
      document.body.appendChild(s);
    });
  }
  return loading;
}

/** Is online payment switched on in admin? */
export async function getPaymentSettings(): Promise<{ razorpay: boolean; test_mode: boolean }> {
  try {
    const r = await fetch(`${API}/payment-settings`, { cache: 'no-store' });
    if (!r.ok) return { razorpay: false, test_mode: false };
    const j = await r.json();
    return { razorpay: !!j.razorpay, test_mode: !!j.test_mode };
  } catch {
    return { razorpay: false, test_mode: false };
  }
}

function authHeaders(orderToken: string | null, customerToken?: string | null): Record<string, string> {
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(orderToken ? { 'X-Order-Token': orderToken } : {}),
    ...(customerToken ? { Authorization: `Bearer ${customerToken}` } : {}),
  };
}

/** Ask the server for a Razorpay order (retry after closing the popup). */
export async function startPayment(orderNumber: string, orderToken: string | null, customerToken?: string | null): Promise<{ payment?: RazorpayCheckout; paid?: boolean; order?: any; message?: string }> {
  try {
    const r = await fetch(`${API}/orders/${encodeURIComponent(orderNumber)}/payment`, {
      method: 'POST', headers: authHeaders(orderToken, customerToken), body: '{}',
    });
    const j = await r.json().catch(() => ({}));
    if (j.paid) return { paid: true, order: j.data };
    if (!r.ok) return { message: j.message || 'Could not start the payment. Please try again.' };
    return { payment: j.payment };
  } catch {
    return { message: 'Network error. Please check your internet and try again.' };
  }
}

/**
 * Open Razorpay Checkout. After the customer pays, the server checks Razorpay's signature —
 * the order is only marked paid when the server confirms it.
 */
export async function openRazorpay(
  checkout: RazorpayCheckout,
  orderNumber: string,
  orderToken: string | null,
  customerToken?: string | null,
): Promise<PayResult> {
  const ok = await loadRazorpay();
  if (!ok) return { status: 'failed', message: 'Could not load the payment window. Please check your internet and try again.' };

  return new Promise<PayResult>(resolve => {
    let settled = false;
    const done = (r: PayResult) => { if (!settled) { settled = true; resolve(r); } };

    const rzp = new (window as any).Razorpay({
      key: checkout.key,
      order_id: checkout.order_id,
      amount: checkout.amount,
      currency: checkout.currency,
      name: checkout.name,
      description: checkout.description,
      image: `${window.location.origin}/brand/icon-192.png`,
      prefill: checkout.prefill,
      theme: { color: '#E6A817' },
      retry: { enabled: true, max_count: 3 },
      handler: async (resp: any) => {
        try {
          const r = await fetch(`${API}/orders/${encodeURIComponent(orderNumber)}/payment/verify`, {
            method: 'POST',
            headers: authHeaders(orderToken, customerToken),
            body: JSON.stringify({
              razorpay_order_id: resp.razorpay_order_id,
              razorpay_payment_id: resp.razorpay_payment_id,
              razorpay_signature: resp.razorpay_signature,
            }),
          });
          const j = await r.json().catch(() => ({}));
          if (r.ok) done({ status: 'paid', order: j.data });
          else done({ status: 'failed', message: j.message || 'We could not confirm the payment yet. It will update automatically in a few minutes.' });
        } catch {
          done({ status: 'failed', message: 'Payment done, but we could not reach the server to confirm. It will update automatically in a few minutes.' });
        }
      },
      modal: { ondismiss: () => done({ status: 'dismissed' }), confirm_close: true },
    });
    rzp.on('payment.failed', () => { /* Razorpay shows the error and lets the customer retry inside the popup */ });
    rzp.open();
  });
}
