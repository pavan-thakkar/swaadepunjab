'use client';

import { useState, useEffect, useRef } from 'react';
import { useCart } from '../context/CartContext';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

const CATEGORY_EMOJI: Record<string, string> = {
  burgers: '🍔', pizza: '🍕', asian: '🍜', desserts: '🍰', drinks: '🥤',
};

export default function CheckoutPage() {
  const { state, dispatch, total, deliveryFee, userPhone, userName } = useCart();
  const router = useRouter();
  const [payment, setPayment] = useState<'cash_on_delivery' | 'card' | 'razorpay'>('cash_on_delivery');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const [form, setForm] = useState({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    delivery_address: '',
    city: '',
    apartment_no: '',
    apartment_name: '',
    special_instructions: '',
    table_number: '',
    pickup_time: '',
  });

  const [orderType, setOrderType] = useState<'delivery' | 'dine_in' | 'takeaway'>('delivery');

  const [coordinates, setCoordinates] = useState<{ lat: number; lng: number } | null>(null);

  const [customDeliveryFee, setCustomDeliveryFee] = useState<number | null>(null);
  const [distanceKm, setDistanceKm] = useState<number | null>(null);
  const [distanceError, setDistanceError] = useState<string>('');

  const updateDeliveryDistanceAndFee = async (address: string, city: string) => {
    if (!address) return;
    try {
      const res = await fetch(`${API}/calculate-delivery-fee`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          delivery_address: address,
          city: city || 'Amritsar',
          subtotal: total,
          latitude: coordinates ? coordinates.lat : null,
          longitude: coordinates ? coordinates.lng : null,
        })
      });
      if (res.ok) {
        const data = await res.json();
        if (data.success) {
          setCustomDeliveryFee(data.fee);
          setDistanceKm(data.distance_km);
          if (data.error) {
            setDistanceError(data.error);
            setError(data.error);
          } else {
            setDistanceError('');
            setError('');
          }
        }
      }
    } catch (err) {
      console.warn("Failed to fetch distance delivery fee:", err);
    }
  };

  useEffect(() => {
    if (orderType !== 'delivery') {
      setCustomDeliveryFee(null);
      setDistanceKm(null);
      setDistanceError('');
      return;
    }
    const delayDebounceFn = setTimeout(() => {
      if (form.delivery_address) {
        const fullAddress = [
          form.apartment_no ? `Apt/Flat: ${form.apartment_no}` : '',
          form.apartment_name ? `Building/Landmark: ${form.apartment_name}` : '',
          form.delivery_address
        ].filter(Boolean).join(', ');
        updateDeliveryDistanceAndFee(fullAddress, form.city);
      }
    }, 1000);

    return () => clearTimeout(delayDebounceFn);
  }, [form.delivery_address, form.city, form.apartment_no, form.apartment_name, orderType, coordinates]);
  const [locating, setLocating] = useState(false);
  const [showSearch, setShowSearch] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [searchResults, setSearchResults] = useState<any[]>([]);

  const [mapLoaded, setMapLoaded] = useState(false);
  const mapRef = useRef<any>(null);
  const markerRef = useRef<any>(null);

  // Razorpay Simulation States
  const [showRazorpaySim, setShowRazorpaySim] = useState(false);
  const [razorpayMethod, setRazorpayMethod] = useState<'upi' | 'card' | 'netbanking'>('upi');
  const [simUPIApp, setSimUPIApp] = useState<'gpay' | 'phonepe' | 'paytm' | 'other'>('gpay');
  const [simUPIId, setSimUPIId] = useState('');
  const [simCardNumber, setSimCardNumber] = useState('');
  const [simCardName, setSimCardName] = useState('');
  const [simCardExpiry, setSimCardExpiry] = useState('');
  const [simCardCvv, setSimCardCvv] = useState('');
  const [paymentSimulating, setPaymentSimulating] = useState(false);
  const [simSuccess, setSimSuccess] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    // Load Leaflet CSS dynamically
    if (!document.getElementById('leaflet-css')) {
      const link = document.createElement('link');
      link.id = 'leaflet-css';
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(link);
    }

    // Load Leaflet JS dynamically
    if (!document.getElementById('leaflet-js')) {
      const script = document.createElement('script');
      script.id = 'leaflet-js';
      script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      script.async = true;
      script.onload = () => {
        setMapLoaded(true);
      };
      document.body.appendChild(script);
    } else {
      if ((window as any).L) {
        setMapLoaded(true);
      }
    }

    // Load Razorpay JS SDK dynamically
    if (!document.getElementById('razorpay-js')) {
      const rpScript = document.createElement('script');
      rpScript.id = 'razorpay-js';
      rpScript.src = 'https://checkout.razorpay.com/v1/checkout.js';
      rpScript.async = true;
      document.body.appendChild(rpScript);
    }

    return () => {
      if (mapRef.current) {
        mapRef.current.remove();
        mapRef.current = null;
        markerRef.current = null;
      }
    };
  }, []);

  const reverseGeocode = async (lat: number, lng: number) => {
    try {
      const res = await fetch(
        `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`
      );
      const data = await res.json();
      if (data && data.address) {
        const addr = data.display_name || '';
        const city = data.address.city || data.address.town || data.address.village || data.address.suburb || '';
        setForm(prev => ({
          ...prev,
          delivery_address: addr,
          city: city,
        }));
      }
    } catch (e) {
      console.error("Reverse geocoding error:", e);
    }
  };

  const initMap = (lat: number, lng: number) => {
    if (typeof window === 'undefined' || !(window as any).L) return;
    const L = (window as any).L;

    // Custom marker icon logic to prevent icon image loading issues in Next.js
    const customIcon = L.icon({
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    });

    if (!mapRef.current) {
      const mapContainer = document.getElementById('map-picker');
      if (!mapContainer) return;

      const map = L.map('map-picker', { zoomControl: false }).setView([lat, lng], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      L.control.zoom({ position: 'bottomright' }).addTo(map);

      const marker = L.marker([lat, lng], { draggable: true, icon: customIcon }).addTo(map);

      marker.on('dragend', async () => {
        const position = marker.getLatLng();
        setCoordinates({ lat: position.lat, lng: position.lng });
        await reverseGeocode(position.lat, position.lng);
      });

      map.on('click', async (e: any) => {
        const { lat: clickLat, lng: clickLng } = e.latlng;
        marker.setLatLng([clickLat, clickLng]);
        setCoordinates({ lat: clickLat, lng: clickLng });
        await reverseGeocode(clickLat, clickLng);
      });

      mapRef.current = map;
      markerRef.current = marker;
    } else {
      mapRef.current.setView([lat, lng], 13);
      markerRef.current.setLatLng([lat, lng]);
    }
  };

  useEffect(() => {
    if (mapLoaded) {
      const defaultLat = 23.0225; // Ahmedabad Lat
      const defaultLng = 72.5714; // Ahmedabad Lng
      setCoordinates({ lat: defaultLat, lng: defaultLng });
      const timer = setTimeout(() => {
        initMap(defaultLat, defaultLng);
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [mapLoaded]);

  // Pre-fill user contact info if logged in
  useEffect(() => {
    if (userPhone) {
      setForm(prev => ({
        ...prev,
        customer_phone: prev.customer_phone || userPhone,
        customer_name: prev.customer_name || (userName && userName !== 'Customer' ? userName : ''),
      }));
    }
  }, [userPhone, userName]);

  const activeDeliveryFee = orderType === 'delivery' ? (customDeliveryFee !== null ? customDeliveryFee : deliveryFee) : 0;
  const grandTotal = total + activeDeliveryFee;

  const handleSearchAddress = async () => {
    if (!searchQuery.trim()) return;
    setSearching(true);
    try {
      const res = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&limit=5&q=${encodeURIComponent(searchQuery)}&addressdetails=1`
      );
      const data = await res.json();
      setSearchResults(data || []);
    } catch {
      alert("Error searching address. Network check karein.");
    } finally {
      setSearching(false);
    }
  };

  const handleSelectResult = (result: any) => {
    const addr = result.display_name || '';
    const city = result.address.city || result.address.town || result.address.village || result.address.suburb || result.address.state || '';
    const lat = parseFloat(result.lat);
    const lon = parseFloat(result.lon);

    setCoordinates({ lat, lng: lon });
    if (mapRef.current && markerRef.current) {
      mapRef.current.setView([lat, lon], 15);
      markerRef.current.setLatLng([lat, lon]);
    } else if (mapLoaded) {
      initMap(lat, lon);
    }

    setForm(prev => ({
      ...prev,
      delivery_address: addr,
      city: city,
    }));
    setShowSearch(false);
    setSearchResults([]);
    setSearchQuery('');
  };

  const handleDetectLocation = () => {
    if (!navigator.geolocation) {
      alert("Apke browser mein location detect karne ki suvidha nahi hai.");
      return;
    }

    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude } = position.coords;
        setCoordinates({ lat: latitude, lng: longitude });

        if (mapRef.current && markerRef.current) {
          mapRef.current.setView([latitude, longitude], 15);
          markerRef.current.setLatLng([latitude, longitude]);
        } else if (mapLoaded) {
          initMap(latitude, longitude);
        }

        try {
          const res = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&addressdetails=1`
          );
          const data = await res.json();
          if (data && data.address) {
            const addr = data.display_name || '';
            const city = data.address.city || data.address.town || data.address.village || data.address.suburb || '';
            setForm(prev => ({
              ...prev,
              delivery_address: addr,
              city: city,
            }));
          } else {
            alert("Location mil gayi, par address read nahi ho paya. Kripya manually fill karein.");
          }
        } catch {
          alert("Network error! Address fetch nahi ho saka.");
        } finally {
          setLocating(false);
        }
      },
      () => {
        alert("Location access permission block hai. Manual address enter karein.");
        setLocating(false);
      }
    );
  };

  if (state.items.length === 0) {
    return (
      <div className="page-wrapper">
        <Navbar />
        <CartDrawer />
        <div style={{ textAlign: 'center', padding: '4rem', color: 'var(--text-muted)' }}>
            <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>🛒</div>
            <h3>Cart khali hai!</h3>
            <p>Pahle kuch tasty add karein.</p>
          <Link href="/" className="btn-primary" style={{ marginTop: '1.5rem', display: 'inline-flex' }}>
            ← Menu pe wapas jao
          </Link>
        </div>
      </div>
    );
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const placeOrder = async (isPaid: boolean = false) => {
    setLoading(true);
    setError('');

    // Concatenate the apartment details with the delivery address before sending to the backend API
    const fullAddress = [
      form.apartment_no ? `Apt/Flat: ${form.apartment_no}` : '',
      form.apartment_name ? `Building/Landmark: ${form.apartment_name}` : '',
      form.delivery_address
    ].filter(Boolean).join(', ');

    try {
      const res = await fetch(`${API}/orders`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          ...form,
          delivery_address: orderType === 'delivery' ? fullAddress : (orderType === 'dine_in' ? 'Dine In' : 'Take Away'),
          city: orderType === 'delivery' ? form.city : 'Amritsar',
          payment_method: payment,
          latitude: orderType === 'delivery' && coordinates ? coordinates.lat : null,
          longitude: orderType === 'delivery' && coordinates ? coordinates.lng : null,
          items: state.items.map(i => ({ menu_item_id: i.menu_item_id, quantity: i.quantity })),
          order_type: orderType,
          table_number: null,
          pickup_time: orderType !== 'delivery' ? form.pickup_time : null,
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        setError(data.message || 'Order place nahi ho saka. Dobara try karein.');
        return false;
      }
      dispatch({ type: 'CLEAR' });
      router.push(`/track/${data.data.order_number}`);
      return true;
    } catch {
      setError('Network error. Check karein ki backend chal raha hai ya nahi.');
      return false;
    } finally {
      setLoading(false);
    }
  };

  const triggerRealRazorpay = (keyId: string) => {
    const options = {
      key: keyId,
      amount: Math.round(grandTotal * 100), // Amount in paisa
      currency: "INR",
      name: "Swaad E Punjab",
      description: "Food Order Payment",
      handler: async function (response: any) {
        await placeOrder(true);
      },
      prefill: {
        name: form.customer_name,
        email: form.customer_email,
        contact: form.customer_phone
      },
      theme: {
        color: "#E6A817"
      }
    };
    try {
      const rzp = new (window as any).Razorpay(options);
      rzp.open();
    } catch (e) {
      console.error("Razorpay open error:", e);
      setError("Razorpay integration initialization failed. Using simulation fallback.");
      setShowRazorpaySim(true);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (payment === 'razorpay') {
      const razorpayKey = process.env.NEXT_PUBLIC_RAZORPAY_KEY_ID;
      if (razorpayKey && (window as any).Razorpay) {
        triggerRealRazorpay(razorpayKey);
      } else {
        // Fallback to our elegant fully-interactive simulation modal!
        setShowRazorpaySim(true);
      }
      return;
    }

    await placeOrder();
  };

  const handleSimulatedPayment = async () => {
    setPaymentSimulating(true);
    
    // Simulate transaction delay
    setTimeout(() => {
      setSimSuccess(true);
      setTimeout(async () => {
        const ok = await placeOrder(true);
        if (ok) {
          setShowRazorpaySim(false);
        }
        // Reset states
        setPaymentSimulating(false);
        setSimSuccess(false);
      }, 1000);
    }, 1800);
  };

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />
      <div style={{ padding: '2rem 2rem 0', maxWidth: 550, margin: '0 auto' }}>
        <Link href="/" className="btn-secondary" style={{ display: 'inline-flex', marginBottom: '1.5rem' }}>
          ← Back to Menu
        </Link>
        <h1 style={{ fontSize: '1.8rem', fontWeight: 800, marginBottom: '0.25rem' }}>Order Karein</h1>
        <p style={{ color: 'var(--text-muted)', marginBottom: '2rem' }}>Neeche apni jaankari bharein aur order place karein.</p>
      </div>

      <form onSubmit={handleSubmit}>
        <div className="checkout-page">
          {/* Left: Form */}
          <div>
            <div className="checkout-section">
              <h3 style={{ margin: '0 0 1rem 0' }}>🍽️ Service Type</h3>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', marginTop: '0.5rem' }}>
                <button 
                  type="button" 
                  className={`payment-option${orderType === 'delivery' ? ' selected' : ''}`}
                  onClick={() => setOrderType('delivery')}
                  style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '12px', gap: '6px', fontSize: '0.85rem', cursor: 'pointer' }}
                >
                  <span style={{ fontSize: '1.5rem' }}>🛵</span>
                  <strong>Delivery</strong>
                </button>
                <button 
                  type="button" 
                  className={`payment-option${orderType === 'dine_in' ? ' selected' : ''}`}
                  onClick={() => setOrderType('dine_in')}
                  style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '12px', gap: '6px', fontSize: '0.85rem', cursor: 'pointer' }}
                >
                  <span style={{ fontSize: '1.5rem' }}>🍽️</span>
                  <strong>Dine In</strong>
                </button>
                <button 
                  type="button" 
                  className={`payment-option${orderType === 'takeaway' ? ' selected' : ''}`}
                  onClick={() => setOrderType('takeaway')}
                  style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '12px', gap: '6px', fontSize: '0.85rem', cursor: 'pointer' }}
                >
                  <span style={{ fontSize: '1.5rem' }}>🛍️</span>
                  <strong>Take Away</strong>
                </button>
              </div>
            </div>

            <div className="checkout-section">
              <h3>👤 Contact Info</h3>
              <div className="form-group">
                <label className="form-label">Full Name</label>
                <input name="customer_name" className="form-input" placeholder="John Doe" required value={form.customer_name} onChange={handleChange} />
              </div>
              <div className="form-grid">
                <div className="form-group">
                  <label className="form-label">Email</label>
                  <input name="customer_email" type="email" className="form-input" placeholder="john@example.com" value={form.customer_email} onChange={handleChange} />
                </div>
                <div className="form-group">
                  <label className="form-label">Phone</label>
                  <input name="customer_phone" className="form-input" placeholder="+1 234 567 8900" required value={form.customer_phone} onChange={handleChange} />
                </div>
              </div>
            </div>

            {orderType === 'delivery' && (
              <div className="checkout-section">
                <h3 style={{ margin: '0 0 1.2rem 0', paddingBottom: '10px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: '8px' }}>📍 Delivery Address</h3>
                
                {/* Location Buttons Group */}
                <div style={{ display: 'flex', gap: '10px', marginBottom: '1.2rem' }}>
                  <button 
                    type="button" 
                    onClick={handleDetectLocation}
                    disabled={locating}
                    className="btn-secondary" 
                    style={{ flex: 1, fontSize: '0.82rem', padding: '12px', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px', cursor: 'pointer', borderRadius: 'var(--radius-sm)' }}
                  >
                    🎯 {locating ? '⏳ GPS locating...' : 'Use My Current GPS Location'}
                  </button>
                </div>

                {/* Map Picker with Inline Search */}
                <div style={{ marginBottom: '1.5rem', position: 'relative' }}>
                  <div style={{ fontSize: '0.78rem', fontWeight: 700, display: 'block', marginBottom: '6px', color: 'var(--text-muted)' }}>
                    🗺️ Pin Delivery Location (Search landmark or drag marker)
                  </div>
                  <div style={{ position: 'relative', height: '300px', width: '100%' }}>
                    
                    {/* Floating Search Bar Inside Map */}
                    {mapLoaded && (
                      <div style={{ position: 'absolute', top: '12px', left: '12px', right: '12px', zIndex: 1000 }}>
                        <div style={{ display: 'flex', gap: '6px', background: '#fff', padding: '6px 8px', borderRadius: 'var(--radius-sm)', border: '1.5px solid var(--border)', boxShadow: '0 4px 12px rgba(100,60,0,0.12)' }}>
                          <input 
                            type="text" 
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="🔍 Search Landmark, Building or Area..."
                            style={{ flex: 1, border: 'none', outline: 'none', padding: '6px 10px', fontSize: '0.82rem', background: 'transparent' }}
                            onKeyDown={(e) => {
                              if (e.key === 'Enter') {
                                e.preventDefault();
                                handleSearchAddress();
                              }
                            }}
                          />
                          <button 
                            type="button"
                            onClick={handleSearchAddress}
                            disabled={searching}
                            className="btn-primary"
                            style={{ padding: '6px 14px', fontSize: '0.78rem', borderRadius: '6px', cursor: 'pointer' }}
                          >
                            {searching ? '⏳' : 'Search'}
                          </button>
                        </div>

                        {searchResults.length > 0 && (
                          <div style={{ position: 'absolute', top: '105%', left: 0, right: 0, background: '#fff', border: '1.5px solid var(--border)', borderRadius: 'var(--radius-sm)', overflow: 'hidden', boxShadow: 'var(--shadow)', zIndex: 1001, maxHeight: '180px', overflowY: 'auto' }}>
                            {searchResults.map((result: any, idx: number) => (
                              <div 
                                key={idx} 
                                onClick={() => handleSelectResult(result)} 
                                className="search-result-item"
                                style={{ padding: '10px 12px', borderBottom: idx < searchResults.length - 1 ? '1px solid var(--border)' : 'none', cursor: 'pointer', transition: 'var(--transition)' }}
                              >
                                <div style={{ fontWeight: 'bold', fontSize: '0.8rem', color: 'var(--text)' }}>{result.display_name.split(',')[0]}</div>
                                <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: '2px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{result.display_name}</div>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )}

                    <div 
                      id="map-picker" 
                      style={{ 
                        height: '100%', 
                        width: '100%', 
                        borderRadius: 'var(--radius-sm)', 
                        border: '1.5px solid var(--border)',
                        boxShadow: 'inset 0 2px 4px rgba(100,60,0,0.06)'
                      }} 
                    />
                    {!mapLoaded && (
                      <div style={{
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        background: 'var(--bg-elevated)',
                        borderRadius: 'var(--radius-sm)',
                        border: '1.5px solid var(--border)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'var(--text-dim)',
                        fontSize: '0.9rem',
                        zIndex: 10
                      }}>
                        ⏳ Loading map...
                      </div>
                    )}
                  </div>
                  {coordinates && (
                    <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginTop: '6px', display: 'flex', justifyContent: 'space-between' }}>
                      <span>Lat: {coordinates.lat.toFixed(6)}</span>
                      <span>Lng: {coordinates.lng.toFixed(6)}</span>
                    </div>
                  )}
                </div>

                <div className="form-group">
                  <label className="form-label">Flat / House / Apartment No.</label>
                  <input name="apartment_no" className="form-input" placeholder="e.g. Flat 102, 1st Floor" required={orderType === 'delivery'} value={form.apartment_no} onChange={handleChange} />
                </div>
                <div className="form-group">
                  <label className="form-label">Building / Apartment / Landmark Name</label>
                  <input name="apartment_name" className="form-input" placeholder="e.g. Shanti Heights, near Temple" required={orderType === 'delivery'} value={form.apartment_name} onChange={handleChange} />
                </div>
                <div className="form-group">
                  <label className="form-label">Street Address / Area</label>
                  <input name="delivery_address" className="form-input" placeholder="e.g. SP Ring Road, Vastral" required={orderType === 'delivery'} value={form.delivery_address} onChange={handleChange} />
                </div>
                <div className="form-group">
                  <label className="form-label">City</label>
                  <input name="city" className="form-input" placeholder="e.g. Ahmedabad" required={orderType === 'delivery'} value={form.city} onChange={handleChange} />
                </div>
              </div>
            )}

            {orderType === 'dine_in' && (
              <div className="checkout-section">
                <h3 style={{ margin: '0 0 1.2rem 0', paddingBottom: '10px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: '8px' }}>🍽️ Dine In Details</h3>
                <div className="form-group">
                  <label className="form-label">How many minutes will you take to arrive?</label>
                  <input 
                    name="pickup_time" 
                    className="form-input" 
                    placeholder="e.g. 15 minutes" 
                    required={orderType === 'dine_in'} 
                    value={form.pickup_time} 
                    onChange={handleChange} 
                  />
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.78rem', marginTop: '6px' }}>
                    💡 Please specify how many minutes until you arrive so we can serve your food hot and fresh!
                  </p>
                </div>
              </div>
            )}

            {orderType === 'takeaway' && (
              <div className="checkout-section">
                <h3 style={{ margin: '0 0 1.2rem 0', paddingBottom: '10px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: '8px' }}>🛍️ Take Away Details</h3>
                <div className="form-group">
                  <label className="form-label">Estimated Collection Time</label>
                  <input 
                    name="pickup_time" 
                    className="form-input" 
                    placeholder="e.g. In 15 minutes, at 1:45 PM" 
                    required={orderType === 'takeaway'} 
                    value={form.pickup_time} 
                    onChange={handleChange} 
                  />
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.78rem', marginTop: '6px' }}>
                    ⏱️ Hum aapka order counter pe pack karke ready rakhenge taaki aapka corporate lunch break waste na ho!
                  </p>
                </div>
              </div>
            )}

            <div className="checkout-section">
              <h3 style={{ margin: '0 0 1rem 0' }}>📝 Special Instructions</h3>
              <div className="form-group">
                <label className="form-label">Instructions for the Chef (optional)</label>
                <textarea name="special_instructions" className="form-input" placeholder="E.g. Make it extra spicy, less butter on naan..." rows={3} value={form.special_instructions} onChange={handleChange} style={{ resize: 'vertical' }} />
              </div>
            </div>

            <div className="checkout-section">
              <h3>💳 Payment Method</h3>
              <div className="payment-options" style={{ display: 'flex', gap: '12px', marginTop: '0.5rem' }}>
                <button type="button" className={`payment-option${payment === 'cash_on_delivery' ? ' selected' : ''}`} onClick={() => setPayment('cash_on_delivery')}>
                  💵 Cash on Delivery
                </button>
                <button type="button" className={`payment-option${payment === 'razorpay' ? ' selected' : ''}`} onClick={() => setPayment('razorpay')}>
                  💳 Pay Online (Razorpay)
                </button>
              </div>
              {payment === 'razorpay' && (
                <p style={{ marginTop: '1rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                  ℹ️ Pay securely using Google Pay, PhonePe, Cards, Paytm & Netbanking via Razorpay.
                </p>
              )}
            </div>

          </div>

          {/* Right: Order Summary */}
          <div>
            <div className="order-summary-card">
              <h3 style={{ fontWeight: 700, marginBottom: '1rem', fontSize: '1rem' }}>🧾 Order Summary</h3>
              {state.items.map(item => (
                <div key={item.menu_item_id} className="summary-item">
                  <span className="summary-name">
                    {CATEGORY_EMOJI[item.category] || '🍽️'} {item.name}
                  </span>
                  <span className="summary-qty">×{item.quantity}</span>
                  <span className="summary-price">₹{(item.price * item.quantity).toFixed(0)}</span>
                </div>
              ))}
              <div style={{ borderTop: '1px solid var(--border)', marginTop: '10px', paddingTop: '10px' }}>
                <div className="cart-total-row"><span>Subtotal</span><span>₹{total.toFixed(0)}</span></div>
                {orderType === 'delivery' && distanceKm !== null && (
                  <div className="cart-total-row" style={{ color: 'var(--primary-dark)', fontSize: '0.82rem', fontWeight: 600 }}>
                    <span>Calculated Distance</span>
                    <span>{distanceKm.toFixed(1)} KM</span>
                  </div>
                )}
                <div className="cart-total-row"><span>Delivery charge</span><span>₹{activeDeliveryFee.toFixed(0)}</span></div>
              </div>
              <div className="summary-total">
                <span>Total</span>
                <span style={{ color: 'var(--primary)' }}>₹{grandTotal.toFixed(0)}</span>
              </div>
              <div style={{ marginTop: '1rem', padding: '12px', background: 'rgba(255,107,53,0.08)', borderRadius: 'var(--radius-sm)', fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                {orderType === 'dine_in' && <>⏱ Expected preparation: <strong style={{ color: 'var(--text)' }}>15–20 minutes</strong> (Serve at Table)</>}
                {orderType === 'takeaway' && <>⏱ Expected preparation: <strong style={{ color: 'var(--text)' }}>15–20 minutes</strong> (Ready for Pickup)</>}
                {orderType === 'delivery' && <>⏱ Expected delivery: <strong style={{ color: 'var(--text)' }}>30–45 minutes</strong></>}
              </div>
            </div>
          </div>

          {/* Bottom Submit Block */}
          <div style={{ width: '100%', marginTop: '0.5rem' }}>
            {error && (
              <div style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid var(--danger)', borderRadius: 'var(--radius-sm)', padding: '12px 16px', color: 'var(--danger)', marginBottom: '1rem', fontSize: '0.9rem' }}>
                ⚠️ {error}
              </div>
            )}

            <button type="submit" className="btn-primary" style={{ width: '100%', justifyContent: 'center', padding: '15px', fontSize: '1rem' }} disabled={loading}>
              {loading ? '⏳ Order ho raha hai...' : `🚀 Order Place Karein — ₹${grandTotal.toFixed(0)}`}
            </button>
          </div>
        </div>
      </form>

      {/* Razorpay Simulation Modal */}
      {showRazorpaySim && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0,0,0,0.6)',
          backdropFilter: 'blur(4px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 9999,
          padding: '20px'
        }}>
          <div style={{
            background: '#fff',
            width: '100%',
            maxWidth: '500px',
            borderRadius: '12px',
            overflow: 'hidden',
            boxShadow: '0 10px 40px rgba(0,0,0,0.25)',
            fontFamily: 'system-ui, -apple-system, sans-serif'
          }}>
            {/* Modal Header */}
            <div style={{
              background: '#1e2c51',
              color: '#fff',
              padding: '20px',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              borderBottom: '1px solid #141e38'
            }}>
              <div>
                <div style={{ fontSize: '0.8rem', opacity: 0.8, textTransform: 'uppercase', letterSpacing: '0.5px' }}>Swaad E Punjab</div>
                <div style={{ fontSize: '1.2rem', fontWeight: 700 }}>₹{grandTotal.toFixed(0)}</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontSize: '0.9rem', fontWeight: 600, color: '#2b6cb0', background: '#ebf8ff', padding: '3px 8px', borderRadius: '4px', display: 'inline-block' }}>TEST MODE</div>
                <div style={{ fontSize: '0.7rem', opacity: 0.6, marginTop: '4px' }}>Razorpay Secure</div>
              </div>
            </div>

            {/* Modal Content */}
            <div style={{ padding: '24px', minHeight: '280px', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
              {paymentSimulating ? (
                /* Simulation loading state */
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', flex: 1, padding: '40px 0', textAlign: 'center' }}>
                  {simSuccess ? (
                    <>
                      <div style={{ fontSize: '3.5rem', color: '#48bb78', marginBottom: '16px', animation: 'scaleUp 0.3s ease' }}>✓</div>
                      <h4 style={{ color: '#2d3748', fontSize: '1.1rem', fontWeight: 700 }}>Payment Successful!</h4>
                      <p style={{ color: '#718096', fontSize: '0.85rem', marginTop: '6px' }}>Placing your order...</p>
                    </>
                  ) : (
                    <>
                      <div style={{
                        border: '4px solid #e2e8f0',
                        borderTop: '4px solid #3182ce',
                        borderRadius: '50%',
                        width: '45px',
                        height: '45px',
                        animation: 'spin 1s linear infinite',
                        marginBottom: '20px'
                      }} />
                      <h4 style={{ color: '#2d3748', fontSize: '1rem', fontWeight: 600 }}>Processing Payment</h4>
                      <p style={{ color: '#718096', fontSize: '0.82rem', marginTop: '6px' }}>Please do not close this window or press back.</p>
                    </>
                  )}
                </div>
              ) : (
                /* Payment form inputs selector */
                <div>
                  <div style={{ display: 'flex', borderBottom: '1px solid #edf2f7', marginBottom: '20px' }}>
                    <button 
                      type="button" 
                      onClick={() => setRazorpayMethod('upi')}
                      style={{ flex: 1, paddingBottom: '12px', border: 'none', borderBottom: razorpayMethod === 'upi' ? '3px solid #3182ce' : 'none', background: 'transparent', fontWeight: 700, color: razorpayMethod === 'upi' ? '#3182ce' : '#718096', fontSize: '0.9rem', cursor: 'pointer' }}
                    >
                      📱 UPI (GPay/PhonePe)
                    </button>
                    <button 
                      type="button" 
                      onClick={() => setRazorpayMethod('card')}
                      style={{ flex: 1, paddingBottom: '12px', border: 'none', borderBottom: razorpayMethod === 'card' ? '3px solid #3182ce' : 'none', background: 'transparent', fontWeight: 700, color: razorpayMethod === 'card' ? '#3182ce' : '#718096', fontSize: '0.9rem', cursor: 'pointer' }}
                    >
                      💳 Card Payment
                    </button>
                    <button 
                      type="button" 
                      onClick={() => setRazorpayMethod('netbanking')}
                      style={{ flex: 1, paddingBottom: '12px', border: 'none', borderBottom: razorpayMethod === 'netbanking' ? '3px solid #3182ce' : 'none', background: 'transparent', fontWeight: 700, color: razorpayMethod === 'netbanking' ? '#3182ce' : '#718096', fontSize: '0.9rem', cursor: 'pointer' }}
                    >
                      🏛 Netbanking
                    </button>
                  </div>

                  {/* Payment Body based on selection */}
                  {razorpayMethod === 'upi' && (
                    <div>
                      <div style={{ fontSize: '0.85rem', color: '#4a5568', fontWeight: 600, marginBottom: '10px' }}>Preferred UPI Apps:</div>
                      <div style={{ display: 'flex', gap: '8px', marginBottom: '16px' }}>
                        <button 
                          type="button" 
                          onClick={() => { setSimUPIApp('gpay'); setSimUPIId('customer@okaxis'); }}
                          style={{ flex: 1, padding: '10px', borderRadius: '6px', border: simUPIApp === 'gpay' ? '2.5px solid #3182ce' : '1px solid #cbd5e0', background: simUPIApp === 'gpay' ? '#ebf8ff' : '#fff', fontWeight: 600, fontSize: '0.8rem', cursor: 'pointer' }}
                        >
                          Google Pay
                        </button>
                        <button 
                          type="button" 
                          onClick={() => { setSimUPIApp('phonepe'); setSimUPIId('customer@ybl'); }}
                          style={{ flex: 1, padding: '10px', borderRadius: '6px', border: simUPIApp === 'phonepe' ? '2.5px solid #3182ce' : '1px solid #cbd5e0', background: simUPIApp === 'phonepe' ? '#ebf8ff' : '#fff', fontWeight: 600, fontSize: '0.8rem', cursor: 'pointer' }}
                        >
                          PhonePe
                        </button>
                        <button 
                          type="button" 
                          onClick={() => { setSimUPIApp('paytm'); setSimUPIId('customer@paytm'); }}
                          style={{ flex: 1, padding: '10px', borderRadius: '6px', border: simUPIApp === 'paytm' ? '2.5px solid #3182ce' : '1px solid #cbd5e0', background: simUPIApp === 'paytm' ? '#ebf8ff' : '#fff', fontWeight: 600, fontSize: '0.8rem', cursor: 'pointer' }}
                        >
                          Paytm
                        </button>
                      </div>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                        <label style={{ fontSize: '0.78rem', color: '#718096', fontWeight: 600 }}>UPI ID / VPA</label>
                        <input 
                          type="text" 
                          value={simUPIId} 
                          onChange={(e) => setSimUPIId(e.target.value)} 
                          placeholder="e.g. customer@okaxis" 
                          style={{ padding: '10px', border: '1px solid #cbd5e0', borderRadius: '6px', fontSize: '0.85rem', outline: 'none' }}
                        />
                      </div>
                    </div>
                  )}

                  {razorpayMethod === 'card' && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                        <label style={{ fontSize: '0.78rem', color: '#718096', fontWeight: 600 }}>Card Number</label>
                        <input 
                          type="text" 
                          maxLength={19} 
                          value={simCardNumber}
                          onChange={(e) => setSimCardNumber(e.target.value.replace(/\s?/g, '').replace(/(\d{4})/g, '$1 ').trim())}
                          placeholder="4111 2222 3333 4444" 
                          style={{ padding: '10px', border: '1px solid #cbd5e0', borderRadius: '6px', fontSize: '0.85rem', outline: 'none' }}
                        />
                      </div>
                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                          <label style={{ fontSize: '0.78rem', color: '#718096', fontWeight: 600 }}>Expiry Date</label>
                          <input 
                            type="text" 
                            maxLength={5} 
                            value={simCardExpiry}
                            onChange={(e) => setSimCardExpiry(e.target.value)}
                            placeholder="MM/YY" 
                            style={{ padding: '10px', border: '1px solid #cbd5e0', borderRadius: '6px', fontSize: '0.85rem', outline: 'none' }}
                          />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                          <label style={{ fontSize: '0.78rem', color: '#718096', fontWeight: 600 }}>CVV</label>
                          <input 
                            type="password" 
                            maxLength={3} 
                            value={simCardCvv}
                            onChange={(e) => setSimCardCvv(e.target.value)}
                            placeholder="123" 
                            style={{ padding: '10px', border: '1px solid #cbd5e0', borderRadius: '6px', fontSize: '0.85rem', outline: 'none' }}
                          />
                        </div>
                      </div>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                        <label style={{ fontSize: '0.78rem', color: '#718096', fontWeight: 600 }}>Cardholder Name</label>
                        <input 
                          type="text" 
                          value={simCardName} 
                          onChange={(e) => setSimCardName(e.target.value)}
                          placeholder="JOHN DOE" 
                          style={{ padding: '10px', border: '1px solid #cbd5e0', borderRadius: '6px', fontSize: '0.85rem', outline: 'none' }}
                        />
                      </div>
                    </div>
                  )}

                  {razorpayMethod === 'netbanking' && (
                    <div>
                      <div style={{ fontSize: '0.85rem', color: '#4a5568', fontWeight: 600, marginBottom: '10px' }}>Select Popular Bank:</div>
                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                        {['SBI', 'HDFC Bank', 'ICICI Bank', 'Axis Bank'].map((bank) => (
                          <button 
                            key={bank}
                            type="button"
                            onClick={() => alert(`Simulating checkout with ${bank}`)}
                            style={{ padding: '10px', border: '1px solid #cbd5e0', borderRadius: '6px', fontSize: '0.8rem', fontWeight: 600, background: '#fff', cursor: 'pointer' }}
                          >
                            🏦 {bank}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Modal Footer (Action Buttons) */}
              {!paymentSimulating && (
                <div style={{ display: 'flex', gap: '10px', marginTop: '24px', borderTop: '1px solid #edf2f7', paddingTop: '16px' }}>
                  <button 
                    type="button" 
                    onClick={() => setShowRazorpaySim(false)}
                    style={{ flex: 1, padding: '12px', border: '1px solid #cbd5e0', borderRadius: '6px', color: '#718096', background: '#fff', fontWeight: 700, fontSize: '0.88rem', cursor: 'pointer' }}
                  >
                    Cancel
                  </button>
                  <button 
                    type="button" 
                    onClick={handleSimulatedPayment}
                    style={{ flex: 2, padding: '12px', border: 'none', borderRadius: '6px', color: '#fff', background: '#3182ce', fontWeight: 700, fontSize: '0.88rem', cursor: 'pointer' }}
                  >
                    Pay ₹{grandTotal.toFixed(0)} Securely
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      <style>{`
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
        @keyframes scaleUp {
          0% { transform: scale(0.5); opacity: 0; }
          100% { transform: scale(1); opacity: 1; }
        }
      `}</style>

      <footer>
        <p>© 2026 Swaad E Punjab — Safe & Secure Ordering</p>
      </footer>
    </div>
  );
}
