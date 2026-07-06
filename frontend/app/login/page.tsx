'use client';

import { useState, useEffect } from 'react';
import { useCart } from '../context/CartContext';
import { useRouter } from 'next/navigation';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';
import Link from 'next/link';

export default function LoginPage() {
  const { loginUser, userPhone, state } = useCart();
  const router = useRouter();

  const [phone, setPhone] = useState('');
  const [name, setName] = useState('');
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  // If already logged in, redirect to correct page
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const storedPhone = localStorage.getItem('user_phone');
      if (storedPhone) {
        const params = new URLSearchParams(window.location.search);
        const redirectParam = params.get('redirect');
        if (redirectParam) {
          router.push(redirectParam);
        } else if (state.items.length > 0) {
          router.push('/checkout');
        } else {
          router.push('/');
        }
      }
    }
  }, [router, state.items.length]);

  const handleLogin = (e: React.FormEvent) => {
    e.preventDefault();
    if (!phone.trim()) {
      setToast('⚠️ Phone number is required!');
      return;
    }

    setLoading(true);
    
    // Simulate database lookup or basic session setup
    setTimeout(() => {
      loginUser(phone.trim(), name.trim() || 'Customer');
      setLoading(false);

      let targetPath = '/';
      if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const redirectParam = params.get('redirect');
        if (redirectParam) {
          targetPath = redirectParam;
        } else if (state.items.length > 0) {
          targetPath = '/checkout';
        }
      }
      router.push(targetPath);
    }, 800);
  };

  useEffect(() => {
    if (toast) {
      const t = setTimeout(() => setToast(null), 3000);
      return () => clearTimeout(t);
    }
  }, [toast]);

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />

      <div style={{ padding: '4rem 2rem', maxWidth: 450, margin: '0 auto' }}>
        <div className="tracking-card" style={{ padding: '2.5rem', border: '1px solid var(--border)' }}>
          <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
            <div style={{ fontSize: '3rem', marginBottom: '0.5rem' }}>👤</div>
            <h1 style={{ fontSize: '1.75rem', fontWeight: 900, color: 'var(--text)' }}>Customer Login</h1>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginTop: '0.25rem' }}>
              Enter your phone number to track orders and view past history.
            </p>
          </div>

          <form onSubmit={handleLogin} style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div className="form-group">
              <label className="form-label" style={{ fontWeight: 600 }}>Phone Number (Required)</label>
              <input 
                type="tel" 
                className="form-input" 
                placeholder="e.g. +91 98765 43210" 
                required 
                value={phone} 
                onChange={(e) => setPhone(e.target.value)} 
                style={{ width: '100%' }}
              />
            </div>

            <div className="form-group">
              <label className="form-label" style={{ fontWeight: 600 }}>Your Name (Optional)</label>
              <input 
                type="text" 
                className="form-input" 
                placeholder="John Doe" 
                value={name} 
                onChange={(e) => setName(e.target.value)} 
                style={{ width: '100%' }}
              />
            </div>

            <button 
              type="submit" 
              className="btn-primary" 
              style={{ width: '100%', padding: '14px', justifyContent: 'center', fontWeight: 800, fontSize: '0.95rem', cursor: 'pointer', marginTop: '0.5rem' }}
              disabled={loading}
            >
              {loading ? '⏳ Logging in...' : '🚀 Login / View My History'}
            </button>
          </form>
        </div>
      </div>

      {toast && (
        <div style={{
          position: 'fixed',
          bottom: '2rem',
          right: '2rem',
          background: '#333',
          color: '#fff',
          padding: '12px 24px',
          borderRadius: '8px',
          boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
          zIndex: 10000,
          fontSize: '0.9rem',
          fontWeight: 600
        }}>
          {toast}
        </div>
      )}
    </div>
  );
}
