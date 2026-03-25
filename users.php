<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Users & Roles';
$activePage = 'users.php';
include __DIR__ . '/layout.php';
?>

<div style="display:flex;flex-direction:column;gap:20px">

  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Users & Roles</h1>
    <button class="btn btn-primary" onclick="openUserModal()">+ Add User</button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Role</th><th>Status</th><th>Created</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody id="users-tbody">
          <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Role permissions reference -->
  <div class="card">
    <div class="card-header"><span>🔑</span><h3 class="card-title">Role Permissions</h3></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
        <div style="padding:16px;background:var(--surface2);border-radius:var(--radius);border-left:4px solid var(--danger)">
          <div style="font-weight:700;font-size:14px;margin-bottom:8px">👑 Admin (Level 3)</div>
          <ul style="margin:0;padding-left:16px;font-size:12.5px;line-height:1.8;color:var(--text-secondary)">
            <li>Full system access</li>
            <li>Manage users & roles</li>
            <li>Configure settings</li>
            <li>Void/refund transactions</li>
            <li>All reports & audit logs</li>
          </ul>
        </div>
        <div style="padding:16px;background:var(--surface2);border-radius:var(--radius);border-left:4px solid var(--info)">
          <div style="font-weight:700;font-size:14px;margin-bottom:8px">🧑‍💼 Staff (Level 2)</div>
          <ul style="margin:0;padding-left:16px;font-size:12.5px;line-height:1.8;color:var(--text-secondary)">
            <li>Inventory management</li>
            <li>Stock receiving</li>
            <li>Supplier records</li>
            <li>View reports (limited)</li>
            <li>Cannot manage users</li>
          </ul>
        </div>
        <div style="padding:16px;background:var(--surface2);border-radius:var(--radius);border-left:4px solid var(--success)">
          <div style="font-weight:700;font-size:14px;margin-bottom:8px">🧑‍💻 Cashier (Level 1)</div>
          <ul style="margin:0;padding-left:16px;font-size:12.5px;line-height:1.8;color:var(--text-secondary)">
            <li>POS sales only</li>
            <li>Open/close shifts</li>
            <li>Accept payments</li>
            <li>Print receipts</li>
            <li>View own transactions</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
async function loadUsers() {
  try {
    const data  = await App.get('/api_users.php?action=list');
    const users = data.users || [];
    const tbody = document.getElementById('users-tbody');
    if (!users.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">No users found</td></tr>';
      return;
    }
    const roleColors = { admin: 'badge-danger', staff: 'badge-info', cashier: 'badge-success' };
    tbody.innerHTML = users.map(u => `
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">
              ${u.full_name.charAt(0).toUpperCase()}
            </div>
            <div>
              <div class="fw-600">${u.full_name}</div>
              <div style="font-size:11.5px;color:var(--text-muted)">ID: ${u.id.substring(0,8)}...</div>
            </div>
          </div>
        </td>
        <td><span class="badge ${roleColors[u.role] || 'badge-gray'}" style="text-transform:capitalize">${u.role}</span></td>
        <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-gray'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
        <td style="font-size:12px;color:var(--text-muted)">${App.formatDate(u.created_at)}</td>
        <td style="text-align:right">
          <button class="btn btn-sm btn-outline" onclick="openUserModal(${JSON.stringify(u).replace(/"/g, '&quot;')})">Edit</button>
        </td>
      </tr>`).join('');
  } catch (err) {
    App.toast('Failed to load users: ' + err.message, 'error');
  }
}

function openUserModal(user = null) {
  const isEdit = !!user;
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title">${isEdit ? 'Edit User' : 'Add User'}</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
      ${!isEdit ? `
      <div class="alert-strip info">ℹ️ Create the user in <strong>Supabase Auth Dashboard</strong> first, then enter their UUID below to link their profile.</div>
      <div>
        <label class="form-label">Supabase User UUID *</label>
        <input type="text" class="form-control" id="u-id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
      </div>
      ` : `<input type="hidden" id="u-id" value="${user.id}">`}
      <div>
        <label class="form-label">Full Name *</label>
        <input type="text" class="form-control" id="u-name" value="${user?.full_name || ''}" placeholder="Juan Dela Cruz">
      </div>
      <div>
        <label class="form-label">Phone</label>
        <input type="text" class="form-control" id="u-phone" value="${user?.phone || ''}" placeholder="+63 9XX XXX XXXX">
      </div>
      <div>
        <label class="form-label">Role *</label>
        <select class="form-select" id="u-role">
          <option value="cashier" ${user?.role === 'cashier' ? 'selected' : ''}>Cashier (Level 1)</option>
          <option value="staff"   ${user?.role === 'staff'   ? 'selected' : ''}>Staff (Level 2)</option>
          <option value="admin"   ${user?.role === 'admin'   ? 'selected' : ''}>Admin (Level 3)</option>
        </select>
      </div>
      ${isEdit ? `
      <div>
        <label class="form-label">Status</label>
        <select class="form-select" id="u-active">
          <option value="true"  ${user.is_active ? 'selected' : ''}>Active</option>
          <option value="false" ${!user.is_active ? 'selected' : ''}>Inactive</option>
        </select>
      </div>` : ''}
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-primary" id="save-user-btn" onclick="saveUser(${isEdit})">
        ${isEdit ? 'Save Changes' : 'Create Profile'}
      </button>
    </div>
  `, 'sm');
}

async function saveUser(isEdit) {
  const btn  = document.getElementById('save-user-btn');
  const id   = document.getElementById('u-id').value.trim();
  const name = document.getElementById('u-name').value.trim();
  const role = document.getElementById('u-role').value;

  if (!id || !name || !role) { App.toast('All required fields must be filled', 'error'); return; }

  App.loading.show(btn, 'Saving...');
  try {
    const payload = { id, full_name: name, role, phone: document.getElementById('u-phone').value };
    if (isEdit) payload.is_active = document.getElementById('u-active').value === 'true';

    const action = isEdit ? 'update' : 'insert';
    // Use Supabase profiles table directly via a dedicated users API
    await App.post('/api_users.php?action=' + action, payload);
    App.modal.close();
    App.toast(`User ${isEdit ? 'updated' : 'created'} successfully`, 'success');
    loadUsers();
  } catch (err) {
    App.toast(err.message, 'error');
    App.loading.hide(btn);
  }
}

document.addEventListener('DOMContentLoaded', loadUsers);

// ── Realtime auto-refresh ──
document.addEventListener('DOMContentLoaded', () => {
  let _rtTimer;
  const debounce = (fn, ms = 1500) => { clearTimeout(_rtTimer); _rtTimer = setTimeout(fn, ms); };
  AppRealtime.onTable('profiles', () => debounce(loadUsers));
});
</script>
