<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession();
$role    = $profile['role'] ?? 'cashier';

if ($role === 'cashier') {
  header('Location: /pos.php?error=forbidden');
  exit;
}

$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle   = 'Dashboard';
$activePage  = 'dashboard.php';
$extraScripts = [];

include __DIR__ . '/layout.php';
?>

<div style="display:flex;flex-direction:column;gap:24px">

  <!-- ── Welcome ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:24px;font-weight:800;margin:0;letter-spacing:-.5px">
        Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
        <?= htmlspecialchars(explode(' ', $profile['full_name'])[0]) ?> 👋
      </h1>
      <p style="color:var(--text-muted);margin:4px 0 0;font-size:13px">
        <?= date('l, F j, Y') ?> · <?= ucfirst($profile['role']) ?>
      </p>
    </div>
    <?php if ($profile['role'] !== 'cashier'): ?>
    <a href="/pos.php" class="btn btn-primary">
      🖥️ Open POS
    </a>
    <?php endif; ?>
  </div>
  <!-- ── Date Filter Bar ── -->
  <div class="card" style="padding:14px 18px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <span style="font-size:13px;font-weight:600;color:var(--text-secondary);white-space:nowrap">📅 Period:</span>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn btn-sm" id="dr-today"  onclick="setDashRange('today')"  >Today</button>
        <button class="btn btn-sm" id="dr-week"   onclick="setDashRange('week')"   >This Week</button>
        <button class="btn btn-sm" id="dr-month"  onclick="setDashRange('month')"  >This Month</button>
        <button class="btn btn-sm" id="dr-year"   onclick="setDashRange('year')"   >This Year</button>
        <button class="btn btn-sm" id="dr-custom" onclick="setDashRange('custom')" >Custom</button>
      </div>
      <div id="dash-custom-range" style="display:none;align-items:center;gap:6px;flex-wrap:wrap">
        <input type="date" class="form-control" id="dash-from" style="width:145px;font-size:13px;padding:6px 10px" oninput="setDashRange('custom')">
        <span style="color:var(--text-muted);font-size:12px">to</span>
        <input type="date" class="form-control" id="dash-to"   style="width:145px;font-size:13px;padding:6px 10px" oninput="setDashRange('custom')">
      </div>
      <span id="dash-range-label" style="margin-left:auto;font-size:12px;color:var(--text-muted);white-space:nowrap"></span>
    </div>
  </div>
  <!-- ── Stat Cards ── -->
  <div id="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
    <div class="stat-card" id="stat-sales">
      <div class="stat-icon" style="background:#fff7ed">💰</div>
      <div class="stat-label" id="stat-sales-label">Sales</div>
      <div class="stat-value" id="today-sales">...</div>
      <div class="stat-sub" id="today-txns">Loading...</div>
    </div>
    <?php if ($profile['role'] !== 'cashier'): ?>
    <div class="stat-card" id="stat-fuel">
      <div class="stat-icon" style="background:#fef3c7">⛽</div>
      <div class="stat-label" id="stat-fuel-label">Fuel Dispensed</div>
      <div class="stat-value" id="today-liters">...</div>
      <div class="stat-sub" id="stat-fuel-sub">Liters</div>
    </div>
    <div class="stat-card" id="stat-low">
      <div class="stat-icon" style="background:#fee2e2">⚠️</div>
      <div class="stat-label">Low Stock Items</div>
      <div class="stat-value" id="low-stock-count">...</div>
      <div class="stat-sub"><a href="/products.php?filter=low" style="color:var(--danger);font-size:12px">View items</a></div>
    </div>
    <div class="stat-card" id="stat-active-shifts">
      <div class="stat-icon" style="background:#dcfce7">🕐</div>
      <div class="stat-label">Active Shifts</div>
      <div class="stat-value" id="active-shifts">...</div>
      <div class="stat-sub">Open right now</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Fuel Tank Levels (non-cashier) ── -->
  <?php if ($profile['role'] !== 'cashier'): ?>
  <div class="card">
    <div class="card-header">
      <span style="font-size:18px">⛽</span>
      <h3 class="card-title">Fuel Tank Levels</h3>
      <a href="/fuel.php" class="btn btn-sm btn-outline ms-auto">Manage</a>
    </div>
    <div class="card-body" id="fuel-tanks-display" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
      <div style="color:var(--text-muted);font-size:13px">Loading tanks...</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Quick Actions ── -->
  <div class="card">
    <div class="card-header">
      <span style="font-size:18px">⚡</span>
      <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
      <div class="quick-actions">
        <?php if (in_array($profile['role'], ['admin','cashier'])): ?>
        <a href="/pos.php" class="quick-action">
          <span class="quick-action-icon">🖥️</span>
          <div class="quick-action-label">POS Terminal</div>
        </a>
        <?php endif; ?>
        <a href="/transactions.php" class="quick-action">
          <span class="quick-action-icon">🧾</span>
          <div class="quick-action-label">Transactions</div>
        </a>
        <a href="/shifts.php" class="quick-action">
          <span class="quick-action-icon">🕐</span>
          <div class="quick-action-label">My Shifts</div>
        </a>
        <?php if (in_array($profile['role'], ['admin','staff'])): ?>
        <a href="/receiving.php" class="quick-action">
          <span class="quick-action-icon">📥</span>
          <div class="quick-action-label">Stock Receive</div>
        </a>
        <a href="/reports.php" class="quick-action">
          <span class="quick-action-icon">📈</span>
          <div class="quick-action-label">Reports</div>
        </a>
        <a href="/products.php" class="quick-action">
          <span class="quick-action-icon">📦</span>
          <div class="quick-action-label">Products</div>
        </a>
        <?php endif; ?>
        <?php if ($profile['role'] === 'admin'): ?>
        <a href="/users.php" class="quick-action">
          <span class="quick-action-icon">👥</span>
          <div class="quick-action-label">Users</div>
        </a>
        <a href="/settings.php" class="quick-action">
          <span class="quick-action-icon">⚙️</span>
          <div class="quick-action-label">Settings</div>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Recent Transactions ── -->
  <div class="card">
    <div class="card-header">
      <span style="font-size:18px">🧾</span>
      <h3 class="card-title">Recent Transactions</h3>
      <a href="/transactions.php" class="btn btn-sm btn-outline ms-auto">View All</a>
    </div>
    <div class="table-wrap">
      <table class="table" id="recent-txns-table">
        <thead>
          <tr>
            <th>TXN #</th>
            <th>Time</th>
            <th>Cashier</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="recent-txns-body">
          <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
