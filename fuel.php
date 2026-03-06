<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin','staff']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Fuel Management';
$activePage = 'fuel.php';
include __DIR__ . '/layout.php';
?>

<div style="display:flex;flex-direction:column;gap:24px">

  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Fuel Management</h1>
    <?php if ($profile['role'] === 'admin'): ?>
    <button class="btn btn-outline" onclick="openFuelTypeModal()">+ Fuel Type</button>
    <button class="btn btn-primary" onclick="openTankModal()">+ Fuel Tank</button>
    <?php endif; ?>
  </div>

  <!-- Fuel Types & Prices -->
  <div class="card">
    <div class="card-header">
      <span>⛽</span>
      <h3 class="card-title">Fuel Types & Prices</h3>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Fuel Type</th>
            <th>Selling Price/L</th>
            <th>Cost/L</th>
            <th>Margin/L</th>
            <th>Status</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody id="fuel-types-tbody">
          <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tank Levels -->
  <div class="card">
    <div class="card-header">
      <span>🏗️</span>
      <h3 class="card-title">Tank Levels</h3>
    </div>
    <div id="tanks-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;padding:20px">
      <div style="color:var(--text-muted);font-size:13px">Loading tanks...</div>
    </div>
  </div>

  <!-- Recent Fuel Activity -->
  <div class="card">
    <div class="card-header">
      <span>📋</span>
      <h3 class="card-title">Recent Tank Activity</h3>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Tank</th><th>Change</th><th>Reason</th><th>Reference</th><th>By</th><th>Date</th></tr>
        </thead>
        <tbody id="tank-logs-tbody">
          <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
let fuelTypes = [];
let fuelTanks = [];

async function loadFuelData() {
  try {
    const [typesData, tanksData, logsData] = await Promise.all([
      App.get('/api_fuel.php?action=types'),
      App.get('/api_fuel.php?action=tanks'),
      App.get('/api_fuel.php?action=tank_logs', { limit: 30 }),
    ]);

    fuelTypes = typesData.fuel_types || [];
    fuelTanks = tanksData.tanks || [];

    // Render fuel types table
    const tbody = document.getElementById('fuel-types-tbody');
    tbody.innerHTML = fuelTypes.map(ft => {
      const margin = (ft.price_per_liter - ft.cost_per_liter).toFixed(4);
      return `
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <span style="width:12px;height:12px;border-radius:50%;background:${ft.color};display:inline-block;flex-shrink:0"></span>
              <span class="fw-600">${ft.name}</span>
            </div>
          </td>
          <td class="fw-700 text-mono" style="color:var(--accent)">${App.money(ft.price_per_liter)}</td>
          <td class="text-mono">${App.money(ft.cost_per_liter)}</td>
          <td class="text-mono ${margin >= 0 ? 'text-success' : 'text-danger'}">${App.money(margin)}</td>
          <td><span class="badge ${ft.is_active ? 'badge-success' : 'badge-gray'}">${ft.is_active ? 'Active' : 'Inactive'}</span></td>
          <td style="text-align:right">
            <button class="btn btn-sm btn-outline" onclick="openPriceModal('${ft.id}', '${ft.name}', ${ft.price_per_liter}, ${ft.cost_per_liter})">💲 Price</button>
            <button class="btn btn-sm btn-outline" style="margin-left:4px" onclick="openFuelTypeModal('${ft.id}')">✏️ Edit</button>
            <button class="btn btn-sm btn-danger" style="margin-left:4px" onclick="deleteFuelType('${ft.id}', '${ft.name.replace(/'/g, "\\'")}')">🗑️ Del</button>
          </td>
        </tr>`;
    }).join('') || '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">No fuel types configured</td></tr>';

    // Render tanks grid
    const grid = document.getElementById('tanks-grid');
    if (!fuelTanks.length) {
      grid.innerHTML = '<div style="color:var(--text-muted);font-size:13px">No tanks configured. <a href="#" onclick="openTankModal()">Add a tank</a></div>';
    } else {
      grid.innerHTML = fuelTanks.map(tank => {
        const pct   = tank.capacity_liters > 0 ? Math.min(100, Math.round((tank.current_liters / tank.capacity_liters) * 100)) : 0;
        const isLow = tank.current_liters <= tank.low_level_liters;
        const color = isLow ? 'var(--danger)' : pct < 30 ? 'var(--warning)' : 'var(--success)';
        const ftColor = tank.fuel_type?.color || '#888';
        const tankJson = JSON.stringify(tank).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
        return `
          <div class="card" style="padding:16px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:14px">${tank.tank_name}</div>
                <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-secondary);margin-top:2px">
                  <span style="width:8px;height:8px;border-radius:50%;background:${ftColor};display:inline-block"></span>
                  ${tank.fuel_type?.name || '—'}
                  ${tank.pump_numbers ? '· Pumps: ' + tank.pump_numbers : ''}
                </div>
              </div>
              ${isLow ? '<span class="badge badge-danger">⚠️ LOW</span>' : ''}
            </div>
            <div style="background:var(--surface2);border-radius:99px;height:12px;overflow:hidden;margin-bottom:8px">
              <div style="width:${pct}%;height:100%;background:${color};border-radius:99px;transition:width .5s ease;position:relative">
                <div style="position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2))"></div>
              </div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px">
              <span class="text-mono fw-600" style="color:${color}">${tank.current_liters.toLocaleString()}L</span>
              <span style="color:var(--text-muted)">/ ${tank.capacity_liters.toLocaleString()}L (${pct}%)</span>
            </div>
            <div style="display:flex;gap:8px;margin-top:12px">
              <button class="btn btn-sm btn-success" style="flex:1" onclick="openRefillModal('${tank.id}', '${tank.tank_name}', ${tank.current_liters}, ${tank.capacity_liters})">
                📥 Refill
              </button>
              <button class="btn btn-sm btn-outline" title="Edit Tank" onclick='openTankModal(${JSON.stringify(tank)})'>
                ✏️ Edit
              </button>
              <button class="btn btn-sm btn-danger" title="Delete Tank" onclick="deleteTank('${tank.id}', '${tank.tank_name.replace(/'/g, "\\'")}')">🗑️ Delete</button>
            </div>
          </div>`;
      }).join('');
    }

    // Render logs
    const logs = logsData.logs || [];
    const logsTbody = document.getElementById('tank-logs-tbody');
    logsTbody.innerHTML = logs.length
      ? logs.map(log => `
          <tr>
            <td class="fw-600" style="font-size:13px">${log.tank?.tank_name || log.tank_id}</td>
            <td>
              <span class="fw-700 text-mono ${log.liters_change > 0 ? 'text-success' : 'text-danger'}">
                ${log.liters_change > 0 ? '+' : ''}${log.liters_change}L
              </span>
            </td>
            <td style="text-transform:capitalize">${log.reason}</td>
            <td class="text-mono" style="font-size:12px">${log.reference_no || '—'}</td>
            <td style="font-size:12px">${log.created_by_profile?.full_name || '—'}</td>
            <td style="font-size:12px;color:var(--text-muted)">${App.formatDateTime(log.created_at)}</td>
          </tr>`)
        .join('')
      : '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">No tank activity yet</td></tr>';

  } catch (err) {
    App.toast('Failed to load fuel data: ' + err.message, 'error');
  }
}

