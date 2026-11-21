'use client';

import { useCart, firstImageUrl } from '../context/CartContext';
import { useEffect, useState } from 'react';
import { CartOptionSheet } from './OptionSheet';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';
import { useRouter, usePathname } from 'next/navigation';
import OfferProgress, { useOffers, computeOffer } from './OfferProgress';
import { itemPath } from '../lib/itemPath';
import { useGst, calcGst, gstLabel, money } from '../lib/gst';
import NoPhoto from './NoPhoto';

const CATEGORY_EMOJI: Record<string, string> = {
  special_combo:  '🍱',
  chur_chur_naan: '🫓',
  punjabi_sabji:  '🍲',
  sabji_combo:    '🍛',
  aloo_combo:     '🥔',
  rice_combo:     '🍚',
};

// Delivery Scooter SVG Icon (Zomato-style)
function ScooterIcon({ size = 28, color = '#1a1a2e' }: { size?: number; color?: string }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 80 56"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      style={{ display: 'block', flexShrink: 0 }}
    >
      {/* Rear wheel */}
      <circle cx="16" cy="42" r="9" stroke={color} strokeWidth="3" fill="none"/>
      <circle cx="16" cy="42" r="3" fill={color}/>

      {/* Front wheel */}
      <circle cx="64" cy="42" r="9" stroke={color} strokeWidth="3" fill="none"/>
      <circle cx="64" cy="42" r="3" fill={color}/>

      {/* Axle/base connector */}
      <line x1="25" y1="42" x2="55" y2="42" stroke={color} strokeWidth="3" strokeLinecap="round"/>

      {/* Body – rear scoop shape */}
      <path d="M16 42 Q16 28 28 26 L44 24 L48 34 L20 36 Z" fill={color} opacity="0.85"/>

      {/* Body – front panel */}
      <path d="M44 24 L56 20 L62 28 L56 36 L48 34 Z" fill={color}/>

      {/* Seat */}
      <rect x="28" y="20" width="18" height="6" rx="3" fill={color}/>

      {/* Handlebar stem */}
      <line x1="56" y1="20" x2="60" y2="12" stroke={color} strokeWidth="3" strokeLinecap="round"/>
      {/* Handlebar */}
      <line x1="56" y1="12" x2="66" y2="12" stroke={color} strokeWidth="3" strokeLinecap="round"/>

      {/* Delivery box */}
      <rect x="8" y="14" width="18" height="14" rx="3" fill="#E63946"/>
      {/* Box lid highlight */}
      <rect x="8" y="14" width="18" height="4" rx="3" fill="#c0392b"/>
      {/* Box letter */}
      <text x="13" y="25" fontSize="8" fill="white" fontWeight="bold" fontFamily="Arial, sans-serif">S</text>

      {/* Exhaust puff */}
      <circle cx="6" cy="40" r="2" fill={color} opacity="0.25"/>
      <circle cx="2" cy="37" r="1.5" fill={color} opacity="0.15"/>
    </svg>
  );
}

