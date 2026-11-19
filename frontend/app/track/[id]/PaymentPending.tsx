'use client';

import { useEffect, useState } from 'react';
import { useCart } from '../../context/CartContext';
import { openRazorpay, startPayment } from '../../lib/razorpay';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

/** Shown on the tracking page while an online order is waiting for payment. */
export default function PaymentPending({ order, onUpdated }: { order: any; onUpdated: (o: any) => void }) {
  const { customerToken } = useCart();
  const [busy, setBusy] = useState<'pay' | 'cash' | 'check' | null>(null);
  const [msg, setMsg] = useState<string | null>(null);

  const token = (() => {
    try { return localStorage.getItem(`order_edit_${order.order_number}`); } catch { return null; }
  })();
  const canAct = !!token || !!order.is_owner;

  const pay = async () => {
    setBusy('pay'); setMsg(null);
    const start = await startPayment(order.order_number, token, customerToken);
    if (start.paid) { onUpdated({ ...order, ...start.order, is_owner: true }); setBusy(null); return; }
    if (!start.payment) { setMsg(start.message || 'Could not start the payment.'); setBusy(null); return; }
    const r = await openRazorpay(start.payment, order.order_number, token, customerToken);
    if (r.status === 'paid') onUpdated({ ...order, ...r.order, is_owner: true });
    else if (r.status === 'failed') setMsg(r.message);
    setBusy(null);
  };

  // Ask Razorpay right now whether the payment went through
  const check = async (quiet = false) => {
    if (!quiet) { setBusy('check'); setMsg(null); }
    try {
      const res = await fetch(`${API}/orders/${encodeURIComponent(order.order_number)}/payment/check`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', Accept: 'application/json',
          ...(token ? { 'X-Order-Token': token } : {}),
          ...(customerToken ? { Authorization: `Bearer ${customerToken}` } : {}),
        },
        body: '{}',
      });
      const j = await res.json().catch(() => ({}));
      if (res.ok && j.result === 'paid') onUpdated({ ...order, ...j.data, is_owner: true });
      else if (!quiet) setMsg(j.message || 'Could not check the payment. Please try again.');
    } catch {
      if (!quiet) setMsg('Network error. Please try again.');
    }
    if (!quiet) setBusy(null);
  };

  // Opening this page (e.g. after paying in the UPI app): check once straight away
  useEffect(() => {
    if (canAct) check(true);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const payCash = async () => {
    setBusy('cash'); setMsg(null);
    try {
      const res = await fetch(`${API}/orders/${encodeURIComponent(order.order_number)}/pay-with-cash`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', Accept: 'application/json',
          ...(token ? { 'X-Order-Token': token } : {}),
          ...(customerToken ? { Authorization: `Bearer ${customerToken}` } : {}),
        },
        body: '{}',
      });
      const j = await res.json().catch(() => ({}));
      if (res.ok) onUpdated({ ...order, ...j.data, is_owner: true });
      else setMsg(j.message || 'Could not change the payment method.');
    } catch {
      setMsg('Network error. Please try again.');
    }
    setBusy(null);
  };

  const minsLeft = Math.max(0, 30 - Math.floor((Date.now() - new Date(order.created_at).getTime()) / 60000));
  const cashLabel = (order.order_type ?? 'delivery') === 'delivery' ? 'Pay cash on delivery instead' : 'Pay at the counter instead';

  return (
    <div className="paypend" role="status">
      <div className="paypend-icon" aria-hidden="true">💳</div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div className="paypend-title">Payment not completed yet</div>
        <p className="paypend-text">
          Your order is saved but not sent to the kitchen until it is paid.
          {minsLeft > 0 ? ` Please pay within ${minsLeft} min, or it will be cancelled automatically.` : ''}
          {' '}If money was already deducted, it will update here in a few minutes.
        </p>
        {canAct ? (
          <div className="paypend-actions">
            <button type="button" className="mp-btn" onClick={pay} disabled={!!busy}>
              {busy === 'pay' ? 'Opening…' : `Pay ₹${Number(order.total).toFixed(0)} now`}
            </button>
            <button type="button" className="mp-btn mp-btn-light" onClick={payCash} disabled={!!busy}>
              {busy === 'cash' ? 'Updating…' : cashLabel}
            </button>
            <button type="button" className="paypend-check" onClick={() => check(false)} disabled={!!busy}>
              {busy === 'check' ? 'Checking with Razorpay…' : '✓ I have already paid — check again'}
            </button>
          </div>
        ) : (
          <p className="paypend-text">Open this page on the phone that placed the order to pay.</p>
        )}
        {msg && <div className="paypend-msg" role="alert">{msg}</div>}
      </div>
    </div>
  );
}
