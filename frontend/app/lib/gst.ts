'use client';

import { useEffect, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export type GstSettings = { enabled: boolean; rate: number; mode: 'exclusive' | 'inclusive' };
export type GstCalc = { amount: number; add: number; rate: number; mode: 'exclusive' | 'inclusive' } | null;

let cache: GstSettings | null = null;
let pending: Promise<GstSettings | null> | null = null;

function load(): Promise<GstSettings | null> {
  if (cache) return Promise.resolve(cache);
  if (!pending) {
    pending = fetch(`${API}/gst-settings`, { cache: 'no-store' })
      .then(r => (r.ok ? r.json() : null))
      .then(d => {
        if (!d) return null;
        cache = { enabled: !!d.enabled, rate: Number(d.rate) || 0, mode: d.mode === 'inclusive' ? 'inclusive' : 'exclusive' };
        return cache;
      })
      .catch(() => null)
      .finally(() => { pending = null; });
  }
  return pending;
}

/** GST settings from Admin → Store & Receipt Config. */
export function useGst(): GstSettings | null {
  const [s, setS] = useState<GstSettings | null>(cache);
  useEffect(() => { load().then(v => v && setS(v)); }, []);
  return s;
}

/** Same rule as the server (app/Services/Gst.php): GST on the food amount after discounts. */
export function calcGst(taxable: number, s: GstSettings | null): GstCalc {
  if (!s || !s.enabled || s.rate <= 0) return null;
  const t = Math.max(0, taxable);
  const raw = s.mode === 'inclusive' ? t - t / (1 + s.rate / 100) : (t * s.rate) / 100;
  const amount = Math.round(raw * 100) / 100;
  return { amount, add: s.mode === 'exclusive' ? amount : 0, rate: s.rate, mode: s.mode };
}

export function gstLabel(g: NonNullable<GstCalc>): string {
  const r = Number(g.rate.toFixed(2)).toString();
  return g.mode === 'inclusive' ? `GST ${r}% (included in price)` : `GST ${r}%`;
}

/** ₹210 or ₹157.50 — paise shown only when there are any. */
export function money(n: number): string {
  const v = Math.round(n * 100) / 100;
  return Number.isInteger(v) ? v.toFixed(0) : v.toFixed(2);
}
