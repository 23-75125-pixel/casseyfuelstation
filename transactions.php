<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession();
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Transactions';
$activePage = 'transactions.php';
include __DIR__ . '/layout.php';
?>

<div style="display:flex;flex-direction:column;gap:20px">

  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Transactions</h1>
    <?php if ($profile['role'] === 'admin'): ?>
    <button class="btn btn-danger btn-sm" onclick="openVoidModal()">Void Transaction</button>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="card" style="padding:14px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <!-- Text search -->
      <input type="text" class="form-control" id="f-search"
        placeholder="Search TXN#, customer, plate..." style="max-width:240px"
        oninput="applyFilters()">
      <!-- Date range -->
      <input type="date" class="form-control" id="f-date-from" style="max-width:150px" onchange="clearQuickBtn()">
      <span style="color:var(--text-muted)">to</span>
      <input type="date" class="form-control" id="f-date-to" style="max-width:150px" onchange="clearQuickBtn()">
      <!-- Status -->
      <select class="form-select" id="f-status" style="max-width:130px" onchange="applyFilters()">
        <option value="">All Status</option>
        <option value="paid">Paid</option>
        <option value="voided">Voided</option>
        <option value="pending">Pending</option>
      </select>
      <button class="btn btn-primary" onclick="loadTransactions()">Search</button>
    </div>
    <!-- Quick range buttons -->
    <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;align-items:center">
      <span style="font-size:12px;color:var(--text-muted)">Quick:</span>
      <button id="tqr-today" class="btn btn-sm btn-primary"  onclick="setTxnRange('today')">Today</button>
      <button id="tqr-week"  class="btn btn-sm btn-outline"  onclick="setTxnRange('week')">This Week</button>
      <button id="tqr-month" class="btn btn-sm btn-outline"  onclick="setTxnRange('month')">This Month</button>
      <button id="tqr-year"  class="btn btn-sm btn-outline"  onclick="setTxnRange('year')">This Year</button>
      <button id="tqr-all"   class="btn btn-sm btn-outline"  onclick="setTxnRange('all')">All Time</button>
      <span id="txn-count" style="font-size:13px;color:var(--text-muted);margin-left:auto"></span>
    </div>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>TXN #</th>
            <th>Date/Time</th>
            <th>Cashier</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="txns-tbody">
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Load More -->
  <div id="load-more-wrap" style="display:none;text-align:center">
    <button class="btn btn-outline" onclick="loadTransactions(true)" id="load-more-btn">Load More Transactions</button>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
// Pagination state
let _allTxns    = [];
let _txnOffset  = 0;
let _txnTotal   = 0;
const _txnLimit = 50;

