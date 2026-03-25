<?php
// public/audit.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Audit Log';
$activePage = 'audit.php';
include __DIR__ . '/layout.php';
?>
<div style="display:flex;flex-direction:column;gap:20px">
  <h1 style="margin:0;font-size:22px;font-weight:800">Audit Log</h1>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Time</th><th>User</th><th>Action</th><th>Table</th><th>Record</th><th>Details</th><th>IP</th></tr>
        </thead>
        <tbody id="audit-tbody">
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer" style="display:flex;justify-content:center">
      <button class="btn btn-outline" id="load-more-btn" onclick="loadMore()">Load More</button>
    </div>
  </div>
</div>
<?php include __DIR__ . '/layout_end.php'; ?>
<script>
let offset = 0;
const limit = 50;

async function loadAuditLogs(append = false) {
  try {
    const data = await App.get('/api_inventory.php?action=audit', { limit, offset });
    const logs = data.logs || [];
    const tbody = document.getElementById('audit-tbody');

    if (!append) tbody.innerHTML = '';
    if (!logs.length && !append) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No audit logs yet</td></tr>';
      return;
    }

    const actionColors = {
      login: 'badge-info', logout: 'badge-gray',
      create_transaction: 'badge-success', void_transaction: 'badge-danger',
      update_fuel_price: 'badge-warning', stock_received: 'badge-success',
      create_user: 'badge-info', update_user: 'badge-warning',
    };

    tbody.innerHTML += logs.map(l => `
      <tr>
        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">${App.formatDateTime(l.created_at)}</td>
        <td style="font-size:13px">${l.user?.full_name || '—'}</td>
        <td><span class="badge ${actionColors[l.action] || 'badge-gray'}" style="font-size:11px">${l.action}</span></td>
        <td style="font-size:12px;color:var(--text-secondary)">${l.table_name || '—'}</td>
        <td style="font-size:11px;font-family:monospace;color:var(--text-muted)">${l.record_id ? l.record_id.substring(0,8) + '...' : '—'}</td>
        <td>
          ${l.details_json ? `<button class="btn btn-sm btn-outline" onclick='showDetails(${JSON.stringify(l.details_json)})' style="font-size:11px">Details</button>` : '—'}
        </td>
        <td style="font-size:11px;color:var(--text-muted)">${l.ip_address || '—'}</td>
      </tr>`).join('');

    document.getElementById('load-more-btn').style.display = logs.length < limit ? 'none' : '';
  } catch (err) {
    App.toast('Failed to load audit logs', 'error');
  }
}

function showDetails(details) {
  App.modal.open(`
    <div class="modal-header"><h5 class="modal-title">Audit Details</h5><button class="modal-close" onclick="App.modal.close()">✕</button></div>
    <div class="modal-body">
      <pre style="background:var(--surface2);padding:16px;border-radius:var(--radius);font-size:12px;overflow-x:auto">${JSON.stringify(details, null, 2)}</pre>
    </div>
    <div class="modal-footer"><button class="btn btn-outline" onclick="App.modal.close()">Close</button></div>
  `, 'md');
}

function loadMore() { offset += limit; loadAuditLogs(true); }
document.addEventListener('DOMContentLoaded', () => loadAuditLogs());
</script>
