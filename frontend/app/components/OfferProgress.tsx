'use client';

import { useEffect, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export type Offer = {
  id: number;
  title: string;
  description: string | null;
  min_order_amount: string | number;
  reward_type: 'free_item' | 'flat_discount' | 'percent_discount';
  free_quantity: number;
  discount_value: string | number | null;
  max_discount: string | number | null;
  free_item?: { id: number; name: string; price: string | number } | null;
};

let cache: Offer[] | null = null;
let pending: Promise<Offer[]> | null = null;

function loadOffers(): Promise<Offer[]> {
  if (cache) return Promise.resolve(cache);
  if (!pending) {
    pending = fetch(`${API}/offers`)
      .then(r => r.json())
      .then(j => (cache = Array.isArray(j.data) ? j.data : []))
      .catch(() => (cache = []));
  }
  return pending;
}

export function useOffers(): Offer[] {
  const [offers, setOffers] = useState<Offer[]>(cache || []);
  useEffect(() => { loadOffers().then(setOffers); }, []);
  return offers;
}

export function rewardText(o: Offer): string {
  if (o.reward_type === 'free_item') {
    const q = o.free_quantity > 1 ? `${o.free_quantity}× ` : '';
    return `${q}${o.free_item?.name || 'a free item'} FREE`;
  }
  if (o.reward_type === 'flat_discount') return `₹${Number(o.discount_value).toFixed(0)} OFF`;
  const max = o.max_discount ? ` (up to ₹${Number(o.max_discount).toFixed(0)})` : '';
  return `${Number(o.discount_value).toFixed(0)}% OFF${max}`;
}

/** Same rule as the server: best = qualifying offer with the highest minimum. */
export function computeOffer(subtotal: number, offers: Offer[]) {
  const sorted = [...offers].sort((a, b) => Number(a.min_order_amount) - Number(b.min_order_amount));
  const qualifying = sorted.filter(o => Number(o.min_order_amount) <= subtotal);
  const current = qualifying.length ? qualifying[qualifying.length - 1] : null;
  const next = sorted.find(o => Number(o.min_order_amount) > subtotal) || null;

  let discount = 0;
  if (current?.reward_type === 'flat_discount') discount = Math.min(Number(current.discount_value), subtotal);
  if (current?.reward_type === 'percent_discount') {
    discount = subtotal * Number(current.discount_value) / 100;
    if (current.max_discount) discount = Math.min(discount, Number(current.max_discount));
  }
  discount = Math.round(discount * 100) / 100;

  const freeLine = current?.reward_type === 'free_item' && current.free_item
    ? { name: current.free_item.name, quantity: Math.max(1, current.free_quantity) }
    : null;

  return { current, next, discount, freeLine, amountToNext: next ? Number(next.min_order_amount) - subtotal : 0 };
}

export default function OfferProgress({ subtotal, compact = false, disabledReason }: { subtotal: number; compact?: boolean; disabledReason?: string | null }) {
  const offers = useOffers();
  if (offers.length === 0 || subtotal <= 0) return null;
  const { current } = computeOffer(subtotal, offers);
  const sorted = [...offers].sort((a, b) => Number(a.min_order_amount) - Number(b.min_order_amount));
  const upcoming = sorted.filter(o => Number(o.min_order_amount) > subtotal);
  if (!current && upcoming.length === 0) return null;

  return (
    <div style={{
      background: 'linear-gradient(135deg, rgba(230,168,23,0.12), rgba(22,163,74,0.08))',
      border: '1px dashed var(--primary)',
      borderRadius: 12,
      padding: compact ? '10px 12px' : '12px 14px',
      marginBottom: 12,
      fontSize: '0.85rem',
      display: 'flex',
      flexDirection: 'column',
      gap: 10,
      opacity: disabledReason ? 0.6 : 1,
    }}>
      <div style={{ fontWeight: 800, fontSize: '0.78rem', letterSpacing: '0.04em', color: 'var(--text-muted)' }}>
        🎁 OFFERS FOR YOU {upcoming.length + (current ? 1 : 0) > 1 ? `(${upcoming.length + (current ? 1 : 0)})` : ''}
      </div>

      {current && (
        <div style={{ fontWeight: 800, color: 'var(--success)' }}>
          ✅ Unlocked: {rewardText(current)}
          <div style={{ fontWeight: 500, fontSize: '0.75rem', color: 'var(--text-muted)' }}>{current.title}</div>
        </div>
      )}

      {upcoming.map(o => {
        const min = Number(o.min_order_amount);
        const pct = Math.min(100, (subtotal / min) * 100);
        return (
          <div key={o.id}>
            <div style={{ fontWeight: 700, color: 'var(--text)' }}>
              Add <span style={{ color: 'var(--primary-dark)' }}>₹{Math.ceil(min - subtotal)}</span> more → <span style={{ color: 'var(--primary-dark)' }}>{rewardText(o)}</span>
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
              On orders above ₹{min.toFixed(0)}{current ? ' (replaces current offer)' : ''}
            </div>
            <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 99, marginTop: 6, overflow: 'hidden' }}>
              <div style={{ width: `${pct}%`, height: '100%', background: 'var(--primary)', borderRadius: 99, transition: 'width 0.3s' }} />
            </div>
          </div>
        );
      })}

      {disabledReason && (
        <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--danger)' }}>{disabledReason}</div>
      )}
    </div>
  );
}
