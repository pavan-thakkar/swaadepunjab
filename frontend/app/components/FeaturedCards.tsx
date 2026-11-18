'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';
const STORAGE = `${API.replace(/\/api\/?$/, '')}/storage`;

type Card = { id: number; title: string | null; badge: string | null; image_path: string; menu_item_id: number | null };

const BADGE_COLORS: Record<string, string> = {
  'NEW': '#16a34a',
  'BEST SELLER': '#dc2626',
  'OFFER': '#2563eb',
  "CHEF'S SPECIAL": '#7c3aed',
};

export default function FeaturedCards() {
  const router = useRouter();
  const [cards, setCards] = useState<Card[]>([]);
  const [title, setTitle] = useState("What's New");
  const [subtitle, setSubtitle] = useState<string | null>(null);
  const rowRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    fetch(`${API}/featured-cards`)
      .then(r => r.json())
      .then(json => {
        setCards(Array.isArray(json.data) ? json.data : []);
        if (json.title) setTitle(json.title);
        setSubtitle(json.subtitle || null);
      })
      .catch(() => {});
  }, []);

  if (cards.length === 0) return null;

  const scrollBy = (dir: number) => {
    const row = rowRef.current;
    if (!row) return;
    row.scrollBy({ left: dir * Math.min(row.clientWidth * 0.8, 600), behavior: 'smooth' });
  };

  const imgSrc = (p: string) => (p.startsWith('http') ? p : `${STORAGE}/${p}`);

  return (
    <section className="section featured-cards-section" style={{ paddingBottom: 0 }}>
      <div className="section-header" style={{ marginBottom: '1rem' }}>
        <div>
          <h2 className="section-title">{title}</h2>
          {subtitle && <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginTop: 4 }}>{subtitle}</p>}
        </div>
        {cards.length > 1 && (
          <div className="featured-arrows">
            <button type="button" onClick={() => scrollBy(-1)} aria-label="Previous cards">‹</button>
            <button type="button" onClick={() => scrollBy(1)} aria-label="Next cards">›</button>
          </div>
        )}
      </div>

      <div className="featured-row" ref={rowRef}>
        {cards.map(card => {
          const clickable = !!card.menu_item_id;
          return (
            <div
              key={card.id}
              className={`featured-card${clickable ? ' clickable' : ''}`}
              role={clickable ? 'link' : undefined}
              tabIndex={clickable ? 0 : undefined}
              onClick={() => clickable && router.push(`/item/${card.menu_item_id}/`)}
              onKeyDown={e => { if (clickable && (e.key === 'Enter' || e.key === ' ')) router.push(`/item/${card.menu_item_id}/`); }}
            >
              <img src={imgSrc(card.image_path)} alt={card.title || 'Featured dish'} loading="lazy" />
              {card.badge && (
                <span className="featured-badge" style={{ background: BADGE_COLORS[card.badge] || 'var(--primary)' }}>
                  {card.badge}
                </span>
              )}
            </div>
          );
        })}
      </div>

      <style>{`
        .featured-row {
          display: flex;
          gap: 18px;
          overflow-x: auto;
          scroll-snap-type: x mandatory;
          padding: 4px 2px 14px;
          scrollbar-width: none;
        }
        .featured-row::-webkit-scrollbar { display: none; }
        .featured-card {
          position: relative;
          flex: 0 0 auto;
          width: min(520px, 82vw);
          aspect-ratio: 16 / 9;
          border-radius: 16px;
          overflow: hidden;
          scroll-snap-align: start;
          background: var(--bg-elevated);
          box-shadow: 0 6px 18px rgba(0,0,0,0.12);
          transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .featured-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .featured-card.clickable { cursor: pointer; }
        .featured-card.clickable:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0,0,0,0.18); }
        .featured-badge {
          position: absolute; top: 12px; left: 12px;
          color: #fff; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.04em;
          padding: 5px 10px; border-radius: 999px; box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .featured-arrows { display: flex; gap: 8px; }
        .featured-arrows button {
          width: 38px; height: 38px; border-radius: 50%;
          border: 1px solid var(--border); background: var(--bg-card); color: var(--text);
          font-size: 1.4rem; line-height: 1; cursor: pointer; box-shadow: var(--shadow);
        }
        @media (max-width: 640px) {
          .featured-arrows { display: none; }
          .featured-row { gap: 12px; }
          .featured-card { border-radius: 12px; }
        }
      `}</style>
    </section>
  );
}
