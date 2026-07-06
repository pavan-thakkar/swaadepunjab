"use client";

import { useState, useEffect, useCallback } from "react";
import styles from "./admin.module.css";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api";

type OrderStatus =
  | "pending"
  | "accepted"
  | "preparing"
  | "out_for_delivery"
  | "delivered"
  | "cancelled";

interface OrderItem {
  id: number;
  name: string;
  price: string;
  quantity: number;
  subtotal: string;
}

interface Order {
  id: number;
  order_number: string;
  customer_name: string;
  customer_email: string | null;
  customer_phone: string;
  delivery_address: string;
  city: string;
  subtotal: string;
  delivery_fee: string;
  total: string;
  status: OrderStatus;
  payment_method: string;
  special_instructions: string | null;
  estimated_delivery_at: string | null;
  created_at: string;
  items: OrderItem[];
  order_type?: string;
  pickup_time?: string | null;
}

interface MenuItem {
  id: number;
  name: string;
  description: string;
  category: string;
  price: string;
  rating: string;
  is_available: boolean;
  is_featured: boolean;
  prep_time: number;
}

const STATUS_CONFIG: Record<
  OrderStatus,
  { label: string; color: string; next?: OrderStatus }
> = {
  pending: { label: "Pending", color: "#f59e0b", next: "accepted" },
  accepted: { label: "Accepted", color: "#3b82f6", next: "preparing" },
  preparing: { label: "Preparing", color: "#8b5cf6", next: "out_for_delivery" },
  out_for_delivery: {
    label: "Out for Delivery",
    color: "#f97316",
    next: "delivered",
  },
  delivered: { label: "Delivered", color: "#10b981", next: undefined },
  cancelled: { label: "Cancelled", color: "#ef4444", next: undefined },
};

function formatCurrency(amount: string | number) {
  return `₹${parseFloat(String(amount)).toFixed(2)}`;
}

