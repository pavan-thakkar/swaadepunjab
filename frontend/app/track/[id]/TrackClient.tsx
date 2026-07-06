'use client';

import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'next/navigation';
import Navbar from '../../components/Navbar';
import CartDrawer from '../../components/CartDrawer';
import Link from 'next/link';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

const STATUSES = [
  { key: 'pending',          label: 'Order Placed',       emoji: '📋', desc: 'Your order has been received and is awaiting confirmation.' },
  { key: 'accepted',         label: 'Order Accepted',     emoji: '✅', desc: 'The restaurant has confirmed your order.' },
  { key: 'preparing',        label: 'Order Ready',        emoji: '🍳', desc: 'Your meal is prepared and ready for dispatch.' },
  { key: 'out_for_delivery', label: 'Out for Delivery',   emoji: '🛵', desc: 'Your order is on its way to you!' },
  { key: 'delivered',        label: 'Delivered',          emoji: '🎉', desc: 'Enjoy your meal!' },
];

const CATEGORY_EMOJI: Record<string, string> = {
  special_combo:  '🍱',
  chur_chur_naan: '🫓',
  punjabi_sabji:  '🍲',
  sabji_combo:    '🍛',
  aloo_combo:     '🥔',
  rice_combo:     '🍚',
  combo:          '🍽️',
};

function Confetti() {
  const [pieces, setPieces] = useState<any[]>([]);

  useEffect(() => {
    const colors = ['#e6a817', '#16a34a', '#2563eb', '#8b5cf6', '#dc2626', '#f43f5e', '#0ea5e9'];
    const newPieces = Array.from({ length: 100 }).map((_, i) => ({
      id: i,
      x: Math.random() * 100,
      y: -10 - Math.random() * 20,
      size: Math.random() * 8 + 6,
      color: colors[Math.floor(Math.random() * colors.length)],
      delay: Math.random() * 4,
      duration: Math.random() * 3 + 2.5,
      tilt: Math.random() * 360
    }));
    setPieces(newPieces);
  }, []);

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      width: '100%',
      height: '100%',
      pointerEvents: 'none',
      zIndex: 9999,
      overflow: 'hidden'
    }}>
      {pieces.map(p => (
        <div
          key={p.id}
          style={{
            position: 'absolute',
            left: `${p.x}%`,
            top: `${p.y}vh`,
            width: `${p.size}px`,
            height: `${p.size * 1.5}px`,
            background: p.color,
            borderRadius: '2px',
            opacity: 0.8,
            transform: `rotate(${p.tilt}deg)`,
            animation: `fall ${p.duration}s linear infinite`,
            animationDelay: `${p.delay}s`
          }}
        />
      ))}
      <style>{`
        @keyframes fall {
          0% {
            transform: translateY(0) rotate(0deg);
          }
          100% {
            transform: translateY(115vh) rotate(720deg);
          }
        }
      `}</style>
    </div>
  );
}

const renderOrderNumber = (num: string) => {
  if (!num) return '';
  const len = num.length;
  if (len <= 4) {
    return <span className="order-id-highlight">{num}</span>;
  }
  const mainPart = num.slice(0, -4);
  const lastFour = num.slice(-4);
  return (
    <>
      {mainPart}
      <span className="order-id-highlight">{lastFour}</span>
    </>
  );
};

