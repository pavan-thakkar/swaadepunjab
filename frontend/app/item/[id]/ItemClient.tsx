'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useCart, MenuItem } from '../../context/CartContext';
import Navbar from '../../components/Navbar';
import CartDrawer from '../../components/CartDrawer';
import Toast from '../../components/Toast';
import Link from 'next/link';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

const DEFAULT_CATEGORY_EMOJI: Record<string, string> = {
  special_combo:  '🍱',
  chur_chur_naan: '🫓',
  punjabi_sabji:  '🍲',
  sabji_combo:    '🍛',
  aloo_combo:     '🥔',
  rice_combo:     '🍚',
};

const DEFAULT_CATEGORY_LABELS: Record<string, string> = {
  special_combo:  'Special Combos',
  chur_chur_naan: 'Chur Chur Naan',
  punjabi_sabji:  'Punjabi Sabji',
  sabji_combo:    'Sabji Combo',
  aloo_combo:     'Aloo Combo',
  rice_combo:     'Rice Combo',
};

export default function ItemDetailPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const { state, dispatch } = useCart();
  const [item, setItem] = useState<MenuItem | null>(null);
  const cartItem = item ? state.items.find(i => i.menu_item_id === item.id) : null;
  const quantity = cartItem ? cartItem.quantity : 0;
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [toast, setToast] = useState<string | null>(null);
  const [activeImageIdx, setActiveImageIdx] = useState(0);
  const [suggestions, setSuggestions] = useState<MenuItem[]>([]);

  const [reviews, setReviews] = useState<any[]>([]);
  const [reviewLoading, setReviewLoading] = useState(true);

  const [categoryEmoji, setCategoryEmoji] = useState<Record<string, string>>(DEFAULT_CATEGORY_EMOJI);
  const [categoryLabels, setCategoryLabels] = useState<Record<string, string>>(DEFAULT_CATEGORY_LABELS);

  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const res = await fetch(`${API}/categories`);
        const json = await res.json();
        const list = json.data || [];
        if (list.length > 0) {
          const emojiMap: Record<string, string> = {};
          const labelMap: Record<string, string> = {};
          list.forEach((c: any) => {
            emojiMap[c.slug] = c.emoji || '🍽️';
            labelMap[c.slug] = c.name;
          });
          setCategoryEmoji(emojiMap);
          setCategoryLabels(labelMap);
        }
      } catch (e) {
        console.error("Error fetching categories:", e);
      }
    };
    fetchCategories();
  }, []);

  const getImagesList = (imageField: any): string[] => {
    if (!imageField) return [];
    if (Array.isArray(imageField)) {
      return imageField.filter(Boolean);
    }
    if (typeof imageField === 'string') {
      try {
        const decoded = JSON.parse(imageField);
        if (Array.isArray(decoded)) {
          return decoded.filter(Boolean);
        }
      } catch {}
      return [imageField];
    }
    return [];
  };

  const imagesList = getImagesList(item?.image);

  useEffect(() => {
    const fetchItem = async () => {
      try {
        const res = await fetch(`${API}/menu/${id}`);
        if (!res.ok) {
          setError('Dish not found in our kitchen!');
          return;
        }
        const data = await res.json();
        setItem(data.data);
      } catch (err) {
        setError('Connection error. Is the backend kitchen active?');
      } finally {
        setLoading(false);
      }
    };

    fetchItem();
  }, [id]);

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [id]);

  useEffect(() => {
    if (!item) return;
    const fetchSuggestions = async () => {
      try {
        const res = await fetch(`${API}/menu`);
        if (res.ok) {
          const json = await res.json();
          const allItems: MenuItem[] = json.data || [];
          const filtered = allItems.filter(i => i.id !== item.id && i.is_available);
          const sameCategory = filtered.filter(i => i.category === item.category);
          
          let selected: MenuItem[] = [];
          if (sameCategory.length >= 3) {
            selected = sameCategory.slice(0, 3);
          } else {
            const others = filtered.filter(i => i.category !== item.category);
            selected = [...sameCategory, ...others].slice(0, 3);
          }
          setSuggestions(selected);
        }
      } catch (err) {
        console.error("Error fetching suggestions:", err);
      }
    };
    fetchSuggestions();
  }, [item]);

  const handleAddToSuggestionsCart = (suggestedItem: MenuItem) => {
    dispatch({ type: 'ADD', item: suggestedItem });
    setToast(`${suggestedItem.name} added to cart! 🛒`);
    setTimeout(() => setToast(null), 2500);
  };

  const fetchReviews = async () => {
    try {
      const res = await fetch(`${API}/menu/${id}/reviews`);
      if (res.ok) {
        const json = await res.json();
        setReviews(json.data || []);
      }
    } catch (e) {
      console.error("Error fetching reviews:", e);
    } finally {
      setReviewLoading(false);
    }
  };

  useEffect(() => {
    if (item) {
      fetchReviews();
    }
  }, [item]);



  const handleAddToCart = () => {
    if (!item) return;
    dispatch({ type: 'ADD', item });
    setToast(`${item.name} added to cart! 🛒`);
    setTimeout(() => setToast(null), 2500);
  };

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />

      <div style={{ padding: '2rem 1.5rem', maxWidth: '850px', margin: '0 auto' }}>
        {/* Back Link */}
        <Link href="/" className="btn-secondary" style={{ display: 'inline-flex', marginBottom: '2rem', gap: '8px', alignItems: 'center' }}>
          ← Back to Menu
        </Link>

        {loading && (
          <div style={{ textAlign: 'center', padding: '6rem 0', color: 'var(--text-muted)' }}>
            <div style={{ fontSize: '3rem', marginBottom: '1rem', animation: 'spin 2s linear infinite' }}>⏳</div>
            <p style={{ fontWeight: 600 }}>Fetching dish details...</p>
          </div>
        )}

        {error && (
          <div className="empty-state" style={{ padding: '4rem 2rem' }}>
            <div className="empty-state-icon" style={{ fontSize: '4rem' }}>😕</div>
            <h3 style={{ fontSize: '1.5rem', fontWeight: 850, marginTop: '1rem' }}>Oops! Item Not Found</h3>
            <p style={{ color: 'var(--text-muted)', marginTop: '0.5rem' }}>{error}</p>
            <Link href="/" className="btn-primary" style={{ marginTop: '2rem', display: 'inline-flex' }}>
              Explore Menu
            </Link>
          </div>
        )}

        {item && !loading && (
          <>
            <div style={{ 
              display: 'flex', 
              flexDirection: 'column', 
            background: 'var(--bg-card)', 
            borderRadius: 'var(--radius)', 
            boxShadow: 'var(--shadow)', 
            overflow: 'hidden', 
            border: '1px solid var(--border)' 
          }}>
            {/* Header / Media Banner with Carousel */}
            <div style={{ 
              position: 'relative', 
              height: '380px', 
              background: 'var(--bg-elevated)', 
              borderBottom: '1px solid var(--border)',
              overflow: 'hidden'
            }}>
              {imagesList.length > 0 ? (
                <>
                  {/* Slider Images */}
                  <div style={{ width: '100%', height: '100%', position: 'relative' }}>
                    {imagesList.map((imgSrc, idx) => (
                      <div
                        key={idx}
                        style={{
                          position: 'absolute',
                          top: 0,
                          left: 0,
                          width: '100%',
                          height: '100%',
                          opacity: activeImageIdx === idx ? 1 : 0,
                          transition: 'opacity 0.4s ease-in-out',
                          pointerEvents: activeImageIdx === idx ? 'auto' : 'none'
                        }}
                      >
                        <img 
                          src={imgSrc.startsWith('http://') || imgSrc.startsWith('https://') 
                            ? imgSrc 
                            : `${API.replace('/api', '/storage')}/${imgSrc}`} 
                          alt={`${item.name} ${idx + 1}`} 
                          style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                        />
                      </div>
                    ))}
                  </div>

                  {/* Navigation Arrows if multiple images */}
                  {imagesList.length > 1 && (
                    <>
                      <button
                        onClick={() => setActiveImageIdx(prev => (prev - 1 + imagesList.length) % imagesList.length)}
                        style={{
                          position: 'absolute',
                          left: '1rem',
                          top: '50%',
                          transform: 'translateY(-50%)',
                          background: 'rgba(255,255,255,0.85)',
                          color: '#000',
                          border: '1px solid var(--border)',
                          borderRadius: '50%',
                          width: '40px',
                          height: '40px',
                          fontSize: '1.25rem',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          cursor: 'pointer',
                          boxShadow: '0 4px 10px rgba(0,0,0,0.1)',
                          zIndex: 10,
                          transition: 'var(--transition)'
                        }}
                        className="carousel-nav-btn"
                      >
                        ‹
                      </button>
                      <button
                        onClick={() => setActiveImageIdx(prev => (prev + 1) % imagesList.length)}
                        style={{
                          position: 'absolute',
                          right: '1rem',
                          top: '50%',
                          transform: 'translateY(-50%)',
                          background: 'rgba(255,255,255,0.85)',
                          color: '#000',
                          border: '1px solid var(--border)',
                          borderRadius: '50%',
                          width: '40px',
                          height: '40px',
                          fontSize: '1.25rem',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          cursor: 'pointer',
                          boxShadow: '0 4px 10px rgba(0,0,0,0.1)',
                          zIndex: 10,
                          transition: 'var(--transition)'
                        }}
                        className="carousel-nav-btn"
                      >
                        ›
                      </button>
                    </>
                  )}

                  {/* Dot Indicators if multiple images */}
                  {imagesList.length > 1 && (
                    <div style={{
                      position: 'absolute',
                      bottom: '1rem',
                      left: '50%',
                      transform: 'translateX(-50%)',
                      display: 'flex',
                      gap: '8px',
                      zIndex: 10
                    }}>
                      {imagesList.map((_, idx) => (
                        <button
                          key={idx}
                          onClick={() => setActiveImageIdx(idx)}
                          style={{
                            width: '8px',
                            height: '8px',
                            borderRadius: '50%',
                            background: activeImageIdx === idx ? 'var(--primary)' : 'rgba(255, 255, 255, 0.5)',
                            border: 'none',
                            padding: 0,
                            cursor: 'pointer',
                            transition: 'var(--transition)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.2)'
                          }}
                        />
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <div style={{ fontSize: '8rem', filter: 'drop-shadow(0 4px 12px rgba(0,0,0,0.15))' }}>
                    {categoryEmoji[item.category] || '🍽️'}
                  </div>
                </div>
              )}
              
              {item.is_featured && (
                <span className="featured-badge" style={{ position: 'absolute', top: '1.5rem', right: '1.5rem', margin: 0, fontSize: '0.9rem', padding: '6px 14px', zIndex: 10 }}>
                  ⭐ Special Combo
                </span>
              )}
            </div>

            {/* Content Details */}
            <div style={{ padding: '2.5rem' }}>
              <div style={{ 
                color: 'var(--primary)', 
                fontSize: '0.95rem', 
                fontWeight: 700, 
                textTransform: 'uppercase', 
                letterSpacing: '0.05em',
                marginBottom: '0.5rem' 
              }}>
                {categoryLabels[item.category] || item.category}
              </div>

              <h1 style={{ 
                fontSize: '2.25rem', 
                fontWeight: 900, 
                color: 'var(--text)', 
                lineHeight: 1.2, 
                marginBottom: '1rem' 
              }}>
                {item.name}
              </h1>

              {/* Stats / Ratings Row */}
              <div style={{ display: 'flex', gap: '1.5rem', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap' }}>
                <span style={{ 
                  background: 'rgba(230,168,23,0.12)', 
                  color: 'var(--primary-dark)', 
                  fontWeight: 800, 
                  fontSize: '0.9rem', 
                  padding: '4px 10px', 
                  borderRadius: '6px', 
                  display: 'flex', 
                  alignItems: 'center', 
                  gap: '4px' 
                }}>
                  ★ {Number(item.rating).toFixed(1)}
                </span>
                
                <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                  ⏱️ Prep Time: <strong>{item.prep_time} mins</strong>
                </span>

                <span style={{ 
                  color: item.is_available ? 'var(--success)' : 'var(--danger)', 
                  fontSize: '0.9rem', 
                  fontWeight: 700,
                  display: 'flex',
                  alignItems: 'center',
                  gap: '6px'
                }}>
                  ● {item.is_available ? 'Available in Kitchen' : 'Sold Out'}
                </span>
              </div>

              {/* Divider */}
              <div style={{ height: '1px', background: 'var(--border)', margin: '1.5rem 0' }} />

              {/* Description */}
              <div style={{ marginBottom: '2.5rem' }}>
                <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '0.75rem', color: 'var(--text)' }}>
                  Dish Description
                </h3>
                <p style={{ color: 'var(--text-muted)', lineHeight: '1.7', fontSize: '1.05rem' }}>
                  {item.description || "Indulge in our authentic, hand-crafted Punjabi recipe, prepared fresh with premium traditional ingredients."}
                </p>
              </div>

              {/* Footer / Buy Action */}
              <div style={{ 
                display: 'flex', 
                justifyContent: 'space-between', 
                alignItems: 'center', 
                background: 'var(--bg-elevated)', 
                padding: '1.5rem 2rem', 
                borderRadius: 'var(--radius-sm)',
                border: '1px solid var(--border)',
                flexWrap: 'wrap',
                gap: '1rem'
              }}>
                <div>
                  <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 600 }}>Total Price</div>
                  <div style={{ fontSize: '2rem', fontWeight: 900, color: 'var(--text)' }}>
                    ₹{Number(item.price).toFixed(0)}
                  </div>
                </div>

                {quantity > 0 ? (
                  <div className="quantity-control-card large" style={{ height: '48px', minWidth: '130px', padding: '6px 16px' }}>
                    <button 
                      type="button" 
                      onClick={() => dispatch({ type: 'DECREMENT', id: item.id })}
                      className="qty-control-btn"
                      style={{ fontSize: '1.4rem' }}
                      title="Quantity kam karein"
                    >
                      −
                    </button>
                    <span className="qty-control-val" style={{ fontSize: '1.1rem' }}>{quantity}</span>
                    <button 
                      type="button" 
                      onClick={() => dispatch({ type: 'INCREMENT', id: item.id })}
                      className="qty-control-btn"
                      style={{ fontSize: '1.4rem' }}
                      title="Quantity badhayein"
                    >
                      +
                    </button>
                  </div>
                ) : (
                  <button 
                    onClick={handleAddToCart}
                    disabled={!item.is_available}
                    className="btn-primary" 
                    style={{ 
                      padding: '14px 32px', 
                      fontSize: '1.05rem', 
                      fontWeight: 800,
                      display: 'flex',
                      alignItems: 'center',
                      gap: '10px',
                      opacity: item.is_available ? 1 : 0.6,
                      cursor: item.is_available ? 'pointer' : 'not-allowed'
                    }}
                  >
                    <span>🛒</span> Add to Cart
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Reviews Section */}
          <div style={{
            background: 'var(--bg-card)',
            borderRadius: 'var(--radius)',
            boxShadow: 'var(--shadow)',
            border: '1px solid var(--border)',
            padding: '2.5rem',
            marginTop: '2rem'
          }}>
            <h2 style={{ fontSize: '1.5rem', fontWeight: 800, color: 'var(--text)', marginBottom: '1.5rem' }}>
              💬 Customer Reviews ({reviews.length})
            </h2>

            {reviewLoading ? (
              <p style={{ color: 'var(--text-muted)' }}>Loading reviews...</p>
            ) : reviews.length === 0 ? (
              <div style={{ padding: '2.5rem 0', textAlign: 'center', color: 'var(--text-muted)' }}>
                <span style={{ fontSize: '2.5rem', display: 'block', marginBottom: '0.5rem' }}>🍲</span>
                No reviews yet. Place an order to write a review!
              </div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem', maxHeight: '400px', overflowY: 'auto', paddingRight: '8px' }}>
                {reviews.map(r => (
                  <div key={r.id} style={{
                    borderBottom: '1px solid var(--border)',
                    paddingBottom: '1rem'
                  }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                      <strong style={{ color: 'var(--text)', fontSize: '0.95rem' }}>{r.customer_name}</strong>
                      <span style={{ color: '#E6A817', fontWeight: 700, fontSize: '0.85rem' }}>
                        {'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)}
                      </span>
                    </div>
                    <p style={{ color: 'var(--text-muted)', fontSize: '0.92rem', lineHeight: '1.5' }}>{r.comment}</p>
                    <span style={{ fontSize: '0.75rem', color: 'var(--text-dim)', marginTop: '4px', display: 'block' }}>
                      {new Date(r.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                    </span>
                    {r.admin_reply && (
                      <div style={{
                        marginTop: '0.75rem',
                        padding: '0.75rem 1rem',
                        background: 'rgba(230, 168, 23, 0.05)',
                        borderLeft: '3px solid var(--primary)',
                        borderRadius: '0 8px 8px 0',
                        fontSize: '0.88rem'
                      }}>
                        <div style={{ fontWeight: 700, color: 'var(--primary-dark)', display: 'flex', alignItems: 'center', gap: '4px', marginBottom: '2px' }}>
                          🧑‍🍳 Swaad E Punjab <span style={{ fontSize: '0.72rem', fontWeight: 600, color: 'var(--text-muted)' }}> (Admin Response)</span>
                        </div>
                        <p style={{ color: 'var(--text)', margin: 0, lineHeight: '1.4' }}>{r.admin_reply}</p>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

            {/* Suggestions Section */}
            {suggestions.length > 0 && (
              <div style={{ marginTop: '4rem' }}>
                <h2 style={{ 
                  fontSize: '1.75rem', 
                  fontWeight: 900, 
                  color: 'var(--text)', 
                  marginBottom: '1.5rem',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px'
                }}>
                  🍲 You Might Also Like
                </h2>
                <div className="menu-grid" style={{ 
                  display: 'grid', 
                  gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', 
                  gap: '2rem' 
                }}>
                  {suggestions.map((suggested) => (
                    <SuggestedFoodCard 
                      key={suggested.id}
                      item={suggested}
                      onAdd={handleAddToSuggestionsCart}
                      categoryEmoji={categoryEmoji}
                      categoryLabels={categoryLabels}
                    />
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {toast && <Toast message={toast} type="success" />}
    </div>
  );
}

function SuggestedFoodCard({ item, onAdd, categoryEmoji, categoryLabels }: { item: MenuItem; onAdd: (i: MenuItem) => void; categoryEmoji: Record<string, string>; categoryLabels: Record<string, string> }) {
  const router = useRouter();
  const { state, dispatch } = useCart();
  const cartItem = state.items.find(i => i.menu_item_id === item.id);
  const quantity = cartItem ? cartItem.quantity : 0;

  const getFirstImage = (imageField: any) => {
    if (!imageField) return null;
    if (Array.isArray(imageField)) {
      return imageField[0] || null;
    }
    if (typeof imageField === 'string') {
      try {
        const decoded = JSON.parse(imageField);
        if (Array.isArray(decoded)) {
          return decoded[0] || null;
        }
      } catch {}
      return imageField;
    }
    return null;
  };

  const displayImage = getFirstImage(item.image);

  return (
    <div 
      className="food-card" 
      style={{ cursor: 'pointer', height: '100%' }}
      onClick={() => router.push(`/item/${item.id}`)}
    >
      <div className="food-card-image-wrap">
        {displayImage ? (
          <img 
            src={displayImage.startsWith('http://') || displayImage.startsWith('https://') 
              ? displayImage 
              : `${API.replace('/api', '/storage')}/${displayImage}`} 
            alt={item.name} 
            className="food-card-image"
          />
        ) : (
          <div className="food-card-image-placeholder">
            {categoryEmoji[item.category] || '🍽️'}
          </div>
        )}
        {item.is_featured && <span className="featured-badge">⭐ Special</span>}
      </div>
      <div className="food-card-body">
        <div className="food-card-category">{categoryLabels[item.category] || item.category}</div>
        <div className="food-card-name">{item.name}</div>
        <div className="food-card-desc">{item.description}</div>
        <div className="food-card-meta">
          <div className="food-card-info">
            <span className="food-card-rating">★ {item.rating}</span>
          </div>
        </div>
      </div>
      <div className="food-card-footer">
        <span className="food-card-price" style={{ fontSize: '1.15rem', fontWeight: 800 }}>
          ₹{Number(item.price).toFixed(0)}
        </span>
        {quantity > 0 ? (
          <div className="quantity-control-card" onClick={(e) => e.stopPropagation()}>
            <button 
              type="button" 
              onClick={(e) => {
                e.stopPropagation();
                dispatch({ type: 'DECREMENT', id: item.id });
              }}
              className="qty-control-btn"
              title="Quantity kam karein"
            >
              −
            </button>
            <span className="qty-control-val">{quantity}</span>
            <button 
              type="button" 
              onClick={(e) => {
                e.stopPropagation();
                dispatch({ type: 'INCREMENT', id: item.id });
              }}
              className="qty-control-btn"
              title="Quantity badhayein"
            >
              +
            </button>
          </div>
        ) : (
          <button 
            className="add-btn" 
            onClick={(e) => {
              e.stopPropagation();
              onAdd(item);
            }} 
            title="Cart mein add karein"
          >
            +
          </button>
        )}
      </div>
    </div>
  );
}