export default function CartDrawer() {
  const { state, dispatch, total, itemCount } = useCart();
  const offers = useOffers();
  const offerCalc = computeOffer(total, offers);
  const gstSettings = useGst();
  const cartGst = calcGst(Math.max(0, total - offerCalc.discount), gstSettings);
  const router = useRouter();
  const pathname = usePathname();

  // Photos for items added before photos were saved in the cart
  const [photos, setPhotos] = useState<Record<number, string>>({});
  const [labels, setLabels] = useState<Record<number, string>>({});
  const [broken, setBroken] = useState<Record<number, boolean>>({});
  const [fetched, setFetched] = useState(false);
  const needsPhotos = state.isOpen && !fetched && state.items.some(i => !i.image || i.quantity_label === undefined);
  useEffect(() => {
    if (!needsPhotos) return;
    let alive = true;
    fetch(`${API}/menu`)
      .then(r => r.json())
      .then(d => {
        if (!alive) return;
        setFetched(true);
        const map: Record<number, string> = {};
        const lab: Record<number, string> = {};
        (d.data || []).forEach((m: any) => { const u = firstImageUrl(m.image); if (u) map[m.id] = u; if (m.quantity_label) lab[m.id] = m.quantity_label; });
        setPhotos(p => ({ ...map, ...p }));
        setLabels(l => ({ ...lab, ...l }));
      })
      .catch(() => {});
    return () => { alive = false; };
  }, [needsPhotos]);

  const handleCheckout = () => {
    dispatch({ type: 'CLOSE_CART' });
    router.push('/checkout');
  };

  const showFloatingButton = itemCount > 0 && !state.isOpen && !pathname.startsWith('/checkout');

  return (
    <>
      <CartOptionSheet />
      {/* Floating Sticky Cart Button with Scooter Icon */}
      {showFloatingButton && (
        <button
          className="floating-cart-btn"
          onClick={() => dispatch({ type: 'TOGGLE_CART' })}
          title="View Cart"
          aria-label="View Cart"
        >
          <div className="floating-cart-left">
            <div className="floating-cart-icon-wrapper">
              <ScooterIcon size={28} color="#1a1a2e" />
              <span className="floating-cart-badge">{itemCount}</span>
            </div>
            <div className="floating-cart-info">
              <span className="floating-cart-title">View Cart</span>
              <span className="floating-cart-sub">{itemCount} {itemCount === 1 ? 'item' : 'items'}</span>
            </div>
          </div>
          <div className="floating-cart-right">
            <span className="floating-cart-price">₹{total.toFixed(0)}</span>
            <span className="floating-cart-arrow">→</span>
          </div>
        </button>
      )}

      {/* Cart Drawer Modal */}
      {state.isOpen && (
        <>
          <div className="cart-overlay" onClick={() => dispatch({ type: 'CLOSE_CART' })} />
          <div className="cart-drawer">
            <div className="cart-header">
              <h2 style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <ScooterIcon size={26} color="#1a1a2e" />
                Aapka Order{' '}
                <span style={{ color: 'var(--text-muted)', fontWeight: 400, fontSize: '0.85rem' }}>
                  ({itemCount} items)
                </span>
              </h2>
              <button className="cart-close-btn" onClick={() => dispatch({ type: 'CLOSE_CART' })}>✕</button>
            </div>

            <div className="cart-items">
              {state.items.length === 0 ? (
                <div className="cart-empty">
                  <div className="cart-empty-icon" style={{ display: 'flex', justifyContent: 'center', marginBottom: '12px' }}>
                    <ScooterIcon size={60} color="#ccc" />
                  </div>
                  <p>Cart khali hai</p>
                  <p style={{ fontSize: '0.85rem' }}>Kuch tasty add karein!</p>
                </div>
              ) : (
                state.items.map(item => (
                  <div key={item.key} className="cart-item">
                    <div
                      className="cart-item-clickable"
                      onClick={() => {
                        dispatch({ type: 'CLOSE_CART' });
                        router.push(itemPath(item));
                      }}
                    >
                      {(item.image || photos[item.menu_item_id]) && !broken[item.menu_item_id] ? (
                        <img
                          className="cart-item-img"
                          src={(item.image || photos[item.menu_item_id]) as string}
                          alt={item.name}
                          loading="lazy"
                          onError={() => setBroken(b => ({ ...b, [item.menu_item_id]: true }))}
                        />
                      ) : (
                        <NoPhoto small className="cart-item-img" />
                      )}
                      <div className="cart-item-info">
                        <div className="cart-item-name">{item.name}</div>
                        {item.option_label && <div className="cart-item-opts">{item.option_label}</div>}
                        {(item.quantity_label || labels[item.menu_item_id]) && <div className="qty-label">{item.quantity_label || labels[item.menu_item_id]}</div>}
                        <div className="cart-item-price">₹{(item.price * item.quantity).toFixed(0)}</div>
                      </div>
                    </div>
                    <div className="cart-item-qty">
                      <button className="qty-btn" onClick={() => dispatch({ type: 'DECREMENT', id: item.key })}>−</button>
                      <span className="qty-num">{item.quantity}</span>
                      <button className="qty-btn" onClick={() => dispatch({ type: 'INCREMENT', id: item.key })}>+</button>
                    </div>
                  </div>
                ))
              )}
            </div>

            {state.items.length > 0 && (
              <div className="cart-footer">
                <OfferProgress subtotal={total} compact />
                <div className="cart-totals">
                  <div className="cart-total-row"><span>Subtotal</span><span>₹{total.toFixed(0)}</span></div>
                  {offerCalc.freeLine && (
                    <div className="cart-total-row" style={{ color: 'var(--success)', fontWeight: 700 }}>
                      <span>🎁 {offerCalc.freeLine.quantity > 1 ? `${offerCalc.freeLine.quantity}× ` : ''}{offerCalc.freeLine.name}</span><span>FREE</span>
                    </div>
                  )}
                  {offerCalc.discount > 0 && (
                    <div className="cart-total-row" style={{ color: 'var(--success)', fontWeight: 700 }}>
                      <span>Offer discount</span><span>−₹{offerCalc.discount.toFixed(0)}</span>
                    </div>
                  )}
                  {cartGst && cartGst.amount > 0 && (
                    <div className="cart-total-row" style={cartGst.mode === 'inclusive' ? { fontSize: '0.8rem', color: 'var(--text-muted)' } : undefined}>
                      <span>{gstLabel(cartGst)}</span><span>{cartGst.mode === 'inclusive' ? '' : '+'}₹{cartGst.amount.toFixed(2)}</span>
                    </div>
                  )}
                  <div className="cart-total-row grand"><span>Total</span><span>₹{money(Math.max(0, total - offerCalc.discount) + (cartGst?.add || 0))}</span></div>
                </div>
                <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', textAlign: 'center', marginTop: '8px', marginBottom: '12px' }}>
                  * Delivery charges will be calculated on the next page based on your address.
                </p>
                <button className="checkout-btn" onClick={handleCheckout}>
                  Order Karein →
                </button>
              </div>
            )}
          </div>
        </>
      )}
    </>
  );
}
