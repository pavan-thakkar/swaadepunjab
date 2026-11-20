'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useCart, MenuItem, qtyInCart, hasOptions } from '../context/CartContext';
import { itemPath } from '../lib/itemPath';

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
              className={`featured-card${clickable ? ' clickable' : ''}${card.menu_item && card.menu_item.is_available !== false ? ' has-bar' : ''}`}
              role={clickable ? 'link' : undefined}
              tabIndex={clickable ? 0 : undefined}
              onClick={() => clickable && router.push(card.menu_item ? itemPath(card.menu_item) : `/item/${card.menu_item_id}/`)}
              onKeyDown={e => { if (clickable && (e.key === 'Enter' || e.key === ' ')) router.push(card.menu_item ? itemPath(card.menu_item) : `/item/${card.menu_item_id}/`); }}
            >
              <img src={imgSrc(card.image_path)} alt={card.title || 'Featured dish'} loading="lazy" />
              {card.badge && (
                <span className="wn-badge" style={{ background: BADGE_COLORS[card.badge] || 'var(--primary)' }}>
                  {card.badge}
                </span>
              )}
              {card.menu_item && card.menu_item.is_available !== false && (() => {
                const dish = card.menu_item!;
                const qty = qtyInCart(state.items, dish.id);
                return (
                  <div className="featured-add-bar" onClick={e => e.stopPropagation()} onKeyDown={e => e.stopPropagation()}>
                    <div className="featured-add-info">
                      <span className="featured-add-name">{dish.name}{dish.quantity_label ? <span className="qty-label" style={{ display: 'inline', marginLeft: 6 }}>· {dish.quantity_label}</span> : null}</span>
                      <span className="featured-add-price">₹{Number(dish.price).toFixed(0)}{hasOptions(dish) && <span className="featured-add-opts">Customisable</span>}</span>
                    </div>
                    {qty > 0 ? (
                      <div className="quantity-control-card featured-qty">
                        <button type="button" className="qty-control-btn" aria-label={`Remove one ${dish.name}`} onClick={() => dispatch({ type: 'DECREMENT', id: dish.id })}>−</button>
                        <span className="qty-control-val">{qty}</span>
                        <button type="button" className="qty-control-btn" aria-label={`Add one ${dish.name}`} onClick={() => dispatch({ type: 'INCREMENT', id: dish.id, item: dish })}>+</button>
                      </div>
                    ) : (
                      <button type="button" className="featured-add-btn" aria-label={`Add ${dish.name} to cart`} onClick={() => dispatch({ type: 'ADD', item: dish })}>ADD<span className="featured-add-plus">+</span></button>
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
          align-items: flex-start;
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
        /* Dish strip under the photo; the ADD button sits half on the photo, half on the strip */
        .featured-card.has-bar { aspect-ratio: auto; overflow: visible; background: #fff; display: flex; flex-direction: column; }
        .featured-card.has-bar > img { aspect-ratio: 16 / 9; height: auto; border-radius: 16px 16px 0 0; }
        .featured-add-bar {
          position: relative; display: flex; align-items: center; justify-content: space-between; gap: 10px;
          background: #fff; border-radius: 0 0 16px 16px; padding: 12px 150px 12px 16px; min-height: 64px; cursor: default;
        }
        .featured-add-bar > .featured-add-btn, .featured-add-bar > .featured-qty {
          position: absolute; right: 16px; top: -21px; z-index: 2;
        }
        .featured-add-info { display: flex; flex-direction: column; min-width: 0; }
        .featured-add-name { font-size: 0.95rem; font-weight: 700; color: #1A0F00; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .featured-add-price { font-size: 0.95rem; margin-top: 2px; font-weight: 800; color: #1A0F00; }
        .featured-add-opts { margin-left: 8px; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }
        .featured-add-btn {
          flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
          min-width: 112px; height: 42px; padding: 0 20px; border-radius: 10px;
          background: #fff; color: var(--primary-text, #8A5A00); border: 1px solid var(--primary-dark, #C98F0F);
          font: inherit; font-weight: 800; font-size: 0.92rem; letter-spacing: 0.05em; cursor: pointer;
          box-shadow: 0 4px 12px rgba(70,40,0,0.16);
          transition: background .2s ease, color .2s ease, transform .15s ease, box-shadow .2s ease;
        }
        .featured-add-plus { font-size: 1.1rem; font-weight: 900; line-height: 1; }
        .featured-add-btn:hover { background: var(--primary); color: #1A0F00; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(230,168,23,0.35); }
        .featured-add-btn:active { transform: scale(0.96); }
        .featured-qty { flex-shrink: 0; min-width: 112px; height: 42px; border-radius: 10px; box-shadow: 0 4px 12px rgba(70,40,0,0.16); }
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
