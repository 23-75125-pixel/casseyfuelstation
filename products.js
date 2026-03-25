/**
 * Products page – Oil Product CRUD
 * Loaded via $extraScripts in public/products.php
 */

let allProducts = [];
let allCategories = [];
let productsMap = {}; // id → product, for safe onclick lookup

// HTML-attribute-safe escaper
function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadData() {
  try {
    const [pData, cData] = await Promise.all([
      App.get('/api_products.php?action=list', { active: 'all' }),
      App.get('/api_products.php?action=categories'),
    ]);
    allProducts   = Array.isArray(pData.products)   ? pData.products   : [];
    allCategories = Array.isArray(cData.categories) ? cData.categories : [];

    // Build id-keyed map so onclick can pass just the id
    productsMap = {};
    allProducts.forEach(p => { productsMap[p.id] = p; });

    // Populate category filter (preserve selection)
    const catSel = document.getElementById('cat-filter');
    const prevCat = catSel.value;
    catSel.innerHTML = '<option value="">All Categories</option>';
    allCategories.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id; o.textContent = c.name;
      catSel.appendChild(o);
    });
    if (prevCat) catSel.value = prevCat;

    // Check URL param: ?filter=low (only on first load)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filter') === 'low') {
      document.getElementById('stock-filter').value = 'low';
    }

    filterProducts();
  } catch (err) {
    const msg = err?.message || String(err) || 'Unknown error';
    console.error('[loadData] Failed:', err);
    try { App.toast('Failed to load products: ' + msg, 'error'); } catch (_) {}
    const tbody = document.getElementById('products-tbody');
    const isAuthErr = /401|session|log\s*in|unauthorized|expired/i.test(msg);
    const hint = isAuthErr
      ? '<br><a href="/login.php" style="color:var(--primary);text-decoration:underline">Click here to log in again</a>'
      : '<br><button class="btn btn-sm btn-outline" style="margin-top:8px" onclick="loadData()">Retry</button>';
    if (tbody) tbody.innerHTML =
      `<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--danger)">⚠️ Failed to load products: ${msg}${hint}</td></tr>`;
  }
}

function filterProducts() {
  const q       = document.getElementById('search-input').value.toLowerCase().trim();
  const catId   = document.getElementById('cat-filter').value;
  const stock   = document.getElementById('stock-filter').value;
  const activeF = document.getElementById('active-filter')?.value || '';

  let filtered = allProducts.filter(p => {
    const searchStr = [p.name, p.sku, p.barcode, p.unit, p.category?.name].filter(Boolean).join(' ').toLowerCase();
    const matchQ      = !q || q.split(/\s+/).every(word => searchStr.includes(word));
    const matchCat    = !catId || p.category_id === catId;
    const matchStk    = !stock ||
      (stock === 'low' && p.stock_qty > 0 && p.stock_qty <= p.low_stock_level) ||
      (stock === 'out' && p.stock_qty <= 0);
    const matchActive = !activeF ||
      (activeF === 'active'   && p.is_active) ||
      (activeF === 'inactive' && !p.is_active);
    return matchQ && matchCat && matchStk && matchActive;
  });

  renderProducts(filtered);
}

