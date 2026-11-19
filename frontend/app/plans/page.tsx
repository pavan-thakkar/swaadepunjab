'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';
import { useCart } from '../context/CartContext';
import { usePlans, planImage, rupees, MonthlyPlan } from '../lib/usePlans';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

type Form = {
  monthly_plan_id: string;
  name: string;
  company: string;
  phone: string;
  email: string;
  people: string;
  start_date: string;
  address: string;
  message: string;
};

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function PlansPage() {
  const info = usePlans();
  const { userName, userPhone, userEmail } = useCart();
  const formRef = useRef<HTMLFormElement>(null);
  const [form, setForm] = useState<Form>({
    monthly_plan_id: '', name: '', company: '', phone: '', email: '', people: '', start_date: '', address: '', message: '',
  });
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);

  // Fill in what we already know about a logged-in customer
  useEffect(() => {
    setForm(f => ({
      ...f,
      name: f.name || userName || '',
      phone: f.phone || (userPhone ? userPhone.replace(/\D/g, '').slice(-10) : ''),
      email: f.email || userEmail || '',
    }));
  }, [userName, userPhone, userEmail]);

  // /plans/#plan-3 → scroll to that plan once loaded
  useEffect(() => {
    if (!info?.enabled) return;
    const id = window.location.hash.slice(1);
    if (id) setTimeout(() => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
  }, [info]);

  const set = (k: keyof Form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }));

  const choose = (p: MonthlyPlan) => {
    setForm(f => ({ ...f, monthly_plan_id: String(p.id), people: f.people || (p.min_people ? String(p.min_people) : '') }));
    setDone(null);
    setTimeout(() => {
      formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      (formRef.current?.querySelector('input[name="name"]') as HTMLInputElement | null)?.focus({ preventScroll: true });
    }, 30);
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    const digits = form.phone.replace(/\D/g, '');
    if (digits.length < 10) { setError('Please enter a valid 10-digit mobile number.'); return; }
    setSending(true);
    try {
      const res = await fetch(`${API}/monthly-plans/enquiry`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          monthly_plan_id: form.monthly_plan_id ? Number(form.monthly_plan_id) : null,
          name: form.name.trim(),
          company: form.company.trim() || null,
          phone: digits,
          email: form.email.trim() || null,
          people: form.people ? Number(form.people) : null,
          start_date: form.start_date || null,
          address: form.address.trim() || null,
          message: form.message.trim() || null,
        }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        const first = json?.errors ? (Object.values(json.errors)[0] as string[])?.[0] : null;
        setError(first || json?.message || (res.status === 429 ? 'Too many requests. Please wait a minute and try again.' : 'Could not send. Please try again.'));
        return;
      }
      setError(null);
      setDone(json.message || 'Thank you! We will call you soon.');
      setForm(f => ({ ...f, people: '', start_date: '', address: '', message: '' }));
    } catch {
      setError('Network error. Please check your internet and try again.');
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="page-wrapper" style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      <Navbar />
      <CartDrawer />

      <main className="mp-page">
        {info === undefined && <div className="mp-loading" aria-busy="true">Loading plans…</div>}

        {info !== undefined && (!info || !info.enabled) && (
          <div className="mp-empty">
            <div style={{ fontSize: '3rem' }} aria-hidden="true">🍱</div>
            <h1>Monthly plans are not available right now</h1>
            <p>Please check back soon, or order from our menu today.</p>
            <Link href="/" className="mp-btn">Go to menu</Link>
          </div>
        )}

        {info && info.enabled && (
          <>
            <header className="mp-hero">
              <span className="mp-eyebrow">For offices &amp; teams</span>
              <h1>{info.title}</h1>
              <p>{info.subtitle}</p>
              <ul className="mp-points">
                <li>🍲 Cooked fresh every day</li>
                <li>🚚 Delivered to your office</li>
                <li>🤝 Custom plans for your team</li>
              </ul>
            </header>

            <section className="mp-grid" aria-label="Plans">
              {info.data.map(p => {
                const img = planImage(p);
                const picked = form.monthly_plan_id === String(p.id);
                return (
                  <article key={p.id} id={`plan-${p.id}`} className={`mp-card${p.badge ? ' featured' : ''}${picked ? ' picked' : ''}`}>
                    {img && <img src={img} alt="" className="mp-card-img" loading="lazy" />}
                    <div className="mp-card-body">
                      <div className="mp-card-top">
                        <h2 className="mp-card-name">{p.name}</h2>
                        {p.badge && <span className="mp-badge">{p.badge}</span>}
                      </div>
                      <div className="mp-card-meta">
                        <span>{p.meal_label}</span>
                        {p.days_per_month ? <span>{p.days_per_month} days / month</span> : null}
                        {p.min_people ? <span>Min. {p.min_people} people</span> : null}
                      </div>
                      <div className="mp-card-price">
                        {rupees(p.price)} <span>{p.price_note}</span>
                      </div>
                      {p.description && <p className="mp-card-desc">{p.description}</p>}
                      {p.includes.length > 0 && (
                        <ul className="mp-includes" aria-label="Included">
                          {p.includes.map((it, i) => <li key={i}>{it}</li>)}
                        </ul>
                      )}
                      <button type="button" className="mp-btn mp-card-btn" onClick={() => choose(p)} aria-pressed={picked}>
                        {picked ? '✓ Selected · fill the form below' : 'Subscribe / Enquire'}
                      </button>
                    </div>
                  </article>
                );
              })}
            </section>

            <section className="mp-form-wrap" aria-labelledby="mp-form-title">
              <form ref={formRef} className="mp-form" onSubmit={submit} noValidate={false}>
                <h2 id="mp-form-title">Request a plan</h2>
                <p className="mp-form-sub">Send your details and we will call you to confirm the menu, timing and price for your team.</p>

                {done ? (
                  <div className="mp-done" role="status">
                    <div style={{ fontSize: '2rem' }} aria-hidden="true">✅</div>
                    <strong>{done}</strong>
                    <button type="button" className="mp-btn mp-btn-light" onClick={() => setDone(null)}>Send another request</button>
                  </div>
                ) : (
                  <>
                    <label className="mp-field mp-full">
                      <span>Plan</span>
                      <select name="plan" value={form.monthly_plan_id} onChange={set('monthly_plan_id')}>
                        <option value="">Not sure yet — please suggest</option>
                        {info.data.map(p => (
                          <option key={p.id} value={p.id}>{p.name} · {rupees(p.price)} {p.price_note}</option>
                        ))}
                      </select>
                    </label>
                    <label className="mp-field">
                      <span>Your name *</span>
                      <input name="name" value={form.name} onChange={set('name')} required maxLength={100} autoComplete="name" />
                    </label>
                    <label className="mp-field">
                      <span>Company</span>
                      <input name="company" value={form.company} onChange={set('company')} maxLength={150} autoComplete="organization" />
                    </label>
                    <label className="mp-field">
                      <span>Mobile number *</span>
                      <input name="phone" value={form.phone} onChange={set('phone')} required inputMode="tel" maxLength={14} autoComplete="tel" placeholder="10-digit mobile" />
                    </label>
                    <label className="mp-field">
                      <span>Email</span>
                      <input name="email" type="email" value={form.email} onChange={set('email')} maxLength={150} autoComplete="email" />
                    </label>
                    <label className="mp-field">
                      <span>Number of people</span>
                      <input name="people" type="number" min={1} max={5000} value={form.people} onChange={set('people')} inputMode="numeric" />
                    </label>
                    <label className="mp-field">
                      <span>Start date</span>
                      <input name="start_date" type="date" min={today()} value={form.start_date} onChange={set('start_date')} />
                    </label>
                    <label className="mp-field mp-full">
                      <span>Office / delivery address</span>
                      <textarea name="address" rows={2} value={form.address} onChange={set('address')} maxLength={500} autoComplete="street-address" />
                    </label>
                    <label className="mp-field mp-full">
                      <span>Anything else? (lunch time, Jain food, budget…)</span>
                      <textarea name="message" rows={3} value={form.message} onChange={set('message')} maxLength={1000} />
                    </label>
                    {error && <div className="mp-error mp-full" role="alert">{error}</div>}
                    <button type="submit" className="mp-btn mp-submit mp-full" disabled={sending}>
                      {sending ? 'Sending…' : 'Send request'}
                    </button>
                  </>
                )}
              </form>
            </section>
          </>
        )}
      </main>
    </div>
  );
}
