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
    city: 'Amritsar',
    state: 'Punjab',
    pincode: '',
    apartment_no: '',
    apartment_name: '',
    address_type: 'Home' as 'Home' | 'Office',
    is_default: true,
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

  const [showAddressModal, setShowAddressModal] = useState(false);
  const [showMapSearch, setShowMapSearch] = useState(false);
  const [modalScreen, setModalScreen] = useState<'form' | 'map_picker' | 'search_location'>('form');

  useEffect(() => {
    if (modalScreen !== 'search_location') return;
    if (!searchQuery || searchQuery.trim().length < 2) {
      setSearchResults([]);
      return;
    }
    const timer = setTimeout(async () => {
      setSearching(true);
      try {
        const res = await fetch(
          `https://nominatim.openstreetmap.org/search?format=json&limit=12&q=${encodeURIComponent(searchQuery)}&addressdetails=1&countrycodes=in`
        );
        const data = await res.json();
        setSearchResults(data || []);
      } catch (e) {
        console.error("Search error:", e);
      } finally {
        setSearching(false);
      }
    }, 350);
    return () => clearTimeout(timer);
  }, [searchQuery, modalScreen]);

  useEffect(() => {
    if (orderType === 'delivery' && !form.delivery_address) {
      setShowAddressModal(true);
      setModalScreen('form');
    }
  }, [orderType]);

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
        const city = data.address.city || data.address.town || data.address.village || data.address.state_district || '';
        const pincode = data.address.postcode || '';
        const state = data.address.state || 'Punjab';
        const locality = data.address.suburb || data.address.neighbourhood || data.address.residential || data.address.road || '';
        setForm(prev => ({
          ...prev,
          delivery_address: addr,
          city: city || prev.city || 'Amritsar',
          pincode: pincode || prev.pincode,
          state: state || prev.state || 'Punjab',
          apartment_name: locality || prev.apartment_name,
        }));
      }
    } catch (e) {
      console.error("Reverse geocoding error:", e);
    }
  };

  const initMap = (lat: number, lng: number) => {
    if (typeof window === 'undefined' || !(window as any).L) return;
    const L = (window as any).L;

    if (mapRef.current) {
      try {
        mapRef.current.remove();
      } catch (e) {}
      mapRef.current = null;
      markerRef.current = null;
    }

    const customIcon = L.icon({
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      iconSize: [28, 46],
      iconAnchor: [14, 46],
      popupAnchor: [1, -34],
      shadowSize: [46, 46]
    });

    if (showAddressModal && modalScreen === 'form') {
      const mapContainer = document.getElementById('mini-map-preview');
      if (!mapContainer) return;
      const map = L.map('mini-map-preview', { zoomControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false }).setView([lat, lng], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      L.marker([lat, lng], { icon: customIcon }).addTo(map);
      mapRef.current = map;
    } else if (showAddressModal && modalScreen === 'map_picker') {
      const mapContainer = document.getElementById('full-map-picker');
      if (!mapContainer) return;
      const map = L.map('full-map-picker', { zoomControl: false }).setView([lat, lng], 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      L.control.zoom({ position: 'bottomright' }).addTo(map);

      map.on('moveend', async () => {
        const center = map.getCenter();
        setCoordinates({ lat: center.lat, lng: center.lng });
        await reverseGeocode(center.lat, center.lng);
      });

      mapRef.current = map;
    }
  };

  useEffect(() => {
    if (mapLoaded && showAddressModal && (modalScreen === 'form' || modalScreen === 'map_picker')) {
      const defaultLat = coordinates ? coordinates.lat : 23.0225; // Default Ahmedabad/Vastral Lat
      const defaultLng = coordinates ? coordinates.lng : 72.5714; // Default Lng
      const timer = setTimeout(() => {
        initMap(defaultLat, defaultLng);
      }, 150);
      return () => clearTimeout(timer);
    }
  }, [mapLoaded, showAddressModal, modalScreen]);

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
    const city = result.address?.city || result.address?.town || result.address?.village || result.address?.suburb || result.address?.state || '';
    const pincode = result.address?.postcode || '';
    const state = result.address?.state || 'Punjab';
    const locality = result.address?.suburb || result.address?.neighbourhood || result.address?.residential || result.address?.road || '';
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
      city: city || prev.city || 'Amritsar',
      pincode: pincode || prev.pincode,
      state: state || prev.state || 'Punjab',
      apartment_name: locality || prev.apartment_name,
    }));
    setShowSearch(false);
    setSearchResults([]);
    setSearchQuery('');
    setModalScreen('map_picker');
  };

  const handleDetectLocation = () => {
    if (!navigator.geolocation) {
      alert("Apke browser mein location detect karne ki suvidha nahi hai.");
      return;
    }

    setLocating(true);
    setModalScreen('map_picker');
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
            const city = data.address.city || data.address.town || data.address.village || data.address.state_district || '';
            const pincode = data.address.postcode || '';
            const state = data.address.state || 'Punjab';
            const locality = data.address.suburb || data.address.neighbourhood || data.address.residential || data.address.road || '';
            setForm(prev => ({
              ...prev,
              delivery_address: addr,
              city: city || prev.city || 'Amritsar',
              pincode: pincode || prev.pincode,
              state: state || prev.state || 'Punjab',
              apartment_name: locality || prev.apartment_name,
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
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.2rem', paddingBottom: '10px', borderBottom: '1px solid var(--border)' }}>
                  <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '8px', border: 'none', padding: 0 }}>📍 Delivery Address</h3>
                  <button
                    type="button"
                    onClick={() => setShowAddressModal(true)}
                    style={{
                      background: 'rgba(244, 63, 94, 0.1)',
                      color: '#F43F5E',
                      border: '1px solid rgba(244, 63, 94, 0.3)',
                      padding: '8px 14px',
                      borderRadius: '8px',
                      fontSize: '0.82rem',
                      fontWeight: 700,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '6px',
                      transition: 'all 0.2s'
                    }}
                  >
                    {form.delivery_address ? '✎ Change Address' : '+ Add New Address'}
                  </button>
                </div>

                {form.delivery_address ? (
                  <div style={{
                    padding: '16px',
                    background: '#F8FAFC',
                    border: '1.5px solid #E2E8F0',
                    borderRadius: '12px',
                    position: 'relative'
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                      <span style={{
                        background: '#F43F5E',
                        color: '#fff',
                        fontSize: '0.72rem',
                        fontWeight: 800,
                        padding: '3px 10px',
                        borderRadius: '6px',
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px'
                      }}>
                        {form.address_type || 'Home'}
                      </span>
                      <strong style={{ fontSize: '0.96rem', color: '#1E293B' }}>{form.customer_name || 'Customer'}</strong>
                      <span style={{ color: '#64748B', fontSize: '0.85rem' }}>({form.customer_phone})</span>
                      {form.is_default && (
                        <span style={{ fontSize: '0.75rem', background: '#E2E8F0', color: '#475569', padding: '2px 8px', borderRadius: '4px', fontWeight: 600 }}>Default</span>
                      )}
                    </div>
                    <div style={{ fontSize: '0.9rem', color: '#334155', lineHeight: 1.5, marginBottom: '6px' }}>
                      {[
                        form.apartment_no,
                        form.apartment_name,
                        form.delivery_address,
                        form.city,
                        form.state,
                        form.pincode ? `- ${form.pincode}` : ''
                      ].filter(Boolean).join(', ')}
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.78rem' }}>
                      {coordinates ? (
                        <span style={{ color: '#10B981', fontWeight: 600 }}>✓ GPS Location Pinned</span>
                      ) : (
                        <span style={{ color: '#64748B', fontStyle: 'italic' }}>ℹ️ Standard doorstep delivery (No mandatory GPS required)</span>
                      )}
                    </div>
                  </div>
                ) : (
                  <div 
                    onClick={() => setShowAddressModal(true)}
                    style={{
                      padding: '28px 20px',
                      border: '2px dashed #CBD5E1',
                      borderRadius: '12px',
                      textAlign: 'center',
                      cursor: 'pointer',
                      background: '#F8FAFC',
                      transition: 'all 0.2s',
                      display: 'flex',
                      flexDirection: 'column',
                      alignItems: 'center',
                      gap: '8px'
                    }}
                  >
                    <div style={{ fontSize: '2.4rem' }}>📍</div>
                    <div style={{ fontWeight: 700, color: '#1E293B', fontSize: '1.05rem' }}>Add New Delivery Address</div>
                    <div style={{ fontSize: '0.85rem', color: '#64748B' }}>Click here to enter your address (Current location setting is optional)</div>
                  </div>
                )}
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

      {/* ADD NEW ADDRESS MODAL OVERLAY - 3 SCREEN MATCHING SCREENSHOT DESIGN EXACTLY */}
      {showAddressModal && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(15, 23, 42, 0.65)',
          backdropFilter: 'blur(6px)',
          zIndex: 100000,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '1rem',
          overflowY: 'auto'
        }}>
          {/* SCREEN 1: ADD NEW ADDRESS FORM (IMAGE 1) */}
          {modalScreen === 'form' && (
            <div style={{
              background: '#F8FAFC',
              width: '100%',
              maxWidth: '520px',
              borderRadius: '20px',
              boxShadow: '0 20px 40px rgba(0,0,0,0.25)',
              overflow: 'hidden',
              display: 'flex',
              flexDirection: 'column',
              maxHeight: '90vh'
            }}>
              {/* Modal Header */}
              <div style={{
                background: '#fff',
                padding: '16px 20px',
                display: 'flex',
                alignItems: 'center',
                gap: '14px',
                borderBottom: '1px solid #E2E8F0',
                position: 'sticky',
                top: 0,
                zIndex: 10
              }}>
                <button
                  type="button"
                  onClick={() => setShowAddressModal(false)}
                  style={{
                    background: 'transparent',
                    border: 'none',
                    fontSize: '1.4rem',
                    color: '#1E293B',
                    cursor: 'pointer',
                    padding: '4px',
                    display: 'flex',
                    alignItems: 'center',
                    fontWeight: 700
                  }}
                >
                  ←
                </button>
                <h2 style={{ fontSize: '1.1rem', fontWeight: 800, margin: 0, color: '#1E293B', letterSpacing: '0.3px', textTransform: 'uppercase' }}>
                  ADD NEW ADDRESS
                </h2>
              </div>

              {/* Modal Body */}
              <div style={{ padding: '20px', overflowY: 'auto', flex: 1, display: 'flex', flexDirection: 'column', gap: '16px', textAlign: 'left' }}>
                
                {/* Contact Details Card */}
                <div style={{ background: '#fff', borderRadius: '16px', padding: '18px', border: '1px solid #E2E8F0', boxShadow: '0 2px 6px rgba(0,0,0,0.02)' }}>
                  <div style={{ fontSize: '0.95rem', fontWeight: 700, color: '#1E293B', marginBottom: '14px' }}>
                    Contact Details
                  </div>
                  <div style={{ marginBottom: '12px' }}>
                    <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>Name*</label>
                    <input
                      type="text"
                      name="customer_name"
                      value={form.customer_name}
                      onChange={handleChange}
                      placeholder="Enter full name"
                      style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>Mobile No*</label>
                    <input
                      type="text"
                      name="customer_phone"
                      value={form.customer_phone}
                      onChange={handleChange}
                      placeholder="Enter 10-digit mobile number"
                      style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                    />
                  </div>
                </div>

                {/* Address Card */}
                <div style={{ background: '#fff', borderRadius: '16px', padding: '18px', border: '1px solid #E2E8F0', boxShadow: '0 2px 6px rgba(0,0,0,0.02)' }}>
                  <div style={{ fontSize: '0.95rem', fontWeight: 700, color: '#1E293B', marginBottom: '12px' }}>
                    Address
                  </div>

                  {/* Map Preview Box (Image 1 Style) */}
                  <div style={{
                    position: 'relative',
                    height: '160px',
                    width: '100%',
                    borderRadius: '14px',
                    overflow: 'hidden',
                    border: '1.5px solid #E2E8F0',
                    marginBottom: '16px',
                    background: '#F1F5F9'
                  }}>
                    <div id="mini-map-preview" style={{ width: '100%', height: '100%' }} />
                    <button
                      type="button"
                      onClick={() => setModalScreen('map_picker')}
                      style={{
                        position: 'absolute',
                        bottom: '12px',
                        left: '50%',
                        transform: 'translateX(-50%)',
                        background: '#fff',
                        color: '#F43F5E',
                        border: '1.5px solid #F43F5E',
                        padding: '8px 18px',
                        borderRadius: '30px',
                        fontSize: '0.88rem',
                        fontWeight: 700,
                        cursor: 'pointer',
                        boxShadow: '0 4px 12px rgba(0,0,0,0.18)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        zIndex: 1000
                      }}
                    >
                      <span style={{ fontSize: '1.1rem' }}>🗺️</span> Edit on map
                    </button>
                  </div>

                  <div style={{ marginBottom: '12px' }}>
                    <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>Pin Code*</label>
                    <input
                      type="text"
                      name="pincode"
                      value={form.pincode || ''}
                      onChange={handleChange}
                      placeholder="e.g. 382418"
                      style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                    />
                  </div>

                  <div style={{ marginBottom: '14px' }}>
                    <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>House Number/Tower/Block*</label>
                    <input
                      type="text"
                      name="apartment_no"
                      value={form.apartment_no}
                      onChange={handleChange}
                      placeholder="e.g. Flat 402, Block A / House 12"
                      style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                    />
                    <div style={{ color: '#D97706', fontSize: '0.76rem', marginTop: '5px', fontWeight: 600, letterSpacing: '0.2px' }}>
                      *House Number will allow a doorstep delivery
                    </div>
                  </div>

                  <div style={{ marginBottom: '14px' }}>
                    <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>Address (locality,building,street)*</label>
                    <input
                      type="text"
                      name="delivery_address"
                      value={form.delivery_address}
                      onChange={handleChange}
                      placeholder="e.g. Vastral Road, Pranami Nagar"
                      style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                    />
                    <div style={{ color: '#D97706', fontSize: '0.76rem', marginTop: '5px', fontWeight: 600, letterSpacing: '0.2px' }}>
                      *Please update society/apartment details
                    </div>
                  </div>

                  <div style={{ marginBottom: '12px' }}>
                    <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>Locality / Town*</label>
                    <input
                      type="text"
                      name="apartment_name"
                      value={form.apartment_name}
                      onChange={handleChange}
                      placeholder="e.g. Vastral / Ranjit Avenue"
                      style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                    />
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                    <div>
                      <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>City / District*</label>
                      <input
                        type="text"
                        name="city"
                        value={form.city}
                        onChange={handleChange}
                        placeholder="Ahmedabad"
                        style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                      />
                    </div>
                    <div>
                      <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: '#64748B', marginBottom: '6px' }}>State*</label>
                      <input
                        type="text"
                        name="state"
                        value={form.state || ''}
                        onChange={handleChange}
                        placeholder="Gujarat"
                        style={{ width: '100%', padding: '12px 14px', border: '1.5px solid #CBD5E1', borderRadius: '10px', fontSize: '0.92rem', color: '#1E293B', outline: 'none', background: '#fff', boxSizing: 'border-box' }}
                      />
                    </div>
                  </div>
                </div>

                {/* Address Type Card */}
                <div style={{ background: '#fff', borderRadius: '16px', padding: '18px', border: '1px solid #E2E8F0', boxShadow: '0 2px 6px rgba(0,0,0,0.02)' }}>
                  <div style={{ fontSize: '0.95rem', fontWeight: 700, color: '#1E293B', marginBottom: '14px' }}>
                    Address Type
                  </div>
                  <div style={{ display: 'flex', gap: '28px', marginBottom: '18px' }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize: '0.95rem', fontWeight: 600, color: '#334155' }}>
                      <input
                        type="radio"
                        name="address_type"
                        value="Home"
                        checked={form.address_type === 'Home' || !form.address_type}
                        onChange={() => setForm(prev => ({ ...prev, address_type: 'Home' as 'Home' | 'Office' }))}
                        style={{ accentColor: '#F43F5E', width: '20px', height: '20px', cursor: 'pointer' }}
                      />
                      Home
                    </label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize: '0.95rem', fontWeight: 600, color: '#334155' }}>
                      <input
                        type="radio"
                        name="address_type"
                        value="Office"
                        checked={form.address_type === 'Office'}
                        onChange={() => setForm(prev => ({ ...prev, address_type: 'Office' as 'Home' | 'Office' }))}
                        style={{ accentColor: '#F43F5E', width: '20px', height: '20px', cursor: 'pointer' }}
                      />
                      Office
                    </label>
                  </div>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer', fontSize: '0.9rem', color: '#475569', fontWeight: 500 }}>
                    <input
                      type="checkbox"
                      checked={form.is_default !== false}
                      onChange={(e) => setForm(prev => ({ ...prev, is_default: e.target.checked }))}
                      style={{ accentColor: '#F43F5E', width: '18px', height: '18px', borderRadius: '4px', cursor: 'pointer' }}
                    />
                    Make this as my default address
                  </label>
                </div>
              </div>

              {/* Modal Footer */}
              <div style={{
                background: '#fff',
                padding: '16px 20px',
                borderTop: '1px solid #E2E8F0',
                display: 'flex',
                gap: '12px',
                position: 'sticky',
                bottom: 0,
                zIndex: 10
              }}>
                <button
                  type="button"
                  onClick={() => setShowAddressModal(false)}
                  style={{
                    flex: 1,
                    padding: '14px',
                    background: '#fff',
                    border: '1.5px solid #CBD5E1',
                    borderRadius: '12px',
                    fontSize: '1rem',
                    fontWeight: 700,
                    color: '#334155',
                    cursor: 'pointer',
                    transition: 'all 0.2s'
                  }}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={() => {
                    if (!form.delivery_address || !form.apartment_no || !form.city) {
                      alert('Please fill the required address fields (*)');
                      return;
                    }
                    setShowAddressModal(false);
                    const fullAddress = [
                      form.apartment_no ? `Apt/Flat: ${form.apartment_no}` : '',
                      form.apartment_name ? `Locality: ${form.apartment_name}` : '',
                      form.delivery_address,
                      form.pincode ? `PIN: ${form.pincode}` : '',
                      form.state ? `State: ${form.state}` : ''
                    ].filter(Boolean).join(', ');
                    updateDeliveryDistanceAndFee(fullAddress, form.city);
                  }}
                  style={{
                    flex: 1,
                    padding: '14px',
                    background: '#F43F5E',
                    border: 'none',
                    borderRadius: '12px',
                    fontSize: '1rem',
                    fontWeight: 700,
                    color: '#fff',
                    cursor: 'pointer',
                    boxShadow: '0 4px 14px rgba(244,63,94,0.35)',
                    transition: 'all 0.2s'
                  }}
                >
                  Save
                </button>
              </div>
            </div>
          )}

          {/* SCREEN 2: MAP PICKER (IMAGE 2) */}
          {modalScreen === 'map_picker' && (
            <div style={{
              background: '#F8FAFC',
              width: '100%',
              maxWidth: '560px',
              height: '85vh',
              maxHeight: '750px',
              borderRadius: '24px',
              boxShadow: '0 25px 50px rgba(0,0,0,0.3)',
              overflow: 'hidden',
              display: 'flex',
              flexDirection: 'column',
              position: 'relative'
            }}>
              {/* Top Floating Search Bar over Map */}
              <div style={{
                position: 'absolute',
                top: '16px',
                left: '16px',
                right: '16px',
                zIndex: 1000,
                display: 'flex',
                alignItems: 'center',
                gap: '12px'
              }}>
                <button
                  type="button"
                  onClick={() => setModalScreen('form')}
                  style={{
                    background: '#fff',
                    border: 'none',
                    borderRadius: '50%',
                    width: '44px',
                    height: '44px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '1.3rem',
                    color: '#1E293B',
                    cursor: 'pointer',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                    fontWeight: 700
                  }}
                >
                  ←
                </button>
                <div
                  onClick={() => setModalScreen('search_location')}
                  style={{
                    flex: 1,
                    background: '#fff',
                    borderRadius: '30px',
                    padding: '12px 18px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    boxShadow: '0 4px 14px rgba(0,0,0,0.15)',
                    cursor: 'pointer',
                    color: '#64748B',
                    fontSize: '0.92rem'
                  }}
                >
                  <span style={{ fontSize: '1.1rem' }}>🔍</span>
                  <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {searchQuery || 'Search for building, street or area'}
                  </span>
                </div>
              </div>

              {/* Map Container */}
              <div style={{ flex: 1, position: 'relative', width: '100%' }}>
                <div id="full-map-picker" style={{ width: '100%', height: '100%' }} />

                {/* Fixed Center Tooltip & Marker Overlay (Exact Image 2 Style) */}
                <div style={{
                  position: 'absolute',
                  top: '48%',
                  left: '50%',
                  transform: 'translate(-50%, -100%)',
                  zIndex: 800,
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  pointerEvents: 'none'
                }}>
                  <div style={{
                    background: '#0F172A',
                    color: '#fff',
                    padding: '10px 16px',
                    borderRadius: '12px',
                    fontSize: '0.8rem',
                    fontWeight: 600,
                    boxShadow: '0 8px 20px rgba(0,0,0,0.3)',
                    textAlign: 'center',
                    marginBottom: '4px',
                    maxWidth: '220px',
                    lineHeight: 1.3
                  }}>
                    Order will be delivered here, Move the pin to change location
                  </div>
                  <div style={{
                    width: '0',
                    height: '0',
                    borderLeft: '7px solid transparent',
                    borderRight: '7px solid transparent',
                    borderTop: '7px solid #0F172A',
                    marginTop: '-4px',
                    marginBottom: '2px'
                  }} />
                  <div style={{ fontSize: '2.6rem', filter: 'drop-shadow(0 4px 8px rgba(0,0,0,0.35))', marginTop: '-4px' }}>
                    📍
                  </div>
                </div>

                {/* Floating Current Location Pill Button at bottom of Map */}
                <button
                  type="button"
                  onClick={handleDetectLocation}
                  style={{
                    position: 'absolute',
                    bottom: '16px',
                    left: '50%',
                    transform: 'translateX(-50%)',
                    background: '#fff',
                    color: '#F43F5E',
                    border: 'none',
                    padding: '10px 22px',
                    borderRadius: '30px',
                    fontSize: '0.9rem',
                    fontWeight: 700,
                    cursor: 'pointer',
                    boxShadow: '0 4px 16px rgba(0,0,0,0.18)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    zIndex: 900,
                    whiteSpace: 'nowrap'
                  }}
                >
                  <span style={{ fontSize: '1.2rem' }}>🎯</span>
                  <span>{locating ? 'Detecting...' : 'Use my current Location'}</span>
                </button>
              </div>

              {/* Bottom Sheet Card: Deliver To (Image 2 Style) */}
              <div style={{
                background: '#fff',
                borderTopLeftRadius: '24px',
                borderTopRightRadius: '24px',
                padding: '20px',
                boxShadow: '0 -4px 25px rgba(0,0,0,0.1)',
                zIndex: 1000,
                position: 'relative'
              }}>
                <div style={{ fontSize: '1.05rem', fontWeight: 800, color: '#1E293B', marginBottom: '12px', textAlign: 'left' }}>
                  Deliver To
                </div>
                <div style={{
                  background: '#F8FAFC',
                  border: '1.5px solid #E2E8F0',
                  borderRadius: '16px',
                  padding: '16px',
                  marginBottom: '16px',
                  textAlign: 'left'
                }}>
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                    <span style={{ fontSize: '1.3rem', marginTop: '2px' }}>📍</span>
                    <div style={{ flex: 1, overflow: 'hidden' }}>
                      <div style={{ fontWeight: 800, fontSize: '0.98rem', color: '#1E293B', marginBottom: '4px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {form.delivery_address ? form.delivery_address.split(',')[0] : (locating ? 'Detecting location...' : 'Selected Location')}
                      </div>
                      <div style={{ fontSize: '0.84rem', color: '#64748B', lineHeight: 1.4 }}>
                        {[form.apartment_name, form.delivery_address, form.city, form.state, form.pincode].filter(Boolean).join(', ') || 'Move pin on map or search to select address'}
                      </div>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setModalScreen('form');
                  }}
                  style={{
                    width: '100%',
                    padding: '16px',
                    background: '#F43F5E',
                    color: '#fff',
                    border: 'none',
                    borderRadius: '14px',
                    fontSize: '1.02rem',
                    fontWeight: 800,
                    cursor: 'pointer',
                    boxShadow: '0 4px 14px rgba(244,63,94,0.35)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '8px'
                  }}
                >
                  <span>Confirm & Proceed</span>
                  <span>&gt;</span>
                </button>
              </div>
            </div>
          )}

          {/* SCREEN 3: SEARCH LOCATION (IMAGE 3) */}
          {modalScreen === 'search_location' && (
            <div style={{
              background: '#fff',
              width: '100%',
              maxWidth: '560px',
              height: '85vh',
              maxHeight: '750px',
              borderRadius: '24px',
              boxShadow: '0 25px 50px rgba(0,0,0,0.3)',
              overflow: 'hidden',
              display: 'flex',
              flexDirection: 'column',
              position: 'relative'
            }}>
              {/* Header */}
              <div style={{
                background: '#fff',
                padding: '16px 20px',
                display: 'flex',
                alignItems: 'center',
                gap: '14px',
                borderBottom: '1px solid #F1F5F9'
              }}>
                <button
                  type="button"
                  onClick={() => setModalScreen('map_picker')}
                  style={{
                    background: 'transparent',
                    border: 'none',
                    fontSize: '1.4rem',
                    color: '#1E293B',
                    cursor: 'pointer',
                    fontWeight: 700,
                    padding: 0
                  }}
                >
                  ←
                </button>
                <h2 style={{ fontSize: '1.1rem', fontWeight: 800, margin: 0, color: '#1E293B' }}>
                  Select Delivery Address
                </h2>
              </div>

              {/* Amber Important Banner */}
              <div style={{
                background: '#FFFBEB',
                padding: '12px 18px',
                display: 'flex',
                gap: '10px',
                alignItems: 'flex-start',
                borderBottom: '1px solid #FEF3C7',
                color: '#B45309',
                fontSize: '0.82rem',
                lineHeight: 1.4,
                fontWeight: 500,
                textAlign: 'left'
              }}>
                <span style={{ fontSize: '1.1rem' }}>ℹ️</span>
                <span><strong>Important:</strong> Use your current location or manually search on the map to guide delivery partners.</span>
              </div>

              {/* Search Bar Input */}
              <div style={{ padding: '14px 18px', borderBottom: '1px solid #F1F5F9', position: 'relative' }}>
                <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
                  <span style={{ position: 'absolute', left: '14px', color: '#64748B', fontSize: '1.1rem' }}>🔍</span>
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search for building, street or area"
                    autoFocus
                    style={{
                      width: '100%',
                      padding: '12px 38px 12px 42px',
                      border: '1.5px solid #CBD5E1',
                      borderRadius: '12px',
                      fontSize: '0.94rem',
                      color: '#1E293B',
                      outline: 'none',
                      background: '#fff',
                      boxSizing: 'border-box'
                    }}
                  />
                  {searchQuery && (
                    <button
                      type="button"
                      onClick={() => {
                        setSearchQuery('');
                        setSearchResults([]);
                      }}
                      style={{
                        position: 'absolute',
                        right: '12px',
                        background: '#94A3B8',
                        color: '#fff',
                        border: 'none',
                        borderRadius: '50%',
                        width: '22px',
                        height: '22px',
                        fontSize: '0.75rem',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center'
                      }}
                    >
                      ✕
                    </button>
                  )}
                </div>
              </div>

              {/* Current Location Row */}
              <div
                onClick={handleDetectLocation}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '12px',
                  padding: '14px 20px',
                  borderBottom: '1px solid #F1F5F9',
                  cursor: 'pointer',
                  color: '#F43F5E',
                  fontWeight: 700,
                  fontSize: '0.95rem',
                  transition: 'background 0.2s',
                  textAlign: 'left'
                }}
              >
                <span style={{ fontSize: '1.3rem' }}>🎯</span>
                <span>Use my current Location</span>
              </div>

              {/* Live Search Results List */}
              <div style={{ flex: 1, overflowY: 'auto', textAlign: 'left' }}>
                {searching && (
                  <div style={{ padding: '30px', textAlign: 'center', color: '#64748B', fontSize: '0.9rem', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
                    <span>⏳ Searching locations across India...</span>
                  </div>
                )}
                {!searching && searchResults.length === 0 && searchQuery && (
                  <div style={{ padding: '30px', textAlign: 'center', color: '#64748B', fontSize: '0.9rem' }}>
                    No locations found for "{searchQuery}". Try searching city, area, or landmark name.
                  </div>
                )}
                {searchResults.map((result: any, idx: number) => {
                  const mainName = result.name || result.display_name.split(',')[0];
                  const subName = result.display_name;
                  return (
                    <div
                      key={idx}
                      onClick={() => handleSelectResult(result)}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '16px 20px',
                        borderBottom: '1px solid #F1F5F9',
                        cursor: 'pointer',
                        transition: 'background 0.2s'
                      }}
                    >
                      <div style={{ display: 'flex', alignItems: 'flex-start', gap: '14px', flex: 1, overflow: 'hidden' }}>
                        <span style={{ fontSize: '1.2rem', color: '#64748B', marginTop: '2px' }}>📍</span>
                        <div style={{ flex: 1, overflow: 'hidden' }}>
                          <div style={{ fontWeight: 700, fontSize: '0.95rem', color: '#1E293B', marginBottom: '3px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {mainName}
                          </div>
                          <div style={{ fontSize: '0.8rem', color: '#64748B', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {subName}
                          </div>
                        </div>
                      </div>
                      <span style={{ color: '#94A3B8', fontSize: '1.2rem', marginLeft: '12px', fontWeight: 600 }}>↗</span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
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