function timeAgo(dateStr: string) {
  const now = new Date();
  const date = new Date(dateStr);
  const diff = Math.floor((now.getTime() - date.getTime()) / 1000);
  if (diff < 60) return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

export default function AdminPanel() {
  const [activeTab, setActiveTab] = useState<"orders" | "menu">("orders");
  const [orders, setOrders] = useState<Order[]>([]);
  const [menuItems, setMenuItems] = useState<MenuItem[]>([]);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [updatingStatus, setUpdatingStatus] = useState<number | null>(null);
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date());
  const [searchQuery, setSearchQuery] = useState("");
  const [dateFilter, setDateFilter] = useState<string>("");
  const [isMounted, setIsMounted] = useState(false);
  const [isDarkMode, setIsDarkMode] = useState(true);

  useEffect(() => {
    setIsMounted(true);
    if (typeof window !== 'undefined') {
      const savedTheme = localStorage.getItem('admin_theme');
      if (savedTheme === 'light') {
        setIsDarkMode(false);
      }
    }
  }, []);

  const toggleTheme = () => {
    setIsDarkMode(prev => {
      const next = !prev;
      localStorage.setItem('admin_theme', next ? 'dark' : 'light');
      return next;
    });
  };

  const fetchOrders = useCallback(async () => {
    try {
      const url =
        statusFilter === "all"
          ? `${API_URL}/orders`
          : `${API_URL}/orders?status=${statusFilter}`;
      const res = await fetch(url);
      const json = await res.json();
      const data = json.data?.data ?? json.data ?? [];
      setOrders(Array.isArray(data) ? data : []);
      setLastRefresh(new Date());
    } catch (err) {
      console.error("Failed to fetch orders:", err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  const fetchMenu = useCallback(async () => {
    try {
      const res = await fetch(`${API_URL}/menu`);
      const json = await res.json();
      setMenuItems(json.data ?? []);
    } catch (err) {
      console.error("Failed to fetch menu:", err);
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchOrders();
  }, [fetchOrders]);

  useEffect(() => {
    fetchMenu();
  }, [fetchMenu]);

  // Auto-refresh orders every 30 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      fetchOrders();
    }, 30000);
    return () => clearInterval(interval);
  }, [fetchOrders]);

  const updateOrderStatus = async (orderId: number, newStatus: OrderStatus) => {
    setUpdatingStatus(orderId);
    try {
      const res = await fetch(`${API_URL}/orders/${orderId}/status`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status: newStatus }),
      });
      if (res.ok) {
        await fetchOrders();
        if (selectedOrder?.id === orderId) {
          const updated = orders.find((o) => o.id === orderId);
          if (updated) setSelectedOrder({ ...updated, status: newStatus });
        }
      }
    } catch (err) {
      console.error("Failed to update status:", err);
    } finally {
      setUpdatingStatus(null);
    }
  };

  const stats = {
    total: orders.length,
    pending: orders.filter((o) => o.status === "pending").length,
    preparing: orders.filter(
      (o) => o.status === "accepted" || o.status === "preparing"
    ).length,
    delivered: orders.filter((o) => o.status === "delivered").length,
    revenue: orders
      .filter((o) => o.status !== "cancelled")
      .reduce((sum, o) => sum + parseFloat(o.total), 0),
  };

  const filteredOrders = orders.filter((o) => {
    const matchesSearch = !searchQuery ? true : (() => {
      const q = searchQuery.toLowerCase();
      return (
        o.order_number.toLowerCase().includes(q) ||
        o.customer_name.toLowerCase().includes(q) ||
        o.customer_phone.includes(q)
      );
    })();

    const matchesDate = !dateFilter ? true : (() => {
      const orderDate = new Date(o.created_at).toISOString().split('T')[0];
      return orderDate === dateFilter;
    })();

    return matchesSearch && matchesDate;
  });

  return (
    <div className={`${styles.container} ${isDarkMode ? styles.darkTheme : styles.lightTheme}`}>
      {/* Sidebar */}
      <aside className={styles.sidebar}>
        <div className={styles.logo}>
          <span className={styles.logoIcon}>🍕</span>
          <span className={styles.logoText}>Admin Panel</span>
        </div>

        <nav className={styles.nav}>
          <button
            className={`${styles.navItem} ${activeTab === "orders" ? styles.navItemActive : ""}`}
            onClick={() => setActiveTab("orders")}
            id="nav-orders"
          >
            <span className={styles.navIcon}>📦</span>
            <span>Orders</span>
            {stats.pending > 0 && (
              <span className={styles.badge}>{stats.pending}</span>
            )}
          </button>
          <button
            className={`${styles.navItem} ${activeTab === "menu" ? styles.navItemActive : ""}`}
            onClick={() => setActiveTab("menu")}
            id="nav-menu"
          >
            <span className={styles.navIcon}>🍽️</span>
            <span>Menu Items</span>
          </button>
        </nav>

        <div className={styles.sidebarFooter}>
          <div className={styles.refreshInfo}>
            <span>🔄 Auto-refresh: 30s</span>
            <span className={styles.refreshTime}>
              Last: {isMounted ? lastRefresh.toLocaleTimeString() : ""}
            </span>
          </div>
          <button
            className={styles.refreshBtn}
            onClick={fetchOrders}
            id="btn-manual-refresh"
          >
            Refresh Now
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className={styles.main}>
        {/* Header */}
        <header className={styles.header}>
          <div>
            <h1 className={styles.headerTitle}>
              {activeTab === "orders" ? "Order Management" : "Menu Items"}
            </h1>
            <p className={styles.headerSub}>
              {activeTab === "orders"
                ? "Manage and track all customer orders"
                : "View all available menu items"}
            </p>
          </div>
          <button 
            onClick={toggleTheme} 
            className={styles.themeToggleBtn}
            id="btn-theme-toggle"
          >
            {isDarkMode ? "☀️ Light Mode" : "🌙 Dark Mode"}
          </button>
        </header>

        {activeTab === "orders" && (
          <>
            {/* Stats */}
            <div className={styles.statsGrid}>
              <div className={`${styles.statCard} ${styles.statTotal}`}>
                <div className={styles.statIcon}>📊</div>
                <div>
                  <div className={styles.statValue}>{stats.total}</div>
                  <div className={styles.statLabel}>Total Orders</div>
                </div>
              </div>
              <div className={`${styles.statCard} ${styles.statPending}`}>
                <div className={styles.statIcon}>⏳</div>
                <div>
                  <div className={styles.statValue}>{stats.pending}</div>
                  <div className={styles.statLabel}>Pending</div>
                </div>
              </div>
              <div className={`${styles.statCard} ${styles.statPreparing}`}>
                <div className={styles.statIcon}>👨‍🍳</div>
                <div>
                  <div className={styles.statValue}>{stats.preparing}</div>
                  <div className={styles.statLabel}>In Kitchen</div>
                </div>
              </div>
              <div className={`${styles.statCard} ${styles.statDelivered}`}>
                <div className={styles.statIcon}>✅</div>
                <div>
                  <div className={styles.statValue}>{stats.delivered}</div>
                  <div className={styles.statLabel}>Delivered</div>
                </div>
              </div>
              <div className={`${styles.statCard} ${styles.statRevenue}`}>
                <div className={styles.statIcon}>💰</div>
                <div>
                  <div className={styles.statValue}>
                    {formatCurrency(stats.revenue)}
                  </div>
                  <div className={styles.statLabel}>Revenue</div>
                </div>
              </div>
            </div>

             {/* Filters + Search */}
            <div className={styles.toolbar}>
              <div style={{ display: 'flex', gap: '12px', alignItems: 'center', flexWrap: 'wrap', flex: 1 }}>
                <div className={styles.searchWrap} style={{ flex: 1, minWidth: '200px', margin: 0 }}>
                  <span className={styles.searchIcon}>🔍</span>
                  <input
                    type="text"
                    placeholder="Search by order #, name, or phone..."
                    className={styles.searchInput}
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    id="input-search-orders"
                  />
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 'bold' }}>📅 Date:</span>
                  <input
                    type="date"
                    value={dateFilter}
                    onChange={(e) => setDateFilter(e.target.value)}
                    style={{
                      background: 'var(--bg-elevated)',
                      border: '1.5px solid var(--border)',
                      borderRadius: 'var(--radius-sm)',
                      padding: '8px 12px',
                      fontSize: '0.85rem',
                      color: 'var(--text)',
                      outline: 'none',
                      cursor: 'pointer'
                    }}
                  />
                  {dateFilter && (
                    <button 
                      onClick={() => setDateFilter("")}
                      style={{
                        background: 'none',
                        border: 'none',
                        color: 'var(--danger)',
                        fontSize: '0.8rem',
                        fontWeight: 'bold',
                        cursor: 'pointer',
                        padding: '4px'
                      }}
                    >
                      Clear
                    </button>
                  )}
                </div>
              </div>
              <div className={styles.filters}>
                {(
                  [
                    "all",
                    "pending",
                    "accepted",
                    "preparing",
                    "out_for_delivery",
                    "delivered",
                    "cancelled",
                  ] as const
                ).map((s) => (
                  <button
                    key={s}
                    id={`filter-${s}`}
                    className={`${styles.filterBtn} ${statusFilter === s ? styles.filterBtnActive : ""}`}
                    onClick={() => setStatusFilter(s)}
                  >
                    {s === "all"
                      ? "All"
                      : STATUS_CONFIG[s as OrderStatus]?.label ?? s}
                  </button>
                ))}
              </div>
            </div>

            {/* Orders Layout */}
            <div className={styles.ordersLayout}>
              {/* Orders List */}
              <div className={styles.ordersList}>
                {loading ? (
                  <div className={styles.loadingState}>
                    <div className={styles.spinner}></div>
                    <p>Loading orders...</p>
                  </div>
                ) : filteredOrders.length === 0 ? (
                  <div className={styles.emptyState}>
                    <span>📭</span>
                    <p>No orders found</p>
                  </div>
                ) : (
                  filteredOrders.map((order) => {
                    const cfg = STATUS_CONFIG[order.status];
                    return (
                      <div
                        key={order.id}
                        id={`order-${order.id}`}
                        className={`${styles.orderCard} ${selectedOrder?.id === order.id ? styles.orderCardSelected : ""}`}
                        onClick={() =>
                          setSelectedOrder(
                            selectedOrder?.id === order.id ? null : order
                          )
                        }
                      >
                        <div className={styles.orderCardTop}>
                          <span className={styles.orderNumber}>
                            {order.order_number}
                          </span>
                          <span
                            className={styles.statusPill}
                            style={{ background: cfg.color + "22", color: cfg.color, border: `1px solid ${cfg.color}44` }}
                          >
                            {cfg.label}
                          </span>
                        </div>
                        <div className={styles.orderCardMid}>
                          <span className={styles.customerName}>
                            👤 {order.customer_name}
                          </span>
                          <span className={styles.orderTime}>
                            {isMounted ? timeAgo(order.created_at) : ""}
                          </span>
                        </div>
                        <div className={styles.orderCardBot}>
                          <span className={styles.orderPhone}>
                            📞 {order.customer_phone}
                          </span>
                          <span className={styles.orderTotal}>
                            {formatCurrency(order.total)}
                          </span>
                        </div>
                        <div className={styles.orderItemsPreview}>
                          {order.items
                            .slice(0, 2)
                            .map((item) => item.name)
                            .join(", ")}
                          {order.items.length > 2 &&
                            ` +${order.items.length - 2} more`}
                        </div>

                        {/* Quick action */}
                        {cfg.next && (
                          <button
                            className={styles.quickActionBtn}
                            id={`quick-action-${order.id}`}
                            onClick={(e) => {
                              e.stopPropagation();
                              updateOrderStatus(order.id, cfg.next!);
                            }}
                            disabled={updatingStatus === order.id}
                          >
                            {updatingStatus === order.id
                              ? "Updating..."
                              : `→ ${STATUS_CONFIG[cfg.next].label}`}
                          </button>
                        )}
                        {order.status === "pending" && (
                          <button
                            className={styles.cancelBtn}
                            id={`cancel-${order.id}`}
                            onClick={(e) => {
                              e.stopPropagation();
                              updateOrderStatus(order.id, "cancelled");
                            }}
                            disabled={updatingStatus === order.id}
                          >
                            ✕ Cancel
                          </button>
                        )}
                      </div>
                    );
                  })
                )}
              </div>

              {/* Order Detail Panel */}
              {selectedOrder && (
                <div className={styles.orderDetail} id="order-detail-panel">
                  <div className={styles.detailHeader}>
                    <div>
                      <h2 className={styles.detailTitle}>
                        {selectedOrder.order_number}
                      </h2>
                      <span
                        className={styles.detailStatus}
                        style={{
                          color:
                            STATUS_CONFIG[selectedOrder.status]?.color ??
                            "#fff",
                        }}
                      >
                        ● {STATUS_CONFIG[selectedOrder.status]?.label}
                      </span>
                    </div>
                    <button
                      className={styles.closeBtn}
                      onClick={() => setSelectedOrder(null)}
                      id="btn-close-detail"
                    >
                      ✕
                    </button>
                  </div>

                  <div className={styles.detailSection}>
                    <h3 className={styles.detailSectionTitle}>
                      👤 Customer Info
                    </h3>
                    <div className={styles.infoGrid}>
                      <div className={styles.infoItem}>
                        <span className={styles.infoLabel}>Name</span>
                        <span className={styles.infoValue}>
                          {selectedOrder.customer_name}
                        </span>
                      </div>
                      <div className={styles.infoItem}>
                        <span className={styles.infoLabel}>Phone</span>
                        <span className={styles.infoValue}>
                          {selectedOrder.customer_phone}
                        </span>
                      </div>
                      {selectedOrder.customer_email && (
                        <div className={styles.infoItem}>
                          <span className={styles.infoLabel}>Email</span>
                          <span className={styles.infoValue}>
                            {selectedOrder.customer_email}
                          </span>
                        </div>
                      )}
                      <div className={styles.infoItem}>
                        <span className={styles.infoLabel}>Payment</span>
                        <span className={styles.infoValue}>
                          {selectedOrder.payment_method.replace(/_/g, " ")}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className={styles.detailSection}>
                    <h3 className={styles.detailSectionTitle}>
                      📍 Service Type / Delivery Address
                    </h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', fontSize: '0.9rem' }}>
                      <div>
                        <strong>Service Type: </strong>
                        <span style={{
                          background: selectedOrder.order_type === 'dine_in' ? 'rgba(22, 163, 74, 0.1)' : (selectedOrder.order_type === 'takeaway' ? 'rgba(217, 119, 6, 0.1)' : 'rgba(37, 99, 235, 0.1)'),
                          color: selectedOrder.order_type === 'dine_in' ? '#16a34a' : (selectedOrder.order_type === 'takeaway' ? '#d97706' : '#2563eb'),
                          padding: '3px 8px',
                          borderRadius: '4px',
                          fontWeight: 'bold',
                          fontSize: '0.8rem',
                          marginLeft: '6px',
                          display: 'inline-block'
                        }}>
                          {selectedOrder.order_type === 'dine_in' && '🍽️ Dine In'}
                          {selectedOrder.order_type === 'takeaway' && '🛍️ Take Away'}
                          {(!selectedOrder.order_type || selectedOrder.order_type === 'delivery') && '🛵 Delivery'}
                        </span>
                      </div>
                      {selectedOrder.order_type === 'dine_in' && (
                        <div><strong>Arriving At: </strong>{selectedOrder.pickup_time || 'N/A'}</div>
                      )}
                      {selectedOrder.order_type === 'takeaway' && (
                        <div><strong>Collection Time: </strong>{selectedOrder.pickup_time || 'N/A'}</div>
                      )}
                      {(!selectedOrder.order_type || selectedOrder.order_type === 'delivery') && (
                        <div><strong>Delivery Address: </strong>{selectedOrder.delivery_address}, {selectedOrder.city}</div>
                      )}
                    </div>
                  </div>

                  <div className={styles.detailSection}>
                    <h3 className={styles.detailSectionTitle}>🛒 Items</h3>
                    <div className={styles.itemsList}>
                      {selectedOrder.items.map((item) => (
                        <div key={item.id} className={styles.itemRow}>
                          <div className={styles.itemName}>
                            <span className={styles.itemQty}>
                              ×{item.quantity}
                            </span>{" "}
                            {item.name}
                          </div>
                          <div className={styles.itemPrice}>
                            {formatCurrency(item.subtotal)}
                          </div>
                        </div>
                      ))}
                      <div className={styles.itemDivider} />
                      <div className={styles.itemRow}>
                        <span className={styles.itemSubLabel}>Subtotal</span>
                        <span>{formatCurrency(selectedOrder.subtotal)}</span>
                      </div>
                      <div className={styles.itemRow}>
                        <span className={styles.itemSubLabel}>
                          Delivery Fee
                        </span>
                        <span>
                          {formatCurrency(selectedOrder.delivery_fee)}
                        </span>
                      </div>
                      <div className={`${styles.itemRow} ${styles.totalRow}`}>
                        <span>Total</span>
                        <span>{formatCurrency(selectedOrder.total)}</span>
                      </div>
                    </div>
                  </div>

                  {selectedOrder.special_instructions && (
                    <div className={styles.detailSection}>
                      <h3 className={styles.detailSectionTitle}>
                        📝 Special Instructions
                      </h3>
                      <p className={styles.instructions}>
                        {selectedOrder.special_instructions}
                      </p>
                    </div>
                  )}

                  {/* Status Actions */}
                  <div className={styles.detailSection}>
                    <h3 className={styles.detailSectionTitle}>
                      🔄 Update Status
                    </h3>
                    <div className={styles.statusActions}>
                      {(
                        [
                          "pending",
                          "accepted",
                          "preparing",
                          "out_for_delivery",
                          "delivered",
                          "cancelled",
                        ] as OrderStatus[]
                      ).map((s) => (
                        <button
                          key={s}
                          id={`status-action-${s}`}
                          className={`${styles.statusActionBtn} ${selectedOrder.status === s ? styles.statusActionBtnActive : ""}`}
                          style={
                            selectedOrder.status === s
                              ? {
                                  background: STATUS_CONFIG[s].color,
                                  borderColor: STATUS_CONFIG[s].color,
                                }
                              : {}
                          }
                          onClick={() =>
                            updateOrderStatus(selectedOrder.id, s)
                          }
                          disabled={
                            selectedOrder.status === s ||
                            updatingStatus === selectedOrder.id
                          }
                        >
                          {updatingStatus === selectedOrder.id &&
                          selectedOrder.status !== s
                            ? "..."
                            : STATUS_CONFIG[s].label}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </>
        )}

        {activeTab === "menu" && (
          <div className={styles.menuGrid}>
            {menuItems.length === 0 ? (
              <div className={styles.emptyState}>
                <span>🍽️</span>
                <p>No menu items found</p>
              </div>
            ) : (
              menuItems.map((item) => (
                <div
                  key={item.id}
                  className={`${styles.menuCard} ${!item.is_available ? styles.menuCardUnavailable : ""}`}
                  id={`menu-item-${item.id}`}
                >
                  <div className={styles.menuCardTop}>
                    <div className={styles.menuCategory}>{item.category}</div>
                    {item.is_featured && (
                      <div className={styles.menuFeatured}>⭐ Featured</div>
                    )}
                  </div>
                  <h3 className={styles.menuName}>{item.name}</h3>
                  <p className={styles.menuDesc}>{item.description}</p>
                  <div className={styles.menuMeta}>
                    <span className={styles.menuPrice}>
                      {formatCurrency(item.price)}
                    </span>
                    <span className={styles.menuRating}>
                      ⭐ {parseFloat(item.rating).toFixed(1)}
                    </span>
                    <span className={styles.menuPrep}>
                      ⏱ {item.prep_time}min
                    </span>
                  </div>
                  <div
                    className={`${styles.menuStatus} ${item.is_available ? styles.menuStatusAvailable : styles.menuStatusUnavailable}`}
                  >
                    {item.is_available ? "✓ Available" : "✗ Unavailable"}
                  </div>
                </div>
              ))
            )}
          </div>
        )}
      </main>
    </div>
  );
}
