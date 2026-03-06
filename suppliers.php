<?php
// public/suppliers.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin','staff']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Suppliers';
$activePage = 'suppliers.php';
include __DIR__ . '/layout.php';
?>
<div style="display:flex;flex-direction:column;gap:20px">

  <div style="display:flex;align-items:center;gap:12px">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Suppliers</h1>
    <button class="btn btn-primary" onclick="openSupplierModal()">+ Add Supplier</button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Address</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody id="suppliers-tbody">
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php include __DIR__ . '/layout_end.php'; ?>
<script>
async function loadSuppliers() {
  try {
    const data = await App.get('/api_inventory.php?action=suppliers');
    const sups = data.suppliers || [];
    const tbody = document.getElementById('suppliers-tbody');
    tbody.innerHTML = sups.length
      ? sups.map(s => `
          <tr>
            <td class="fw-600">${s.name}</td>
            <td>${s.contact_person || '—'}</td>
            <td>${s.phone || '—'}</td>
            <td style="color:var(--info)">${s.email || '—'}</td>
            <td style="color:var(--text-secondary);font-size:12px">${s.address || '—'}</td>
            <td style="text-align:right">
              <button class="btn btn-sm btn-outline" onclick='openSupplierModal(${JSON.stringify(s).replace(/"/g,"&quot;")})'>Edit</button>
            </td>
          </tr>`)
        .join('')
      : '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No suppliers yet</td></tr>';
  } catch (err) {
    App.toast('Error loading suppliers', 'error');
  }
}

function openSupplierModal(sup = null) {
  const isEdit = !!sup;
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title">${isEdit ? 'Edit' : 'Add'} Supplier</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div style="grid-column:1/-1">
        <label class="form-label">Supplier Name *</label>
        <input type="text" class="form-control" id="sup-name" value="${sup?.name || ''}" placeholder="Company or Individual Name">
      </div>
      <div>
        <label class="form-label">Contact Person</label>
        <input type="text" class="form-control" id="sup-contact" value="${sup?.contact_person || ''}">
      </div>
      <div>
        <label class="form-label">Phone</label>
        <input type="text" class="form-control" id="sup-phone" value="${sup?.phone || ''}">
      </div>
      <div>
        <label class="form-label">Email</label>
        <input type="email" class="form-control" id="sup-email" value="${sup?.email || ''}">
      </div>
      <div>
        <label class="form-label">Address</label>
        <input type="text" class="form-control" id="sup-address" value="${sup?.address || ''}">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-primary" id="save-sup-btn" onclick="saveSupplier('${sup?.id || ''}')">
        ${isEdit ? 'Save Changes' : 'Add Supplier'}
      </button>
    </div>
  `, 'md');
}

async function saveSupplier(id) {
  const btn  = document.getElementById('save-sup-btn');
  const name = document.getElementById('sup-name').value.trim();
  if (!name) { App.toast('Supplier name required', 'error'); return; }
  App.loading.show(btn, 'Saving...');
  try {
    await App.post('/api_inventory.php?action=supplier', {
      id:             id || undefined,
      name,
      contact_person: document.getElementById('sup-contact').value,
      phone:          document.getElementById('sup-phone').value,
      email:          document.getElementById('sup-email').value,
      address:        document.getElementById('sup-address').value,
    });
    App.modal.close();
    App.toast(`Supplier ${id ? 'updated' : 'added'}`, 'success');
    loadSuppliers();
  } catch (err) {
    App.toast(err.message, 'error');
    App.loading.hide(btn);
  }
}

document.addEventListener('DOMContentLoaded', loadSuppliers);
</script>
