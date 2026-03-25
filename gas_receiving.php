<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw = new Middleware();
$profile = $mw->requireSession(['admin','staff']);
$sb = new Supabase();
$settings = getSettings($sb);
$pageTitle = 'Gas Receiving';
$activePage = 'gas_receiving.php';
$allowedCategories = ['LPG Tanks', 'LPG Accessories'];
include __DIR__ . '/layout.php';
?>
<div style="display:flex;flex-direction:column;gap:20px">
  <h1 style="margin:0;font-size:22px;font-weight:800">Gas Tank Inventory Receiving</h1>
  <div class="card">
    <div class="card-header"><span>📦</span><h3 class="card-title">New Gas Tank Stock Receipt</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div><label class="form-label">Supplier</label><select class="form-select" id="rcv-supplier"><option value="">— No supplier —</option></select></div>
        <div><label class="form-label">Reference / DR No.</label><input type="text" class="form-control" id="rcv-ref" placeholder="DR-12345"></div>
        <div style="grid-column:1/-1"><label class="form-label">Notes</label><textarea class="form-control" id="rcv-notes" rows="2" placeholder="Optional notes..."></textarea></div>
      </div>
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px"><span style="font-weight:700;font-size:14px">Gas Tank Items</span><button class="btn btn-sm btn-primary" onclick="addReceivingRow()">+ Add Item</button></div>
        <div class="table-wrap"><table class="table" style="min-width:600px"><thead><tr><th>Product</th><th>Qty</th><th>Unit Cost</th><th>Line Total</th><th></th></tr></thead><tbody id="rcv-items-tbody"><tr id="rcv-empty-row"><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No gas tank items added yet.</td></tr></tbody><tfoot><tr style="font-weight:700;background:var(--surface2)"><td colspan="3" style="padding:10px 14px;text-align:right">Total:</td><td style="padding:10px 14px" id="rcv-total" class="text-mono">₱0.00</td><td></td></tr></tfoot></table></div>
      </div>
      <div style="display:flex;justify-content:flex-end"><button class="btn btn-success btn-lg" id="save-receipt-btn" onclick="saveReceipt()">Save Gas Receipt & Update Stock</button></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/layout_end.php'; ?>
<script>
const allowedCategoryNames = <?= json_encode($allowedCategories) ?>;
let allProducts = [];
let rowCount = 0;
const allowedProduct = p => allowedCategoryNames.includes(p?.category?.name || '');
async function loadData() {
  try {
    const [prodData, supData] = await Promise.all([App.get('/api_products.php?action=list', { active: 'true' }), App.get('/api_inventory.php?action=suppliers')]);
    allProducts = (prodData.products || []).filter(allowedProduct);
    const supSel = document.getElementById('rcv-supplier');
    supSel.innerHTML = '<option value="">— No supplier —</option>';
    (supData.suppliers || []).forEach(s => { const o = document.createElement('option'); o.value = s.id; o.textContent = s.name; supSel.appendChild(o); });
  } catch (err) { App.toast('Failed to load data: ' + err.message, 'error'); }
}
function addReceivingRow() {
  document.getElementById('rcv-empty-row')?.remove();
  rowCount++;
  const id = `row-${rowCount}`;
  const tr = document.createElement('tr');
  tr.id = id;
  tr.innerHTML = `<td style="min-width:220px"><select class="form-select" id="${id}-product" onchange="updateRowTotal('${id}')" style="font-size:13px"><option value="">— Select Gas Product —</option>${allProducts.map(p => `<option value="${p.id}" data-price="${p.cost}">${p.name} (${p.unit})</option>`).join('')}</select></td><td style="min-width:100px"><input type="number" class="form-control" id="${id}-qty" value="1" min="0.001" step="0.001" oninput="updateRowTotal('${id}')" style="font-size:13px;text-align:center"></td><td style="min-width:120px"><input type="number" class="form-control" id="${id}-cost" value="0" min="0" step="0.01" oninput="updateRowTotal('${id}')" style="font-size:13px;text-align:right" placeholder="0.00"></td><td id="${id}-total" class="text-mono fw-600" style="font-size:13px">₱0.00</td><td><button class="btn btn-sm btn-danger" onclick="removeReceivingRow('${id}')">✕</button></td>`;
  document.getElementById('rcv-items-tbody').appendChild(tr);
  document.getElementById(`${id}-product`).addEventListener('change', function() { const price = this.options[this.selectedIndex]?.dataset?.price; if (price) document.getElementById(`${id}-cost`).value = parseFloat(price).toFixed(2); updateRowTotal(id); });
}
function updateRowTotal(id) { const qty = parseFloat(document.getElementById(`${id}-qty`)?.value) || 0; const cost = parseFloat(document.getElementById(`${id}-cost`)?.value) || 0; document.getElementById(`${id}-total`).textContent = App.money(qty * cost); updateGrandTotal(); }
function updateGrandTotal() { let grand = 0; document.querySelectorAll('#rcv-items-tbody tr[id]').forEach(row => { const id = row.id; grand += (parseFloat(document.getElementById(`${id}-qty`)?.value) || 0) * (parseFloat(document.getElementById(`${id}-cost`)?.value) || 0); }); document.getElementById('rcv-total').textContent = App.money(grand); }
async function saveReceipt() {
  const rows = document.querySelectorAll('#rcv-items-tbody tr[id]');
  if (!rows.length) { App.toast('Add at least one gas tank item', 'error'); return; }
  const items = [];
  let valid = true;
  rows.forEach(row => { const id = row.id; const product_id = document.getElementById(`${id}-product`)?.value; const qty = parseFloat(document.getElementById(`${id}-qty`)?.value) || 0; const cost = parseFloat(document.getElementById(`${id}-cost`)?.value) || 0; if (!product_id || qty <= 0) valid = false; else items.push({ product_id, qty, cost }); });
  if (!valid) { App.toast('Please fill all gas item fields correctly', 'error'); return; }
  const confirmed = await App.confirm(`Receive <strong>${items.length}</strong> gas tank item(s) and update stock?<br><small style="color:var(--text-muted)">Total: ${document.getElementById('rcv-total').textContent}</small>`, 'Confirm Gas Receiving', { icon: 'question', confirmText: 'Yes, receive & update stock', confirmColor: '#16a34a' });
  if (!confirmed) return;
  const btn = document.getElementById('save-receipt-btn');
  App.loading.show(btn, 'Saving...');
  try {
    await App.post('/api_inventory.php?action=receive', { supplier_id: document.getElementById('rcv-supplier').value || null, reference_no: document.getElementById('rcv-ref').value, notes: document.getElementById('rcv-notes').value, items });
    App.toast('Gas receipt saved & stock updated!', 'success');
    location.reload();
  } catch (err) { App.toast(err.message, 'error'); App.loading.hide(btn); }
}
async function removeReceivingRow(id) { if (await App.confirm('Remove this gas item from the list?', 'Remove Item', true)) { document.getElementById(id)?.remove(); updateGrandTotal(); } }
document.addEventListener('DOMContentLoaded', loadData);
</script>
