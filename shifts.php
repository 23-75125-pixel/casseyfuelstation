<?php
// public/shifts.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession();
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Shifts';
$activePage = 'shifts.php';
include __DIR__ . '/layout.php';
?>
<div style="display:flex;flex-direction:column;gap:20px">

  <div style="display:flex;align-items:center;gap:12px">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Shifts</h1>
    <button class="btn btn-success" onclick="openShiftAction()">Shift Action</button>
  </div>

  <!-- Active shift banner -->
  <div id="active-shift-banner" class="hidden"></div>

  <!-- Shifts table -->
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Cashier</th><th>Opened</th><th>Closed</th><th>Opening Cash</th><th>Closing Cash</th><th>Expected</th><th>Variance</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody id="shifts-tbody">
          <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php include __DIR__ . '/layout_end.php'; ?>
<script src="/pos.js"></script>
<script>
// Prevent POS from running its full init on this non-POS page.
// We only need the shift open/close modal logic from POS.
window._POS_SKIP_INIT = true;

// Override POS shift confirm methods so shifts list reloads after action
document.addEventListener('DOMContentLoaded', () => {
  // Wrap confirmOpenShift
  const _origOpen  = POS.confirmOpenShift.bind(POS);
  POS.confirmOpenShift = async function() {
    await _origOpen();
    loadShifts();
  };

  // Wrap confirmCloseShift
  const _origClose = POS.confirmCloseShift.bind(POS);
  POS.confirmCloseShift = async function() {
    await _origClose();
    loadShifts();
  };
});
async function loadShifts() {
  try {
    // Check active shift
    const activeData = await App.get('/api_shifts.php?action=active');
    const banner     = document.getElementById('active-shift-banner');
    if (activeData.shift) {
      banner.className = 'alert-strip info';
      banner.innerHTML = `⏳ <strong>Active Shift</strong> opened at ${App.formatDateTime(activeData.shift.opened_at)} · Opening Cash: <strong>${App.money(activeData.shift.opening_cash)}</strong>`;
    } else {
      banner.className = 'alert-strip warning';
      banner.innerHTML = '⚠️ No active shift. <a href="/pos.php" style="font-weight:700">Open POS</a> to start a shift.';
    }

    // Load shifts list
    const data   = await App.get('/api_shifts.php?action=list', { limit: 30 });
    const shifts = data.shifts || [];
    const tbody  = document.getElementById('shifts-tbody');

    if (!shifts.length) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No shifts yet</td></tr>';
      return;
    }

    tbody.innerHTML = shifts.map(s => {
      const variance  = parseFloat(s.variance || 0);
      const varClass  = variance === 0 ? '' : variance > 0 ? 'text-success' : 'text-danger';
      return `
        <tr>
          <td class="fw-600">${s.cashier?.full_name || '—'}</td>
          <td style="font-size:12px">${App.formatDateTime(s.opened_at)}</td>
          <td style="font-size:12px;color:var(--text-muted)">${s.closed_at ? App.formatDateTime(s.closed_at) : '—'}</td>
          <td class="text-mono">${App.money(s.opening_cash)}</td>
          <td class="text-mono">${s.closing_cash != null ? App.money(s.closing_cash) : '—'}</td>
          <td class="text-mono">${s.expected_cash != null ? App.money(s.expected_cash) : '—'}</td>
          <td class="text-mono fw-700 ${varClass}">${s.variance != null ? (variance >= 0 ? '+' : '') + App.money(variance) : '—'}</td>
          <td><span class="badge ${s.status === 'open' ? 'badge-success' : 'badge-gray'}">${s.status}</span></td>
          <td>
            <button class="btn btn-sm btn-outline" onclick="viewShiftSummary('${s.id}')">Summary</button>
          </td>
        </tr>`;
    }).join('');
  } catch (err) {
    App.toast('Failed to load shifts: ' + err.message, 'error');
  }
}

async function openShiftAction() {
  const activeData = await App.get('/api_shifts.php?action=active');
  if (activeData.shift) {
    POS.activeShift = activeData.shift;
    POS.closeShift();
  } else {
    POS.openShift();
  }
}

async function viewShiftSummary(shiftId) {
  try {
    const data = await App.get('/api_shifts.php?action=summary', { shift_id: shiftId });
    const shift = data.shift;
    const payBreak = data.payment_breakdown || {};

    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">📊 Shift Summary</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
          <div><span style="color:var(--text-muted)">Cashier:</span> <strong>${shift.cashier?.full_name}</strong></div>
          <div><span style="color:var(--text-muted)">Status:</span> <span class="badge ${shift.status === 'open' ? 'badge-success' : 'badge-gray'}">${shift.status}</span></div>
          <div><span style="color:var(--text-muted)">Opened:</span> <strong>${App.formatDateTime(shift.opened_at)}</strong></div>
          <div><span style="color:var(--text-muted)">Closed:</span> <strong>${shift.closed_at ? App.formatDateTime(shift.closed_at) : 'Still open'}</strong></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
          <div class="stat-card" style="padding:12px"><div class="stat-label">Total Sales</div><div class="stat-value" style="font-size:16px">${App.money(data.total_sales)}</div></div>
          <div class="stat-card" style="padding:12px"><div class="stat-label">Transactions</div><div class="stat-value" style="font-size:16px">${data.txn_count}</div></div>
          <div class="stat-card" style="padding:12px"><div class="stat-label">Variance</div>
            <div class="stat-value" style="font-size:16px;color:${shift.variance > 0 ? 'var(--success)' : shift.variance < 0 ? 'var(--danger)' : 'inherit'}">${App.money(shift.variance || 0)}</div>
          </div>
        </div>
        <div>
          <div style="font-weight:700;font-size:13px;margin-bottom:8px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Payment Breakdown</div>
          ${Object.entries(payBreak).map(([m, a]) => `
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:13px">
              <span style="text-transform:capitalize;font-weight:600">${m}</span>
              <span class="fw-700 text-mono">${App.money(a)}</span>
            </div>`).join('') || '<div style="color:var(--text-muted);font-size:13px">No payments</div>'}
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:13px;padding:12px;background:var(--surface2);border-radius:var(--radius)">
          <div><span style="color:var(--text-muted)">Opening Cash</span><div class="fw-700 text-mono">${App.money(shift.opening_cash)}</div></div>
          <div><span style="color:var(--text-muted)">Expected Cash</span><div class="fw-700 text-mono">${App.money(shift.expected_cash || 0)}</div></div>
          <div><span style="color:var(--text-muted)">Closing Cash</span><div class="fw-700 text-mono">${shift.closing_cash != null ? App.money(shift.closing_cash) : '—'}</div></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Close</button>
      </div>
    `, 'md');
  } catch (err) {
    App.toast('Failed to load summary: ' + err.message, 'error');
  }
}

document.addEventListener('DOMContentLoaded', loadShifts);

// ── Realtime auto-refresh ──
document.addEventListener('DOMContentLoaded', () => {
  let _rtTimer;
  const debounce = (fn, ms = 1500) => { clearTimeout(_rtTimer); _rtTimer = setTimeout(fn, ms); };
  AppRealtime.onTable('transactions', () => debounce(loadShifts));
});
</script>
