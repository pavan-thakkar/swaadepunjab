'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { rupees } from '../lib/usePlans';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

const LOCAL_KEY = 'swaad_plan_requests';

/** Remember a request sent from this browser so it shows here without logging in. */
export function rememberPlanRequest(id: number, token: string) {
  try {
    const list: { id: number; token: string }[] = JSON.parse(localStorage.getItem(LOCAL_KEY) || '[]');
    const next = [{ id, token }, ...list.filter(x => x.id !== id)].slice(0, 10);
    localStorage.setItem(LOCAL_KEY, JSON.stringify(next));
  } catch {}
}

function localRequests(): { id: number; token: string }[] {
  try {
    const list = JSON.parse(localStorage.getItem(LOCAL_KEY) || '[]');
    return Array.isArray(list) ? list.filter(x => x && x.id && x.token) : [];
  } catch { return []; }
}

export interface MyPlan {
  id: number;
  stage: 'requested' | 'upcoming' | 'active' | 'ended' | 'closed';
  status_label?: string;
  request?: {
    name: string; company: string | null; phone: string; email: string | null; people: number | null;
    start_date: string | null; address: string | null; message: string | null;
  };
  plan_name: string;
  meal_label: string | null;
  company: string | null;
  people: number | null;
  active_from: string | null;
  active_until: string | null;
  total_days: number | null;
  days_left: number | null;
  price: number | null;
  amount_paid: number;
  balance: number | null;
  payment_status: string;
  payment_label: string;
  delivery_time: string | null;
  note: string | null;
  requested_at: string | null;
}

const fmtWhen = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '';

const fmt = (d: string | null) =>
  d ? new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

const daysUntil = (d: string | null) => {
  if (!d) return 0;
  const t = new Date(); t.setHours(0, 0, 0, 0);
  return Math.round((new Date(d + 'T00:00:00').getTime() - t.getTime()) / 86400000);
};

