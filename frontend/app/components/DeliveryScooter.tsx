/** The delivery scooter drawing used on the Cart button (shared so status pills match). */
// Delivery Scooter SVG Icon
export default function DeliveryScooter({ size = 26, color = '#1a1a2e' }: { size?: number; color?: string }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 80 56"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      style={{ display: 'block', flexShrink: 0 }}
    >
      <circle cx="16" cy="42" r="9" stroke={color} strokeWidth="3" fill="none"/>
      <circle cx="16" cy="42" r="3" fill={color}/>
      <circle cx="64" cy="42" r="9" stroke={color} strokeWidth="3" fill="none"/>
      <circle cx="64" cy="42" r="3" fill={color}/>
      <line x1="25" y1="42" x2="55" y2="42" stroke={color} strokeWidth="3" strokeLinecap="round"/>
      <path d="M16 42 Q16 28 28 26 L44 24 L48 34 L20 36 Z" fill={color} opacity="0.85"/>
      <path d="M44 24 L56 20 L62 28 L56 36 L48 34 Z" fill={color}/>
      <rect x="28" y="20" width="18" height="6" rx="3" fill={color}/>
      <line x1="56" y1="20" x2="60" y2="12" stroke={color} strokeWidth="3" strokeLinecap="round"/>
      <line x1="56" y1="12" x2="66" y2="12" stroke={color} strokeWidth="3" strokeLinecap="round"/>
      <rect x="8" y="14" width="18" height="14" rx="3" fill="#E63946"/>
      <rect x="8" y="14" width="18" height="4" rx="3" fill="#c0392b"/>
      <text x="13" y="25" fontSize="8" fill="white" fontWeight="bold" fontFamily="Arial, sans-serif">S</text>
      <circle cx="6" cy="40" r="2" fill={color} opacity="0.25"/>
    </svg>
  );
}
