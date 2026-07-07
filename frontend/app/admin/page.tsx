"use client";

import { useState } from "react";

const PRODUCTION_ADMIN = "https://backend.swaadepunjab.com/admin";
const LOCAL_ADMIN = "http://127.0.0.1:8000/admin";

export default function AdminLauncher() {
  const [hoveredCard, setHoveredCard] = useState<string | null>(null);

  return (
    <div style={{
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      minHeight: '100vh',
      background: 'linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F172A 100%)',
      fontFamily: "'Inter', -apple-system, sans-serif",
      padding: '2rem',
    }}>
      {/* Header */}
      <div style={{ textAlign: 'center', marginBottom: '3rem' }}>
        <div style={{ fontSize: '3rem', marginBottom: '0.75rem' }}>🍕</div>
        <h1 style={{
          color: '#F8FAFC',
          fontSize: '1.8rem',
          fontWeight: 800,
          margin: '0 0 0.5rem',
          letterSpacing: '-0.5px'
        }}>
          Swaad E Punjab
        </h1>
        <p style={{ color: '#94A3B8', fontSize: '0.95rem', margin: 0 }}>
          Select the Admin Panel to manage orders
        </p>
      </div>

      {/* Cards */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
        gap: '1.5rem',
        width: '100%',
        maxWidth: '680px',
      }}>
        {/* Production Card */}
        <a
          href={PRODUCTION_ADMIN}
          target="_blank"
          rel="noopener noreferrer"
          onMouseEnter={() => setHoveredCard('prod')}
          onMouseLeave={() => setHoveredCard(null)}
          style={{
            display: 'block',
            textDecoration: 'none',
            background: hoveredCard === 'prod'
              ? 'linear-gradient(135deg, #1E40AF, #2563EB)'
              : 'linear-gradient(135deg, #1E3A8A, #1E40AF)',
            borderRadius: '20px',
            padding: '2rem',
            border: '1px solid rgba(59,130,246,0.3)',
            boxShadow: hoveredCard === 'prod'
              ? '0 20px 40px rgba(37,99,235,0.4)'
              : '0 8px 20px rgba(0,0,0,0.3)',
            transform: hoveredCard === 'prod' ? 'translateY(-4px)' : 'none',
            transition: 'all 0.25s ease',
            cursor: 'pointer',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '1rem' }}>
            <div style={{
              background: 'rgba(255,255,255,0.15)',
              borderRadius: '12px',
              padding: '10px',
              fontSize: '1.5rem'
            }}>🌐</div>
            <div>
              <div style={{ color: '#93C5FD', fontSize: '0.75rem', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                LIVE PRODUCTION
              </div>
              <div style={{ color: '#F8FAFC', fontSize: '1.1rem', fontWeight: 700 }}>
                Production Admin
              </div>
            </div>
          </div>
          <p style={{ color: '#BFDBFE', fontSize: '0.88rem', margin: '0 0 1.25rem', lineHeight: 1.5 }}>
            Orders placed from <strong>localhost:3000</strong> go here. This is the live production database.
          </p>
          <div style={{
            background: 'rgba(255,255,255,0.1)',
            borderRadius: '10px',
            padding: '10px 14px',
            fontSize: '0.8rem',
            color: '#93C5FD',
            fontFamily: 'monospace',
            wordBreak: 'break-all'
          }}>
            backend.swaadepunjab.com/admin
          </div>
          <div style={{
            marginTop: '1.25rem',
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            color: '#60A5FA',
            fontSize: '0.88rem',
            fontWeight: 600
          }}>
            <span style={{
              width: '8px', height: '8px',
              borderRadius: '50%',
              background: '#22C55E',
              boxShadow: '0 0 6px #22C55E',
              display: 'inline-block',
              animation: 'pulse 2s infinite'
            }} />
            Open Production Admin →
          </div>
        </a>

        {/* Local Card */}
        <a
          href={LOCAL_ADMIN}
          target="_blank"
          rel="noopener noreferrer"
          onMouseEnter={() => setHoveredCard('local')}
          onMouseLeave={() => setHoveredCard(null)}
          style={{
            display: 'block',
            textDecoration: 'none',
            background: hoveredCard === 'local'
              ? 'linear-gradient(135deg, #065F46, #047857)'
              : 'linear-gradient(135deg, #064E3B, #065F46)',
            borderRadius: '20px',
            padding: '2rem',
            border: '1px solid rgba(16,185,129,0.3)',
            boxShadow: hoveredCard === 'local'
              ? '0 20px 40px rgba(16,185,129,0.4)'
              : '0 8px 20px rgba(0,0,0,0.3)',
            transform: hoveredCard === 'local' ? 'translateY(-4px)' : 'none',
            transition: 'all 0.25s ease',
            cursor: 'pointer',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '1rem' }}>
            <div style={{
              background: 'rgba(255,255,255,0.15)',
              borderRadius: '12px',
              padding: '10px',
              fontSize: '1.5rem'
            }}>💻</div>
            <div>
              <div style={{ color: '#6EE7B7', fontSize: '0.75rem', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                LOCAL DEVELOPMENT
              </div>
              <div style={{ color: '#F8FAFC', fontSize: '1.1rem', fontWeight: 700 }}>
                Local Admin
              </div>
            </div>
          </div>
          <p style={{ color: '#A7F3D0', fontSize: '0.88rem', margin: '0 0 1.25rem', lineHeight: 1.5 }}>
            For testing with a <strong>local backend</strong>. Requires the Laravel server on port 8000 to be running.
          </p>
          <div style={{
            background: 'rgba(255,255,255,0.1)',
            borderRadius: '10px',
            padding: '10px 14px',
            fontSize: '0.8rem',
            color: '#6EE7B7',
            fontFamily: 'monospace'
          }}>
            127.0.0.1:8000/admin
          </div>
          <div style={{
            marginTop: '1.25rem',
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            color: '#34D399',
            fontSize: '0.88rem',
            fontWeight: 600
          }}>
            <span style={{
              width: '8px', height: '8px',
              borderRadius: '50%',
              background: '#FCD34D',
              boxShadow: '0 0 6px #FCD34D',
              display: 'inline-block'
            }} />
            Open Local Admin →
          </div>
        </a>
      </div>

      {/* Info Box */}
      <div style={{
        marginTop: '2.5rem',
        background: 'rgba(255,255,255,0.05)',
        border: '1px solid rgba(255,255,255,0.1)',
        borderRadius: '14px',
        padding: '1.25rem 1.75rem',
        maxWidth: '680px',
        width: '100%',
        color: '#94A3B8',
        fontSize: '0.85rem',
        lineHeight: 1.6
      }}>
        <strong style={{ color: '#F59E0B' }}>ℹ️ Tip:</strong> The frontend at{' '}
        <code style={{ background: 'rgba(255,255,255,0.08)', padding: '2px 6px', borderRadius: '4px', color: '#E2E8F0' }}>
          localhost:3000
        </code>{' '}
        is configured to send orders to the <strong style={{ color: '#60A5FA' }}>Production</strong> server. 
        To test with a local backend, change <code style={{ background: 'rgba(255,255,255,0.08)', padding: '2px 6px', borderRadius: '4px', color: '#E2E8F0' }}>
          NEXT_PUBLIC_API_URL
        </code> in <code style={{ background: 'rgba(255,255,255,0.08)', padding: '2px 6px', borderRadius: '4px', color: '#E2E8F0' }}>.env.local</code> to{' '}
        <code style={{ background: 'rgba(255,255,255,0.08)', padding: '2px 6px', borderRadius: '4px', color: '#6EE7B7' }}>
          http://127.0.0.1:8000/api
        </code>
      </div>

      <style>{`
        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.4; }
        }
      `}</style>
    </div>
  );
}