function renderProducts(products) {
  document.getElementById('product-count').textContent = `${products.length} products`;
  const tbody = document.getElementById('products-tbody');
  if (!products.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No products found</td></tr>';
    return;
  }
  const canDelete = window.__canDelete;
  tbody.innerHTML = products.map(p => {
    const isLow = p.stock_qty <= p.low_stock_level && p.stock_qty > 0;
    const isOut = p.stock_qty <= 0;
    return `
    <tr>
      <td>
        <div style="font-weight:600;font-size:13.5px">${esc(p.name)}</div>
        <div style="font-size:11.5px;color:var(--text-muted)">${esc(p.unit)}</div>
      </td>
      <td>
        <div class="text-mono" style="font-size:12px">${esc(p.sku) || '—'}</div>
        <div class="text-mono" style="font-size:11px;color:var(--text-muted)">${esc(p.barcode)}</div>
      </td>
      <td><span class="badge badge-gray">${esc(p.category?.name) || '—'}</span></td>
      <td class="fw-700 text-mono">${App.money(p.price)}</td>
      <td class="text-mono" style="color:var(--text-secondary)">${App.money(p.cost)}</td>
      <td>
        <span class="fw-700 text-mono ${isOut ? 'text-danger' : isLow ? 'text-warning' : ''}">${p.stock_qty}</span>
        ${isLow ? '<span class="badge badge-warning" style="margin-left:4px">Low</span>' : ''}
        ${isOut ? '<span class="badge badge-danger" style="margin-left:4px">Out</span>' : ''}
      </td>
      <td><span class="badge ${p.is_active ? 'badge-success' : 'badge-gray'}">${p.is_active ? 'Active' : 'Inactive'}</span></td>
      <td style="text-align:right">
        <button class="btn btn-sm btn-outline" onclick="openProductModal('${p.id}')">Edit</button>
        ${canDelete ? `<button class="btn btn-sm btn-danger" style="margin-left:4px" onclick="deleteProduct('${p.id}')">Del</button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

function openProductModal(productOrId) {
  productOrId = productOrId || null;
  let product = null;
  if (typeof productOrId === 'string' && productOrId) {
    product = productsMap[productOrId] || null;
    if (!product) { App.toast('Product data not found. Refreshing…', 'warning'); loadData(); return; }
  } else if (productOrId && typeof productOrId === 'object') {
    product = productOrId;
  }
  const isEdit = product !== null;
  App.modal.open(`
    <div class="modal-header">
      <h5 class="modal-title">${isEdit ? 'Edit' : 'Add'} Product</h5>
      <button class="modal-close" onclick="App.modal.close()">✕</button>
    </div>
    <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div style="grid-column:1/-1">
        <label class="form-label">Product Name *</label>
        <input type="text" class="form-control" id="p-name" value="${esc(product?.name)}" placeholder="e.g. Shell Advance 4T 10W-40 1L">
      </div>
      <div>
        <label class="form-label">SKU</label>
        <div style="display:flex;gap:6px">
          <input type="text" class="form-control" id="p-sku" value="${esc(product?.sku)}" placeholder="EO-001" style="flex:1">
          <button type="button" class="btn btn-sm btn-outline" onclick="generateRandomSku()" title="Generate random SKU" style="white-space:nowrap;font-size:11px">🎲 Gen</button>
        </div>
      </div>
      <div>
        <label class="form-label">Barcode</label>
        <div style="display:flex;gap:6px">
          <input type="text" class="form-control" id="p-barcode" value="${esc(product?.barcode)}" placeholder="Optional" style="flex:1">
          <button type="button" class="btn btn-sm btn-outline" onclick="generateRandomBarcode()" title="Generate random barcode" style="white-space:nowrap;font-size:11px">🎲 Gen</button>
        </div>
      </div>
      <div>
        <label class="form-label">Category</label>
        <select class="form-select" id="p-category">
          <option value="">— Select —</option>
          ${allCategories.map(c => `<option value="${c.id}" ${product?.category_id === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
        </select>
      </div>
      <div>
        <label class="form-label">Unit</label>
        <select class="form-select" id="p-unit">
          <option value="bottle" ${(product?.unit || 'bottle') === 'bottle' ? 'selected' : ''}>Bottle</option>
          <option value="pcs" ${product?.unit === 'pcs' ? 'selected' : ''}>Pcs</option>
          <option value="liter" ${product?.unit === 'liter' ? 'selected' : ''}>Liter</option>
          <option value="gallon" ${product?.unit === 'gallon' ? 'selected' : ''}>Gallon</option>
          <option value="can" ${product?.unit === 'can' ? 'selected' : ''}>Can</option>
          <option value="tank" ${product?.unit === 'tank' ? 'selected' : ''}>Tank</option>
          <option value="set" ${product?.unit === 'set' ? 'selected' : ''}>Set</option>
          <option value="pack" ${product?.unit === 'pack' ? 'selected' : ''}>Pack</option>
          <option value="kg" ${product?.unit === 'kg' ? 'selected' : ''}>Kg</option>
        </select>
      </div>
      <div>
        <label class="form-label">Selling Price * (₱)</label>
        <input type="number" class="form-control" id="p-price" value="${product?.price || ''}" min="0" step="0.01" placeholder="0.00">
      </div>
      <div>
        <label class="form-label">Cost Price (₱)</label>
        <input type="number" class="form-control" id="p-cost" value="${product?.cost || ''}" min="0" step="0.01" placeholder="0.00">
      </div>
      <div>
        <label class="form-label">Current Stock</label>
        <input type="number" class="form-control" id="p-stock" value="${product?.stock_qty || 0}" min="0" step="1">
      </div>
      <div>
        <label class="form-label">Low Stock Level</label>
        <input type="number" class="form-control" id="p-low" value="${product?.low_stock_level || 5}" min="0" step="1">
      </div>
      ${isEdit ? `<div style="grid-column:1/-1">
        <label class="form-label">Status</label>
        <select class="form-select" id="p-active">
          <option value="true" ${product.is_active ? 'selected' : ''}>Active</option>
          <option value="false" ${!product.is_active ? 'selected' : ''}>Inactive</option>
        </select>
      </div>` : ''}
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
      <button class="btn btn-primary" id="save-product-btn" onclick="saveProduct(${isEdit ? `'${product.id}'` : 'null'})">
        ${isEdit ? 'Save Changes' : 'Add Product'}
      </button>
    </div>
  `, 'md');
}

// Generate random SKU (e.g. PRD-A3F7K2)
function generateRandomSku() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let code = 'PRD-';
  for (let i = 0; i < 6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
  document.getElementById('p-sku').value = code;
}

// Generate random barcode (13-digit EAN-like)
function generateRandomBarcode() {
  let code = '899'; // local-use prefix
  for (let i = 0; i < 10; i++) code += Math.floor(Math.random() * 10);
  document.getElementById('p-barcode').value = code;
}

// Check for duplicate SKU/barcode against loaded products
function checkDuplicate(field, value, excludeId) {
  if (!value) return null;
  const dup = allProducts.find(p => p[field] && p[field].toLowerCase() === value.toLowerCase() && p.id !== excludeId);
  return dup || null;
}

async function saveProduct(id) {
  const btn  = document.getElementById('save-product-btn');
  const name = document.getElementById('p-name').value.trim();
  const price = parseFloat(document.getElementById('p-price').value);

  if (!name) { App.toast('Product name is required', 'error'); return; }
  if (!price) { App.toast('Price is required', 'error'); return; }

  const skuVal     = document.getElementById('p-sku').value.trim() || null;
  const barcodeVal = document.getElementById('p-barcode').value.trim() || null;

  // Client-side duplicate check
  const dupSku = checkDuplicate('sku', skuVal, id);
  if (dupSku) {
    const useRandom = await App.confirm(
      `SKU <strong>${esc(skuVal)}</strong> is already used by <strong>${esc(dupSku.name)}</strong>.<br>Generate a random SKU instead?`,
      'Duplicate SKU',
      { icon: 'warning', confirmText: 'Yes, generate random', confirmColor: '#f97316' }
    );
    if (useRandom) { generateRandomSku(); }
    return;
  }
  const dupBarcode = checkDuplicate('barcode', barcodeVal, id);
  if (dupBarcode) {
    const useRandom = await App.confirm(
      `Barcode <strong>${esc(barcodeVal)}</strong> is already used by <strong>${esc(dupBarcode.name)}</strong>.<br>Generate a random barcode instead?`,
      'Duplicate Barcode',
      { icon: 'warning', confirmText: 'Yes, generate random', confirmColor: '#f97316' }
    );
    if (useRandom) { generateRandomBarcode(); }
    return;
  }

  const payload = {
    id:              id,
    name,
    sku:             skuVal,
    barcode:         barcodeVal,
    category_id:     document.getElementById('p-category').value || null,
    unit:            document.getElementById('p-unit').value || 'bottle',
    price,
    cost:            parseFloat(document.getElementById('p-cost').value) || 0,
    stock_qty:       parseFloat(document.getElementById('p-stock').value) || 0,
    low_stock_level: parseFloat(document.getElementById('p-low').value) || 5,
    is_active:       id ? document.getElementById('p-active').value === 'true' : true,
  };

  App.loading.show(btn);
  try {
    const action = id ? 'update' : 'create';
    await App.post(`/api_products.php?action=${action}`, payload);
    App.modal.close();
    App.toast(`Product ${id ? 'updated' : 'created'} successfully`, 'success');
    await loadData();
  } catch (err) {
    App.toast(err.message, 'error');
    App.loading.hide(btn);
  }
}

async function deleteProduct(id) {
  const product = productsMap[id];
  if (!product) { App.toast('Product not found. Refreshing…', 'warning'); loadData(); return; }
  const name = product.name || 'this product';
  const confirmed = await App.confirm(`Delete <strong>${esc(name)}</strong>? This will mark it as inactive.`, 'Delete Product', true);
  if (!confirmed) return;
  try {
    await App.post('/api_products.php?action=delete', { id });
    App.toast('Product deleted', 'success');
    await loadData();
  } catch (err) {
    App.toast(err.message, 'error');
  }
}

// ── Boot ──
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadData);
} else {
  loadData();
}

// ── Realtime auto-refresh ──
(function () {
  let _rtTimer;
  const debounce = (fn, ms = 1500) => { clearTimeout(_rtTimer); _rtTimer = setTimeout(fn, ms); };
  if (typeof AppRealtime !== 'undefined') {
    AppRealtime.onTable('products', () => debounce(loadData));
  }
})();
