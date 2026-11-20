'use client';

import { useEffect, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

type Info = {
  order_number: string; name: string; date: string; status: string;
  items: { name: string; quantity: number }[]; total: number;
  rating: number | null; feedback: string | null;
};

const LABELS = ['', 'Very bad', 'Not good', 'Okay', 'Good', 'Loved it!'];

/** 1–5 star rating for the whole order (used on /rate and on the tracking page). */
export default function OrderRating({ orderNumber, token, compact = false }: { orderNumber: string; token: string; compact?: boolean }) {
  const [info, setInfo] = useState<Info | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [rating, setRating] = useState(0);
  const [hover, setHover] = useState(0);
  const [feedback, setFeedback] = useState('');
  const [saving, setSaving] = useState(false);
  const [done, setDone] = useState(false);

  useEffect(() => {
    if (!orderNumber || !token) { setError('This rating link is not complete.'); return; }
    fetch(`${API}/orders/${encodeURIComponent(orderNumber)}/rating?t=${encodeURIComponent(token)}`, { headers: { Accept: 'application/json' } })
      .then(async r => {
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.message || 'This rating link is not valid.');
        setInfo(d);
        if (d.rating) { setRating(d.rating); setFeedback(d.feedback || ''); setDone(true); }
      })
      .catch(e => setError(e.message));
  }, [orderNumber, token]);

  const submit = async () => {
    if (!rating) return;
    setSaving(true); setError(null);
    try {
      const r = await fetch(`${API}/orders/${encodeURIComponent(orderNumber)}/rating`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ t: token, rating, feedback: feedback.trim() || null }),
      });
      const d = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(d.message || 'Could not save your rating. Please try again.');
      setDone(true);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  if (error && !info) return <div className="ordrate-card"><p className="ordrate-err">{error}</p></div>;
  if (!info) return <div className="ordrate-card"><p className="ordrate-muted">Loading…</p></div>;

  const shown = hover || rating;

  return (
    <div className={`ordrate-card${compact ? ' compact' : ''}`}>
      {!compact && (
        <>
          <h1 className="ordrate-title">{info.name ? `Hi ${info.name}!` : 'Hi!'} How was your order?</h1>
          <p className="ordrate-muted">Order #{info.order_number.slice(-6)} · {info.date}</p>
          <p className="ordrate-items">{info.items.map(i => `${i.quantity}× ${i.name}`).join(', ')}</p>
        </>
      )}
      {compact && <h3 className="ordrate-sub">How was your overall order?</h3>}

      {done ? (
        <div className="ordrate-done">
          <div className="ordrate-stars static" aria-label={`${rating} out of 5`}>
            {[1, 2, 3, 4, 5].map(n => <span key={n} className={n <= rating ? 'on' : ''}>★</span>)}
          </div>
          <p><strong>Thank you for your feedback!</strong></p>
          <p className="ordrate-muted">
            {rating <= 2 ? 'We are sorry it wasn’t great. Our team will call you to make it right.' : rating === 3 ? 'We’ll keep improving — thank you for telling us.' : 'So happy you enjoyed it! See you again soon. 🙏'}
          </p>
          {!compact && <a className="ordrate-btn" href="/">Order again</a>}
        </div>
      ) : (
        <>
          <div className="ordrate-stars" role="radiogroup" aria-label="Rating" onMouseLeave={() => setHover(0)}>
            {[1, 2, 3, 4, 5].map(n => (
              <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n} star${n > 1 ? 's' : ''}`}
                className={n <= shown ? 'on' : ''} onMouseEnter={() => setHover(n)} onClick={() => setRating(n)}>★</button>
            ))}
          </div>
          <div className="ordrate-label">{shown ? LABELS[shown] : 'Tap a star'}</div>
          {rating > 0 && (
            <>
              <textarea className="ordrate-text" rows={3} maxLength={1000} value={feedback} onChange={e => setFeedback(e.target.value)}
                placeholder={rating <= 2 ? 'What went wrong? (helps us fix it)' : 'Anything you’d like to tell us? (optional)'} />
              {error && <p className="ordrate-err">{error}</p>}
              <button type="button" className="ordrate-btn" onClick={submit} disabled={saving}>{saving ? 'Saving…' : 'Submit rating'}</button>
            </>
          )}
        </>
      )}
    </div>
  );
}
