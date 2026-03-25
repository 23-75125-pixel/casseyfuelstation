// ============================================================
// assets/js/app.js
// GasPOS - Core Application JS
// ============================================================

// ── Config ───────────────────────────────
const App = {
  version: '1.0.0',
  currency: '₱',

  // ── API helper ─────────────────────────
  async api(endpoint, options = {}) {
    const defaults = {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
    };
    try {
      const res = await fetch(endpoint, { ...defaults, ...options });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
      return data;
    } catch (err) {
      throw err;
    }
  },

  get(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    if (!qs) return this.api(url);
    const sep = url.includes('?') ? '&' : '?';
    return this.api(`${url}${sep}${qs}`);
  },

  post(url, body = {}) {
    return this.api(url, { method: 'POST', body: JSON.stringify(body) });
  },

  // ── Toast notifications (SweetAlert2) ─────────
  toast(msg, type = 'info', duration = 3000) {
    const iconMap = { info: 'info', success: 'success', error: 'error', warning: 'warning' };
    if (typeof Swal === 'undefined') {
      // Fallback if Swal not loaded yet
      console.log(`[${type}] ${msg}`);
      return;
    }
    Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: duration,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
      },
    }).fire({
      icon: iconMap[type] || 'info',
      title: msg,
    });
  },

  // ── Modal ────────────────────────────────
  modal: {
    open(html, size = 'md') {
      const existing = document.querySelector('.modal-overlay');
      if (existing) existing.remove();
      const overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.innerHTML = `<div class="modal-box ${size}">${html}</div>`;
      overlay.addEventListener('click', (e) => { if (e.target === overlay) App.modal.close(); });
      document.body.appendChild(overlay);
      // Only block body scroll if not already locked by .is-app
      if (!document.body.classList.contains('is-app')) {
        document.body.style.overflow = 'hidden';
      }
      const firstInput = overlay.querySelector('input, select, textarea');
      if (firstInput) setTimeout(() => firstInput.focus(), 100);
      return overlay;
    },
    close() {
      const overlay = document.querySelector('.modal-overlay');
      if (overlay) {
        overlay.remove();
        // Only restore body scroll if not locked by .is-app
        if (!document.body.classList.contains('is-app')) {
          document.body.style.overflow = '';
        }
      }
    },
  },

  // ── Confirm dialog (SweetAlert2) ──────────
  // danger can be boolean or an options object: { icon, confirmText, confirmColor }
  async confirm(msg, title = 'Confirm', danger = false) {
    if (typeof Swal === 'undefined') return window.confirm(msg);
    const opts = typeof danger === 'object' ? danger : {};
    const isDanger = danger === true || opts.icon === 'warning';
    const result = await Swal.fire({
      title,
      html: msg,
      icon: opts.icon || (isDanger ? 'warning' : 'question'),
      showCancelButton: true,
      confirmButtonText: opts.confirmText || (isDanger ? 'Yes, delete' : 'Confirm'),
      cancelButtonText: 'Cancel',
      confirmButtonColor: opts.confirmColor || (isDanger ? '#ef4444' : '#f97316'),
      cancelButtonColor: '#64748b',
      reverseButtons: true,
      focusCancel: isDanger,
    });
    return result.isConfirmed;
  },

  // ── Format currency ─────────────────────
  money(val) {
    return this.currency + Number(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },

  // ── Format number ───────────────────────
  num(val, dp = 2) {
    return Number(val || 0).toFixed(dp);
  },

  // ── Loading state ────────────────────────
  loading: {
    show(el, msg = 'Loading...') {
      if (typeof el === 'string') el = document.querySelector(el);
      if (!el) return;
      el._originalHTML = el.innerHTML;
      el._originalDisabled = el.disabled;
      el.disabled = true;
      el.innerHTML = `<span class="spinner"></span> ${msg}`;
    },
    hide(el) {
      if (typeof el === 'string') el = document.querySelector(el);
      if (!el || !el._originalHTML) return;
      el.innerHTML = el._originalHTML;
      el.disabled = el._originalDisabled || false;
    },
  },

  // ── Local state ─────────────────────────
  state: {},
  setState(key, val) { this.state[key] = val; },
  getState(key) { return this.state[key]; },

  // ── Date helpers ─────────────────────────
  formatDate(d) {
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  },
  formatDateTime(d) {
    return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  },
  today() { return new Date().toISOString().split('T')[0]; },
};

