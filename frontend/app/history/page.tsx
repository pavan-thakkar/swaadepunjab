'use client';

import { useState, useEffect } from 'react';
import { useCart } from '../context/CartContext';
import { useRouter } from 'next/navigation';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';
import Link from 'next/link';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

const STATUS_DETAILS: Record<string, { label: string; color: string; bg: string }> = {
  pending:          { label: '⏳ Pending',         color: '#d97706', bg: 'rgba(217,119,6,0.08)' },
  accepted:         { label: '✅ Accepted',        color: '#2563eb', bg: 'rgba(37,99,235,0.08)' },
  preparing:        { label: '🍳 Ready',            color: '#8b5cf6', bg: 'rgba(139,92,246,0.08)' },
  out_for_delivery: { label: '🛵 Out for Delivery', color: '#0ea5e9', bg: 'rgba(14,165,233,0.08)' },
  delivered:        { label: '🎉 Delivered',       color: '#16a34a', bg: 'rgba(22,163,74,0.08)' },
  cancelled:        { label: '❌ Cancelled',       color: '#dc2626', bg: 'rgba(220,38,38,0.08)' },
};

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

interface Order {
  id: number;
  order_number: string;
  subtotal: string;
  delivery_fee: string;
  total: string;
  status: string;
  payment_method: string;
  created_at: string;
  order_type?: string;
  table_number?: string;
  pickup_time?: string;
  items: Array<{
    id: number;
    name: string;
    quantity: number;
    price: string;
    menu_item_id: number;
  }>;
}

