'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { MenuItem, ChosenOption, OptionGroup, cleanGroups, firstImageUrl, useCart } from '../context/CartContext';

const money = (n: number) => `₹${Math.round(n)}`;

/** Bottom sheet where the customer picks a dish's options (bread, sabji, add-ons...). */
export default function OptionSheet({
  item,
  onConfirm,
  onClose,
  confirmLabel = 'Add Item',
}: {
  item: MenuItem;
  onConfirm: (options: ChosenOption[], quantity: number) => void;
  onClose: () => void;
  confirmLabel?: string;
}) {
  const groups = useMemo(() => cleanGroups(item), [item]);
  const img = firstImageUrl(item.image);
  const [qty, setQty] = useState(1);
  const [tried, setTried] = useState(false);
  const sheetRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef(onClose);
  closeRef.current = onClose;

  // Start with the first available choice picked for groups where only one must be chosen
  const [picked, setPicked] = useState<Record<string, string[]>>(() => {
    const init: Record<string, string[]> = {};
    groups.forEach(g => {
      if (g.type !== 'multiple' && g.required) {
        const first = g.choices.find(c => c.available !== false);
        if (first) init[g.name] = [first.name];
      }
    });
    return init;
  });

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') closeRef.current(); };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    sheetRef.current?.focus();
    return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = prev; };
  }, []);

  const maxOf = (g: OptionGroup) => (g.type === 'multiple' ? (Number(g.max) > 0 ? Number(g.max) : Infinity) : 1);

  const toggle = (g: OptionGroup, choice: string) => {
    setPicked(p => {
      const cur = p[g.name] || [];
      if (g.type !== 'multiple') {
        // tapping the picked choice again clears it only when the group is optional
        if (cur[0] === choice && !g.required) return { ...p, [g.name]: [] };
        return { ...p, [g.name]: [choice] };
      }
      if (cur.includes(choice)) return { ...p, [g.name]: cur.filter(c => c !== choice) };
      if (cur.length >= maxOf(g)) return p;
      return { ...p, [g.name]: [...cur, choice] };
    });
  };

  const chosen: ChosenOption[] = [];
  groups.forEach(g => {
    g.choices.forEach(c => {
      if ((picked[g.name] || []).includes(c.name)) chosen.push({ group: g.name, choice: c.name, price: Number(c.price) || 0 });
    });
  });
  const missing = groups.filter(g => g.required && !(picked[g.name] || []).length);
  const unit = Number(item.price) + chosen.reduce((s, o) => s + o.price, 0);

  const submit = () => {
    if (missing.length) {
      setTried(true);
      document.getElementById(`og-${missing[0].name}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    onConfirm(chosen, qty);
  };

  const hint = (g: OptionGroup) => {
    if (g.type !== 'multiple') return g.required ? 'Select any 1' : 'Optional · select up to 1';
    const m = maxOf(g);
    const lim = m === Infinity ? 'Select any' : `Select up to ${m}`;
    return g.required ? `${lim} (at least 1)` : `Optional · ${lim.toLowerCase()}`;
  };

  return (
    <div className="os-backdrop" onClick={onClose} role="presentation">
      <div className="os-wrap" onClick={e => e.stopPropagation()}>
      <button type="button" className="os-close" aria-label="Close" onClick={onClose}>✕</button>
      <div
        className="os-sheet"
        role="dialog"
        aria-modal="true"
        aria-labelledby="os-title"
        tabIndex={-1}
        ref={sheetRef}
        onClick={e => e.stopPropagation()}
      >
        <div className="os-head">
          {img && <img src={img} alt="" className="os-thumb" />}
          <div style={{ minWidth: 0 }}>
            <h2 id="os-title" className="os-title">{item.name}</h2>
            <div className="os-sub">
              {money(Number(item.price))}
              {item.quantity_label ? ` · ${item.quantity_label}` : ''}
            </div>
          </div>
        </div>

        <div className="os-body">
          {groups.map(g => {
            const sel = picked[g.name] || [];
            const err = tried && g.required && !sel.length;
            const multi = g.type === 'multiple';
            return (
              <section key={g.name} id={`og-${g.name}`} className="os-group" aria-label={g.name}>
                <div className="os-group-head">
                  <div>
                    <div className="os-group-name">{g.name}</div>
                    <div className={`os-group-hint${err ? ' err' : ''}`}>{err ? 'Please choose one' : hint(g)}</div>
                  </div>
                  {g.required && <span className={`os-req${sel.length ? ' ok' : ''}`}>{sel.length ? '✓' : 'Required'}</span>}
                </div>
                <div className="os-choices" role={multi ? 'group' : 'radiogroup'}>
                  {g.choices.map(c => {
                    const off = c.available === false;
                    const on = sel.includes(c.name);
                    const full = multi && !on && sel.length >= maxOf(g);
                    const extra = Number(c.price) || 0;
                    return (
                      <button
                        key={c.name}
                        type="button"
                        role={multi ? 'checkbox' : 'radio'}
                        aria-checked={on}
                        disabled={off || full}
                        className={`os-choice${on ? ' on' : ''}`}
                        onClick={() => toggle(g, c.name)}
                      >
                        <span className="veg-mark" aria-hidden="true" />
                        <span className="os-choice-name">{c.name}</span>
                        <span className="os-choice-price">{off ? 'Not available' : extra > 0 ? `+ ${money(extra)}` : ''}</span>
                        <span className={`os-tick ${multi ? 'box' : 'dot'}`} aria-hidden="true" />
                      </button>
                    );
                  })}
                </div>
              </section>
            );
          })}
        </div>

        <div className="os-foot">
          <div className="os-qty" aria-label="Quantity">
            <button type="button" onClick={() => setQty(q => Math.max(1, q - 1))} aria-label="One less">−</button>
            <span aria-live="polite">{qty}</span>
            <button type="button" onClick={() => setQty(q => Math.min(20, q + 1))} aria-label="One more">+</button>
          </div>
          <button type="button" className={`os-add${missing.length ? ' dim' : ''}`} onClick={submit}>
            {confirmLabel} | {money(unit * qty)}
          </button>
        </div>
      </div>
      </div>
    </div>
  );
}

/** The sheet for whatever dish an ADD button asked about (opened from the cart's ADD action). */
export function CartOptionSheet() {
  const { state, dispatch } = useCart();
  const item = state.picker;
  if (!item) return null;
  return (
    <OptionSheet
      key={item.id}
      item={item}
      onClose={() => dispatch({ type: 'CLOSE_PICKER' })}
      onConfirm={(options, quantity) => dispatch({ type: 'ADD', item, options, quantity })}
    />
  );
}
