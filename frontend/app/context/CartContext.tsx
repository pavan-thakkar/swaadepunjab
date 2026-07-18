'use client';

import { createContext, useContext, useReducer, ReactNode, useState, useEffect } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export interface MenuItem {
  id: number;
  name: string;
  description: string;
  category: string;
  price: number;
  prep_time: number;
  rating: number;
  is_available: boolean;
  is_featured: boolean;
  image: string | null;
}

export interface CartItem {
  menu_item_id: number;
  name: string;
  price: number;
  quantity: number;
  category: string;
}

interface CartState {
  items: CartItem[];
  isOpen: boolean;
}

type CartAction =
  | { type: 'ADD'; item: MenuItem }
  | { type: 'REMOVE'; id: number }
  | { type: 'INCREMENT'; id: number }
  | { type: 'DECREMENT'; id: number }
  | { type: 'CLEAR' }
  | { type: 'TOGGLE_CART' }
  | { type: 'CLOSE_CART' }
  | { type: 'INIT_CART'; items: CartItem[] };

const initialState: CartState = { items: [], isOpen: false };

function cartReducer(state: CartState, action: CartAction): CartState {
  switch (action.type) {
    case 'ADD': {
      const existing = state.items.find(i => i.menu_item_id === action.item.id);
      if (existing) {
        return {
          ...state,
          items: state.items.map(i =>
            i.menu_item_id === action.item.id ? { ...i, quantity: i.quantity + 1 } : i
          ),
        };
      }
      return {
        ...state,
        items: [
          ...state.items,
          { menu_item_id: action.item.id, name: action.item.name, price: action.item.price, quantity: 1, category: action.item.category },
        ],
      };
    }
    case 'REMOVE':
      return { ...state, items: state.items.filter(i => i.menu_item_id !== action.id) };
    case 'INCREMENT':
      return {
        ...state,
        items: state.items.map(i => i.menu_item_id === action.id ? { ...i, quantity: i.quantity + 1 } : i),
      };
    case 'DECREMENT': {
      const item = state.items.find(i => i.menu_item_id === action.id);
      if (item && item.quantity <= 1) {
        return { ...state, items: state.items.filter(i => i.menu_item_id !== action.id) };
      }
      return {
        ...state,
        items: state.items.map(i => i.menu_item_id === action.id ? { ...i, quantity: i.quantity - 1 } : i),
      };
    }
    case 'CLEAR':
      return { ...state, items: [] };
    case 'TOGGLE_CART':
      return { ...state, isOpen: !state.isOpen };
    case 'CLOSE_CART':
      return { ...state, isOpen: false };
    case 'INIT_CART':
      return { ...state, items: action.items };
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
  loginUser: (phone: string | null, email: string | null, name: string) => void;
  logoutUser: () => void;
} | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(cartReducer, initialState);
  const [deliverySettings, setDeliverySettings] = useState<DeliveryTier[]>([]);
  const [userPhone, setUserPhone] = useState<string | null>(null);
  const [userEmail, setUserEmail] = useState<string | null>(null);
  const [userName, setUserName] = useState<string | null>(null);

  useEffect(() => {
    // Load auth and cart items from localStorage on mount
    if (typeof window !== 'undefined') {
      setUserPhone(localStorage.getItem('user_phone'));
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

  const loginUser = (phone: string | null, email: string | null, name: string) => {
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
    <CartContext.Provider value={{ state, dispatch, total, itemCount, deliveryFee, freeDeliveryThreshold, userPhone, userEmail, userName, loginUser, logoutUser }}>
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart must be used inside CartProvider');
  return ctx;
}
