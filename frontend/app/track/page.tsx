'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';

export default function TrackSearchPage() {
  const [orderNumber, setOrderNumber] = useState('');
  const router = useRouter();

  const handleTrack = (e: React.FormEvent) => {
    e.preventDefault();
    if (orderNumber.trim()) {
      router.push(`/track/${orderNumber.trim()}`);
    }
  };

  return (
    <div className="page-wrapper" style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      <Navbar />
      <CartDrawer />

      <div style={{ padding: '6rem 2rem', maxWidth: 600, margin: '0 auto', textAlign: 'center', flex: 1 }}>
        <div style={{ fontSize: '4rem', marginBottom: '1.5rem' }}>📦</div>
        <h1 style={{ fontSize: '2.2rem', fontWeight: 900, marginBottom: '1rem', color: 'var(--text)' }}>
          Track Your <span style={{ color: 'var(--primary)' }}>Order</span>
        </h1>
        <p style={{ color: 'var(--text-muted)', marginBottom: '2.5rem', fontSize: '1.05rem' }}>
          Enter your order number below to check its preparation or delivery progress in real-time.
        </p>

        <form onSubmit={handleTrack} className="search-bar" style={{ maxWidth: 450, margin: '0 auto' }}>
          <span>🔍</span>
          <input
            type="text"
            value={orderNumber}
            onChange={e => setOrderNumber(e.target.value)}
            placeholder="Order number (e.g. SEP-123)..."
            required
            style={{ flex: 1, background: 'none', border: 'none', outline: 'none' }}
          />
          <button type="submit" className="search-btn">Track</button>
        </form>
      </div>

      <footer>
        <p>© 2026 <strong style={{ color: 'var(--primary)' }}>Swaad E Punjab</strong> — Track your order in real-time 🛵</p>
      </footer>
    </div>
  );
}
