'use client';

import { useEffect, useState } from 'react';
import Navbar from '../components/Navbar';
import CartDrawer from '../components/CartDrawer';
import OrderRating from '../components/OrderRating';

/** Rating link sent after delivery: /rate/?o=ORDER_NUMBER&t=SECRET (works on any phone, no login). */
export default function RatePage() {
  const [params, setParams] = useState<{ o: string; t: string } | null>(null);

  useEffect(() => {
    const q = new URLSearchParams(window.location.search);
    setParams({ o: q.get('o') || '', t: q.get('t') || '' });
  }, []);

  return (
    <>
      <Navbar />
      <CartDrawer />
      <main className="ordrate-page">
        {params && <OrderRating orderNumber={params.o} token={params.t} />}
      </main>
    </>
  );
}
