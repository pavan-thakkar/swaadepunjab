{{-- Swaad E Punjab admin theme: dark "tandoor" sidebar, amber accents, warm background --}}
<style>
  :root { --sp-tandoor:#2C1A00; --sp-cream:#FFF8E7; --sp-muted:#E9D9B8; --sp-amber:#E6A817; --sp-amber-text:#8A5A00; --sp-sand:#EDE0C4; --sp-bg:#F7F3EA; --sp-ink:#1A0F00; --sp-ink-soft:#6B5232; }
  html:not(.dark) .fi-body { background: var(--sp-bg) !important; }
  /* Sidebar */
  html:not(.dark) .fi-sidebar { background: var(--sp-tandoor) !important; }
  html:not(.dark) .fi-sidebar-header { background: var(--sp-tandoor) !important; border-bottom: 1px solid rgba(255,248,231,0.08) !important; box-shadow: none !important; }
  html:not(.dark) .fi-sidebar-header a, html:not(.dark) .fi-sidebar-header .fi-logo { color: var(--sp-cream) !important; }
  /* Wide screens: tables use the full width, but forms stay readable */
  .fi-resource-create-record-page .fi-fo-component-ctn,
  .fi-resource-edit-record-page .fi-fo-component-ctn,
  .fi-resource-create-record-page .fi-form-actions,
  .fi-resource-edit-record-page .fi-form-actions { max-width: 1200px; }
  /* Tables never push the page wider than the screen; they scroll inside their card instead */
  .fi-ta-ctn { max-width: 100%; }
  .fi-ta-content { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  /* Brand logo: a bit more room in the sidebar header, bigger on the login page */
  .fi-sidebar-header { min-height: 4.5rem; }
  .fi-sidebar-header .fi-logo, .fi-topbar .fi-logo { width: auto !important; max-width: 100%; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25)); }
  .fi-simple-header .fi-logo { height: 6rem !important; width: auto !important; margin: 0 auto; }
  html:not(.dark) .fi-sidebar-group-label { color: #C8B48E !important; letter-spacing: .08em; text-transform: uppercase; font-size: 11px !important; font-weight: 800 !important; }
  html:not(.dark) .fi-sidebar-group-button svg, html:not(.dark) .fi-sidebar-group-collapse-button svg { color: #C8B48E !important; }
  html:not(.dark) .fi-sidebar-item-button { border-radius: 10px !important; }
  html:not(.dark) .fi-sidebar-item-label { color: #F3E6CC !important; font-weight: 500; }
  html:not(.dark) .fi-sidebar-item-icon { color: #C8B48E !important; }
  html:not(.dark) .fi-sidebar-item-button:hover { background: rgba(255,248,231,0.08) !important; }
  html:not(.dark) .fi-sidebar-item-active .fi-sidebar-item-button { background: var(--sp-amber) !important; }
  html:not(.dark) .fi-sidebar-item-active .fi-sidebar-item-label, html:not(.dark) .fi-sidebar-item-active .fi-sidebar-item-icon { color: var(--sp-ink) !important; font-weight: 800 !important; }
  html:not(.dark) .fi-sidebar-nav { scrollbar-color: rgba(255,248,231,0.2) transparent; }
  /* Top bar & surfaces */
  html:not(.dark) .fi-topbar > nav { background: rgba(255,255,255,0.9) !important; backdrop-filter: blur(8px); }
  .fi-section, .fi-wi-stats-overview-stat, .fi-ta-ctn, .fi-wi-chart > div, .fi-fo-section { border-radius: 16px !important; }
  html:not(.dark) .fi-wi-stats-overview-stat { border: 1px solid var(--sp-sand); box-shadow: none !important; }
  .fi-header-heading { font-weight: 900 !important; letter-spacing: -0.02em; }
  .fi-btn { border-radius: 10px !important; }

  /* ---- Shared widget pieces (sp-*) ---- */
  .sp-card { background:#fff; border:1px solid var(--sp-sand); border-radius:18px; padding:18px; }
  .dark .sp-card { background: rgb(24 24 27); border-color: rgb(63 63 70); }
  .sp-card h2 { margin:0 0 12px; font-size:16px; font-weight:800; }
  .sp-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .sp-muted { color: var(--sp-ink-soft); font-size:12px; }
  .dark .sp-muted { color: rgb(161 161 170); }
  .sp-switch { width:44px; height:24px; border-radius:12px; border:0; padding:0; position:relative; cursor:pointer; background:#D9CCB0; flex-shrink:0; transition: background .2s; }
  .sp-switch[aria-checked="true"] { background:#16A34A; }
  .sp-switch::after { content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:10px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.25); transition:left .2s; }
  .sp-switch[aria-checked="true"]::after { left:22px; }
  .sp-chip { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; padding:4px 10px; border-radius:999px; border:0; cursor:pointer; font-family:inherit; }
  .sp-chip-red { background:#FDECEC; color:#9F1D1D; }
  .sp-input { width:100%; height:42px; border-radius:12px; border:1px solid var(--sp-sand); background:#FBF8F1; padding:0 12px; font:inherit; font-size:14px; color:var(--sp-ink); }
  .dark .sp-input { background: rgb(39 39 42); border-color: rgb(63 63 70); color:#fff; }
  .sp-btn { height:38px; border-radius:10px; border:0; padding:0 14px; font:inherit; font-size:13px; font-weight:800; cursor:pointer; }
  .sp-btn-amber { background: var(--sp-amber); color: var(--sp-ink); }
  .sp-btn-dark { background: var(--sp-tandoor); color: var(--sp-cream); }
  .sp-btn-green { background:#E8F6EC; color:#11622F; }
  .sp-btn-light { background:#fff; color:var(--sp-ink); border:1px solid var(--sp-sand); }
  /* Live orders board */
  .sp-board { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
  .sp-col { background:#FBF8F1; border-radius:14px; padding:10px; display:flex; flex-direction:column; gap:10px; min-height:120px; }
  .dark .sp-col { background: rgb(39 39 42); }
  .sp-col-head { display:flex; justify-content:space-between; align-items:center; font-size:12px; font-weight:800; letter-spacing:.04em; padding:2px 4px; }
  .sp-count { background:#fff; border:1px solid var(--sp-sand); padding:1px 8px; border-radius:999px; color:var(--sp-ink); }
  .sp-order { background:#fff; border:1px solid var(--sp-sand); border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:6px; box-shadow:0 1px 3px rgba(100,60,0,.06); }
  .dark .sp-order { background: rgb(24 24 27); border-color: rgb(63 63 70); }
  .sp-order.new { border-color:#F2C66B; }
  .sp-order a.sp-no { font-weight:800; font-size:14px; color:inherit; text-decoration:none; }
  .sp-type { font-size:11px; font-weight:800; padding:2px 7px; border-radius:6px; }
  .sp-type-delivery { background:#E7EEFC; color:#1D3F91; } .sp-type-takeaway { background:#FFF1D6; color:#7A4A00; } .sp-type-dine_in { background:#E8F6EC; color:#11622F; }
  .sp-empty { font-size:12px; color: var(--sp-ink-soft); text-align:center; padding:16px 4px; }
  /* Bars */
  .sp-bars { display:flex; align-items:flex-end; gap:6px; height:120px; border-bottom:1px solid var(--sp-sand); }
  .sp-bar { flex:1; background:#E9D9B8; border-radius:4px 4px 0 0; min-height:2px; }
  .sp-bar.peak { background: var(--sp-amber); }
  .sp-rank { width:22px; height:22px; border-radius:11px; background:#FFF1D6; color:var(--sp-amber-text); font-size:12px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
  .sp-avatar { width:28px; height:28px; border-radius:14px; background:#F3EAD6; color:var(--sp-ink); font-weight:800; font-size:12px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
</style>
