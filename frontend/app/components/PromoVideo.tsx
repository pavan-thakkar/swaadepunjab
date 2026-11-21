'use client';

import { useEffect, useRef, useState } from 'react';

/**
 * Background video for a promo banner.
 * Browsers only autoplay muted video, so it always starts muted; the 🔊 button turns sound on.
 * Only the banner on screen plays — the others are paused so two sounds never overlap.
 * Sound is heard only while the banner is actually visible: scrolling it off screen (or switching tab) mutes it,
 * scrolling back brings the sound back (if the customer had turned it on).
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
  // Is the banner on screen? (at least ~40% of it visible, and the browser tab is in front)
  const [inView, setInView] = useState(true);
  const [tabVisible, setTabVisible] = useState(true);
  useEffect(() => {
    const box = ref.current?.parentElement;
    if (!box || typeof IntersectionObserver === 'undefined') return;
    const io = new IntersectionObserver(([e]) => setInView(e.isIntersecting && e.intersectionRatio >= 0.4), { threshold: [0, 0.4, 0.6, 1] });
    io.observe(box);
    return () => io.disconnect();
  }, []);
  useEffect(() => {
    const onVis = () => setTabVisible(document.visibilityState === 'visible');
    onVis();
    document.addEventListener('visibilitychange', onVis);
    return () => document.removeEventListener('visibilitychange', onVis);
  }, []);
  const audible = active && hasSound && soundOn && inView && tabVisible;
  // A tall / square video in a wide banner would be cropped badly → show it whole, with a soft blurred copy behind
  const [fitWhole, setFitWhole] = useState(false);
  const checkFit = () => {
    const v = ref.current;
    const box = v?.parentElement;
    if (!v || !box || !v.videoWidth || !box.clientHeight) return;
    const videoRatio = v.videoWidth / v.videoHeight;
    const boxRatio = box.clientWidth / box.clientHeight;
    setFitWhole(videoRatio < boxRatio * 0.6);
  };
  useEffect(() => {
    window.addEventListener('resize', checkFit);
    return () => window.removeEventListener('resize', checkFit);
  });

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
      {fitWhole && (
        <video className="promo-video-blur" src={src} muted playsInline loop autoPlay={active} preload="metadata" aria-hidden="true" />
      )}
      <video
        ref={ref}
        className={`promo-video-bg${fitWhole ? ' fit-whole' : ''}`}
        onLoadedMetadata={checkFit}
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
