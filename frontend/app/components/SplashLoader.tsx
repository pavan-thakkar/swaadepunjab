'use client';

/** Full-screen brand loader shown while the menu loads (logo + gentle animation). */
export default function SplashLoader({ hiding }: { hiding: boolean }) {
  return (
    <div className={`splash${hiding ? ' hide' : ''}`} role="status" aria-live="polite" aria-label="Loading menu">
      <div className="splash-inner">
        <div className="splash-mark">
          <div className="splash-glow" aria-hidden="true" />
          <div className="splash-ring" aria-hidden="true" />
          <picture>
            <source srcSet="/brand/logo.webp" type="image/webp" />
            <img src="/brand/logo.png" alt="Swaad E Punjab" className="splash-logo" width={547} height={240} />
          </picture>
        </div>
        <div className="splash-dots" aria-hidden="true"><i /><i /><i /></div>
        <p className="splash-text">Garma-garam menu aa raha hai…</p>
      </div>
    </div>
  );
}
