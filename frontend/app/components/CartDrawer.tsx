'use client';

import { useCart } from '../context/CartContext';
import { useRouter } from 'next/navigation';

const CATEGORY_EMOJI: Record<string, string> = {
  special_combo:  '🍱',
  chur_chur_naan: '🫓',
  punjabi_sabji:  '🍲',
  sabji_combo:    '🍛',
  aloo_combo:     '🥔',
  rice_combo:     '🍚',
};

export default function CartDrawer() {
  const { state, dispatch, total, itemCount, deliveryFee, freeDeliveryThreshold } = useCart();
  const router = useRouter();
  const neededForFree = freeDeliveryThreshold - total;

  if (!state.isOpen) return null;

  const grandTotal = total + deliveryFee;

  const handleCheckout = () => {
    dispatch({ type: 'CLOSE_CART' });
    router.push('/checkout');
  };

  return (
    <>
      <div className="cart-overlay" onClick={() => dispatch({ type: 'CLOSE_CART' })} />
      <div className="cart-drawer">
        <div className="cart-header">
          <h2>🛒 Aapka Order <span style={{ color: 'var(--text-muted)', fontWeight: 400, fontSize: '0.85rem' }}>({itemCount} items)</span></h2>
          <button className="cart-close-btn" onClick={() => dispatch({ type: 'CLOSE_CART' })}>✕</button>
        </div>

        <div className="cart-items">
          {state.items.length === 0 ? (
            <div className="cart-empty">
              <div className="cart-empty-icon">🛒</div>
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
  );
}
