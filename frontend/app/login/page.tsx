'use client';

import { useState, useEffect, useRef } from 'react';
import { useCart } from '../context/CartContext';
import { useRouter } from 'next/navigation';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';
import Link from 'next/link';
import Script from 'next/script';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';
// Use process.env.NEXT_PUBLIC_GOOGLE_CLIENT_ID or placeholder
const GOOGLE_CLIENT_ID = process.env.NEXT_PUBLIC_GOOGLE_CLIENT_ID || '1017482487441-testplaceholdermockid.apps.googleusercontent.com';

export default function LoginPage() {
  const { loginUser, userPhone, userEmail, state } = useCart();
  const router = useRouter();

  const [identifier, setIdentifier] = useState(''); // Holds Phone or Email
  const [name, setName] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [otpCode, setOtpCode] = useState(''); // OTP code input by user
  const [devOtp, setDevOtp] = useState(''); // OTP code returned by backend for easy dev copy-paste
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  // If already logged in, redirect to correct page
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const storedPhone = localStorage.getItem('user_phone');
      const storedEmail = localStorage.getItem('user_email');
      if (storedPhone || storedEmail) {
        const params = new URLSearchParams(window.location.search);
        const redirectParam = params.get('redirect');
        if (redirectParam) {
          router.push(redirectParam);
        } else {
          router.push('/');
        }
      }
    }
  }, [router]);

  // Request OTP code
  const handleRequestOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!identifier.trim()) {
      setToast('⚠️ Phone number or Email is required!');
      return;
    }

    setLoading(true);
    try {
      const res = await fetch(`${API}/otp/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: identifier.trim() })
      });
      const data = await res.json();
      
      if (res.ok) {
        setOtpSent(true);
        if (data.otp) {
          setDevOtp(data.otp);
          setToast(`🔑 OTP Sent! Development Code: ${data.otp}`);
        } else {
          setToast('📩 Verification code sent successfully!');
        }
      } else {
        setToast(`❌ Error: ${data.message || 'Failed to send OTP'}`);
      }
    } catch (err) {
      setToast('❌ Connection error. Is the backend server running?');
    } finally {
      setLoading(false);
    }
  };

  // Verify OTP code and Login
  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!otpCode.trim()) {
      setToast('⚠️ Verification code is required!');
      return;
    }

    setLoading(true);
    try {
      const res = await fetch(`${API}/otp/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          identifier: identifier.trim(),
          code: otpCode.trim()
        })
      });
      const data = await res.json();

      if (res.ok) {
        const isEmail = data.is_email;
        const phoneParam = isEmail ? null : identifier.trim();
        const emailParam = isEmail ? identifier.trim() : null;
        const resolvedName = name.trim() || data.name || 'Customer';

        loginUser(phoneParam, emailParam, resolvedName);
        setToast('✅ Login Successful!');
        
        setTimeout(() => {
          let targetPath = '/';
          const params = new URLSearchParams(window.location.search);
          const redirectParam = params.get('redirect');
          if (redirectParam) {
            targetPath = redirectParam;
          }
          router.push(targetPath);
        }, 500);
      } else {
        setToast(`❌ Verification failed: ${data.message}`);
      }
    } catch (err) {
      setToast('❌ Connection error during verification.');
    } finally {
      setLoading(false);
    }
  };

  // Google Login handling
  const handleGoogleCredentialResponse = async (response: any) => {
    setLoading(true);
    try {
      const res = await fetch(`${API}/auth/google`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credential: response.credential })
      });
      const data = await res.json();

      if (res.ok) {
        loginUser(null, data.email, data.name || 'Google User');
        setToast('✅ Signed in with Google successfully!');
        setTimeout(() => {
          let targetPath = '/';
          const params = new URLSearchParams(window.location.search);
          const redirectParam = params.get('redirect');
          if (redirectParam) {
            targetPath = redirectParam;
          }
          router.push(targetPath);
        }, 500);
      } else {
        setToast(`❌ Google Sign-In failed: ${data.message}`);
      }
    } catch (err) {
      setToast('❌ Error verifying Google account.');
    } finally {
      setLoading(false);
    }
  };

  // Trigger Google script initialization
  const initGoogleSignIn = () => {
    try {
      if (typeof window !== 'undefined' && (window as any).google) {
        const container = document.getElementById('google-signin-btn');
        const windowWidth = typeof window !== 'undefined' ? window.innerWidth : 450;
        // Fallback calculation: Card has 2.5rem (40px) padding on each side on desktop/mobile
        const calculatedWidth = Math.min(370, windowWidth - 80);
        const btnWidth = container && container.clientWidth > 0 ? container.clientWidth : calculatedWidth;

        (window as any).google.accounts.id.initialize({
          client_id: GOOGLE_CLIENT_ID,
          callback: handleGoogleCredentialResponse,
        });
        (window as any).google.accounts.id.renderButton(
          document.getElementById('google-signin-btn'),
          { theme: 'outline', size: 'large', width: Math.max(200, Math.min(400, btnWidth)) }
        );
      }
    } catch (err) {
      console.warn('Google GSI initialization error:', err);
    }
  };

  useEffect(() => {
    initGoogleSignIn();
  }, [otpSent]);

  // Mock Google sign in function for quick development/offline testing
  const handleMockGoogleLogin = () => {
    // Generate a mock JWT token locally
    const header = b64e(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
    const mockEmail = identifier.includes('@') ? identifier.trim() : 'mockuser@gmail.com';
    const payload = b64e(JSON.stringify({
      email: mockEmail,
      name: name.trim() || 'Mock Google User',
      picture: '',
      sub: 'mock_sub_123456789'
    }));
    const signature = 'mocksignature';
    const mockCredential = `${header}.${payload}.${signature}`;

    handleGoogleCredentialResponse({ credential: mockCredential });
  };

  function b64e(str: string) {
    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
      return String.fromCharCode(parseInt(p1, 16));
    })).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  useEffect(() => {
    if (toast) {
      const t = setTimeout(() => setToast(null), 4000);
      return () => clearTimeout(t);
    }
  }, [toast]);

  return (
    <div className="page-wrapper">
      <Navbar />
      <CartDrawer />
      <Script 
        src="https://accounts.google.com/gsi/client" 
        onLoad={initGoogleSignIn}
        strategy="afterInteractive"
      />

      <div style={{ padding: '4rem 2rem', maxWidth: 450, margin: '0 auto' }}>
        <div className="tracking-card" style={{ padding: '2.5rem', border: '1px solid var(--border)' }}>
          <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
            <div style={{ fontSize: '3rem', marginBottom: '0.5rem' }}>👤</div>
            <h1 style={{ fontSize: '1.75rem', fontWeight: 900, color: 'var(--text)' }}>Customer Login</h1>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginTop: '0.25rem' }}>
              {!otpSent 
                ? 'Enter your phone number or email address to verify and log in.'
                : `Verification code sent to: ${identifier}`
              }
            </p>
          </div>

          {!otpSent ? (
            /* STEP 1: Enter phone/email */
            <form onSubmit={handleRequestOtp} style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
              <div className="form-group">
                <label className="form-label" style={{ fontWeight: 600 }}>Phone Number or Email</label>
                <input 
                  type="text" 
                  className="form-input" 
                  placeholder="e.g. +91 9876543210 or user@example.com" 
                  required 
                  value={identifier} 
                  onChange={(e) => setIdentifier(e.target.value)} 
                  style={{ width: '100%' }}
                />
              </div>

              <div className="form-group">
                <label className="form-label" style={{ fontWeight: 600 }}>Your Name (Optional)</label>
                <input 
                  type="text" 
                  className="form-input" 
                  placeholder="e.g. John Doe (Optional)" 
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
                {loading ? '⏳ Processing...' : '🚀 Send Verification Code (OTP)'}
              </button>

              {/* Separator */}
              <div style={{ display: 'flex', alignItems: 'center', margin: '1rem 0' }}>
                <div style={{ flex: 1, height: '1px', background: 'var(--border)' }}></div>
                <span style={{ padding: '0 10px', fontSize: '0.8rem', color: 'var(--text-muted)' }}>OR</span>
                <div style={{ flex: 1, height: '1px', background: 'var(--border)' }}></div>
              </div>

              {/* Google Sign-in Buttons */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', alignItems: 'center' }}>
                <div id="google-signin-btn" style={{ width: '100%' }}></div>
                <button
                  type="button"
                  onClick={handleMockGoogleLogin}
                  className="btn-secondary"
                  style={{
                    width: '100%',
                    padding: '12px',
                    justifyContent: 'center',
                    fontWeight: 700,
                    fontSize: '0.88rem',
                    cursor: 'pointer',
                    background: '#f8fafc',
                    borderColor: '#cbd5e1',
                    color: '#334155',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px'
                  }}
                >
                  🌐 Sign in with Google (Mock Mode)
                </button>
              </div>
            </form>
          ) : (
            /* STEP 2: Verify OTP */
            <form onSubmit={handleVerifyOtp} style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
              <div className="form-group">
                <label className="form-label" style={{ fontWeight: 600 }}>Verification Code (OTP)</label>
                <input 
                  type="text" 
                  className="form-input" 
                  placeholder="Enter 6-digit OTP code" 
                  required 
                  maxLength={6}
                  value={otpCode} 
                  onChange={(e) => setOtpCode(e.target.value)} 
                  style={{ width: '100%', letterSpacing: '0.2em', textAlign: 'center', fontSize: '1.25rem', fontWeight: 'bold' }}
                />
              </div>

              {devOtp && (
                <div style={{ 
                  background: '#fef3c7', 
                  border: '1px solid #f59e0b', 
                  color: '#b45309', 
                  padding: '10px', 
                  borderRadius: '6px', 
                  fontSize: '0.82rem', 
                  textAlign: 'center',
                  fontWeight: 600 
                }}>
                  🛠️ Local Testing OTP: <span style={{ fontFamily: 'monospace', fontSize: '1.05rem', color: '#78350f' }}>{devOtp}</span>
                </div>
              )}

              <button 
                type="submit" 
                className="btn-primary" 
                style={{ width: '100%', padding: '14px', justifyContent: 'center', fontWeight: 800, fontSize: '0.95rem', cursor: 'pointer', marginTop: '0.5rem' }}
                disabled={loading}
              >
                {loading ? '⏳ Verifying...' : '✅ Verify & Login'}
              </button>

              <button 
                type="button" 
                onClick={() => setOtpSent(false)} 
                className="btn-secondary"
                style={{ width: '100%', padding: '12px', justifyContent: 'center', fontWeight: 700 }}
              >
                ← Edit Phone/Email
              </button>
            </form>
          )}
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