export default function HistoryPage() {
  const { userPhone, userName } = useCart();
  const router = useRouter();

  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [authChecked, setAuthChecked] = useState(false);
  const [activePhone, setActivePhone] = useState<string | null>(null);

  const [reviewingOrderId, setReviewingOrderId] = useState<number | null>(null);
  const [reviewingItemId, setReviewingItemId] = useState<number | null>(null);
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewComment, setReviewComment] = useState('');
  const [submittingReview, setSubmittingReview] = useState(false);
  const [reviewSuccessMessage, setReviewSuccessMessage] = useState<Record<string, string>>({});
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
    if (activePhone) {
      fetchUserReviews(activePhone);
    }
  }, [activePhone]);

  // Redirect to login if user not authenticated
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const storedPhone = localStorage.getItem('user_phone');
      if (!storedPhone) {
        router.push('/login?redirect=/history');
      } else {
        setActivePhone(storedPhone);
        setAuthChecked(true);
      }
    }
  }, [userPhone, router]);

  useEffect(() => {
    if (!authChecked || !activePhone) return;

    const fetchHistory = async () => {
      try {
        const res = await fetch(`${API}/orders/history?phone=${encodeURIComponent(activePhone)}`, {
          cache: 'no-store'
        });
        if (!res.ok) {
          setError('Failed to load order history.');
          return;
        }
        const data = await res.json();
        setOrders(data.data || []);
      } catch (err) {
        setError('Network error. Backend connection failed.');
      } finally {
        setLoading(false);
      }
    };

    fetchHistory();
  }, [authChecked, activePhone]);

  const handlePostReview = async (orderId: number, menuItemId: number) => {
    if (!reviewComment.trim()) {
      setReviewErrorMessage('Please enter your comment!');
      return;
    }
    setSubmittingReview(true);
    setReviewErrorMessage('');

    try {
      const res = await fetch(`${API}/menu/${menuItemId}/reviews`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customer_name: userName || 'Customer',
          customer_phone: activePhone || null,
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
        
        const uniqueKey = `${orderId}_${menuItemId}`;
        setReviewSuccessMessage(prev => ({
          ...prev,
          [uniqueKey]: 'Thank you! Review posted.'
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

  if (!authChecked) {
    return (
      <div className="page-wrapper">
        <Navbar />
        <div style={{ textAlign: 'center', padding: '5rem', color: 'var(--text-muted)' }}>
          <div style={{ fontSize: '3.5rem', marginBottom: '1rem' }}>⏳</div>
          <h3>Verifying session...</h3>
        </div>
      </div>
    );
  }

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />

      <div style={{ padding: '2.5rem 2rem', maxWidth: 850, margin: '0 auto' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2rem', flexWrap: 'wrap', gap: '1rem' }}>
          <div>
            <h1 style={{ fontSize: '2rem', fontWeight: 900, color: 'var(--text)' }}>📜 My Orders</h1>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.92rem', marginTop: '0.25rem' }}>
              Order history matching phone: <strong>{userPhone}</strong>
            </p>
          </div>
          <Link href="/" className="btn-primary" style={{ padding: '10px 20px', fontSize: '0.9rem', display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
            🍽️ Order New Food
          </Link>
        </div>

        {loading && (
          <div style={{ textAlign: 'center', padding: '5rem', color: 'var(--text-muted)' }}>
            <div style={{ fontSize: '3.5rem', marginBottom: '1rem' }}>⏳</div>
            <h3>Loading history...</h3>
            <p>Fetching your past Punjabi cravings.</p>
          </div>
        )}

        {error && (
          <div className="empty-state" style={{ padding: '3rem 2rem' }}>
            <div className="empty-state-icon">😕</div>
            <h3>Something went wrong</h3>
            <p>{error}</p>
          </div>
        )}

        {!loading && !error && orders.length === 0 && (
          <div className="empty-state" style={{ padding: '4rem 2rem' }}>
            <div className="empty-state-icon">🍽️</div>
            <h3>No orders found</h3>
            <p>You haven't placed any orders with this phone number yet.</p>
            <Link href="/" className="btn-primary" style={{ marginTop: '1.5rem', display: 'inline-flex' }}>
              Order Punjabi Delicacies Now
            </Link>
          </div>
        )}

        {!loading && !error && orders.length > 0 && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            {orders.map((order) => {
              const status = STATUS_DETAILS[order.status] || { label: order.status, color: '#333', bg: '#eee' };
              const dateStr = new Date(order.created_at).toLocaleDateString('en-IN', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
              });

              return (
                <div key={order.id} className="tracking-card" style={{ padding: '1.75rem', position: 'relative', border: '1px solid var(--border)' }}>
                  
                  {/* Top Row: Order Number & Status */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '0.75rem', paddingBottom: '1rem', borderBottom: '1px solid var(--border)' }}>
                    <div>
                      <div style={{ fontSize: '1.05rem', fontWeight: 800, color: 'var(--text)', fontFamily: 'monospace' }}>
                        {renderOrderNumber(order.order_number)}
                      </div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '4px', display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                        <span>Ordered on {dateStr}</span>
                        <span style={{
                          background: order.order_type === 'dine_in' ? 'rgba(22, 163, 74, 0.1)' : (order.order_type === 'takeaway' ? 'rgba(217, 119, 6, 0.1)' : 'rgba(37, 99, 235, 0.1)'),
                          color: order.order_type === 'dine_in' ? '#16a34a' : (order.order_type === 'takeaway' ? '#d97706' : '#2563eb'),
                          padding: '2px 8px',
                          borderRadius: '4px',
                          fontSize: '0.72rem',
                          fontWeight: 700
                        }}>
                          {order.order_type === 'dine_in' && `🍽️ Dine In (Table #${order.table_number || 'N/A'})`}
                          {order.order_type === 'takeaway' && `🛍️ Take Away (${order.pickup_time || 'N/A'})`}
                          {(!order.order_type || order.order_type === 'delivery') && '🛵 Delivery'}
                        </span>
                      </div>
                    </div>
                    
                    <span style={{
                      color: status.color,
                      background: status.bg,
                      padding: '6px 12px',
                      borderRadius: '8px',
                      fontSize: '0.82rem',
                      fontWeight: 800,
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '4px'
                    }}>
                      {status.label}
                    </span>
                  </div>

                  {/* Middle Row: Items List */}
                  <div style={{ padding: '1rem 0' }}>
                    <div style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '8px' }}>
                      Items
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                      {order.items && order.items.map((item) => (
                        <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.92rem' }}>
                          <span style={{ color: 'var(--text)' }}>
                            <strong style={{ color: 'var(--primary-dark)' }}>{item.quantity}x</strong> {item.name}
                          </span>
                          <span style={{ color: 'var(--text-muted)' }}>
                            ₹{Number(item.price).toFixed(0)} each
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Bottom Row: Total & Actions */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', paddingTop: '1rem', borderTop: '1px solid var(--border)', flexWrap: 'wrap', gap: '1rem' }}>
                    <div>
                      <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)', display: 'block' }}>Total Paid</span>
                      <strong style={{ fontSize: '1.35rem', color: 'var(--success)', fontWeight: 900 }}>
                        ₹{Number(order.total).toFixed(0)}
                      </strong>
                    </div>

                    <div style={{ display: 'flex', gap: '8px' }}>
                      {order.status === 'delivered' && (
                        <button 
                          onClick={() => {
                            setReviewingOrderId(reviewingOrderId === order.id ? null : order.id);
                            setReviewingItemId(null);
                            setReviewErrorMessage('');
                          }}
                          className="btn-secondary" 
                          style={{ 
                            padding: '10px 18px', 
                            fontSize: '0.85rem', 
                            fontWeight: 700, 
                            display: 'inline-flex', 
                            alignItems: 'center', 
                            gap: '6px',
                            background: reviewingOrderId === order.id ? 'var(--border)' : 'var(--bg-elevated)',
                            color: 'var(--text)',
                            cursor: 'pointer'
                          }}
                        >
                          ⭐ Rate Dishes
                        </button>
                      )}
                      <Link 
                        href={`/track/${order.order_number}`}
                        className="btn-secondary" 
                        style={{ padding: '10px 18px', fontSize: '0.85rem', fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                      >
                        🛵 Track Order
                      </Link>
                    </div>
                  </div>

                  {reviewingOrderId === order.id && (
                    <div style={{ 
                      marginTop: '1.5rem', 
                      paddingTop: '1.5rem', 
                      borderTop: '1px solid var(--border)',
                      textAlign: 'left'
                    }}>
                      <h4 style={{ fontSize: '0.95rem', fontWeight: 800, marginBottom: '1rem', color: 'var(--text)' }}>
                        ⭐ Rate the Dishes You Ordered
                      </h4>
                      
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                        {order.items && order.items.map((item) => {
                          const uniqueKey = `${order.id}_${item.menu_item_id}`;
                          const pastReview = userReviews[item.menu_item_id];
                          const isReviewed = !!pastReview || !!reviewSuccessMessage[uniqueKey];
                          const activeReview = pastReview || (reviewSuccessMessage[uniqueKey] ? { rating: reviewRating, comment: reviewComment } : null);

                          return (
                            <div key={item.id} style={{ 
                              border: '1px solid var(--border)',
                              borderRadius: 'var(--radius-sm)',
                              padding: '1rem',
                              background: 'var(--bg-elevated)'
                            }}>
                              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                                <span style={{ fontWeight: 700, color: 'var(--text)', fontSize: '0.9rem' }}>
                                  🍽️ {item.name}
                                </span>
                                
                                {isReviewed ? (
                                  <span style={{ color: 'var(--success)', fontWeight: 700, fontSize: '0.82rem' }}>
                                    ✓ Reviewed
                                  </span>
                                ) : (
                                  <button 
                                    className="btn-secondary" 
                                    style={{ padding: '4px 10px', fontSize: '0.75rem', fontWeight: 800, cursor: 'pointer' }}
                                    onClick={() => setReviewingItemId(reviewingItemId === item.menu_item_id ? null : item.menu_item_id)}
                                  >
                                    {reviewingItemId === item.menu_item_id ? 'Cancel' : '★ Write a Review'}
                                  </button>
                                )}
                              </div>
                              
                              {activeReview && (
                                <div style={{ marginTop: '0.65rem', padding: '0.65rem', background: 'var(--bg-card)', borderRadius: '6px', border: '1px solid var(--border)' }}>
                                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' }}>
                                    <span style={{ fontSize: '0.78rem', fontWeight: 700, color: 'var(--text-muted)' }}>Your Review:</span>
                                    <span style={{ color: '#E6A817', fontWeight: 700, fontSize: '0.8rem' }}>
                                      {'★'.repeat(activeReview.rating)}{'☆'.repeat(5 - activeReview.rating)}
                                    </span>
                                  </div>
                                  <p style={{ color: 'var(--text)', fontSize: '0.85rem', margin: 0, fontStyle: 'italic' }}>
                                    "{activeReview.comment}"
                                  </p>
                                  {activeReview.admin_reply && (
                                    <div style={{
                                      marginTop: '0.5rem',
                                      padding: '0.5rem 0.75rem',
                                      background: 'rgba(230, 168, 23, 0.05)',
                                      borderLeft: '2px solid var(--primary)',
                                      borderRadius: '0 4px 4px 0',
                                      fontSize: '0.78rem'
                                    }}>
                                      <span style={{ fontWeight: 700, color: 'var(--primary-dark)', display: 'block', marginBottom: '2px' }}>
                                        🧑‍🍳 Admin Response:
                                      </span>
                                      <p style={{ color: 'var(--text)', margin: 0 }}>{activeReview.admin_reply}</p>
                                    </div>
                                  )}
                                </div>
                              )}

                              {reviewSuccessMessage[uniqueKey] && !pastReview && (
                                <div style={{ color: 'var(--success)', fontSize: '0.8rem', marginTop: '6px', fontWeight: 600 }}>
                                  ✅ {reviewSuccessMessage[uniqueKey]}
                                </div>
                              )}

                              {reviewingItemId === item.menu_item_id && !isReviewed && (
                                <div style={{ marginTop: '0.85rem', display: 'flex', flexDirection: 'column', gap: '0.75rem', paddingTop: '0.85rem', borderTop: '1px dashed var(--border)' }}>
                                  
                                  {/* Rating Selection */}
                                  <div>
                                    <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 700, marginBottom: '2px', color: 'var(--text)' }}>Rating:</label>
                                    <div style={{ display: 'flex', gap: '6px' }}>
                                      {[1, 2, 3, 4, 5].map(star => (
                                        <button
                                          key={star}
                                          type="button"
                                          onClick={() => setReviewRating(star)}
                                          style={{
                                            background: 'none',
                                            border: 'none',
                                            fontSize: '1.35rem',
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
                                    <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 700, marginBottom: '2px', color: 'var(--text)' }}>Your Review:</label>
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
                                        fontSize: '0.88rem',
                                        outline: 'none',
                                        resize: 'none',
                                        fontFamily: 'inherit'
                                      }}
                                    />
                                  </div>

                                  {reviewErrorMessage && (
                                    <div style={{ color: 'var(--danger)', fontSize: '0.8rem', fontWeight: 600 }}>
                                      ⚠️ {reviewErrorMessage}
                                    </div>
                                  )}

                                  <button
                                    onClick={() => handlePostReview(order.id, item.menu_item_id)}
                                    disabled={submittingReview}
                                    className="btn-primary"
                                    style={{
                                      padding: '6px 14px',
                                      fontSize: '0.8rem',
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
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
