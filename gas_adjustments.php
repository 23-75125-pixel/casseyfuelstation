<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw = new Middleware();
$profile = $mw->requireSession(['admin','staff']);
$sb = new Supabase();
$settings = getSettings($sb);
$pageTitle = 'Gas Adjustments';
$activePage = 'gas_adjustments.php';
$allowedCategories = ['LPG Tanks', 'LPG Accessories'];
include __DIR__ . '/layout.php';
?>
<div style="display:flex;flex-direction:column;gap:20px">
  <div style="display:flex;align-items:center;gap:12px"><h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Gas Tank Stock Adjustments</h1><button class="btn btn-primary" onclick="openAdjustModal()">+ New Adjustment</button></div>
  <div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Product</th><th>Change</th><th>Reason</th><th>Notes</th><th>By</th></tr></thead><tbody id="adj-tbody"><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">Loading...</td></tr></tbody></table></div></div>
</div>
<?php include __DIR__ . '/layout_end.php'; ?>
<script>
const allowedCategoryNames = <?= json_encode($allowedCategories) ?>;
let allProducts = [];
const isAllowed = p => allowedCategoryNames.includes(p?.category?.name || '');
async function loadData() {
  try {
    const [prodData, adjData] = await Promise.all([App.get('/api_products.php?action=list', { active: 'true' }), App.get('/api_inventory.php?action=adjustments', { limit: 50 })]);
    allProducts = (prodData.products || []).filter(isAllowed);
    const productIds = new Set(allProducts.map(p => p.id));
    const adjs = (adjData.adjustments || []).filter(a => productIds.has(a.product_id));
    document.getElementById('adj-tbody').innerHTML = adjs.length ? adjs.map(a => `<tr><td style="font-size:12px;color:var(--text-muted)">${App.formatDateTime(a.created_at)}</td><td class="fw-600">${a.product?.name || '—'}</td><td><span class="fw-700 text-mono ${a.qty_change > 0 ? 'text-success' : 'text-danger'}">${a.qty_change > 0 ? '+' : ''}${a.qty_change}</span></td><td>${a.reason}</td><td style="color:var(--text-muted);font-size:12px">${a.notes || '—'}</td><td style="font-size:12px">${a.created_by_user?.full_name || '—'}</td></tr>`).join('') : '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No adjustments yet</td></tr>';
  } catch (err) { App.toast('Error loading data: ' + err.message, 'error'); }
}
function openAdjustModal() {
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  App.modal.open(`<div class="modal-header"><h5 class="modal-title">Gas Tank Stock Adjustment</h5><button class="modal-close" onclick="App.modal.close()">✕</button></div><div class="modal-body" style="display:flex;flex-direction:column;gap:14px"><div><label class="form-label">Product *</label><select class="form-select" id="adj-product" onchange="updateCurrentStock()"><option value="">— Select Gas Product —</option>${allProducts.map(p => `<option value="${p.id}" data-stock="${p.stock_qty}">${esc(p.name)} (Current: ${p.stock_qty} ${esc(p.unit)})</option>`).join('')}</select></div><div id="adj-current-stock" class="hidden" style="padding:10px;background:var(--surface2);border-radius:var(--radius);font-size:13px"></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div><label class="form-label">Adjustment Type</label><select class="form-select" id="adj-type"><option value="add">Add (+)</option><option value="subtract">Subtract (-)</option></select></div><div><label class="form-label">Quantity</label><input type="number" class="form-control" id="adj-qty" min="0.001" step="0.001" placeholder="0"></div></div><div><label class="form-label">Reason *</label><select class="form-select" id="adj-reason"><option value="damage">Damage / Spoilage</option><option value="discrepancy">Count Discrepancy</option><option value="returned">Customer Return</option><option value="correction">Correction / Error</option><option value="sample">Sample / Internal Use</option><option value="other">Other</option></select></div><div><label class="form-label">Notes</label><textarea class="form-control" id="adj-notes" rows="2" placeholder="Additional details..."></textarea></div></div><div class="modal-footer"><button class="btn btn-outline" onclick="App.modal.close()">Cancel</button><button class="btn btn-primary" id="save-adj-btn" onclick="saveAdjustment()">Save Adjustment</button></div>`, 'sm');
}
function updateCurrentStock() { const opt = document.getElementById('adj-product').options[document.getElementById('adj-product').selectedIndex]; const stockEl = document.getElementById('adj-current-stock'); if (opt && opt.value) { stockEl.textContent = `Current stock: ${opt.dataset.stock}`; stockEl.classList.remove('hidden'); } else stockEl.classList.add('hidden'); }
async function saveAdjustment() { const btn = document.getElementById('save-adj-btn'); const prodId = document.getElementById('adj-product').value; const type = document.getElementById('adj-type').value; const qty = parseFloat(document.getElementById('adj-qty').value) || 0; const reason = document.getElementById('adj-reason').value; const notes = document.getElementById('adj-notes').value; if (!prodId || qty <= 0) { App.toast('Select product and enter quantity', 'error'); return; } App.loading.show(btn, 'Saving...'); try { const result = await App.post('/api_inventory.php?action=adjust', { product_id: prodId, qty_change: type === 'subtract' ? -qty : qty, reason, notes }); App.modal.close(); App.toast(`Stock adjusted. New qty: ${result.new_stock}`, 'success'); loadData(); } catch (err) { App.toast(err.message, 'error'); App.loading.hide(btn); } }
document.addEventListener('DOMContentLoaded', loadData);
</script>
