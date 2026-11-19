'use client';

import Link from 'next/link';
import { usePlans, rupees } from '../lib/usePlans';

/** Home page teaser for Monthly Plans (only when the owner switched it on and has active plans). */
export default function PlansSection() {
  const info = usePlans();
  if (!info || !info.enabled || info.data.length === 0) return null;
  const plans = info.data.slice(0, 3);

  return (
    <section className="section" id="monthly-plans" aria-labelledby="mp-home-title">
      <div className="mp-home">
        <div className="mp-home-intro">
          <span className="mp-eyebrow">For offices &amp; teams</span>
          <h2 id="mp-home-title" className="mp-home-title">{info.title}</h2>
          <p className="mp-home-sub">{info.subtitle}</p>
          <Link href="/plans/" className="mp-btn mp-btn-light">See plans &amp; enquire →</Link>
        </div>
        <div className="mp-home-cards">
          {plans.map(p => (
            <Link key={p.id} href={`/plans/#plan-${p.id}`} className="mp-mini">
              {p.badge && <span className="mp-badge">{p.badge}</span>}
              <div className="mp-mini-name">{p.name}</div>
              <div className="mp-mini-meta">
                {p.meal_label}{p.days_per_month ? ` · ${p.days_per_month} days` : ''}
              </div>
              <div className="mp-mini-price">
                {rupees(p.price)} <span>{p.price_note}</span>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
