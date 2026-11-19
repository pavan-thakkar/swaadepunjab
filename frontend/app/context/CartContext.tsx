'use client';

import { createContext, useContext, useReducer, ReactNode, useState, useEffect } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export interface OptionChoice {
  name: string;
  price: number | string;
  available?: boolean;
}

export interface OptionGroup {
  name: string;
  type?: 'single' | 'multiple';
  required?: boolean;
  max?: number | string | null;
  choices: OptionChoice[];
}

export interface ChosenOption {
  group: string;
  choice: string;
  price: number;
}

export interface MenuItem {
  id: number;
  name: string;
  slug?: string; // pretty web name, e.g. "paneer-tikka"
  description: string;
  category: string;
  price: number;
  prep_time: number;
  quantity_label?: string | null;
  option_groups?: OptionGroup[] | null;
  rating: number;
  is_available: boolean;
  is_featured: boolean;
  image: string | null;
}

export interface CartItem {
  key: string;            // menu_item_id + chosen options
  menu_item_id: number;
  name: string;
  price: number;          // dish price + extra for options
  quantity: number;
  category: string;
  image?: string | null;
  quantity_label?: string | null;
  options?: ChosenOption[];
  option_label?: string;  // "Butter Naan, Extra butter"
}

/** Admin-set option groups with empty names/choices removed. */
export function cleanGroups(item: Pick<MenuItem, 'option_groups'> | null | undefined): OptionGroup[] {
  const raw = item?.option_groups;
  if (!Array.isArray(raw)) return [];
  return raw
    .map(g => ({
      ...g,
      name: String(g?.name || '').trim(),
      type: (g?.type === 'multiple' ? 'multiple' : 'single') as 'single' | 'multiple',
      required: !!g?.required,
      choices: (Array.isArray(g?.choices) ? g.choices : []).filter(c => String(c?.name || '').trim() !== ''),
    }))
    .filter(g => g.name && g.choices.length > 0);
}

export function hasOptions(item: Pick<MenuItem, 'option_groups'> | null | undefined): boolean {
  return cleanGroups(item).length > 0;
}

export function lineKey(menuItemId: number, options?: { group: string; choice: string }[]): string {
  const parts = (options || []).map(o => `${o.group}=${o.choice}`).sort();
  return `${menuItemId}|${parts.join(';')}`;
}

/** Total quantity of a dish in the cart, across all its option lines. */
export function qtyInCart(items: CartItem[], menuItemId: number): number {
  return items.filter(i => i.menu_item_id === menuItemId).reduce((s, i) => s + i.quantity, 0);
}

// First photo of a menu item as a full URL (image may be a path, URL or JSON list)
export function firstImageUrl(imageField: any): string | null {
  let list: string[] = [];
  if (Array.isArray(imageField)) list = imageField.filter(Boolean);
  else if (typeof imageField === 'string' && imageField) {
    try { const d = JSON.parse(imageField); list = Array.isArray(d) ? d.filter(Boolean) : [imageField]; } catch { list = [imageField]; }
  }
  const img = list[0];
  if (!img) return null;
  return img.startsWith('http') ? img : `${API.replace(/\/api\/?$/, '')}/storage/${img.replace(/^\/+/, '')}`;
}

interface CartState {
  items: CartItem[];
  isOpen: boolean;
  picker: MenuItem | null; // dish whose options sheet is open
}

type CartAction =
  // Dishes with options open the options sheet unless `options` is given
  | { type: 'ADD'; item: MenuItem; options?: ChosenOption[]; quantity?: number }
  // id: line key (string) or dish id (number → last line of that dish)
  | { type: 'REMOVE'; id: number | string }
  | { type: 'INCREMENT'; id: number | string; item?: MenuItem }
  | { type: 'DECREMENT'; id: number | string }
  | { type: 'OPEN_PICKER'; item: MenuItem }
  | { type: 'CLOSE_PICKER' }
  | { type: 'CLEAR' }
  | { type: 'TOGGLE_CART' }
  | { type: 'CLOSE_CART' }
  | { type: 'INIT_CART'; items: CartItem[] };

