'use client';

import { useEffect, useRef } from 'react';

/**
 * Background video for a promo banner.
 * Browsers only autoplay muted video, so it always starts muted; the 🔊 button turns sound on.
 * Only the banner on screen plays — the others are paused so two sounds never overlap.
 */
export default function PromoVideo({
  src,
  poster,
  active,
  hasSound,
  soundOn,
  onToggleSound,
  onEnded,
  loop = false,
}: {
  src: string;
  poster?: string | null;
  active: boolean;
  hasSound: boolean;
  soundOn: boolean;
  onToggleSound: () => void;
  onEnded?: () => void;
  /** true when there is only one banner (nothing to move on to) */
  loop?: boolean;
}) {
  const ref = useRef<HTMLVideoElement>(null);
  const audible = active && hasSound && soundOn;

  useEffect(() => {
    const v = ref.current;
    if (!v) return;
    v.muted = !audible;
    if (active) {
      const p = v.play();
      if (p && typeof p.catch === 'function') {
        p.catch(() => {
          // Sound not allowed yet (no tap) → play muted instead
          v.muted = true;
          v.play().catch(() => {});
        });
      }
    } else {
      v.pause();
      try { v.currentTime = 0; } catch {}
    }
  }, [active, audible]);

  return (
    <>
      <video
        ref={ref}
        className="promo-video-bg"
        src={src}
        poster={poster || undefined}
        muted
        playsInline
        loop={loop}
        preload={active ? 'auto' : 'metadata'}
        onEnded={() => { if (active && onEnded) onEnded(); }}
        // A video that can't load must not block the slider
        onError={() => { if (active && onEnded) setTimeout(onEnded, 4000); }}
        aria-hidden="true"
      />
      {hasSound && active && (
        <button
          type="button"
          className={`promo-sound-btn${soundOn ? ' on' : ''}`}
          onClick={e => {
            e.stopPropagation();
            // Unmute inside the tap itself — phones only allow sound from a direct tap
            const v = ref.current;
            if (v) {
              v.muted = soundOn;
              if (!soundOn) v.play().catch(() => {});
            }
            onToggleSound();
          }}
          aria-pressed={soundOn}
          aria-label={soundOn ? 'Mute video' : 'Turn on video sound'}
        >
          <span aria-hidden="true">{soundOn ? '🔊' : '🔇'}</span>
          {!soundOn && <span className="promo-sound-hint">Tap for sound</span>}
        </button>
      )}
    </>
  );
}