function setTxnRange(range) {
  const today = new Date();
  const fmt   = d => d.toISOString().split('T')[0];
  let from = '', to = fmt(today);

  if (range === 'today') {
    from = to;
  } else if (range === 'week') {
    const d = new Date(today); const day = d.getDay() || 7; d.setDate(d.getDate() - day + 1); from = fmt(d);
  } else if (range === 'month') {
    from = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-01`;
  } else if (range === 'year') {
    from = `${today.getFullYear()}-01-01`;
  } else if (range === 'all') {
    from = ''; to = '';
  }
  document.getElementById('f-date-from').value = from;
  document.getElementById('f-date-to').value   = to;
  ['today','week','month','year','all'].forEach(r => {
    const btn = document.getElementById('tqr-' + r);
    if (btn) btn.className = `btn btn-sm ${r === range ? 'btn-primary' : 'btn-outline'}`;
  });
  loadTransactions(false);
}

function clearQuickBtn() {
  ['today','week','month','year','all'].forEach(r => {
    const btn = document.getElementById('tqr-' + r);
    if (btn) btn.className = 'btn btn-sm btn-outline';
  });
}

// Set default date range
setTxnRange('today');

// ── Realtime auto-refresh ──
let _rtTxnTimer;
AppRealtime.onTable('transactions', () => {
  clearTimeout(_rtTxnTimer);
  _rtTxnTimer = setTimeout(() => loadTransactions(false), 1800);
});

async function loadTransactions(append = false) {
  if (!append) {
    _txnOffset = 0;
    _allTxns   = [];
  }

  const params = { offset: _txnOffset, limit: _txnLimit };
  const from = document.getElementById('f-date-from').value;
  const to   = document.getElementById('f-date-to').value;
  if (from) params.date_from = from;
  if (to)   params.date_to   = to;

  const loadMoreBtn = document.getElementById('load-more-btn');
  if (loadMoreBtn) { loadMoreBtn.disabled = true; loadMoreBtn.textContent = 'Loading...'; }

  try {
    const data  = await App.get('/api_transactions.php?action=list', params);
    const batch = data.transactions || [];
    _txnTotal  = data.total  ?? (_txnOffset + batch.length);
    _txnOffset += batch.length;
    _allTxns    = append ? _allTxns.concat(batch) : batch;
    applyFilters();

    const wrap = document.getElementById('load-more-wrap');
    if (wrap) wrap.style.display = _allTxns.length < _txnTotal ? '' : 'none';
  } catch (err) {
    App.toast('Failed to load transactions: ' + err.message, 'error');
  } finally {
    if (loadMoreBtn) { loadMoreBtn.disabled = false; loadMoreBtn.textContent = 'Load More Transactions'; }
  }
}

function applyFilters() {
  const search = (document.getElementById('f-search')?.value || '').toLowerCase().trim();
  const status = document.getElementById('f-status')?.value || '';

  let txns = _allTxns;
  if (status) txns = txns.filter(t => t.payment_status === status);
  if (search) txns = txns.filter(t =>
    (t.txn_no || '').toLowerCase().includes(search) ||
    (t.customer_name || '').toLowerCase().includes(search) ||
    (t.vehicle_plate || '').toLowerCase().includes(search) ||
    (t.cashier?.full_name || '').toLowerCase().includes(search)
  );

  const shownLabel = txns.length < _allTxns.length
    ? `${txns.length} filtered / ${_allTxns.length} loaded`
    : `${_allTxns.length} of ${_txnTotal} transactions`;
  document.getElementById('txn-count').textContent = shownLabel;

  const tbody = document.getElementById('txns-tbody');
  if (!txns.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No transactions found</td></tr>';
    return;
  }

  tbody.innerHTML = txns.map(t => {
    // Payment method badges from joined payments
    const pays = t.payments || [];
    const payLabel = pays.length
      ? pays.map(p => `<span class="badge badge-gray" style="font-size:10px;text-transform:capitalize">${p.method}</span>`).join(' ')
      : '<span style="color:var(--text-muted);font-size:12px">—</span>';

    return `
      <tr style="${t.payment_status === 'voided' ? 'opacity:.5;text-decoration:line-through' : ''}">
        <td><span class="text-mono fw-600" style="font-size:12px">${t.txn_no}</span></td>
        <td style="font-size:12px;color:var(--text-secondary);white-space:nowrap">${App.formatDateTime(t.created_at)}</td>
        <td style="font-size:13px">${t.cashier?.full_name || '—'}</td>
        <td style="font-size:13px;color:var(--text-secondary)">${t.customer_name || (t.vehicle_plate ? '🚗 ' + t.vehicle_plate : '—')}</td>
        <td class="fw-700 text-mono">${App.money(t.total)}</td>
        <td>${payLabel}</td>
        <td>
          <span class="badge ${t.payment_status === 'paid' ? 'badge-success' : t.payment_status === 'voided' ? 'badge-danger' : 'badge-warning'}">
            ${t.payment_status}
          </span>
        </td>
        <td>
          <button class="btn btn-sm btn-outline" onclick="viewTransaction('${t.id}')">View</button>
          <?php if ($profile['role'] === 'admin'): ?>
          ${t.payment_status === 'paid' ? `<button class="btn btn-sm btn-danger" style="margin-left:4px" onclick="voidTransaction('${t.id}', '${t.txn_no}')">Void</button>` : ''}
          <?php endif; ?>
        </td>
      </tr>`;
  }).join('');
}

async function viewTransaction(id) {
  try {
    const data = await App.get('/api_transactions.php?action=get', { id });
    const txn  = data.transaction;
    const items = data.items || [];
    const pays  = data.payments || [];

    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">🧾 ${txn.txn_no}</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
          <div><span style="color:var(--text-muted)">Date:</span> <strong>${App.formatDateTime(txn.created_at)}</strong></div>
          <div><span style="color:var(--text-muted)">Status:</span>
            <span class="badge ${txn.payment_status === 'paid' ? 'badge-success' : 'badge-danger'}">${txn.payment_status}</span>
          </div>
          ${txn.customer_name ? `<div><span style="color:var(--text-muted)">Customer:</span> <strong>${txn.customer_name}</strong></div>` : ''}
          ${txn.vehicle_plate ? `<div><span style="color:var(--text-muted)">Plate:</span> <strong>${txn.vehicle_plate}</strong></div>` : ''}
          ${txn.void_reason ? `<div style="grid-column:1/-1"><span style="color:var(--text-muted)">Void reason:</span> <strong style="color:var(--danger)">${txn.void_reason}</strong></div>` : ''}
        </div>

        <div>
          <div style="font-weight:700;font-size:13px;margin-bottom:8px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Items</div>
          ${items.map(i => `
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);font-size:13px">
              <div>
                <div class="fw-600">${i.item_type === 'fuel' ? (i.fuel?.name || 'Fuel') + ' ⛽' : (i.product?.name || 'Product')}</div>
                <div style="color:var(--text-muted);font-size:11.5px">
                  ${i.item_type === 'fuel' ? App.num(i.qty,3) + 'L × ' + App.money(i.unit_price) + '/L' : i.qty + ' ' + (i.product?.unit||'pcs') + ' × ' + App.money(i.unit_price)}
                  ${i.pump_number ? ' · Pump ' + i.pump_number : ''}
                  ${i.discount > 0 ? ' · <span style="color:var(--success)">-' + App.money(i.discount) + '</span>' : ''}
                </div>
              </div>
              <div class="fw-700 text-mono">${App.money(i.line_total)}</div>
            </div>`).join('')}
        </div>

        <div style="background:var(--surface2);padding:14px;border-radius:var(--radius)">
          <div style="display:flex;justify-content:space-between;font-size:13px;padding:3px 0"><span style="color:var(--text-secondary)">Subtotal</span><span class="text-mono">${App.money(txn.subtotal)}</span></div>
          ${txn.discount_total > 0 ? `<div style="display:flex;justify-content:space-between;font-size:13px;padding:3px 0"><span style="color:var(--text-secondary)">Discount</span><span class="text-mono text-success">-${App.money(txn.discount_total)}</span></div>` : ''}
          ${txn.tax_total > 0 ? `<div style="display:flex;justify-content:space-between;font-size:13px;padding:3px 0"><span style="color:var(--text-secondary)">Tax</span><span class="text-mono">${App.money(txn.tax_total)}</span></div>` : ''}
          <div style="display:flex;justify-content:space-between;font-size:17px;font-weight:800;padding:8px 0 0;border-top:1px solid var(--border);margin-top:6px"><span>Total</span><span class="text-mono">${App.money(txn.total)}</span></div>
        </div>

        <div>
          <div style="font-weight:700;font-size:13px;margin-bottom:8px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Payments</div>
          ${pays.map(p => `
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--border-light)">
              <span style="text-transform:capitalize;font-weight:600">${p.method} ${p.reference_no ? '· <span style="color:var(--text-muted)">' + p.reference_no + '</span>' : ''}</span>
              <span class="fw-700 text-mono">${App.money(p.amount)}</span>
            </div>`).join('')}
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Close</button>
      </div>
    `, 'md');
  } catch (err) {
    App.toast('Failed to load transaction: ' + err.message, 'error');
  }
}

async function voidTransaction(id, txnNo) {
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title" style="color:var(--danger)">⚠️ Void Transaction</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
      <div class="alert-strip danger">You are about to void <strong>${txnNo}</strong>. This action cannot be undone.</div>
      <div>
        <label class="form-label">Void Reason *</label>
        <input type="text" class="form-control" id="void-reason" placeholder="Enter reason for voiding...">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-danger" id="void-confirm-btn" onclick="confirmVoid('${id}')">Confirm Void</button>
    </div>
  `, 'sm');
}

async function confirmVoid(id) {
  const reason = document.getElementById('void-reason').value.trim();
  if (!reason) { App.toast('Please enter a void reason', 'error'); return; }
  const btn = document.getElementById('void-confirm-btn');
  App.loading.show(btn, 'Voiding...');
  try {
    await App.post('/api_transactions.php?action=void', { transaction_id: id, reason });
    App.modal.close();
    App.toast('Transaction voided successfully', 'success');
    loadTransactions();
  } catch (err) {
    App.toast(err.message, 'error');
    App.loading.hide(btn);
  }
}
</script>