const initialState: CartState = { items: [], isOpen: false, picker: null };

function findLine(items: CartItem[], id: number | string): CartItem | undefined {
  if (typeof id === 'string') return items.find(i => i.key === id);
  const lines = items.filter(i => i.menu_item_id === id);
  return lines[lines.length - 1];
}

function cartReducer(state: CartState, action: CartAction): CartState {
  switch (action.type) {
    case 'ADD': {
      if (!action.options && hasOptions(action.item)) {
        return { ...state, picker: action.item };
      }
      const options = action.options || [];
      const key = lineKey(action.item.id, options);
      const qty = Math.max(1, action.quantity || 1);
      const picker = null;
      const existing = state.items.find(i => i.key === key);
      if (existing) {
        return {
          ...state,
          picker,
          items: state.items.map(i => (i.key === key ? { ...i, quantity: i.quantity + qty } : i)),
        };
      }
      const extra = options.reduce((s, o) => s + (Number(o.price) || 0), 0);
      return {
        ...state,
        picker,
        items: [
          ...state.items,
          {
            key,
            menu_item_id: action.item.id,
            name: action.item.name,
            price: Number(action.item.price) + extra,
            quantity: qty,
            category: action.item.category,
            image: firstImageUrl(action.item.image),
            quantity_label: action.item.quantity_label || null,
            options: options.length ? options : undefined,
            option_label: options.length ? options.map(o => o.choice).join(', ') : undefined,
          },
        ],
      };
    }
    case 'REMOVE': {
      const line = findLine(state.items, action.id);
      return line ? { ...state, items: state.items.filter(i => i !== line) } : state;
    }
    case 'INCREMENT': {
      // "+" on a menu card for a dish with options → let the customer choose again
      if (typeof action.id === 'number' && action.item && hasOptions(action.item)) {
        return { ...state, picker: action.item };
      }
      const line = findLine(state.items, action.id);
      if (!line) return state;
      return { ...state, items: state.items.map(i => (i === line ? { ...i, quantity: i.quantity + 1 } : i)) };
    }
    case 'DECREMENT': {
      const line = findLine(state.items, action.id);
      if (!line) return state;
      if (line.quantity <= 1) return { ...state, items: state.items.filter(i => i !== line) };
      return { ...state, items: state.items.map(i => (i === line ? { ...i, quantity: i.quantity - 1 } : i)) };
    }
    case 'OPEN_PICKER':
      return { ...state, picker: action.item };
    case 'CLOSE_PICKER':
      return { ...state, picker: null };
    case 'CLEAR':
      return { ...state, items: [] };
    case 'TOGGLE_CART':
      return { ...state, isOpen: !state.isOpen };
    case 'CLOSE_CART':
      return { ...state, isOpen: false };
    case 'INIT_CART':
      // Older saved carts have no key: one line per dish
      return {
        ...state,
        items: action.items
          .filter(i => i && i.menu_item_id)
          .map(i => ({ ...i, price: Number(i.price), key: i.key || lineKey(i.menu_item_id, i.options) })),
      };
    default:
      return state;
  }
}

interface DeliveryTier {
  min_order_amount: string;
  max_order_amount: string | null;
  delivery_fee: string;
}