// ── Date range state ──
let _dashFrom = App.today();
let _dashTo   = App.today();
let _dashMode = 'today';

function setDashRange(mode) {
  _dashMode = mode;
  const today = App.today();
  const d     = new Date();

  if (mode === 'today') {
    _dashFrom = _dashTo = today;
  } else if (mode === 'week') {
    const day = d.getDay();           // 0=Sun
    const mon = new Date(d); mon.setDate(d.getDate() - ((day + 6) % 7));
    const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
    _dashFrom = mon.toISOString().split('T')[0];
    _dashTo   = sun.toISOString().split('T')[0];
  } else if (mode === 'month') {
    _dashFrom = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-01`;
    _dashTo   = today;
  } else if (mode === 'year') {
    _dashFrom = `${d.getFullYear()}-01-01`;
    _dashTo   = today;
  } else if (mode === 'custom') {
    const from = document.getElementById('dash-from')?.value;
    const to   = document.getElementById('dash-to')?.value;
    if (!from || !to) {
      // Just show the range pickers, don't reload yet
      _highlightRangeBtn(mode);
      document.getElementById('dash-custom-range').style.display = 'flex';
      return;
    }
    _dashFrom = from;
    _dashTo   = to > today ? today : to;
  }

  // Sync custom calendar inputs
  const fromEl = document.getElementById('dash-from');
  const toEl   = document.getElementById('dash-to');
  if (fromEl) fromEl.value = _dashFrom;
  if (toEl)   toEl.value   = _dashTo;

  // Show/hide custom pickers
  const customEl = document.getElementById('dash-custom-range');
  if (customEl) customEl.style.display = mode === 'custom' ? 'flex' : 'none';

  _highlightRangeBtn(mode);
  loadDashboard(_dashFrom, _dashTo);
}

function _highlightRangeBtn(mode) {
  ['today','week','month','year','custom'].forEach(id => {
    const btn = document.getElementById('dr-' + id);
    if (btn) btn.className = `btn btn-sm ${id === mode ? 'btn-primary' : 'btn-outline'}`;
  });
}

async function loadDashboard(dateFrom, dateTo) {
  dateFrom = dateFrom || _dashFrom;
  dateTo   = dateTo   || _dashTo;

  // Update range label
  const labelEl = document.getElementById('dash-range-label');
  if (labelEl) {
    labelEl.textContent = dateFrom === dateTo
      ? dateFrom
      : `${dateFrom}  →  ${dateTo}`;
  }

  // Update stat card label based on period
  const periodLabel = { today: "Today's", week: "This Week's", month: "This Month's", year: "This Year's", custom: 'Period' }[_dashMode] || '';
  const salesLbl = document.getElementById('stat-sales-label');
  if (salesLbl) salesLbl.textContent = `${periodLabel} Sales`;
  const fuelLbl = document.getElementById('stat-fuel-label');
  if (fuelLbl) fuelLbl.textContent = `${periodLabel} Fuel Dispensed`;
  const fuelSub = document.getElementById('stat-fuel-sub');
  if (fuelSub) fuelSub.textContent = 'Liters';

  // Sales summary
  try {
    const data = await App.get('/api_reports.php?action=daily', { date_from: dateFrom, date_to: dateTo });
    document.getElementById('today-sales').textContent = App.money(data.total_sales || 0);
    document.getElementById('today-txns').textContent  = `${data.txn_count || 0} transaction${data.txn_count !== 1 ? 's' : ''}`;
  } catch { document.getElementById('today-sales').textContent = 'N/A'; }

  // Fuel liters
  const litEl = document.getElementById('today-liters');
  if (litEl) {
    try {
      const data = await App.get('/api_reports.php?action=fuel_sales', { date_from: dateFrom, date_to: dateTo });
      const total = (data.fuel_sales || []).reduce((s, f) => s + f.total_liters, 0);
      litEl.textContent = total.toFixed(1) + 'L';
    } catch { litEl.textContent = 'N/A'; }
  }

  // Low stock (static — not date-filtered)
  const lowEl = document.getElementById('low-stock-count');
  if (lowEl) {
    try {
      const [stockData, fuelLowData] = await Promise.all([
        App.get('/api_reports.php?action=low_stock'),
        App.get('/api_reports.php?action=fuel_low_stock'),
      ]);
      const count = (stockData.low_stock || []).length + (fuelLowData.fuel_low_stock || []).length;
      lowEl.textContent = count;
      lowEl.style.color = count > 0 ? 'var(--danger)' : 'inherit';
    } catch { lowEl.textContent = 'N/A'; }
  }

  // Active shifts (static)
  const shiftEl = document.getElementById('active-shifts');
  if (shiftEl) {
    try {
      const data = await App.get('/api_shifts.php?action=list');
      const active = (data.shifts || []).filter(s => s.status === 'open').length;
      shiftEl.textContent = active;
    } catch { shiftEl.textContent = 'N/A'; }
  }

  // Fuel tanks (static live levels)
  const tanksEl = document.getElementById('fuel-tanks-display');
  if (tanksEl) {
    try {
      const data = await App.get('/api_fuel.php?action=tanks');
      if ((data.tanks || []).length) {
        tanksEl.innerHTML = (data.tanks || []).map(tank => {
          const pct   = Math.min(100, Math.round((tank.current_liters / tank.capacity_liters) * 100));
          const isLow = tank.current_liters <= tank.low_level_liters;
          const color = isLow ? 'var(--danger)' : (pct < 30 ? 'var(--warning)' : 'var(--success)');
          return `
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px">
                <span class="fw-600">${tank.tank_name}</span>
                <span style="font-family:'DM Mono',monospace;font-size:12px;color:var(--text-secondary)">${tank.current_liters.toLocaleString()}L / ${tank.capacity_liters.toLocaleString()}L</span>
              </div>
              <div style="background:var(--surface2);border-radius:99px;height:8px;overflow:hidden">
                <div style="width:${pct}%;height:100%;background:${color};border-radius:99px;transition:width .5s ease"></div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:4px">
                <span style="font-size:11px;color:var(--text-muted)">${tank.fuel_type?.name || ''}</span>
                <span style="font-size:11px;font-weight:600;color:${color}">${pct}%${isLow ? ' ⚠️ LOW' : ''}</span>
              </div>
            </div>
          `;
        }).join('');
      } else {
        tanksEl.innerHTML = '<div style="color:var(--text-muted);font-size:13px">No tanks configured</div>';
      }
    } catch {}
  }

  // Recent transactions filtered by date
  try {
    const data = await App.get('/api_transactions.php?action=list', { limit: 10, date_from: dateFrom, date_to: dateTo });
    const tbody = document.getElementById('recent-txns-body');
    if (tbody) {
      const txns = data.transactions || [];
      if (!txns.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">No transactions for this period</td></tr>';
        return;
      }
      tbody.innerHTML = txns.map(t => `
        <tr>
          <td><span class="text-mono fw-600" style="font-size:12px">${t.txn_no}</span></td>
          <td style="color:var(--text-muted);font-size:12px">${App.formatDateTime(t.created_at)}</td>
          <td>${t.cashier?.full_name || '—'}</td>
          <td class="fw-700 text-mono">${App.money(t.total)}</td>
          <td>
            <span class="badge ${t.payment_status === 'paid' ? 'badge-success' : t.payment_status === 'voided' ? 'badge-danger' : 'badge-warning'}">
              ${t.payment_status}
            </span>
          </td>
        </tr>
      `).join('');
    }
  } catch {}
}

document.addEventListener('DOMContentLoaded', () => {
  // Set custom calendar default values
  document.getElementById('dash-from').value = App.today();
  document.getElementById('dash-to').value   = App.today();
  // Default: Today
  setDashRange('today');
});

// ── Realtime auto-refresh ──
document.addEventListener('DOMContentLoaded', () => {
  let _rtTimer;
  const debounce = (fn, ms = 1800) => { clearTimeout(_rtTimer); _rtTimer = setTimeout(fn, ms); };
  AppRealtime.onTable('transactions', () => debounce(() => loadDashboard(_dashFrom, _dashTo)));
  AppRealtime.onTable('fuel_tanks',   () => debounce(() => loadDashboard(_dashFrom, _dashTo)));
  AppRealtime.onTable('products',     () => debounce(() => loadDashboard(_dashFrom, _dashTo)));
});
</script>
