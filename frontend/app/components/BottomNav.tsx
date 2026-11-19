'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

/** Mobile bottom menu (hidden on desktop and on checkout / dish / tracking pages). */
export default function BottomNav() {
  const pathname = usePathname() || '/';
  const show = pathname === '/' || pathname.startsWith('/history') || pathname.startsWith('/login');
  if (!show) return null;

  const items = [
    { href: '/', label: 'Home', active: pathname === '/', icon: <path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z" /> },
    { href: '/#offers', label: 'Offers', active: false, icon: <><path d="M20 12l-8 8-9-9V3h8z" /><circle cx="7.5" cy="7.5" r="1.5" /></> },
    { href: '/history/', label: 'Orders', active: pathname.startsWith('/history'), icon: <><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z" /><path d="M9 8h6M9 12h6" /></> },
    { href: '/history/#points', label: 'Points', active: false, icon: <path d="M12 2l3 6 6.5 1-4.7 4.6 1.1 6.4L12 17l-5.9 3 1.1-6.4L2.5 9 9 8z" /> },
  ];

  return (
    <nav className="bottom-nav" aria-label="Main">
      {items.map(it => (
        <Link key={it.label} href={it.href} className={`bottom-nav-item${it.active ? ' active' : ''}`} aria-current={it.active ? 'page' : undefined}>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{it.icon}</svg>
          <span>{it.label}</span>
        </Link>
      ))}
    </nav>
  );
}