/** "My Monthly Plan" for a logged-in customer: days left, dates, people, payment. Hidden when they have none. */
export default function MyPlans({ token, refreshKey = 0, title }: { token: string | null; refreshKey?: number; title?: string }) {
  const [plans, setPlans] = useState<MyPlan[] | null>(null);
  const [phone, setPhone] = useState<string | null>(null);

  useEffect(() => {
    let alive = true;
    const json = (r: Response) => (r.ok ? r.json() : null);
    const local = localRequests();
    // Logged-in customer's plans + requests sent from this browser
    Promise.all([
      token
        ? fetch(`${API}/monthly-plans/mine`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }, cache: 'no-store' }).then(json).catch(() => null)
        : Promise.resolve(null),
      local.length
        ? fetch(`${API}/monthly-plans/my-requests`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ items: local }),
          }).then(json).catch(() => null)
        : Promise.resolve(null),
    ]).then(([mine, mineLocal]) => {
      if (!alive) return;
      const byId = new Map<number, MyPlan>();
      [...(mine?.data || []), ...(mineLocal?.data || [])].forEach((p: MyPlan) => byId.set(p.id, p));
      const order = { active: 0, upcoming: 1, requested: 2, ended: 3, closed: 4 } as Record<string, number>;
      setPlans(Array.from(byId.values()).sort((a, b) => (order[a.stage] - order[b.stage]) || b.id - a.id));
      setPhone(mine?.phone || mineLocal?.phone || null);
    });
    return () => { alive = false; };
  }, [token, refreshKey]);

  if (!plans || plans.length === 0) return null;

  const tel = phone ? phone.replace(/[^\d+]/g, '') : null;
  const wa = phone ? phone.replace(/\D/g, '').slice(-10) : null;

  return (
    <section className="myp" id="my-plan" aria-label="My monthly plans and requests">
      {title && <h2 className="myp-title">{title}</h2>}
      {plans.map(p => {
        const used = p.total_days && p.days_left !== null ? Math.max(0, p.total_days - p.days_left) : 0;
        const pct = p.total_days ? Math.min(100, Math.round((used / p.total_days) * 100)) : 0;
        const ending = p.stage === 'active' && p.days_left !== null && p.days_left <= 5;
        const renewMsg = encodeURIComponent(`Hello Swaad E Punjab, I want to renew my ${p.plan_name} plan (ends ${fmt(p.active_until)}).`);
        const askMsg = encodeURIComponent(`Hello Swaad E Punjab, I sent a request for the ${p.plan_name} plan. Please call me.`);
        return (
          <article key={p.id} className={`myp-card stage-${p.stage}${ending ? ' ending' : ''}`}>
            <div className="myp-top">
              <div style={{ minWidth: 0 }}>
                <span className="myp-eyebrow">{p.stage === 'requested' || p.stage === 'closed' ? 'My plan request' : 'My Monthly Plan'}</span>
                <h3 className="myp-name">{p.plan_name}</h3>
                <div className="myp-sub">
                  {[p.meal_label, p.company, p.people ? `${p.people} ${p.people === 1 ? 'person' : 'people'}` : null].filter(Boolean).join(' · ')}
                </div>
              </div>
              <span className={`myp-pill pill-${p.stage}`}>
                {p.stage === 'active' ? (ending ? 'Ending soon' : 'Active')
                  : p.stage === 'upcoming' ? 'Starting soon'
                  : p.stage === 'ended' ? 'Ended'
                  : p.stage === 'closed' ? 'Closed'
                  : p.status_label === 'Contacted' ? 'We contacted you'
                  : 'Request sent'}
              </span>
            </div>

            {p.stage === 'requested' && (
              <p className="myp-msg">
                {p.status_label === 'Contacted'
                  ? 'Thank you for talking with us. Your plan will show here with its dates once it is confirmed.'
                  : 'We have your request and will call you soon to confirm the menu, start date and price.'}
              </p>
            )}
            {p.stage === 'closed' && (
              <p className="myp-msg">This request is closed. You can send a new request anytime.</p>
            )}

            {(p.stage === 'requested' || p.stage === 'closed') && p.request && (
              <details className="myp-sent" open={p.stage === 'requested'}>
                <summary>What you sent{p.requested_at ? ` · ${fmtWhen(p.requested_at)}` : ''}</summary>
                <dl className="myp-facts">
                  <dt>Plan</dt><dd>{p.plan_name || 'Not sure yet'}</dd>
                  <dt>Name</dt><dd>{p.request.name}</dd>
                  {p.request.company && (<><dt>Company</dt><dd>{p.request.company}</dd></>)}
                  <dt>Mobile</dt><dd>{p.request.phone}</dd>
                  {p.request.email && (<><dt>Email</dt><dd>{p.request.email}</dd></>)}
                  {p.request.people && (<><dt>People</dt><dd>{p.request.people}</dd></>)}
                  {p.request.start_date && (<><dt>Start date</dt><dd>{fmt(p.request.start_date)}</dd></>)}
                  {p.request.address && (<><dt>Address</dt><dd>{p.request.address}</dd></>)}
                  {p.request.message && (<><dt>Note</dt><dd>{p.request.message}</dd></>)}
                </dl>
              </details>
            )}

            {p.stage === 'upcoming' && (
              <div className="myp-big">
                <strong>Starts in {daysUntil(p.active_from)} {daysUntil(p.active_from) === 1 ? 'day' : 'days'}</strong>
                <span>{fmt(p.active_from)} → {fmt(p.active_until)}</span>
              </div>
            )}

            {p.stage === 'active' && (p.days_left === null || !p.total_days) && (
              <p className="myp-msg">Your plan is active.{p.active_from ? ` Started ${fmt(p.active_from)}.` : ''} The restaurant will add the plan dates here soon.</p>
            )}

            {p.stage === 'active' && p.days_left !== null && !!p.total_days && (
              <>
                <div className="myp-big">
                  <strong>{p.days_left} {p.days_left === 1 ? 'day' : 'days'} left</strong>
                  <span>Day {Math.min((p.total_days || 0), used + 1)} of {p.total_days}</span>
                </div>
                <div className="myp-bar" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={pct} aria-label="Plan used">
                  <span style={{ width: `${pct}%` }} />
                </div>
                <div className="myp-dates"><span>{fmt(p.active_from)}</span><span>{fmt(p.active_until)}</span></div>
              </>
            )}

            {p.stage === 'ended' && (
              <p className="myp-msg">Your plan ended on {fmt(p.active_until)}. Renew to keep getting fresh meals.</p>
            )}

            {p.stage !== 'requested' && p.stage !== 'closed' && (
              <dl className="myp-facts">
                {p.delivery_time && (<><dt>Delivery</dt><dd>{p.delivery_time}</dd></>)}
                {p.price !== null && (<><dt>Plan amount</dt><dd>{rupees(p.price)}</dd></>)}
                <dt>Payment</dt>
                <dd className={`pay-${p.payment_status}`}>
                  {p.payment_label}
                  {p.payment_status !== 'paid' && p.balance ? ` · ${rupees(p.balance)} due` : ''}
                </dd>
              </dl>
            )}

            {p.note && <p className="myp-note">📌 {p.note}</p>}

            {(tel || wa) && (
              <div className="myp-actions">
                {(p.stage === 'ended' || ending) && wa && (
                  <a className="mp-btn" href={`https://wa.me/91${wa}?text=${renewMsg}`} target="_blank" rel="noopener noreferrer">Renew now</a>
                )}
                {p.stage === 'requested' && wa && (
                  <a className="mp-btn mp-btn-light" href={`https://wa.me/91${wa}?text=${askMsg}`} target="_blank" rel="noopener noreferrer">WhatsApp us</a>
                )}
                {tel && <a className="mp-btn mp-btn-light" href={`tel:${tel}`}>Call restaurant</a>}
              </div>
            )}
          </article>
        );
      })}
      {plans.every(p => p.stage === 'ended') && (
        <Link href="/plans/" className="myp-more">See all monthly plans →</Link>
      )}
    </section>
  );
}