export default function TrackOrderPage() {
  const { id } = useParams<{ id: string }>();
  const [order, setOrder] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [reviewingItemId, setReviewingItemId] = useState<number | null>(null);
  const [reviewName, setReviewName] = useState('');
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewComment, setReviewComment] = useState('');
  const [submittingReview, setSubmittingReview] = useState(false);
  const [reviewSuccessMessage, setReviewSuccessMessage] = useState<Record<number, string>>({});
  const [reviewErrorMessage, setReviewErrorMessage] = useState('');

  const [userReviews, setUserReviews] = useState<Record<number, any>>({});
  
  const fetchUserReviews = async (phone: string) => {
    try {
      const res = await fetch(`${API}/reviews/history?phone=${encodeURIComponent(phone)}`);
      if (res.ok) {
        const json = await res.json();
        const map: Record<number, any> = {};
        if (json.data && Array.isArray(json.data)) {
          json.data.forEach((rev: any) => {
            map[rev.menu_item_id] = rev;
          });
        }
        setUserReviews(map);
      }
    } catch (e) {
      console.error("Error fetching user reviews:", e);
    }
  };

  useEffect(() => {
    if (order && order.customer_phone) {
      fetchUserReviews(order.customer_phone);
    }
  }, [order]);

  useEffect(() => {
    if (order && order.customer_name) {
      setReviewName(order.customer_name);
    }
  }, [order]);

  const fetchOrder = useCallback(async () => {
    try {
      const res = await fetch(`${API}/orders/${id}`, { cache: 'no-store' });
      if (!res.ok) { setError('Order not found.'); return; }
      const data = await res.json();
      setOrder(data.data);
    } catch {
      setError('Cannot connect to server. Is the backend running?');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    fetchOrder();
    const interval = setInterval(fetchOrder, 5000); // poll every 5s
    return () => clearInterval(interval);
  }, [fetchOrder]);

  const handlePostReview = async (menuItemId: number) => {
    if (!reviewName.trim() || !reviewComment.trim()) {
      setReviewErrorMessage('Please enter your name and comment!');
      return;
    }
    setSubmittingReview(true);
    setReviewErrorMessage('');

    try {
      const res = await fetch(`${API}/menu/${menuItemId}/reviews`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customer_name: reviewName,
          customer_phone: order.customer_phone || null,
          rating: reviewRating,
          comment: reviewComment,
        }),
      });

      if (res.ok) {
        const jsonRes = await res.json();
        const newReview = jsonRes.data;
        setUserReviews(prev => ({
          ...prev,
          [menuItemId]: newReview
        }));
        setReviewSuccessMessage(prev => ({
          ...prev,
          [menuItemId]: 'Thank you! Your review has been posted successfully.'
        }));
        setReviewComment('');
        setReviewRating(5);
        setReviewingItemId(null);
      } else {
        const errJson = await res.json();
        setReviewErrorMessage(errJson.message || 'Failed to submit review.');
      }
    } catch (e) {
      setReviewErrorMessage('Network error. Failed to post review.');
    } finally {
      setSubmittingReview(false);
    }
  };

  const currentStep = STATUSES.findIndex(s => s.key === order?.status);
  const isCancelled = order?.status === 'cancelled';

  const isJustPlaced = order && (new Date().getTime() - new Date(order.created_at).getTime() < 12000);
  const showConfetti = order && (order.status === 'delivered' || isJustPlaced);

  return (
    <div className="page-wrapper">
      {showConfetti && <Confetti />}
      <Navbar />
      <CartDrawer />

      <div style={{ padding: '2rem', maxWidth: 700, margin: '0 auto' }}>
        <Link href="/" className="btn-secondary" style={{ display: 'inline-flex', marginBottom: '1.5rem' }}>
          ← Back to Menu
        </Link>

        {loading && (
          <div style={{ textAlign: 'center', padding: '4rem', color: 'var(--text-muted)' }}>
            <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>⏳</div>
            <p>Loading your order...</p>
          </div>
        )}

        {error && (
          <div className="empty-state">
            <div className="empty-state-icon">😕</div>
            <h3>Order not found</h3>
            <p>{error}</p>
            <Link href="/" className="btn-primary" style={{ marginTop: '1.5rem', display: 'inline-flex' }}>Order Again</Link>
          </div>
        )}

        {order && !loading && (
          <>
            <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
              <h1 style={{ fontSize: '2rem', fontWeight: 900, marginBottom: '0.5rem' }}>
                {isCancelled ? '❌ Order Cancelled' : currentStep >= 3 ? '🛵 On The Way!' : '🍳 Tracking Your Order'}
              </h1>
              <p style={{ color: 'var(--text-muted)' }}>
                Order <strong style={{ color: 'var(--primary)' }}>#{renderOrderNumber(order.order_number)}</strong> for <strong>{order.customer_name}</strong>
              </p>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>Updates every 5 seconds automatically</p>
            </div>

            {/* Status Stepper */}
            {!isCancelled && (
              <div className="tracking-card" style={{ marginBottom: '1.5rem' }}>
                <div className="status-steps">
                  {STATUSES.map((step, index) => {
                    const isDone = index < currentStep;
                    const isActive = index === currentStep;
                    return (
                      <div key={step.key} className="status-step">
                        <div className="step-line-wrap">
                          <div className={`step-dot${isActive ? ' active' : isDone ? ' done' : ''}`}>
                            {isDone ? '✓' : step.emoji}
                          </div>
                          {index < STATUSES.length - 1 && (
                            <div className={`step-connector${isDone ? ' done' : ''}`} />
                          )}
                        </div>
                        <div className="step-content">
                          <div className={`step-title${isActive ? ' active' : isDone ? ' done' : ''}`}>{step.label}</div>
                          {isActive && <div className="step-desc">{step.desc}</div>}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Order Details */}
            <div className="tracking-card">
              <h3 style={{ fontWeight: 700, marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: 8 }}>🧾 Order Details</h3>
              {order.items && order.items.map((item: any) => (
                <div key={item.id} className="summary-item">
                  <span className="summary-name">
                    {CATEGORY_EMOJI[item.menu_item?.category || ''] || '🍽️'} {item.name}
                  </span>
                  <span className="summary-qty">×{item.quantity}</span>
                  <span className="summary-price">₹{Number(item.subtotal).toFixed(2)}</span>
                </div>
              ))}

              <div style={{ marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid var(--border)' }}>
                <div className="cart-total-row"><span>Subtotal</span><span>₹{Number(order.subtotal).toFixed(2)}</span></div>
                <div className="cart-total-row"><span>Delivery fee</span><span>₹{Number(order.delivery_fee).toFixed(2)}</span></div>
                <div className="cart-total-row grand"><span>Total</span><span style={{ color: 'var(--primary)' }}>₹{Number(order.total).toFixed(2)}</span></div>
              </div>

              <div style={{ marginTop: '1.5rem', padding: '12px 16px', background: 'var(--bg-elevated)', borderRadius: 'var(--radius-sm)', fontSize: '0.85rem', display: 'flex', flexDirection: 'column', gap: '6px' }}>
                <div>
                  <span style={{ color: 'var(--text-muted)' }}>🍽️ Service: </span>
                  <strong style={{ color: 'var(--primary-dark)' }}>
                    {order.order_type === 'dine_in' && '🍽️ Dine In'}
                    {order.order_type === 'takeaway' && '🛍️ Take Away'}
                    {(!order.order_type || order.order_type === 'delivery') && '🛵 Delivery'}
                  </strong>
                </div>
                {order.order_type === 'dine_in' && (
                  <div><span style={{ color: 'var(--text-muted)' }}>⏱️ Arriving At: </span><strong>{order.pickup_time || 'N/A'}</strong></div>
                )}
                {order.order_type === 'takeaway' && (
                  <div><span style={{ color: 'var(--text-muted)' }}>🛍️ Collection Time: </span><strong>{order.pickup_time || 'N/A'}</strong></div>
                )}
                {(!order.order_type || order.order_type === 'delivery') && (
                  <div><span style={{ color: 'var(--text-muted)' }}>📍 Delivering to: </span>{order.delivery_address}, {order.city}</div>
                )}
                <div><span style={{ color: 'var(--text-muted)' }}>📞 Phone: </span>{order.customer_phone}</div>
                <div><span style={{ color: 'var(--text-muted)' }}>💳 Payment: </span>{order.payment_method === 'cash_on_delivery' ? 'Cash on Delivery' : 'Card'}</div>
                {order.special_instructions && (
                  <div><span style={{ color: 'var(--text-muted)' }}>📝 Note: </span>{order.special_instructions}</div>
                )}
              </div>
            </div>

            {order.status === 'delivered' && (
              <div style={{ 
                textAlign: 'center', 
                marginTop: '2rem',
                background: 'var(--bg-card)',
                borderRadius: 'var(--radius)',
                boxShadow: 'var(--shadow)',
                border: '1px solid var(--border)',
                padding: '2.5rem'
              }}>
                <div style={{ fontSize: '3rem', marginBottom: '0.5rem' }}>🎉</div>
                <h2 style={{ marginBottom: '0.5rem', fontWeight: 900 }}>Enjoy your meal!</h2>
                <p style={{ color: 'var(--text-muted)', marginBottom: '1.5rem' }}>Thank you for choosing Swaad E Punjab.</p>
                
                {/* Review Request Section */}
                <div style={{ 
                  borderTop: '1px solid var(--border)', 
                  paddingTop: '1.5rem', 
                  marginTop: '1.5rem',
                  textAlign: 'left'
                }}>
                  <h3 style={{ fontSize: '1.2rem', fontWeight: 800, marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    ⭐ Rate the Dishes You Ordered
                  </h3>
                  
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                    {order.items && order.items.map((item: any) => {
                      const pastReview = userReviews[item.menu_item_id];
                      const isReviewed = !!pastReview || !!reviewSuccessMessage[item.menu_item_id];
                      const activeReview = pastReview || (reviewSuccessMessage[item.menu_item_id] ? { rating: reviewRating, comment: reviewComment } : null);
                      
                      return (
                        <div key={item.id} style={{ 
                          border: '1px solid var(--border)',
                          borderRadius: 'var(--radius-sm)',
                          padding: '1.25rem',
                          background: 'var(--bg-elevated)'
                        }}>
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                            <span style={{ fontWeight: 700, color: 'var(--text)' }}>
                              {CATEGORY_EMOJI[item.menu_item?.category || ''] || '🍽️'} {item.name}
                            </span>
                            
                            {isReviewed ? (
                              <span style={{ color: 'var(--success)', fontWeight: 700, fontSize: '0.85rem' }}>
                                ✓ Reviewed
                              </span>
                            ) : (
                              <button 
                                className="btn-secondary" 
                                style={{ padding: '6px 14px', fontSize: '0.8rem', fontWeight: 800, cursor: 'pointer' }}
                                onClick={() => setReviewingItemId(reviewingItemId === item.menu_item_id ? null : item.menu_item_id)}
                              >
                                {reviewingItemId === item.menu_item_id ? 'Cancel' : '★ Write a Review'}
                              </button>
                            )}
                          </div>
                          
                          {activeReview && (
                            <div style={{ marginTop: '0.75rem', padding: '0.75rem', background: 'var(--bg-card)', borderRadius: '6px', border: '1px solid var(--border)' }}>
                              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' }}>
                                <span style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-muted)' }}>Your Review:</span>
                                <span style={{ color: '#E6A817', fontWeight: 700, fontSize: '0.85rem' }}>
                                  {'★'.repeat(activeReview.rating)}{'☆'.repeat(5 - activeReview.rating)}
                                </span>
                              </div>
                              <p style={{ color: 'var(--text)', fontSize: '0.9rem', margin: 0, fontStyle: 'italic' }}>
                                "{activeReview.comment}"
                              </p>
                              {activeReview.admin_reply && (
                                <div style={{
                                  marginTop: '0.5rem',
                                  padding: '0.5rem 0.75rem',
                                  background: 'rgba(230, 168, 23, 0.05)',
                                  borderLeft: '2px solid var(--primary)',
                                  borderRadius: '0 4px 4px 0',
                                  fontSize: '0.8rem'
                                }}>
                                  <span style={{ fontWeight: 700, color: 'var(--primary-dark)', display: 'block', marginBottom: '2px' }}>
                                    🧑‍🍳 Admin Response:
                                  </span>
                                  <p style={{ color: 'var(--text)', margin: 0 }}>{activeReview.admin_reply}</p>
                                </div>
                              )}
                            </div>
                          )}

                          {reviewSuccessMessage[item.menu_item_id] && !pastReview && (
                            <div style={{ color: 'var(--success)', fontSize: '0.82rem', marginTop: '8px', fontWeight: 600 }}>
                              ✅ {reviewSuccessMessage[item.menu_item_id]}
                            </div>
                          )}

                          {reviewingItemId === item.menu_item_id && !isReviewed && (
                            <div style={{ marginTop: '1rem', display: 'flex', flexDirection: 'column', gap: '0.85rem', paddingTop: '1rem', borderTop: '1px dashed var(--border)' }}>
                              
                              {/* Rating Selection */}
                              <div>
                                <label style={{ display: 'block', fontSize: '0.82rem', fontWeight: 700, marginBottom: '4px', color: 'var(--text)' }}>Rating:</label>
                                <div style={{ display: 'flex', gap: '6px' }}>
                                  {[1, 2, 3, 4, 5].map(star => (
                                    <button
                                      key={star}
                                      type="button"
                                      onClick={() => setReviewRating(star)}
                                      style={{
                                        background: 'none',
                                        border: 'none',
                                        fontSize: '1.5rem',
                                        cursor: 'pointer',
                                        color: star <= reviewRating ? '#E6A817' : 'var(--text-dim)',
                                        padding: 0
                                      }}
                                    >
                                      ★
                                    </button>
                                  ))}
                                </div>
                              </div>

                              {/* Comment Field */}
                              <div>
                                <label style={{ display: 'block', fontSize: '0.82rem', fontWeight: 700, marginBottom: '4px', color: 'var(--text)' }}>Your Review:</label>
                                <textarea
                                  value={reviewComment}
                                  onChange={e => setReviewComment(e.target.value)}
                                  rows={2}
                                  placeholder="How was the taste? Let us know!"
                                  style={{
                                    width: '100%',
                                    padding: '8px 12px',
                                    borderRadius: '6px',
                                    border: '1px solid var(--border)',
                                    background: 'var(--bg-card)',
                                    color: 'var(--text)',
                                    fontSize: '0.9rem',
                                    outline: 'none',
                                    resize: 'none',
                                    fontFamily: 'inherit'
                                  }}
                                />
                              </div>

                              {reviewErrorMessage && (
                                <div style={{ color: 'var(--danger)', fontSize: '0.82rem', fontWeight: 600 }}>
                                  ⚠️ {reviewErrorMessage}
                                </div>
                              )}

                              <button
                                onClick={() => handlePostReview(item.menu_item_id)}
                                disabled={submittingReview}
                                className="btn-primary"
                                style={{
                                  padding: '8px 16px',
                                  fontSize: '0.85rem',
                                  fontWeight: 800,
                                  alignSelf: 'flex-start',
                                  opacity: submittingReview ? 0.6 : 1,
                                  cursor: submittingReview ? 'not-allowed' : 'pointer'
                                }}
                              >
                                {submittingReview ? 'Submitting...' : 'Submit Review'}
                              </button>
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>

                <div style={{ marginTop: '2rem' }}>
                  <Link href="/" className="btn-primary">Order Again</Link>
                </div>
              </div>
            )}
          </>
        )}

        {/* Search by Order Number */}
        {!id && <OrderSearch />}
      </div>

      <footer>
        <p>© 2026 Swaad E Punjab — Track your order in real-time</p>
      </footer>
    </div>
  );
}

function OrderSearch() {
  const [num, setNum] = useState('');
  const router = require('next/navigation').useRouter();
  return (
    <div style={{ textAlign: 'center', padding: '3rem 0' }}>
      <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>📦</div>
      <h2 style={{ marginBottom: '1rem' }}>Track Your Order</h2>
      <div className="search-bar" style={{ maxWidth: 400 }}>
        <span>🔍</span>
        <input value={num} onChange={e => setNum(e.target.value)} placeholder="Enter order number..." />
        <button className="search-btn" onClick={() => num && router.push(`/track/${num}`)}>Track</button>
      </div>
    </div>
  );
}