function openPriceModal(id, name, currentPrice, currentCost) {
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title">Update Price: ${name}</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
      <div class="alert-strip warning">⚠️ Changing price affects all future sales. Current: <strong>${App.money(currentPrice)}/L</strong></div>
      <div>
        <label class="form-label">New Selling Price / Liter</label>
        <input type="number" class="form-control" id="new-price" value="${currentPrice}" min="0" step="0.0001"
          style="font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:center;padding:12px">
      </div>
      <div>
        <label class="form-label">Cost Price / Liter</label>
        <input type="number" class="form-control" id="new-cost" value="${currentCost}" min="0" step="0.0001">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-primary" id="save-price-btn" onclick="saveFuelPrice('${id}')">Update Price</button>
    </div>
  `, 'sm');
}

async function saveFuelPrice(id) {
  const btn   = document.getElementById('save-price-btn');
  const price = parseFloat(document.getElementById('new-price').value);
  const cost  = parseFloat(document.getElementById('new-cost').value) || 0;
  if (!price || price <= 0) { App.toast('Invalid price', 'error'); return; }

  const confirmed = await App.confirm(
    `Update fuel price to <strong>${App.money(price)}/L</strong>?<br><small style="color:var(--text-muted)">This will affect all future sales.</small>`,
    'Confirm Price Change',
    { icon: 'warning', confirmText: 'Yes, update price', confirmColor: '#f97316' }
  );
  if (!confirmed) return;

  App.loading.show(btn, 'Saving...');
  try {
    await App.post('/api_fuel.php?action=update_price', { id, price_per_liter: price, cost_per_liter: cost });
    App.modal.close();
    App.toast('Fuel price updated', 'success');
    loadFuelData();
  } catch (err) { App.toast(err.message, 'error'); App.loading.hide(btn); }
}

function openRefillModal(tankId, tankName, currentLiters, capacityLiters) {
  const maxRefill = capacityLiters - currentLiters;
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title">📥 Refill: ${tankName}</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:10px;background:var(--surface2);border-radius:var(--radius)">
        <span>Current Level</span><span class="fw-700 text-mono">${currentLiters.toLocaleString()}L / ${capacityLiters.toLocaleString()}L</span>
      </div>
      <div>
        <label class="form-label">Liters to Add (max: ${maxRefill.toFixed(0)}L)</label>
        <input type="number" class="form-control" id="refill-liters" value="${maxRefill.toFixed(0)}" min="1" max="${maxRefill}" step="0.001"
          style="font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:center;padding:12px">
      </div>
      <div>
        <label class="form-label">Reference / Delivery Note</label>
        <input type="text" class="form-control" id="refill-ref" placeholder="DR-12345">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-success" id="refill-btn" onclick="confirmRefill('${tankId}')">📥 Confirm Refill</button>
    </div>
  `, 'sm');
}

