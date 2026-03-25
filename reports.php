<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin','staff']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Reports';
$activePage = 'reports.php';
include __DIR__ . '/layout.php';
?>

<div style="display:flex;flex-direction:column;gap:20px">

  <!-- Header + Date Filter -->
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Reports</h1>
    <!-- Quick range buttons -->
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <button id="qr-today" class="btn btn-outline btn-sm" onclick="setRange('today')">Today</button>
      <button id="qr-week"  class="btn btn-outline btn-sm" onclick="setRange('week')">This Week</button>
      <button id="qr-month" class="btn btn-outline btn-sm" onclick="setRange('month')">This Month</button>
      <button id="qr-year"  class="btn btn-outline btn-sm" onclick="setRange('year')">This Year</button>
    </div>
    <!-- Custom date range -->
    <div style="display:flex;align-items:center;gap:8px">
      <input type="date" class="form-control" id="date-from" style="max-width:160px">
      <span style="color:var(--text-muted)">→</span>
      <input type="date" class="form-control" id="date-to" style="max-width:160px">
      <button class="btn btn-primary" onclick="setRange('custom')">Apply</button>
    </div>
  </div>

  <!-- Summary Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fff7ed">💰</div>
      <div class="stat-label">Total Sales</div>
      <div class="stat-value" id="rpt-total-sales">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f0fdf4">📊</div>
      <div class="stat-label">Transactions</div>
      <div class="stat-value" id="rpt-txn-count">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7">⛽</div>
      <div class="stat-label">Fuel Revenue</div>
      <div class="stat-value" id="rpt-fuel-rev">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe">📦</div>
      <div class="stat-label">Product Revenue</div>
      <div class="stat-value" id="rpt-prod-rev">—</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    <!-- Fuel Sales by Type -->
    <div class="card">
      <div class="card-header">
        <span>⛽</span>
        <h3 class="card-title">Fuel Sales</h3>
      </div>
      <div class="card-body" id="fuel-sales-report">
        <div style="color:var(--text-muted);font-size:13px">Loading...</div>
      </div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
      <div class="card-header">
        <span>💳</span>
        <h3 class="card-title">Payment Methods</h3>
      </div>
      <div class="card-body" id="pay-methods-report">
        <div style="color:var(--text-muted);font-size:13px">Loading...</div>
      </div>
    </div>

  </div>

  <!-- Sales by Cashier -->
  <div class="card">
    <div class="card-header">
      <span>👤</span>
      <h3 class="card-title">Sales by Cashier</h3>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Cashier</th><th>Transactions</th><th>Total Sales</th><th>Avg per Txn</th></tr></thead>
        <tbody id="cashier-report-body">
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Products -->
  <div class="card">
    <div class="card-header">
      <span>📦</span>
      <h3 class="card-title">Top Selling Products</h3>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th><th>Est. Profit</th></tr></thead>
        <tbody id="product-report-body">
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Low Stock Alert -->
  <div class="card">
    <div class="card-header">
      <span>⚠️</span>
      <h3 class="card-title">Low Stock Alert</h3>
      <span class="badge badge-danger ms-auto" id="low-stock-badge">0</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Min Level</th><th>Reorder</th></tr></thead>
        <tbody id="low-stock-body">
          <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Fuel Tank Low Levels -->
  <div class="card">
    <div class="card-header">
      <span>⛽</span>
      <h3 class="card-title">Fuel Tank Low Levels</h3>
      <span class="badge badge-danger ms-auto" id="fuel-low-badge">0</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tank</th><th>Fuel Type</th><th>Current (L)</th><th>Low Alert (L)</th><th>Capacity (L)</th><th>Level</th></tr></thead>
        <tbody id="fuel-low-body">
          <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
