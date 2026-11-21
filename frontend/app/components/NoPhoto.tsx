'use client';

import type { SyntheticEvent } from 'react';

/** Our logo, used wherever a dish/category has no photo uploaded. */
export const LOGO_SRC = '/brand/logo.png';

/** Placeholder box with the logo (fills its parent). */
export default function NoPhoto({ className = '', small = false }: { className?: string; small?: boolean }) {
  return (
    <div className={`no-photo${small ? ' small' : ''}${className ? ' ' + className : ''}`} aria-hidden="true">
      <img src={LOGO_SRC} alt="" loading="lazy" />
    </div>
  );
}

/** onError for <img>: a photo that fails to load is swapped for the logo (once). */
export function logoOnError(e: SyntheticEvent<HTMLImageElement>) {
  const img = e.currentTarget;
  if (img.dataset.fallback) return;
  img.dataset.fallback = '1';
  img.src = LOGO_SRC;
  img.classList.add('img-logo-fallback');
}