async function confirmRefill(tankId) {
  const btn    = document.getElementById('refill-btn');
  const liters = parseFloat(document.getElementById('refill-liters').value);
  const ref    = document.getElementById('refill-ref').value;
  if (!liters || liters <= 0) { App.toast('Enter valid liters', 'error'); return; }
  App.loading.show(btn, 'Processing...');
  try {
    const result = await App.post('/api_fuel.php?action=refill', { tank_id: tankId, liters, reason: 'delivery', reference_no: ref });
    App.modal.close();
    App.toast(`Tank refilled! New level: ${result.new_level.toLocaleString()}L`, 'success');
    loadFuelData();
  } catch (err) { App.toast(err.message, 'error'); App.loading.hide(btn); }
}

function openFuelTypeModal(editId = null) {
  const ft = editId ? fuelTypes.find(f => f.id === editId) : null;
  const isEdit = ft !== null;
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title">${isEdit ? 'Edit' : 'Add'} Fuel Type</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div style="grid-column:1/-1">
        <label class="form-label">Fuel Type Name *</label>
        <input type="text" class="form-control" id="ft-name" placeholder="e.g. Diesel, Premium 97" value="${isEdit ? ft.name : ''}">
      </div>
      <div>
        <label class="form-label">Selling Price / Liter</label>
        <input type="number" class="form-control" id="ft-price" min="0" step="0.0001" placeholder="0.0000" value="${isEdit ? ft.price_per_liter : ''}">
      </div>
      <div>
        <label class="form-label">Cost Price / Liter</label>
        <input type="number" class="form-control" id="ft-cost" min="0" step="0.0001" placeholder="0.0000" value="${isEdit ? ft.cost_per_liter : ''}">
      </div>
      <div>
        <label class="form-label">Color Tag</label>
        <input type="color" class="form-control" id="ft-color" value="${isEdit ? ft.color : '#3b82f6'}" style="height:40px;cursor:pointer">
      </div>
      ${isEdit ? `<div>
        <label class="form-label">Status</label>
        <select class="form-select" id="ft-active">
          <option value="true" ${ft.is_active ? 'selected' : ''}>Active</option>
          <option value="false" ${!ft.is_active ? 'selected' : ''}>Inactive</option>
        </select>
      </div>` : ''}
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-primary" id="save-ft-btn" onclick="saveFuelType('${isEdit ? ft.id : ''}')">${isEdit ? 'Save Changes' : 'Add Fuel Type'}</button>
    </div>
  `, 'sm');
}

async function saveFuelType(editId) {
  const btn  = document.getElementById('save-ft-btn');
  const name = document.getElementById('ft-name').value.trim();
  if (!name) { App.toast('Name required', 'error'); return; }

  const payload = {
    name,
    price_per_liter: parseFloat(document.getElementById('ft-price').value) || 0,
    cost_per_liter:  parseFloat(document.getElementById('ft-cost').value) || 0,
    color:           document.getElementById('ft-color').value,
  };

  App.loading.show(btn, 'Saving...');
  try {
    if (editId) {
      payload.id = editId;
      payload.is_active = document.getElementById('ft-active').value === 'true';
      await App.post('/api_fuel.php?action=update_type', payload);
      App.toast('Fuel type updated', 'success');
    } else {
      await App.post('/api_fuel.php?action=create_type', payload);
      App.toast('Fuel type added', 'success');
    }
    App.modal.close();
    loadFuelData();
  } catch (err) { App.toast(err.message, 'error'); App.loading.hide(btn); }
}

async function deleteFuelType(id, name) {
  const confirmed = await App.confirm(
    `Delete fuel type <strong>${name}</strong>?<br><small style="color:var(--text-muted)">This will deactivate it. Existing tanks and transactions won't be affected.</small>`,
    'Delete Fuel Type',
    true
  );
  if (!confirmed) return;
  try {
    await App.post('/api_fuel.php?action=delete_type', { id });
    App.toast('Fuel type deleted', 'success');
    loadFuelData();
  } catch (err) { App.toast(err.message, 'error'); }
}

