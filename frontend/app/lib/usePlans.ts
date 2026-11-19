'use client';

import { useEffect, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';
export const STORAGE = `${API.replace(/\/api\/?$/, '')}/storage`;

export interface MonthlyPlan {
  id: number;
  name: string;
  badge: string | null;
  description: string | null;
  price: number;
  price_note: string;
  meal_type: string;
  meal_label: string;
  days_per_month: number | null;
  includes: string[];
  min_people: number | null;
  image: string | null;
}

export interface PlansInfo {
  enabled: boolean;
  title: string;
  subtitle: string;
  data: MonthlyPlan[];
}

// One request per page load, shared by the navbar, home section and plans page
let cache: Promise<PlansInfo | null> | null = null;

function load(): Promise<PlansInfo | null> {
  if (!cache) {
    cache = fetch(`${API}/monthly-plans`, { cache: 'no-store' })
      .then(r => (r.ok ? r.json() : null))
      .then(j => (j && Array.isArray(j.data) ? (j as PlansInfo) : null))
      .catch(() => null);
  }
  return cache;
}

/** undefined while loading, null if unavailable */
export function usePlans(): PlansInfo | null | undefined {
  const [info, setInfo] = useState<PlansInfo | null | undefined>(undefined);
  useEffect(() => {
    let alive = true;
    load().then(i => { if (alive) setInfo(i); });
    return () => { alive = false; };
  }, []);
  return info;
}

export const planImage = (p: MonthlyPlan) =>
  p.image ? (p.image.startsWith('http') ? p.image : `${STORAGE}/${p.image.replace(/^\/+/, '')}`) : null;

export const rupees = (n: number) => `₹${Math.round(n).toLocaleString('en-IN')}`;
