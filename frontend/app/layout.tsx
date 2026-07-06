import type { Metadata } from "next";
import "./globals.css";
import { CartProvider } from "./context/CartContext";

export const metadata: Metadata = {
  title: "Swaad E Punjab — Authentic Punjabi Food Delivery",
  description: "Order authentic Punjabi food — Dal Makhani, Paneer Butter Masala, Sarson da Saag and more. Fast delivery, pure desi flavours!",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <CartProvider>
          {children}
        </CartProvider>
      </body>
    </html>
  );
}