function openTankModal(tank = null) {
  const isEdit = tank !== null;
  const title  = isEdit ? `Edit Tank: ${tank.tank_name}` : 'Add Fuel Tank';

  // Fetch ALL fuel types fresh (including inactive) so dropdown is never empty
  App.get('/api_fuel.php?action=types', { active: 'false' }).then(res => {
    const allTypes = res.fuel_types || fuelTypes;
    const opts = allTypes.map(f =>
      `<option value="${f.id}" ${isEdit && tank.fuel_type_id === f.id ? 'selected' : ''}>${f.name}</option>`
    ).join('');

    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">${title}</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <input type="hidden" id="tk-id" value="${isEdit ? tank.id : ''}">
        <div style="grid-column:1/-1">
          <label class="form-label">Tank Name *</label>
          <input type="text" class="form-control" id="tk-name" placeholder="e.g. Tank A - Diesel"
            value="${isEdit ? tank.tank_name : ''}">
        </div>
        <div style="grid-column:1/-1">
          <label class="form-label">Fuel Type *</label>
          <select class="form-select" id="tk-fuel-type">
            <option value="">— Select Fuel Type —</option>
            ${opts || '<option disabled>No fuel types found — add one first</option>'}
          </select>
        </div>
        <div>
          <label class="form-label">Capacity (Liters)</label>
          <input type="number" class="form-control" id="tk-capacity" min="0" placeholder="10000"
            value="${isEdit ? tank.capacity_liters : ''}">
        </div>
        ${!isEdit ? `
        <div>
          <label class="form-label">Initial Level (Liters)</label>
          <input type="number" class="form-control" id="tk-current" min="0" placeholder="0" value="0">
        </div>` : '<div></div>'}
        <div>
          <label class="form-label">Low Level Alert (Liters)</label>
          <input type="number" class="form-control" id="tk-low" min="0" placeholder="500"
            value="${isEdit ? tank.low_level_liters : ''}">
        </div>
        <div>
          <label class="form-label">Pump Numbers</label>
          <input type="text" class="form-control" id="tk-pumps" placeholder="1, 2, 3"
            value="${isEdit ? (tank.pump_numbers || '') : ''}">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
        <button class="btn btn-primary" id="save-tk-btn" onclick="saveTank(${isEdit})">${
          isEdit ? '💾 Save Changes' : 'Add Tank'}
        </button>
      </div>
    `, 'md');
  }).catch(() => App.toast('Failed to load fuel types', 'error'));
}

async function saveTank(isEdit = false) {
  const btn  = document.getElementById('save-tk-btn');
  const id   = document.getElementById('tk-id')?.value;
  const name = document.getElementById('tk-name').value.trim();
  const ftId = document.getElementById('tk-fuel-type').value;
  if (!name || !ftId) { App.toast('Tank name and fuel type are required', 'error'); return; }
  App.loading.show(btn, 'Saving...');
  try {
    if (isEdit && id) {
      await App.post('/api_fuel.php?action=update_tank', {
        id,
        tank_name:        name,
        fuel_type_id:     ftId,
        capacity_liters:  parseFloat(document.getElementById('tk-capacity').value) || 0,
        low_level_liters: parseFloat(document.getElementById('tk-low').value) || 500,
        pump_numbers:     document.getElementById('tk-pumps').value,
      });
      App.modal.close();
      App.toast('Tank updated', 'success');
    } else {
      await App.post('/api_fuel.php?action=create_tank', {
        tank_name:        name,
        fuel_type_id:     ftId,
        capacity_liters:  parseFloat(document.getElementById('tk-capacity').value) || 0,
        current_liters:   parseFloat(document.getElementById('tk-current').value) || 0,
        low_level_liters: parseFloat(document.getElementById('tk-low').value) || 500,
        pump_numbers:     document.getElementById('tk-pumps').value,
      });
      App.modal.close();
      App.toast('Tank added', 'success');
    }
    loadFuelData();
  } catch (err) { App.toast(err.message, 'error'); App.loading.hide(btn); }
}

async function deleteTank(tankId, tankName) {
  const ok = await App.confirm(
    `Delete tank <strong>${tankName}</strong>? This cannot be undone. Existing tank logs will remain.`,
    'Delete Tank', true
  );
  if (!ok) return;
  try {
    await App.post('/api_fuel.php?action=delete_tank', { id: tankId });
    App.toast(`Tank "${tankName}" deleted`, 'success');
    loadFuelData();
  } catch (err) { App.toast(err.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', loadFuelData);

// ── Realtime auto-refresh ──
document.addEventListener('DOMContentLoaded', () => {
  let _rtTimer;
  const debounce = (fn, ms = 1500) => { clearTimeout(_rtTimer); _rtTimer = setTimeout(fn, ms); };
  AppRealtime.onTable('fuel_tanks', () => debounce(loadFuelData));
  AppRealtime.onTable('fuel_types', () => debounce(loadFuelData));
});
</script>
