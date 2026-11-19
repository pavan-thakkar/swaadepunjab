import type { Metadata } from "next";
import "./globals.css";
import { CartProvider } from "./context/CartContext";
import BottomNav from './components/BottomNav';

const SITE_DESCRIPTION = "Order authentic Punjabi food — Dal Makhani, Paneer Butter Masala, Sarson da Saag and more. Fast delivery, pure desi flavours!";

export const metadata: Metadata = {
  metadataBase: new URL("https://swaadepunjab.com"),
  title: "Swaad E Punjab — Authentic Punjabi Food Delivery",
  description: SITE_DESCRIPTION,
  applicationName: "Swaad E Punjab",
  manifest: "/manifest.webmanifest",
  // favicon.ico, icon.png and apple-icon.png in this folder are picked up automatically
  openGraph: {
    type: "website",
    siteName: "Swaad E Punjab",
    title: "Swaad E Punjab — Authentic Punjabi Food Delivery",
    description: SITE_DESCRIPTION,
    url: "https://swaadepunjab.com",
    images: [{ url: "/brand/og-image.jpg", width: 1200, height: 630, alt: "Swaad E Punjab" }],
  },
  twitter: {
    card: "summary_large_image",
    title: "Swaad E Punjab — Authentic Punjabi Food Delivery",
    description: SITE_DESCRIPTION,
    images: ["/brand/og-image.jpg"],
  },
};

export const viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#FFFDF5",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <CartProvider>
          {children}
          <BottomNav />
        </CartProvider>
      </body>
    </html>
  );
}
