'use client';

import { useCart } from '../context/CartContext';
import { useRouter, usePathname } from 'next/navigation';

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
  const router = useRouter();
  const pathname = usePathname();

  const handleCheckout = () => {
    dispatch({ type: 'CLOSE_CART' });
    router.push('/checkout');
  };

  const showFloatingButton = itemCount > 0 && !state.isOpen && !pathname.startsWith('/checkout');

  return (
    <>
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
                  <div key={item.menu_item_id} className="cart-item">
                    <div
                      className="cart-item-clickable"
                      onClick={() => {
                        dispatch({ type: 'CLOSE_CART' });
                        router.push(`/item/${item.menu_item_id}`);
                      }}
                    >
                      <div className="cart-item-emoji">{CATEGORY_EMOJI[item.category] || '🍽️'}</div>
                      <div className="cart-item-info">
                        <div className="cart-item-name">{item.name}</div>
                        <div className="cart-item-price">₹{(item.price * item.quantity).toFixed(0)}</div>
                      </div>
                    </div>
                    <div className="cart-item-qty">
                      <button className="qty-btn" onClick={() => dispatch({ type: 'DECREMENT', id: item.menu_item_id })}>−</button>
                      <span className="qty-num">{item.quantity}</span>
                      <button className="qty-btn" onClick={() => dispatch({ type: 'INCREMENT', id: item.menu_item_id })}>+</button>
                    </div>
                  </div>
                ))
              )}
            </div>

            {state.items.length > 0 && (
              <div className="cart-footer">
                <div className="cart-totals">
                  <div className="cart-total-row"><span>Subtotal</span><span>₹{total.toFixed(0)}</span></div>
                  <div className="cart-total-row grand"><span>Total</span><span>₹{total.toFixed(0)}</span></div>
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
