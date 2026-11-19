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

export default function OfferProgress({ subtotal, compact = false, disabledReason, variant = 'light', showWhenEmpty = false }: { subtotal: number; compact?: boolean; disabledReason?: string | null; variant?: 'light' | 'dark'; showWhenEmpty?: boolean }) {
  const offers = useOffers();
  if (offers.length === 0 || (subtotal <= 0 && !showWhenEmpty)) return null;
  const { current } = computeOffer(subtotal, offers);
  const sorted = [...offers].sort((a, b) => Number(a.min_order_amount) - Number(b.min_order_amount));
  const upcoming = sorted.filter(o => Number(o.min_order_amount) > subtotal);
  if (!current && upcoming.length === 0) return null;

  const dark = variant === 'dark';
  const c = dark
    ? { bg: '#2C1A00', border: 'none', text: '#FFF8E7', muted: '#E9D9B8', amount: '#E6A817', track: 'rgba(255,248,231,0.18)', bar: '#E6A817', ok: '#86EFAC', bad: '#FCA5A5' }
    : { bg: 'linear-gradient(135deg, rgba(230,168,23,0.12), rgba(22,163,74,0.08))', border: '1px dashed var(--primary)', text: 'var(--text)', muted: 'var(--text-muted)', amount: 'var(--primary-text, #8A5A00)', track: 'var(--bg-elevated)', bar: 'var(--primary)', ok: 'var(--success)', bad: 'var(--danger)' };
  const total = upcoming.length + (current ? 1 : 0);

  return (
    <div style={{
      background: c.bg,
      border: c.border,
      color: c.text,
      borderRadius: 16,
      padding: compact ? '10px 12px' : '14px 16px',
      marginBottom: 12,
      fontSize: '0.85rem',
      display: 'flex',
      flexDirection: 'column',
      gap: 10,
      opacity: disabledReason ? 0.6 : 1,
    }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
        <span style={{ fontWeight: 800, fontSize: '0.75rem', letterSpacing: '0.05em', color: c.muted }}>OFFERS FOR YOU</span>
        {total > 1 && <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 8px', borderRadius: 999, background: dark ? 'rgba(255,248,231,0.12)' : 'rgba(230,168,23,0.15)' }}>{total} offers</span>}
      </div>

      {current && (
        <div style={{ fontWeight: 800, color: c.ok }}>
          Unlocked: {rewardText(current)}
          <div style={{ fontWeight: 500, fontSize: '0.75rem', color: c.muted }}>{current.title}</div>
        </div>
      )}

      {upcoming.map((o, idx) => {
        const min = Number(o.min_order_amount);
        const pct = Math.min(100, (subtotal / min) * 100);
        return (
          <div key={o.id}>
            <div style={{ fontWeight: 700, fontSize: idx === 0 ? '0.92rem' : '0.85rem' }}>
              {subtotal > 0
                ? <>Add <span style={{ color: c.amount }}>₹{Math.ceil(min - subtotal)}</span> more → <span style={{ color: c.amount }}>{rewardText(o)}</span></>
                : <>Order ₹{min.toFixed(0)}+ → <span style={{ color: c.amount }}>{rewardText(o)}</span></>}
            </div>
            <div style={{ fontSize: '0.75rem', color: c.muted }}>
              On orders above ₹{min.toFixed(0)}{current ? ' (replaces current offer)' : ''}
            </div>
            {subtotal > 0 && (
              <div style={{ height: 6, background: c.track, borderRadius: 99, marginTop: 6, overflow: 'hidden' }}>
                <div style={{ width: `${pct}%`, height: '100%', background: c.bar, borderRadius: 99, transition: 'width 0.3s' }} />
              </div>
            )}
          </div>
        );
      })}

      {disabledReason && (
        <div style={{ fontSize: '0.75rem', fontWeight: 700, color: c.bad }}>{disabledReason}</div>
      )}
    </div>
  );
}
