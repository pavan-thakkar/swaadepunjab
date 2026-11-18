'use client';

import { useEffect, useMemo, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

type DraftItem = { menu_item_id: number; name: string; price: number; quantity: number };

function getToken(orderNumber: string): string | null {
  try { return localStorage.getItem(`order_edit_${orderNumber}`); } catch { return null; }
}

export default function EditOrderPanel({ order, onUpdated }: { order: any; onUpdated: (o: any) => void }) {
  const [token, setToken] = useState<string | null>(null);
  const [now, setNow] = useState(Date.now());
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<DraftItem[]>([]);
  const [note, setNote] = useState('');
  const [menu, setMenu] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  useEffect(() => { setToken(getToken(order.order_number)); }, [order.order_number]);
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);

  const deadline = order.editable_until ? new Date(order.editable_until).getTime() : 0;
  const secondsLeft = Math.max(0, Math.floor((deadline - now) / 1000));
  const paidOnline = order.payment_method && order.payment_method !== 'cash_on_delivery';
  const canEdit = !!token && order.status === 'pending' && order.can_edit !== false && secondsLeft > 0;

  // Clean up the token once the window is over
  useEffect(() => {
    if (token && deadline && secondsLeft === 0) {
      try { localStorage.removeItem(`order_edit_${order.order_number}`); } catch {}
    }
  }, [secondsLeft, token, deadline, order.order_number]);

  const startEditing = async () => {
    setItems((order.items || []).map((i: any) => ({
      menu_item_id: i.menu_item_id, name: i.name, price: Number(i.price), quantity: Number(i.quantity),
    })));
    setNote(order.special_instructions || '');
    setMsg(null);
    setOpen(true);
    if (!paidOnline && menu.length === 0) {
      try {
        const res = await fetch(`${API}/menu`);
        const json = await res.json();
        setMenu(Array.isArray(json.data) ? json.data : []);
      } catch {}
    }
  };

  const changeQty = (id: number, delta: number) => {
    setItems(prev => prev
      .map(i => i.menu_item_id === id ? { ...i, quantity: Math.min(20, i.quantity + delta) } : i)
      .filter(i => i.quantity > 0));
  };

  const addItem = (m: any) => {
    setItems(prev => {
      const found = prev.find(i => i.menu_item_id === m.id);
      if (found) return prev.map(i => i.menu_item_id === m.id ? { ...i, quantity: Math.min(20, i.quantity + 1) } : i);
      return [...prev, { menu_item_id: m.id, name: m.name, price: Number(m.price), quantity: 1 }];
    });
    setSearch('');
  };

  const subtotal = items.reduce((s, i) => s + i.price * i.quantity, 0);

  const results = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return [];
    return menu.filter(m => m.name?.toLowerCase().includes(q)).slice(0, 8);
  }, [search, menu]);

  const save = async () => {
    if (!paidOnline && items.length === 0) {
      setMsg({ ok: false, text: 'Your order needs at least one item.' });
      return;
    }
    setSaving(true);
    setMsg(null);
    try {
      const body: any = { edit_token: token, special_instructions: note.trim() || null };
      if (!paidOnline) body.items = items.map(i => ({ menu_item_id: i.menu_item_id, quantity: i.quantity }));
      const res = await fetch(`${API}/orders/${order.order_number}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });
      const json = await res.json();
      if (!res.ok) {
        setMsg({ ok: false, text: json.message || 'Could not update the order.' });
        return;
      }
      onUpdated(json.data);
      setOpen(false);
      setMsg({ ok: true, text: '✅ Your order has been updated!' });
    } catch {
      setMsg({ ok: false, text: 'Network error. Please try again.' });
    } finally {
      setSaving(false);
    }
  };

  if (!canEdit) {
    return msg?.ok ? (
      <div className="tracking-card" style={{ marginBottom: '1.5rem', color: 'var(--success)', fontWeight: 700 }}>{msg.text}</div>
    ) : null;
  }

  const mm = Math.floor(secondsLeft / 60);
  const ss = String(secondsLeft % 60).padStart(2, '0');

  const qtyBtn: React.CSSProperties = {
    width: 32, height: 32, borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg-card)',
    fontSize: '1.1rem', fontWeight: 800, cursor: 'pointer', color: 'var(--text)',
  };

  return (
    <div className="tracking-card" style={{ marginBottom: '1.5rem', border: '2px solid var(--primary)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <div style={{ fontWeight: 800 }}>✏️ Need to change something?</div>
          <div style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
            You can change your order for <strong style={{ color: 'var(--primary-dark)' }}>{mm}:{ss}</strong> more
          </div>
        </div>
        {!open && (
          <button className="btn-primary" onClick={startEditing} style={{ padding: '10px 18px', justifyContent: 'center' }}>
            Change Order
          </button>
        )}
      </div>

      {msg && !msg.ok && !open && (
        <div style={{ color: 'var(--danger)', fontSize: '0.85rem', fontWeight: 600, marginTop: 10 }}>⚠️ {msg.text}</div>
      )}

      {open && (
        <div style={{ marginTop: '1.25rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {paidOnline ? (
            <div style={{ fontSize: '0.85rem', background: 'var(--bg-elevated)', padding: '10px 12px', borderRadius: 8, color: 'var(--text-muted)' }}>
              💳 This order is already paid online, so items can&apos;t be changed here. You can still update your special instructions.
            </div>
          ) : (
            <>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {items.map(i => (
                  <div key={i.menu_item_id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', background: 'var(--bg-elevated)', borderRadius: 8 }}>
                    <span style={{ flex: 1, fontWeight: 600, fontSize: '0.9rem' }}>{i.name}</span>
                    <button type="button" style={qtyBtn} onClick={() => changeQty(i.menu_item_id, -1)} aria-label={`Remove one ${i.name}`}>−</button>
                    <span style={{ minWidth: 20, textAlign: 'center', fontWeight: 800 }}>{i.quantity}</span>
                    <button type="button" style={qtyBtn} onClick={() => changeQty(i.menu_item_id, 1)} aria-label={`Add one ${i.name}`}>+</button>
                    <span style={{ minWidth: 70, textAlign: 'right', fontSize: '0.85rem' }}>₹{(i.price * i.quantity).toFixed(2)}</span>
                    <button type="button" onClick={() => changeQty(i.menu_item_id, -99)} style={{ background: 'none', border: 'none', color: 'var(--danger)', cursor: 'pointer', fontSize: '1rem' }} aria-label={`Remove ${i.name}`}>🗑️</button>
                  </div>
                ))}
                {items.length === 0 && <div style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>No items — add at least one below.</div>}
              </div>

              <div style={{ position: 'relative' }}>
                <label style={{ display: 'block', fontSize: '0.82rem', fontWeight: 700, marginBottom: 4 }}>➕ Add more items</label>
                <input
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  placeholder="Search the menu, e.g. paneer, naan…"
                  style={{ width: '100%', padding: '10px 12px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg-card)', color: 'var(--text)', fontSize: '0.9rem' }}
                />
                {results.length > 0 && (
                  <div style={{ marginTop: 6, border: '1px solid var(--border)', borderRadius: 8, overflow: 'hidden', background: 'var(--bg-card)' }}>
                    {results.map(m => (
                      <button key={m.id} type="button" onClick={() => addItem(m)}
                        style={{ display: 'flex', width: '100%', justifyContent: 'space-between', padding: '10px 12px', background: 'none', border: 'none', borderBottom: '1px solid var(--border)', cursor: 'pointer', color: 'var(--text)', fontSize: '0.88rem', textAlign: 'left' }}>
                        <span>{m.name}</span><span style={{ fontWeight: 700 }}>₹{Number(m.price).toFixed(0)} +</span>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <div style={{ textAlign: 'right', fontWeight: 800 }}>
                New items total: ₹{subtotal.toFixed(2)}
                <div style={{ fontWeight: 400, fontSize: '0.78rem', color: 'var(--text-muted)' }}>Delivery fee is recalculated when you save.</div>
              </div>
            </>
          )}

          <div>
            <label style={{ display: 'block', fontSize: '0.82rem', fontWeight: 700, marginBottom: 4 }}>📝 Special Instructions</label>
            <textarea
              value={note}
              onChange={e => setNote(e.target.value.slice(0, 500))}
              rows={3}
              placeholder="e.g. Less spicy, no onion, extra butter…"
              style={{ width: '100%', padding: '10px 12px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg-card)', color: 'var(--text)', fontSize: '0.9rem', fontFamily: 'inherit', resize: 'vertical' }}
            />
          </div>

          {msg && !msg.ok && <div style={{ color: 'var(--danger)', fontSize: '0.85rem', fontWeight: 600 }}>⚠️ {msg.text}</div>}

          <div style={{ display: 'flex', gap: 12 }}>
            <button type="button" className="btn-secondary" onClick={() => setOpen(false)} style={{ flex: 1, justifyContent: 'center', padding: '10px' }}>
              Cancel
            </button>
            <button type="button" className="btn-primary" onClick={save} disabled={saving} style={{ flex: 1, justifyContent: 'center', padding: '10px', opacity: saving ? 0.6 : 1 }}>
              {saving ? 'Saving…' : 'Save Changes'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