const CartContext = createContext<{
  state: CartState;
  dispatch: React.Dispatch<CartAction>;
  total: number;
  itemCount: number;
  deliveryFee: number;
  freeDeliveryThreshold: number;
  userPhone: string | null;
  userEmail: string | null;
  userName: string | null;
  customerToken: string | null;
  loginUser: (phone: string | null, email: string | null, name: string, token?: string | null) => void;
  logoutUser: () => void;
} | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(cartReducer, initialState);
  const [deliverySettings, setDeliverySettings] = useState<DeliveryTier[]>([]);
  const [userPhone, setUserPhone] = useState<string | null>(null);
  const [userEmail, setUserEmail] = useState<string | null>(null);
  const [userName, setUserName] = useState<string | null>(null);
  const [customerToken, setCustomerToken] = useState<string | null>(null);

  useEffect(() => {
    // Load auth and cart items from localStorage on mount
    if (typeof window !== 'undefined') {
      setUserPhone(localStorage.getItem('user_phone'));
      setCustomerToken(localStorage.getItem('customer_token'));
      setUserEmail(localStorage.getItem('user_email'));
      setUserName(localStorage.getItem('user_name'));
      const savedCart = localStorage.getItem('swaad_cart_items');
      if (savedCart) {
        try {
          const parsed = JSON.parse(savedCart);
          if (Array.isArray(parsed) && parsed.length > 0) {
            dispatch({ type: 'INIT_CART', items: parsed });
          }
        } catch (e) {
          console.warn("Failed to parse cart items from localStorage", e);
        }
      }
    }
  }, []);

  // Save cart to localStorage on change
  useEffect(() => {
    if (typeof window !== 'undefined') {
      localStorage.setItem('swaad_cart_items', JSON.stringify(state.items));
    }
  }, [state.items]);

  const loginUser = (phone: string | null, email: string | null, name: string, token?: string | null) => {
    if (token) {
      localStorage.setItem('customer_token', token);
      setCustomerToken(token);
    }
    if (phone) {
      localStorage.setItem('user_phone', phone);
      setUserPhone(phone);
    } else {
      localStorage.removeItem('user_phone');
      setUserPhone(null);
    }
    if (email) {
      localStorage.setItem('user_email', email);
      setUserEmail(email);
    } else {
      localStorage.removeItem('user_email');
      setUserEmail(null);
    }
    localStorage.setItem('user_name', name);
    setUserName(name);
  };

  const logoutUser = () => {
    localStorage.removeItem('user_phone');
    localStorage.removeItem('user_email');
    localStorage.removeItem('user_name');
    localStorage.removeItem('customer_token');
    setCustomerToken(null);
    setUserPhone(null);
    setUserEmail(null);
    setUserName(null);
  };

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        const res = await fetch(`${API}/delivery-settings`);
        if (res.ok) {
          const data = await res.json();
          if (Array.isArray(data.data)) {
            setDeliverySettings(data.data);
          }
        }
      } catch (err) {
        console.warn('Failed to load delivery settings:', err);
      }
    };
    fetchSettings();
  }, []);

  const total = state.items.reduce((sum, i) => sum + i.price * i.quantity, 0);
  const itemCount = state.items.reduce((sum, i) => sum + i.quantity, 0);

  // Calculate delivery fee
  const getDeliveryFee = (): number => {
    if (state.items.length === 0) return 0;
    
    if (deliverySettings.length > 0) {
      const match = deliverySettings.find(tier => {
        const min = parseFloat(tier.min_order_amount);
        const max = tier.max_order_amount ? parseFloat(tier.max_order_amount) : null;
        return total >= min && (max === null || total <= max);
      });
      if (match) {
        return parseFloat(match.delivery_fee);
      }
    }

    // Default fallback:
    if (total < 500) return 49.00;
    if (total < 1000) return 29.00;
    return 0.00;
  };

  const deliveryFee = getDeliveryFee();

  const getFreeDeliveryThreshold = (): number => {
    if (deliverySettings.length > 0) {
      const freeTiers = deliverySettings.filter(t => parseFloat(t.delivery_fee) === 0);
      if (freeTiers.length > 0) {
        return Math.min(...freeTiers.map(t => parseFloat(t.min_order_amount)));
      }
    }
    return 1000; // default fallback
  };

  const freeDeliveryThreshold = getFreeDeliveryThreshold();

  return (
    <CartContext.Provider value={{ state, dispatch, total, itemCount, deliveryFee, freeDeliveryThreshold, userPhone, userEmail, userName, customerToken, loginUser, logoutUser }}>
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart must be used inside CartProvider');
  return ctx;
}
