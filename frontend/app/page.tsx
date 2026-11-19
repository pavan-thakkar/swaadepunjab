'use client';

import { useState, useEffect, useRef, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { useCart, MenuItem } from './context/CartContext';
import CartDrawer from './components/CartDrawer';
import Navbar from './components/Navbar';
import Toast from './components/Toast';
import FeaturedCards from './components/FeaturedCards';
import OfferProgress from './components/OfferProgress';
import { searchMenu } from './lib/search';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

const DEFAULT_CATEGORIES = [
  { key: 'all',            label: 'All',            emoji: '🍽️' },
  { key: 'special_combo',  label: 'Special Combos',  emoji: '🍱' },
  { key: 'chur_chur_naan', label: 'Chur Chur Naan', emoji: '🫓' },
  { key: 'punjabi_sabji',  label: 'Punjabi Sabji',  emoji: '🍲' },
  { key: 'sabji_combo',    label: 'Sabji Combo',    emoji: '🍛' },
  { key: 'aloo_combo',     label: 'Aloo Combo',     emoji: '🥔' },
  { key: 'rice_combo',     label: 'Rice Combo',     emoji: '🍚' },
];

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

export default function HomePage() {
  const router = useRouter();
  const { dispatch, itemCount, total } = useCart();
  const [delivery, setDelivery] = useState<{ available: boolean; hours: string | null } | null>(null);
  const [savedArea, setSavedArea] = useState<string | null>(null);
  const [items, setItems]         = useState<MenuItem[]>([]);
  const [allItems, setAllItems]   = useState<MenuItem[]>([]);
  const [suggestions, setSuggestions] = useState<MenuItem[]>([]);
  const [loading, setLoading]     = useState(true);
  const [category, setCategory]   = useState('all');
  const [search, setSearch]       = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [toast, setToast] = useState<{ msg: string; type: 'success' | 'error' } | null>(null);
  const [promoSlides, setPromoSlides] = useState<any[]>([]);
  const [activeSlide, setActiveSlide] = useState(0);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const menuRef = useRef<HTMLElement>(null);
  const fetchSeq = useRef(0);
  const [activeIdx, setActiveIdx] = useState(-1);
  const [showSuggest, setShowSuggest] = useState(false);

  const [categories, setCategories] = useState<any[]>(DEFAULT_CATEGORIES);
  const [categoryEmoji, setCategoryEmoji] = useState<Record<string, string>>(DEFAULT_CATEGORY_EMOJI);
  const [categoryLabels, setCategoryLabels] = useState<Record<string, string>>(DEFAULT_CATEGORY_LABELS);

  const fetchCategories = async () => {
    try {
      const res = await fetch(`${API}/categories`);
      const data = await res.json();
      const list = data.data || [];
      if (list.length > 0) {
        const dynamicCats = [
          { key: 'all', label: 'All', emoji: '🍽️' },
          ...list.map((c: any) => ({
            key: c.slug,
            label: c.name,
            emoji: c.emoji || '🍽️'
          }))
        ];
        setCategories(dynamicCats);

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

  const fetchPromoSlides = async () => {
    try {
      const res = await fetch(`${API}/marketing-cards`);
      const data = await res.json();
      setPromoSlides(data.data || []);
    } catch (e) {
      console.error("Error fetching promo cards:", e);
    }
  };

  const fetchAllItems = async () => {
    try {
      const res = await fetch(`${API}/menu`);
      const data = await res.json();
      setAllItems(data.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  useEffect(() => {
    fetchPromoSlides();
    fetchAllItems();
    fetchCategories();
    fetch(`${API}/delivery-status`, { cache: 'no-store' })
      .then(r => r.json())
      .then(d => setDelivery({ available: d.available !== false, hours: d.hours || null }))
      .catch(() => {});
    try {
      const saved = JSON.parse(localStorage.getItem('swaad_user_address') || 'null');
      if (saved) {
        const area = [saved.apartment_name, saved.city].filter(Boolean).join(', ') || saved.delivery_address;
        if (area) setSavedArea(String(area).slice(0, 40));
      }
    } catch {}
  }, []);

  useEffect(() => {
    if (promoSlides.length === 0) return;
    const timer = setInterval(() => {
      setActiveSlide(prev => (prev + 1) % promoSlides.length);
    }, 4500);
    return () => clearInterval(timer);
  }, [promoSlides]);

  const handlePromoOrder = (slide: any) => {
    const matchedItem = items.find(i => i.id === slide.menu_item_id);
    if (matchedItem) {
      handleAddToCart(matchedItem);
    } else {
      dispatch({ 
        type: 'ADD', 
        item: { 
          id: slide.menu_item_id || Math.floor(Math.random() * 1000 + 100), 
          name: slide.title, 
          price: parseFloat(slide.price ? slide.price.replace('₹', '') : '100'),
          category: 'special_combo',
          description: slide.description || '',
          prep_time: 30,
          rating: 4.8,
          is_available: true,
          is_featured: true,
          image: ''
        } 
      });
      showToast(`${slide.title} cart mein add ho gaya! 🛒`);
    }
  };

  const showToast = (msg: string, type: 'success' | 'error' = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 2500);
  };

  const fetchMenu = async (cat: string) => {
    const seq = ++fetchSeq.current;
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (cat !== 'all') params.set('category', cat);
      const res = await fetch(`${API}/menu?${params}`, { cache: 'no-store' });
      const data = await res.json();
      if (seq === fetchSeq.current) setItems(data.data || []); // ignore late, older replies
    } catch {
      if (seq === fetchSeq.current) showToast('Could not load the menu. Please check your internet and try again.', 'error');
    } finally {
      if (seq === fetchSeq.current) setLoading(false);
    }
  };

  useEffect(() => {
    fetchMenu(category);
  }, [category]);

  // Debounced search trigger as user types
  useEffect(() => {
    const handler = setTimeout(() => {
      setSearchQuery(search);
    }, 300);

    return () => clearTimeout(handler);
  }, [search]);

  // Suggestions while typing (forgiving search over the whole menu)
  useEffect(() => {
    setSuggestions(search.trim() ? searchMenu(allItems, search, categoryLabels).slice(0, 6) : []);
    setActiveIdx(-1);
  }, [search, allItems, categoryLabels]);

  // Results for the menu list: whole menu, every category
  const searchResults = useMemo(
    () => (searchQuery.trim() ? searchMenu(allItems.length ? allItems : items, searchQuery, categoryLabels) : null),
    [searchQuery, allItems, items, categoryLabels]
  );

  // Close suggestions dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowSuggest(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleSuggestionClick = (item: MenuItem) => {
    setShowSuggest(false);
    router.push(`/item/${item.id}`);
  };

  const showResults = () => {
    setSearchQuery(search);
    setShowSuggest(false);
    setTimeout(() => menuRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (activeIdx >= 0 && suggestions[activeIdx]) { handleSuggestionClick(suggestions[activeIdx]); return; }
    showResults();
  };

  const clearSearch = () => {
    setSearch('');
    setSearchQuery('');
    setShowSuggest(false);
  };

  const handleSearchKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown' && suggestions.length) { e.preventDefault(); setShowSuggest(true); setActiveIdx(i => (i + 1) % suggestions.length); }
    else if (e.key === 'ArrowUp' && suggestions.length) { e.preventDefault(); setActiveIdx(i => (i <= 0 ? suggestions.length - 1 : i - 1)); }
    else if (e.key === 'Escape') { setShowSuggest(false); setActiveIdx(-1); }
  };

  const handleAddToCart = (item: MenuItem) => {
    dispatch({ type: 'ADD', item });
    showToast(`${item.name} cart mein add ho gaya! 🛒`);
  };

  const featured = items.filter(i => i.is_featured);
  const regular  = items.filter(i => !i.is_featured);
  // Search shows every match (bestsellers too). Without search, the full menu skips the
  // bestsellers already shown above; a single category shows all its dishes.
  const shownItems: MenuItem[] = searchResults
    ? searchResults
    : category === 'all' && featured.length > 0 ? regular : items;

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />

      {/* Top: status, search, offers */}
      <section className="home-top">
        {savedArea && (
          <button type="button" className="home-location" onClick={() => router.push('/checkout')}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
            <span>Delivering to <strong>{savedArea}</strong></span>
          </button>
        )}
        <div className="status-pills">
          {delivery && (
            delivery.available ? (
              <span className="status-pill open"><span className="status-dot" />Open{delivery.hours ? ` · Delivery ${delivery.hours}` : ' · Delivering now'}</span>
            ) : (
              <span className="status-pill closed"><span className="status-dot" />Delivery closed · Take Away open</span>
            )
          )}
          <span className="status-pill"><span className="veg-mark" />100% Pure Veg</span>
          <span className="status-pill">30–45 min</span>
        </div>

        <div ref={dropdownRef} style={{ position: 'relative', width: '100%' }}>
          <form className="home-search" onSubmit={handleSearch} role="search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A6040" strokeWidth="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input
              type="search"
              placeholder="Search paneer, naan, thali…"
              aria-label="Search the menu"
              role="combobox"
              aria-expanded={showSuggest && suggestions.length > 0}
              aria-controls="search-suggest-list"
              aria-autocomplete="list"
              aria-activedescendant={activeIdx >= 0 ? `sugg-${suggestions[activeIdx]?.id}` : undefined}
              autoComplete="off"
              enterKeyHint="search"
              value={search}
              onChange={e => { setSearch(e.target.value); setShowSuggest(true); }}
              onFocus={() => setShowSuggest(true)}
              onKeyDown={handleSearchKey}
            />
            {search && (
              <button type="button" className="home-search-clear" onClick={clearSearch} aria-label="Clear search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
              </button>
            )}
            {search && <button type="submit">Search</button>}
          </form>

          {showSuggest && search.trim() && (
            <ul className="search-suggestions" id="search-suggest-list" role="listbox" aria-label="Matching dishes">
              {suggestions.length === 0 && (
                <li className="search-suggestion-item" aria-disabled="true" style={{ cursor: 'default', color: 'var(--text-muted)' }}>
                  No dishes match &quot;{search}&quot;. Try another word, like paneer, naan or dal.
                </li>
              )}
              {suggestions.map((item, idx) => (
                <li 
                  key={item.id} 
                  id={`sugg-${item.id}`}
                  role="option"
                  aria-selected={idx === activeIdx}
                  className={`search-suggestion-item${idx === activeIdx ? ' active' : ''}`} 
                  onMouseDown={e => e.preventDefault()}
                  onMouseEnter={() => setActiveIdx(idx)}
                  onClick={() => handleSuggestionClick(item)}
                >
                  <span className="search-suggestion-emoji">{categoryEmoji[item.category] || '🍽️'}</span>
                  <span className="search-suggestion-name">{item.name}</span>
                  <span className="search-suggestion-price">₹{Number(item.price).toFixed(0)}</span>
                </li>
              ))}
              {suggestions.length > 0 && (
                <li role="option" aria-selected={false} className="search-suggestion-item search-see-all" onMouseDown={e => e.preventDefault()} onClick={showResults}>
                  See all results for &quot;{search}&quot;
                </li>
              )}
            </ul>
          )}
        </div>

        <div id="offers" style={{ scrollMarginTop: 90 }}>
          <OfferProgress subtotal={total} variant="dark" showWhenEmpty />
        </div>
      </section>

      {/* Marketing Promo Carousel */}
      {promoSlides.length > 0 && (
        <section className="section" style={{ paddingBottom: 0 }}>
          <div className="promo-slider-container">
            {promoSlides.map((slide, idx) => {
              const bgImage = slide.image_path 
                ? `${API.replace('/api', '')}/storage/${slide.image_path}` 
                : slide.image_url;
              return (
                <div 
                  key={slide.id} 
                  className={`promo-slide${activeSlide === idx ? ' active' : ''}`}
                >
                  {/* background video loop */}
                  {slide.video_url && (
                    <video 
                      className="promo-video-bg" 
                      src={slide.video_url} 
                      autoPlay 
                      loop 
                      muted 
                      playsInline 
                    />
                  )}
                  {/* background image fallback if video fails */}
                  {bgImage && (
                    <div 
                      className="promo-image-fallback" 
                      style={{ backgroundImage: `url(${bgImage})` }}
                    />
                  )}
                  
                  {/* sleek dark gradient overlay */}
                  <div className="promo-slide-overlay" />

                  {/* content text & buttons */}
                  <div className="promo-content">
                    <span className="promo-tag">{slide.tag || 'PROMO'}</span>
                    <h2 className="promo-title">{slide.title}</h2>
                    <p className="promo-desc">{slide.description}</p>
                    <div className="promo-actions">
                      {slide.price && <span className="promo-price">{slide.price}</span>}
                      {slide.menu_item_id && items.some(i => i.id === slide.menu_item_id) && (
                        <button 
                          type="button" 
                          className="promo-btn"
                          onClick={() => handlePromoOrder(slide)}
                        >
                          ⚡ Order Now
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}

            {/* navigation dots */}
            <div className="promo-dots">
              {promoSlides.map((_, idx) => (
                <button 
                  key={idx} 
                  type="button" 
                  className={`promo-dot${activeSlide === idx ? ' active' : ''}`}
                  onClick={() => setActiveSlide(idx)}
                  aria-label={`Go to slide ${idx + 1}`}
                />
              ))}
            </div>
          </div>
        </section>
      )}

      {/* What's New cards (admin: Catalog → What's New Cards) */}
      <FeaturedCards />

      {/* Categories */}
      <section className="section">
        <div className="section-header">
          <h2 className="section-title">What would you like?</h2>
        </div>
        <div className="categories">
          {categories.map((c, i) => {
            const isActive = category === c.key;
            const gradients = [
              'linear-gradient(135deg,#f97316,#ef4444)',
              'linear-gradient(135deg,#f59e0b,#f97316)',
              'linear-gradient(135deg,#eab308,#f59e0b)',
              'linear-gradient(135deg,#22c55e,#16a34a)',
              'linear-gradient(135deg,#a855f7,#7c3aed)',
              'linear-gradient(135deg,#ec4899,#f43f5e)',
              'linear-gradient(135deg,#06b6d4,#3b82f6)',
              'linear-gradient(135deg,#f43f5e,#e11d48)',
            ];
            const gradient = gradients[i % gradients.length];
            return (
              <button
                key={c.key}
                className={`story-circle${isActive ? ' active' : ''}`}
                onClick={() => { setCategory(c.key); setSearchQuery(''); setSearch(''); }}
              >
                <span
                  className="story-ring"
                  style={{ background: isActive ? gradient : 'transparent', borderColor: isActive ? 'transparent' : 'var(--border)' }}
                >
                  <span
                    className="story-inner"
                    style={{ background: isActive ? '#fff' : '#f9f5ee' }}
                  >
                    <span className="story-emoji">{c.emoji}</span>
                  </span>
                </span>
                <span className={`story-label${isActive ? ' active' : ''}`}>{c.label}</span>
              </button>
            );
          })}
        </div>
      </section>

      {/* Featured */}
      {category === 'all' && !searchQuery && featured.length > 0 && (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="section-header">
            <h2 className="section-title">Bestsellers</h2>
          </div>
          <div className="best-grid">
            {featured.map(item => (
              <BestCard key={item.id} item={item} onAdd={handleAddToCart} emoji={categoryEmoji[item.category] || '🍽️'} />
            ))}
          </div>
        </section>
      )}

      {/* Menu Grid */}
      <section className="section" ref={menuRef} style={{ paddingTop: 0, scrollMarginTop: 80 }}>
        <div className="section-header">
          <h2 className="section-title">
            {searchQuery
              ? `Results for "${searchQuery}"`
              : category === 'all'
              ? 'Full Menu'
              : `${categoryEmoji[category] || ''} ${categories.find(c => c.key === category)?.label || category}`}
          </h2>
          <span style={{ display: 'flex', alignItems: 'center', gap: 10, color: 'var(--text-muted)', fontSize: '0.9rem' }}>
            {shownItems.length} {shownItems.length === 1 ? 'dish' : 'dishes'}
            {searchQuery && <button type="button" className="section-link" onClick={clearSearch}>Clear search</button>}
          </span>
        </div>

        {loading && !searchQuery ? (
          <div className="menu-grid">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="food-card">
                <div className="skeleton" style={{ height: 180 }} />
                <div style={{ padding: '1rem' }}>
                  <div className="skeleton" style={{ height: 16, marginBottom: 8, width: '60%' }} />
                  <div className="skeleton" style={{ height: 20, marginBottom: 8 }} />
                  <div className="skeleton" style={{ height: 14, marginBottom: 4 }} />
                  <div className="skeleton" style={{ height: 14, width: '70%' }} />
                </div>
              </div>
            ))}
          </div>
        ) : shownItems.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-icon">🍽️</div>
            <h3>{searchQuery ? `No dishes match "${searchQuery}"` : 'No dishes here yet'}</h3>
            <p>{searchQuery ? 'Check the spelling or try a simpler word, like paneer, naan or dal.' : 'Try another category.'}</p>
            {searchQuery && <button type="button" className="btn-secondary" style={{ marginTop: '1rem' }} onClick={clearSearch}>Show full menu</button>}
          </div>
        ) : (
          <div className="menu-grid">
            {shownItems.map((item, i) => (
              <FoodCard key={item.id} item={item} onAdd={handleAddToCart} delay={i * 60} categoryEmoji={categoryEmoji} categoryLabels={categoryLabels} />
            ))}
          </div>
        )}
      </section>

      {toast && <Toast message={toast.msg} type={toast.type} />}

      <footer>
        <p>© 2026 <strong style={{ color: 'var(--primary)' }}>Swaad E Punjab</strong> — Dil se banaya, ghar tak pahunchaya 🧡</p>
      </footer>
    </div>
  );
}

function FoodCard({ item, onAdd, delay, categoryEmoji, categoryLabels }: { item: MenuItem; onAdd: (i: MenuItem) => void; delay: number; categoryEmoji: Record<string, string>; categoryLabels: Record<string, string> }) {
  const router = require('next/navigation').useRouter();
  const { state, dispatch } = useCart();
  const cartItem = state.items.find(i => i.menu_item_id === item.id);
  const quantity = cartItem ? cartItem.quantity : 0;

  const [imgIdx, setImgIdx] = useState(0);

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

  const imagesList = getImagesList(item.image);
  const currentImage = imagesList[imgIdx] || null;

  return (
    <div 
      className="food-card" 
      style={{ animationDelay: `${delay}ms`, cursor: 'pointer' }}
      onClick={() => router.push(`/item/${item.id}`)}
    >
      {/* Details Side (Left on mobile, bottom on desktop) */}
      <div className="food-card-body">
        <div className="food-card-top-meta">
          <div className="veg-badge-icon" title="100% Pure Veg">
            <span className="veg-badge-dot" />
          </div>
          {item.is_featured && <span className="featured-badge-inline">⭐ Special</span>}
        </div>

        <div className="food-card-name">{item.name}</div>

        <div className="food-card-price-row">
          <span className="food-card-price">₹{Number(item.price).toFixed(0)}</span>
          {item.rating && (
            <span className="food-card-rating">★ {item.rating}</span>
          )}
        </div>

        {item.description && (
          <div className="food-card-desc">{item.description}</div>
        )}
      </div>

      {/* Media & Action Side (Right on mobile, top on desktop) */}
      <div className="food-card-media-side">
        <div className="food-card-image-wrap">
          {currentImage ? (
            <img 
              src={currentImage.startsWith('http://') || currentImage.startsWith('https://') 
                ? currentImage 
                : `${API.replace('/api', '/storage')}/${currentImage}`} 
              alt={item.name} 
              className="food-card-image"
            />
          ) : (
            <div className="food-card-image-placeholder">
              {categoryEmoji[item.category] || '🍽️'}
            </div>
          )}

          {/* Multi-Photo Arrows on Card */}
          {imagesList.length > 1 && (
            <>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setImgIdx((prev) => (prev - 1 + imagesList.length) % imagesList.length);
                }}
                className="food-card-img-nav-btn left"
              >
                ‹
              </button>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setImgIdx((prev) => (prev + 1) % imagesList.length);
                }}
                className="food-card-img-nav-btn right"
              >
                ›
              </button>
            </>
          )}
        </div>

        {/* Floating Action Button */}
        <div className="food-card-action-wrap" onClick={(e) => e.stopPropagation()}>
          {quantity > 0 ? (
            <div className="quantity-control-card">
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
              type="button"
              className="swiggy-add-btn" 
              onClick={(e) => {
                e.stopPropagation();
                onAdd(item);
              }} 
              title="Cart mein add karein"
            >
              ADD <span style={{ fontSize: '1rem', marginLeft: '2px', fontWeight: 900 }}>+</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function firstImage(imageField: any): string | null {
  let list: string[] = [];
  if (Array.isArray(imageField)) list = imageField.filter(Boolean);
  else if (typeof imageField === 'string' && imageField) {
    try { const d = JSON.parse(imageField); list = Array.isArray(d) ? d.filter(Boolean) : [imageField]; } catch { list = [imageField]; }
  }
  const img = list[0];
  if (!img) return null;
  return img.startsWith('http') ? img : `${API.replace('/api', '/storage')}/${img}`;
}

function BestCard({ item, onAdd, emoji }: { item: MenuItem; onAdd: (i: MenuItem) => void; emoji: string }) {
  const router = useRouter();
  const { state, dispatch } = useCart();
  const qty = state.items.find(i => i.menu_item_id === item.id)?.quantity || 0;
  const img = firstImage(item.image);
  return (
    <div className="best-card" role="link" tabIndex={0} onClick={() => router.push(`/item/${item.id}`)} onKeyDown={e => { if (e.key === 'Enter') router.push(`/item/${item.id}`); }}>
      <div className="best-card-img">
        {img ? <img src={img} alt={item.name} loading="lazy" /> : <span aria-hidden="true">{emoji}</span>}
        {item.rating ? <span className="best-card-rating">★ {Number(item.rating).toFixed(1)}</span> : null}
      </div>
      <div className="best-card-body">
        <div className="best-card-name">{item.name}</div>
        <div className="best-card-row" onClick={e => e.stopPropagation()}>
          <span className="best-card-price">₹{Number(item.price).toFixed(0)}</span>
          {qty > 0 ? (
            <div className="quantity-control-card" style={{ minWidth: 84 }}>
              <button type="button" className="qty-control-btn" aria-label={`Remove one ${item.name}`} onClick={() => dispatch({ type: 'DECREMENT', id: item.id })}>−</button>
              <span className="qty-control-val">{qty}</span>
              <button type="button" className="qty-control-btn" aria-label={`Add one ${item.name}`} onClick={() => dispatch({ type: 'INCREMENT', id: item.id })}>+</button>
            </div>
          ) : (
            <button type="button" className="best-add" onClick={() => onAdd(item)}>ADD</button>
          )}
        </div>
      </div>
    </div>
  );
}
