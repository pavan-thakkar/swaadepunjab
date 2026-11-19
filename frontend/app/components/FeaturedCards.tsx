'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useCart, MenuItem } from '../context/CartContext';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';
const STORAGE = `${API.replace(/\/api\/?$/, '')}/storage`;

type Card = { id: number; title: string | null; badge: string | null; image_path: string; menu_item_id: number | null; menu_item?: MenuItem | null };

const BADGE_COLORS: Record<string, string> = {
  'NEW': '#16a34a',
  'BEST SELLER': '#dc2626',
  'OFFER': '#2563eb',
  "CHEF'S SPECIAL": '#7c3aed',
};

export default function FeaturedCards() {
  const router = useRouter();
  const { state, dispatch } = useCart();
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
                <span className="wn-badge" style={{ background: BADGE_COLORS[card.badge] || 'var(--primary)' }}>
                  {card.badge}
                </span>
              )}
              {card.menu_item && card.menu_item.is_available !== false && (() => {
                const dish = card.menu_item!;
                const qty = state.items.find(i => i.menu_item_id === dish.id)?.quantity || 0;
                return (
                  <div className="featured-add-bar" onClick={e => e.stopPropagation()} onKeyDown={e => e.stopPropagation()}>
                    <div className="featured-add-info">
                      <span className="featured-add-name">{dish.name}</span>
                      <span className="featured-add-price">₹{Number(dish.price).toFixed(0)}</span>
                    </div>
                    {qty > 0 ? (
                      <div className="quantity-control-card featured-qty">
                        <button type="button" className="qty-control-btn" aria-label={`Remove one ${dish.name}`} onClick={() => dispatch({ type: 'DECREMENT', id: dish.id })}>−</button>
                        <span className="qty-control-val">{qty}</span>
                        <button type="button" className="qty-control-btn" aria-label={`Add one ${dish.name}`} onClick={() => dispatch({ type: 'INCREMENT', id: dish.id })}>+</button>
                      </div>
                    ) : (
                      <button type="button" className="featured-add-btn" aria-label={`Add ${dish.name} to cart`} onClick={() => dispatch({ type: 'ADD', item: dish })}>ADD +</button>
                    )}
                  </div>
                );
              })()}
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
        .wn-badge {
          position: absolute; top: 12px; left: 12px;
          color: #fff; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.04em;
          padding: 5px 10px; border-radius: 999px; box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .featured-add-bar {
          position: absolute; left: 10px; right: 10px; bottom: 10px;
          display: flex; align-items: center; justify-content: space-between; gap: 10px;
          background: rgba(255,255,255,0.96); border-radius: 12px; padding: 8px 8px 8px 12px;
          box-shadow: 0 4px 14px rgba(0,0,0,0.18); cursor: default;
        }
        .featured-add-info { display: flex; flex-direction: column; min-width: 0; }
        .featured-add-name { font-size: 0.85rem; font-weight: 700; color: #1A0F00; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .featured-add-price { font-size: 0.9rem; font-weight: 800; color: #1A0F00; }
        .featured-add-btn {
          flex-shrink: 0; min-height: 40px; padding: 0 16px; border: 0; border-radius: 10px;
          background: var(--primary); color: #1A0F00; font: inherit; font-weight: 800; font-size: 0.88rem; cursor: pointer;
        }
        .featured-add-btn:hover { background: var(--primary-dark); }
        .featured-qty { flex-shrink: 0; min-width: 96px; height: 40px; }
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