// ── Sidebar toggle ────────────────────────
function initSidebar() {
  const sidebar   = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  const overlay   = document.getElementById('sidebar-overlay');
  if (!sidebar) return;

  const MOBILE_BP = 768;
  const isMobile  = () => window.innerWidth <= MOBILE_BP;

  // ── Sync --sidebar-w to actual rendered sidebar width ──
  function syncSidebarWidth() {
    if (isMobile()) return; // mobile uses its own width
    const w = sidebar.getBoundingClientRect().width;
    if (w > 0) document.documentElement.style.setProperty('--sidebar-w', w + 'px');
  }
  // Run once fonts/layout settle, then on every resize
  setTimeout(syncSidebarWidth, 80);
  window.addEventListener('resize', syncSidebarWidth);

  // ── Helpers ──
  function openDesktop() {
    sidebar.classList.remove('sidebar-hidden');
    localStorage.setItem('sidebar-hidden', 'false');
    // Re-sync margin after slide-in transition finishes
    setTimeout(syncSidebarWidth, 300);
  }
  function closeDesktop() {
    sidebar.classList.add('sidebar-hidden');
    localStorage.setItem('sidebar-hidden', 'true');
  }
  function openMobile() {
    sidebar.classList.add('mobile-open');
    if (overlay) overlay.classList.remove('hidden');
  }
  function closeMobile() {
    sidebar.classList.remove('mobile-open');
    if (overlay) overlay.classList.add('hidden');
  }

  // ── Toggle button ──
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (isMobile()) {
        sidebar.classList.contains('mobile-open') ? closeMobile() : openMobile();
      } else {
        sidebar.classList.contains('sidebar-hidden') ? openDesktop() : closeDesktop();
      }
    });
  }

  // ── Overlay click closes mobile sidebar ──
  if (overlay) {
    overlay.addEventListener('click', () => closeMobile());
  }

  // ── Close on nav click (mobile) ──
  sidebar.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
      if (isMobile()) closeMobile();
    });
  });

  // ── Cleanup on resize ──
  window.addEventListener('resize', () => {
    if (!isMobile()) {
      // Switching to desktop: clear mobile state
      sidebar.classList.remove('mobile-open');
      if (overlay) overlay.classList.add('hidden');
      // Restore desktop hidden preference
      if (localStorage.getItem('sidebar-hidden') === 'true') {
        sidebar.classList.add('sidebar-hidden');
      } else {
        sidebar.classList.remove('sidebar-hidden');
      }
    } else {
      // Switching to mobile: clear desktop state
      sidebar.classList.remove('sidebar-hidden');
    }
  });

  // ── Restore desktop state from localStorage ──
  if (!isMobile() && localStorage.getItem('sidebar-hidden') === 'true') {
    sidebar.classList.add('sidebar-hidden');
  }

  // ── Highlight active nav item ──
  const currentPath = window.location.pathname.split('/').pop() || 'dashboard.php';
  document.querySelectorAll('.nav-item[data-page]').forEach(item => {
    item.classList.toggle('active', item.dataset.page === currentPath);
  });
}

// ── Logout ────────────────────────────────
async function logout() {
  const confirmed = await App.confirm(
    'Are you sure you want to log out?',
    'Logout',
    { icon: 'question', confirmText: 'Yes, log out', confirmColor: '#ef4444' }
  );
  if (!confirmed) return;
  try {
    await App.post('/api_auth.php?action=logout');
  } catch {}
  window.location.href = '/login.php';
}

