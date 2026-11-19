'use client';

import { useEffect, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export type LoyaltySettings = {
  enabled: boolean;
  earn_per_100: number;
  point_value: number;
  min_redeem: number;
  max_redeem_percent: number;
};

export function getCustomerToken(): string | null {
  try { return localStorage.getItem('customer_token'); } catch { return null; }
}

/** Settings (public) + the logged-in customer's balance. */
export function useLoyalty(token: string | null) {
  const [settings, setSettings] = useState<LoyaltySettings | null>(null);
  const [balance, setBalance] = useState<number | null>(null);
  const [history, setHistory] = useState<any[]>([]);

  useEffect(() => {
    fetch(`${API}/loyalty/settings`).then(r => r.json()).then(setSettings).catch(() => {});
  }, []);

  useEffect(() => {
    if (!token) { setBalance(null); return; }
    fetch(`${API}/loyalty`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then(r => (r.ok ? r.json() : null))
      .then(d => {
        if (d) { setBalance(Number(d.balance) || 0); setHistory(d.history || []); }
      })
      .catch(() => {});
  }, [token]);

  return { settings, balance, history };
}

export function pointsToEarn(amount: number, s: LoyaltySettings | null): number {
  return s?.enabled ? Math.floor((amount / 100) * Number(s.earn_per_100)) : 0;
}

export function redeemable(balance: number, amount: number, s: LoyaltySettings | null) {
  if (!s?.enabled || balance <= 0 || Number(s.point_value) <= 0) return { points: 0, discount: 0 };
  const maxByOrder = Math.floor((amount * Number(s.max_redeem_percent) / 100) / Number(s.point_value));
  const points = Math.min(balance, maxByOrder);
  if (points < Number(s.min_redeem)) return { points: 0, discount: 0 };
  return { points, discount: Math.round(points * Number(s.point_value) * 100) / 100 };
}

/** Balance card for the Orders/History page. */
export function LoyaltyCard({ token }: { token: string | null }) {
  const { settings, balance, history } = useLoyalty(token);
  const [open, setOpen] = useState(false);
  if (!settings?.enabled || balance === null) return null;

  const TYPE: Record<string, string> = { earn: 'Earned', redeem: 'Used', refund: 'Returned', reverse: 'Removed', adjust: 'Bonus / adjustment' };

  return (
    <div id="points" className="tracking-card" style={{ scrollMarginTop: 90, marginBottom: '1.5rem', background: 'linear-gradient(135deg, rgba(230,168,23,0.15), rgba(230,168,23,0.03))', border: '1px solid var(--primary)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <div style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-muted)' }}>⭐ YOUR LOYALTY POINTS</div>
          <div style={{ fontSize: '1.8rem', fontWeight: 900 }}>{balance} <span style={{ fontSize: '0.95rem', fontWeight: 600, color: 'var(--text-muted)' }}>= ₹{(balance * Number(settings.point_value)).toFixed(0)}</span></div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
            Earn {Number(settings.earn_per_100)} points on every ₹100. Use {settings.min_redeem}+ points at checkout.
          </div>
        </div>
        {history.length > 0 && (
          <button type="button" className="btn-secondary" onClick={() => setOpen(o => !o)} style={{ padding: '8px 14px' }}>
            {open ? 'Hide history' : 'Points history'}
          </button>
        )}
      </div>
      {open && (
        <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 6 }}>
          {history.map((h, i) => (
            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', padding: '6px 0', borderTop: '1px solid var(--border)' }}>
              <span>{TYPE[h.type] || h.type} · <span style={{ color: 'var(--text-muted)' }}>{new Date(h.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}</span></span>
              <strong style={{ color: h.points > 0 ? 'var(--success)' : 'var(--danger)' }}>{h.points > 0 ? '+' : ''}{h.points}</strong>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
