'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { useCart, MenuItem } from './context/CartContext';
import CartDrawer from './components/CartDrawer';
import Navbar from './components/Navbar';
import Toast from './components/Toast';

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
  const { dispatch, itemCount } = useCart();
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

  const fetchMenu = async (cat: string, q: string) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (cat !== 'all') params.set('category', cat);
      if (q) params.set('search', q);
      const res = await fetch(`${API}/menu?${params}`);
      const data = await res.json();
      setItems(data.data || []);
    } catch {
      showToast('Backend se connect nahi ho pa raha. Check karein!', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMenu(category, searchQuery);
  }, [category, searchQuery]);

  // Debounced search trigger as user types
  useEffect(() => {
    const handler = setTimeout(() => {
      setSearchQuery(search);
    }, 300);

    return () => clearTimeout(handler);
  }, [search]);

  // Generate suggestions list from allItems based on search input
  useEffect(() => {
    if (!search.trim()) {
      setSuggestions([]);
      return;
    }
    const query = search.toLowerCase();
    const filtered = allItems.filter(item => 
      item.name.toLowerCase().includes(query) || 
      item.description?.toLowerCase().includes(query)
    ).slice(0, 5); // Max 5 suggestions
    setSuggestions(filtered);
  }, [search, allItems]);

  // Close suggestions dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setSuggestions([]);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleSuggestionClick = (item: MenuItem) => {
    setSearch(item.name);
    setSearchQuery(item.name);
    setSuggestions([]);
    router.push(`/item/${item.id}`);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setSearchQuery(search);
    setSuggestions([]);
  };

  const handleAddToCart = (item: MenuItem) => {
    dispatch({ type: 'ADD', item });
    showToast(`${item.name} cart mein add ho gaya! 🛒`);
  };

  const featured = items.filter(i => i.is_featured);
  const regular  = items.filter(i => !i.is_featured);

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />

      {/* Hero */}
      <section className="hero">
        <h1>
          Punjab da <span className="highlight">Asli Swad,</span><br />
          Ghar Baitha!
        </h1>
        <p>Amritsar di Kulfi se lekar Delhi di Daal tak — sab kuch ek click mein. Garam, taaza, seedha aapke ghar!</p>

        <div ref={dropdownRef} style={{ position: 'relative', width: '100%', maxWidth: '580px', margin: '0 auto' }}>
          <form className="search-bar" onSubmit={handleSearch}>
            <span>🔍</span>
            <input
              type="text"
              placeholder="Kya khaenge aaj? Search karein..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
            <button type="submit" className="search-btn">Khojo</button>
          </form>

          {suggestions.length > 0 && (
            <ul className="search-suggestions">
              {suggestions.map(item => (
                <li 
                  key={item.id} 
                  className="search-suggestion-item" 
                  onClick={() => handleSuggestionClick(item)}
                >
                  <span className="search-suggestion-emoji">{categoryEmoji[item.category] || '🍽️'}</span>
                  <span className="search-suggestion-name">{item.name}</span>
                  <span className="search-suggestion-price">₹{Number(item.price).toFixed(0)}</span>
                </li>
              ))}
            </ul>
          )}
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

      {/* Categories */}
      <section className="section">
        <div className="section-header">
          <h2 className="section-title">Category se <span>Chunein</span></h2>
        </div>
        <div className="categories">
          {categories.map(c => (
            <button
              key={c.key}
              className={`category-chip${category === c.key ? ' active' : ''}`}
              onClick={() => { setCategory(c.key); setSearchQuery(''); setSearch(''); }}
            >
              <span>{c.emoji}</span> {c.label}
            </button>
          ))}
        </div>
      </section>

      {/* Featured */}
      {category === 'all' && !searchQuery && featured.length > 0 && (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="section-header">
            <h2 className="section-title">⭐ <span>Aaj ka</span> Special</h2>
          </div>
          <div className="menu-grid">
            {featured.map((item, i) => (
              <FoodCard key={item.id} item={item} onAdd={handleAddToCart} delay={i * 60} categoryEmoji={categoryEmoji} categoryLabels={categoryLabels} />
            ))}
          </div>
        </section>
      )}

      {/* Menu Grid */}
      <section className="section" style={{ paddingTop: 0 }}>
        <div className="section-header">
          <h2 className="section-title">
            {searchQuery
              ? `"${searchQuery}" ke results`
              : category === 'all'
              ? 'Hamara Poora Menu'
              : `${categoryEmoji[category] || ''} ${categories.find(c => c.key === category)?.label || category}`}
          </h2>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>{items.length} items</span>
        </div>

        {loading ? (
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
        ) : regular.length === 0 && (category === 'all' || featured.length === 0) ? (
          <div className="empty-state">
            <div className="empty-state-icon">🍽️</div>
            <h3>Kuch nahi mila</h3>
            <p>Doosri category ya search try karein.</p>
          </div>
        ) : (
          <div className="menu-grid">
            {(category === 'all' && !searchQuery ? regular : items).map((item, i) => (
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
      style={{ animationDelay: `${delay}ms`, cursor: 'pointer' }}
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