// ── Init on load ──────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();

  // Keyboard shortcut: Escape closes modals
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') App.modal.close();
  });

  // Start realtime if Supabase JS is loaded
  AppRealtime.init();
});

// ============================================================
// AppRealtime — Supabase Realtime WebSocket subscriptions
// Subscribes to key tables and broadcasts custom DOM events
// Pages can listen: document.addEventListener('rt:transactions', ...)
// ============================================================
const AppRealtime = {
  client: null,
  channel: null,
  connected: false,

  // Tables to watch and the event name they emit
  WATCHES: [
    { table: 'transactions',   event: 'rt:transactions' },
    { table: 'fuel_tanks',     event: 'rt:fuel_tanks'   },
    { table: 'fuel_types',     event: 'rt:fuel_types'   },
    { table: 'products',       event: 'rt:products'     },
    { table: 'profiles',       event: 'rt:profiles'     },
    { table: 'settings',       event: 'rt:settings'     },
  ],

  init() {
    const urlMeta  = document.querySelector('meta[name="sb-url"]');
    const anonMeta = document.querySelector('meta[name="sb-anon"]');
    if (!urlMeta || !anonMeta) return;           // not an authenticated page

    const sbUrl  = urlMeta.content;
    const sbAnon = anonMeta.content;
    if (!sbUrl || !sbAnon) return;

    if (typeof supabase === 'undefined') {
      console.warn('[AppRealtime] @supabase/supabase-js not loaded');
      return;
    }

    this.client = supabase.createClient(sbUrl, sbAnon);

    // Build a single channel that subscribes to all watched tables
    let ch = this.client.channel('gaspos-realtime');

    this.WATCHES.forEach(({ table, event }) => {
      ch = ch.on(
        'postgres_changes',
        { event: '*', schema: 'public', table },
        (payload) => {
          // Dispatch a custom DOM event so any page script can react
          document.dispatchEvent(new CustomEvent(event, { detail: payload }));

          // Show a subtle toast for staff-visible events
          const evtTypeMap = { INSERT: 'added', UPDATE: 'updated', DELETE: 'deleted' };
          const label = evtTypeMap[payload.eventType] || payload.eventType;
          const friendlyNames = {
            transactions: 'Transaction', fuel_tanks: 'Fuel Tank',
            fuel_types: 'Fuel Price', products: 'Product',
            profiles: 'User', settings: 'Settings',
          };
          const name = friendlyNames[table] || table;
          // Only show toast if page is NOT already auto-refreshing (suppress on POS)
          const quiet = document.body.dataset.realtimeQuiet === 'true';
          if (!quiet) {
            App.toast(`${name} ${label}`, 'info', 2000);
          }
        }
      );
    });

    ch.subscribe((status) => {
      this.connected = status === 'SUBSCRIBED';
      this._setIndicator(this.connected);
      if (status === 'SUBSCRIBED') {
        console.info('[AppRealtime] Connected to Supabase Realtime \u2705');
      } else if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT') {
        console.warn('[AppRealtime] Realtime connection issue:', status);
        this._setIndicator(false);
      }
    });

    this.channel = ch;
  },

  // Dot indicator in the topbar showing realtime status
  _setIndicator(online) {
    let dot = document.getElementById('rt-dot');
    if (!dot) {
      const topbarActions = document.querySelector('.topbar-actions');
      if (!topbarActions) return;
      dot = document.createElement('span');
      dot.id = 'rt-dot';
      dot.title = 'Realtime connection';
      dot.style.cssText = 'width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0;transition:background .4s';
      topbarActions.prepend(dot);
    }
    dot.style.background = online ? '#22c55e' : '#ef4444';
    dot.title = online ? 'Realtime: connected' : 'Realtime: disconnected';
  },

  // Pages can call this to refresh themselves on realtime events
  onTable(table, callback) {
    const eventName = `rt:${table}`;
    document.addEventListener(eventName, (e) => callback(e.detail));
  },

  disconnect() {
    if (this.client && this.channel) {
      this.client.removeChannel(this.channel);
    }
  },
};