// ── Quick range helper ────────────────────────────────────
function setRange(range) {
  const today = new Date();
  const fmt   = d => d.toISOString().split('T')[0];
  let from, to = fmt(today);

  if (range === 'today') {
    from = to;
  } else if (range === 'week') {
    // Monday of current week
    const d = new Date(today);
    const day = d.getDay() || 7;
    d.setDate(d.getDate() - day + 1);
    from = fmt(d);
  } else if (range === 'month') {
    from = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-01`;
  } else if (range === 'year') {
    from = `${today.getFullYear()}-01-01`;
  } else {
    // custom — just use current date inputs
    from = document.getElementById('date-from').value || fmt(today);
    to   = document.getElementById('date-to').value   || fmt(today);
  }

  document.getElementById('date-from').value = from;
  document.getElementById('date-to').value   = to;

  // Highlight active quick button
  ['today','week','month','year'].forEach(r => {
    const btn = document.getElementById('qr-' + r);
    if (btn) btn.className = `btn btn-sm ${r === range ? 'btn-primary' : 'btn-outline'}`;
  });

  loadReports();
}

// Set default to Today on load
document.addEventListener('DOMContentLoaded', () => {
  setRange('today');
});

// Re-highlight when custom dates change
document.getElementById('date-from')?.addEventListener('change', () => {
  ['today','week','month','year'].forEach(r => {
    const btn = document.getElementById('qr-' + r);
    if (btn) btn.className = 'btn btn-sm btn-outline';
  });
});
document.getElementById('date-to')?.addEventListener('change', () => {
  ['today','week','month','year'].forEach(r => {
    const btn = document.getElementById('qr-' + r);
    if (btn) btn.className = 'btn btn-sm btn-outline';
  });
});

async function loadReports() {
  const from = document.getElementById('date-from').value;
  const to   = document.getElementById('date-to').value;
  const params = { date_from: from, date_to: to };

  try {
    // Daily summary
    const daily = await App.get('/api_reports.php?action=daily', params);
    document.getElementById('rpt-total-sales').textContent = App.money(daily.total_sales || 0);
    document.getElementById('rpt-txn-count').textContent   = daily.txn_count || 0;

    // Payment breakdown for modal
    const payEl = document.getElementById('pay-methods-report');
    const payData = daily.payment_breakdown || {};
    const payTotal = Object.values(payData).reduce((a,b) => a+b, 0);
    payEl.innerHTML = Object.entries(payData).map(([method, amount]) => {
      const pct = payTotal > 0 ? Math.round((amount/payTotal)*100) : 0;
      const colors = { cash: 'var(--success)', card: 'var(--info)', ewallet: '#8b5cf6', charge: 'var(--warning)', other: 'var(--text-muted)' };
      return `
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;margin-bottom:5px">
            <span style="font-weight:600;font-size:13px;text-transform:capitalize">${method}</span>
            <span style="font-family:'DM Mono';font-weight:700">${App.money(amount)}</span>
          </div>
          <div style="background:var(--surface2);border-radius:99px;height:7px;overflow:hidden">
            <div style="width:${pct}%;height:100%;background:${colors[method]||'var(--accent)'};border-radius:99px"></div>
          </div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:3px">${pct}% of total</div>
        </div>`;
    }).join('') || '<div style="color:var(--text-muted);font-size:13px">No payment data</div>';
  } catch (err) { App.toast('Error loading daily data', 'error'); }

  // Fuel sales
  try {
    const fuelData = await App.get('/api_reports.php?action=fuel_sales', params);
    const fuels    = fuelData.fuel_sales || [];
    const fuelRev  = fuels.reduce((s,f) => s + f.total_sales, 0);
    document.getElementById('rpt-fuel-rev').textContent = App.money(fuelRev);

    document.getElementById('fuel-sales-report').innerHTML = fuels.length
      ? fuels.map(f => `
          <div style="margin-bottom:14px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
              <span style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:13px">
                <span style="width:10px;height:10px;border-radius:50%;background:${f.color};display:inline-block"></span>
                ${f.name}
              </span>
              <span style="font-family:'DM Mono';font-size:12px">${App.num(f.total_liters,2)}L</span>
            </div>
            <div style="font-weight:700;font-family:'DM Mono';font-size:15px">${App.money(f.total_sales)}</div>
          </div>`)
        .join('')
      : '<div style="color:var(--text-muted);font-size:13px">No fuel sales in period</div>';
  } catch {}

  // Product sales
  try {
    const prodData = await App.get('/api_reports.php?action=product_sales', params);
    const prods    = prodData.product_sales || [];
    const prodRev  = prods.reduce((s,p) => s + p.total_sales, 0);
    document.getElementById('rpt-prod-rev').textContent = App.money(prodRev);

    const tbody = document.getElementById('product-report-body');
    tbody.innerHTML = prods.length
      ? prods.slice(0,10).map(p => `
          <tr>
            <td class="fw-600">${p.name}</td>
            <td class="text-mono">${App.num(p.total_qty,2)} ${p.unit}</td>
            <td class="fw-700 text-mono">${App.money(p.total_sales)}</td>
            <td class="text-mono ${p.profit_est >= 0 ? 'text-success' : 'text-danger'}">${App.money(p.profit_est)}</td>
          </tr>`)
        .join('')
      : '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px">No product sales in period</td></tr>';
  } catch {}

  // By cashier
  try {
    const cashierData = await App.get('/api_reports.php?action=by_cashier', params);
    const cashiers    = cashierData.cashier_sales || [];
    document.getElementById('cashier-report-body').innerHTML = cashiers.length
      ? cashiers.map(c => `
          <tr>
            <td class="fw-600">${c.name}</td>
            <td>${c.txn_count}</td>
            <td class="fw-700 text-mono">${App.money(c.total_sales)}</td>
            <td class="text-mono">${App.money(c.txn_count ? c.total_sales / c.txn_count : 0)}</td>
          </tr>`)
        .join('')
      : '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px">No data</td></tr>';
  } catch {}

  // Low stock
  try {
    const lowData = await App.get('/api_reports.php?action=low_stock');
    const items   = lowData.low_stock || [];
    document.getElementById('low-stock-badge').textContent = items.length;
    document.getElementById('low-stock-body').innerHTML = items.length
      ? items.map(p => `
          <tr>
            <td class="fw-600">${p.name}</td>
            <td><span class="badge badge-gray">${p.category?.name || '—'}</span></td>
            <td><span class="fw-700 text-mono ${p.stock_qty <= 0 ? 'text-danger' : 'text-warning'}">${p.stock_qty}</span></td>
            <td class="text-mono">${p.low_stock_level}</td>
            <td><a href="/receiving.php" class="btn btn-sm btn-primary">Order Now</a></td>
          </tr>`)
        .join('')
      : '<tr><td colspan="5" style="text-align:center;color:var(--success);padding:24px">✅ All items adequately stocked</td></tr>';
  } catch {}

  // Fuel tank low levels
  try {
    const fuelLowData = await App.get('/api_reports.php?action=fuel_low_stock');
    const tanks       = fuelLowData.fuel_low_stock || [];
    document.getElementById('fuel-low-badge').textContent = tanks.length;
    document.getElementById('fuel-low-body').innerHTML = tanks.length
      ? tanks.map(t => {
          const pct   = t.capacity_liters > 0 ? Math.round((t.current_liters / t.capacity_liters) * 100) : 0;
          const color = t.current_liters <= 0 ? 'var(--danger)' : (pct < 10 ? 'var(--danger)' : 'var(--warning)');
          return `
            <tr>
              <td class="fw-600">${t.tank_name}</td>
              <td><span class="badge badge-gray">${t.fuel_type?.name || '—'}</span></td>
              <td><span class="fw-700 text-mono" style="color:${color}">${Number(t.current_liters).toLocaleString()}</span></td>
              <td class="text-mono">${Number(t.low_level_liters).toLocaleString()}</td>
              <td class="text-mono">${Number(t.capacity_liters).toLocaleString()}</td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <div style="flex:1;background:var(--surface2);border-radius:99px;height:6px;overflow:hidden;min-width:60px">
                    <div style="width:${pct}%;height:100%;background:${color};border-radius:99px"></div>
                  </div>
                  <span style="font-size:12px;font-weight:700;color:${color}">${pct}%</span>
                </div>
              </td>
            </tr>`;
        }).join('')
      : '<tr><td colspan="6" style="text-align:center;color:var(--success);padding:24px">✅ All fuel tanks above low-level threshold</td></tr>';
  } catch {}
}
</script>
