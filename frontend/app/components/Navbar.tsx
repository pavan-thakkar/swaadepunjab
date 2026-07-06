'use client';

import { useCart } from '../context/CartContext';
import Link from 'next/link';
import { useState } from 'react';

export default function Navbar() {
  const { dispatch, itemCount, userPhone, userName, logoutUser } = useCart();
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <nav className="navbar">
      <Link href="/" className="navbar-brand">
        🍛 <span>Swaad <span style={{ color: 'var(--primary)' }}>E Punjab</span></span>
      </Link>
      
      {/* Desktop Navbar Actions */}
      <div className="navbar-actions desktop-nav">
        {userPhone ? (
          <>
            <span className="navbar-welcome nav-btn-text" style={{ fontSize: '0.85rem', color: 'var(--text-muted)', display: 'inline-block', marginRight: '6px' }}>
              Hi, {userName || 'Customer'} 👋
            </span>
            <Link href="/history" className="btn-secondary nav-action-btn">
              <div className="nav-icon-container">📜</div>
              <span className="nav-label">Orders</span>
            </Link>
            <button 
              onClick={() => setShowLogoutConfirm(true)} 
              className="btn-secondary nav-action-btn logout-btn" 
              title="Logout"
            >
              <div className="nav-icon-container">🚪</div>
              <span className="nav-label">Logout</span>
            </button>
          </>
        ) : (
          <Link href="/login" className="btn-secondary nav-action-btn">
            <div className="nav-icon-container">👤</div>
            <span className="nav-label">Login</span>
          </Link>
        )}
        <Link href="/track" className="btn-secondary nav-action-btn">
          <div className="nav-icon-container">📦</div>
          <span className="nav-label">Track</span>
        </Link>
        <button
          className="cart-btn nav-action-btn"
          onClick={() => dispatch({ type: 'TOGGLE_CART' })}
        >
          <div className="nav-icon-container">
            <span>🛒</span>
            {itemCount > 0 && <span className="cart-badge">{itemCount}</span>}
          </div>
          <span className="nav-label">Cart</span>
        </button>
      </div>

      {/* Mobile Navbar Actions (Cart + Hamburger Toggle) */}
      <div className="mobile-nav">
        <button
          className="cart-btn-compact"
          onClick={() => dispatch({ type: 'TOGGLE_CART' })}
          title="Open Cart"
        >
          <span>🛒</span>
          {itemCount > 0 && <span className="cart-badge">{itemCount}</span>}
        </button>
        <button
          className="hamburger-btn"
          onClick={() => setMenuOpen(!menuOpen)}
          aria-label="Toggle Menu"
          title="Menu"
        >
          {menuOpen ? '✕' : '☰'}
        </button>
      </div>

      {/* Mobile Dropdown Menu Overlay */}
      {menuOpen && (
        <div className="mobile-menu-overlay" onClick={() => setMenuOpen(false)}>
          <div className="mobile-menu-content" onClick={(e) => e.stopPropagation()}>
            {userPhone && (
              <div className="mobile-welcome">
                Hi, {userName || 'Customer'} 👋
              </div>
            )}
            <Link href="/" className="mobile-menu-item" onClick={() => setMenuOpen(false)}>
              <span>🏠</span> Home
            </Link>
            
            {userPhone ? (
              <>
                <Link href="/history" className="mobile-menu-item" onClick={() => setMenuOpen(false)}>
                  <span>📜</span> My Orders
                </Link>
                <button 
                  className="mobile-menu-item logout-menu-btn" 
                  onClick={() => {
                    setMenuOpen(false);
                    setShowLogoutConfirm(true);
                  }}
                >
                  <span>🚪</span> Logout
                </button>
              </>
            ) : (
              <Link href="/login" className="mobile-menu-item" onClick={() => setMenuOpen(false)}>
                <span>👤</span> Login / Register
              </Link>
            )}
            
            <Link href="/track" className="mobile-menu-item" onClick={() => setMenuOpen(false)}>
              <span>📦</span> Track Order
            </Link>
          </div>
        </div>
      )}

      {showLogoutConfirm && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0,0,0,0.4)',
          backdropFilter: 'blur(5px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 10000
        }}>
          <div className="tracking-card" style={{
            background: 'var(--bg-card)',
            border: '1px solid var(--border)',
            borderRadius: 'var(--radius)',
            padding: '2.25rem 2rem',
            width: '90%',
            maxWidth: '380px',
            boxShadow: 'var(--shadow)',
            textAlign: 'center'
          }}>
            <div style={{ fontSize: '2.5rem', marginBottom: '0.75rem' }}>🚪</div>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text)', marginBottom: '0.5rem' }}>
              Confirm Logout
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '1.75rem', lineHeight: '1.5' }}>
              Are you sure you want to log out of your account? You will need to log in again to view your order history.
            </p>
            <div style={{ display: 'flex', gap: '12px', justifyContent: 'center' }}>
              <button 
                onClick={() => setShowLogoutConfirm(false)}
                className="btn-secondary"
                style={{ padding: '10px 20px', fontSize: '0.88rem', fontWeight: 700, cursor: 'pointer', flex: 1, border: '1px solid var(--border)' }}
              >
                Cancel
              </button>
              <button 
                onClick={() => {
                  logoutUser();
                  setShowLogoutConfirm(false);
                }}
                className="btn-primary"
                style={{ 
                  padding: '10px 20px', 
                  fontSize: '0.88rem', 
                  fontWeight: 700, 
                  cursor: 'pointer', 
                  background: 'var(--danger)', 
                  borderColor: 'var(--danger)',
                  color: '#fff',
                  flex: 1 
                }}
              >
                Yes, Logout
              </button>
            </div>
          </div>
        </div>
      )}
    </nav>
  );
}
