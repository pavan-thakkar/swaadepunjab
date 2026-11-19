'use client';

import { useEffect, useState } from 'react';
import { useCart, MenuItem } from '../context/CartContext';
import { useOffers, computeOffer, rewardText } from './OfferProgress';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';
let menuCache: MenuItem[] | null = null;

/** Suggests 3 dishes that close the gap to the next offer, with one-tap add. */
export default function QuickAdds({ subtotal, dark = true }: { subtotal: number; dark?: boolean }) {
  const { state, dispatch } = useCart();
  const offers = useOffers();
  const [menu, setMenu] = useState<MenuItem[]>(menuCache || []);

  useEffect(() => {
    if (menuCache) return;
    fetch(`${API}/menu`).then(r => r.json()).then(j => { menuCache = Array.isArray(j.data) ? j.data : []; setMenu(menuCache!); }).catch(() => {});
  }, []);

  const { next } = computeOffer(subtotal, offers);
  if (!next || subtotal <= 0 || menu.length === 0) return null;
  const gap = Number(next.min_order_amount) - subtotal;
  const inCart = new Set(state.items.map(i => i.menu_item_id));
  const pool = menu.filter(m => m.is_available !== false && !inCart.has(m.id) && Number(m.price) > 0);
  const closing = pool.filter(m => Number(m.price) >= gap).sort((a, b) => Number(a.price) - Number(b.price));
  const picks = (closing.length ? closing : [...pool].sort((a, b) => Number(b.price) - Number(a.price))).slice(0, 3);
  if (picks.length === 0) return null;

  const c = dark
    ? { text: '#FFF8E7', muted: '#E9D9B8', bg: 'rgba(255,248,231,0.08)', border: 'rgba(255,248,231,0.25)', wrap: '#2C1A00' }
    : { text: 'var(--text)', muted: 'var(--text-muted)', bg: '#fff', border: 'var(--border)', wrap: 'transparent' };

  return (
    <div style={{ background: c.wrap, borderRadius: 16, padding: dark ? '0 14px 14px' : 0, marginTop: dark ? -18 : 0, marginBottom: 12 }}>
      <div style={{ fontSize: 12, color: c.muted, marginBottom: 8, paddingTop: dark ? 4 : 0 }}>Quick adds to unlock {rewardText(next)}</div>
      <div style={{ display: 'flex', gap: 8, overflowX: 'auto', scrollbarWidth: 'none' }}>
        {picks.map(m => (
          <button
            key={m.id}
            type="button"
            onClick={() => dispatch({ type: 'ADD', item: m })}
            style={{ flex: '0 0 auto', display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 2, background: c.bg, border: `1px solid ${c.border}`, borderRadius: 12, padding: '8px 12px', color: c.text, font: 'inherit', cursor: 'pointer', maxWidth: 180, textAlign: 'left', minHeight: 44 }}
          >
            <span style={{ fontSize: 13, fontWeight: 700, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 156 }}>{m.name}</span>
            <span style={{ fontSize: 12, color: c.muted }}>₹{Number(m.price).toFixed(0)} · ADD +</span>
          </button>
        ))}
      </div>
    </div>
  );
}
