// ============================================================
// assets/js/pos.js
// GasPOS - POS Screen Logic
// ============================================================

const POS = {
  cart: [],
  activeShift: null,
  fuelTypes: [],
  settings: {},
  selectedFuelType: null,
  fuelMode: 'liters', // 'liters' or 'amount'
  allOilProducts: [],
  allCategories: [],
  selectedCatId: '', // '' = All

  // ── Initialize ──────────────────────────
  async init() {
    await Promise.all([
      this.loadFuelTypes(),
      this.loadActiveShift(),
      this.loadSettings(),
      this.loadProducts(),
    ]);
    this.renderFuelButtons();
    this.renderOilCategoryTabs();
    this.renderOilProducts();
    this.renderCart();
    this.bindEvents();
    this.updateShiftBar();
    console.log('[POS] Initialized');
  },

  // ── Load fuel types ──────────────────────
  async loadFuelTypes() {
    try {
      const data = await App.get('/api_fuel.php?action=types', { active: 'true' });
      this.fuelTypes = data.fuel_types || [];
    } catch (err) {
      App.toast('Failed to load fuel types', 'error');
    }
  },

  // ── Load active shift ─────────────────────
  async loadActiveShift() {
    try {
      const data = await App.get('/api_shifts.php?action=active');
      this.activeShift = data.shift;
    } catch {}
  },

  // ── Load settings ─────────────────────────
  async loadSettings() {
    try {
      const data = await App.get('/api_settings.php?action=list');
      this.settings = data.settings || {};
    } catch {}
  },

  // ── Load oil products & categories ────────
  async loadProducts() {
    try {
      const [pData, cData] = await Promise.all([
        App.get('/api_products.php?action=list', { active: 'true' }),
        App.get('/api_products.php?action=categories'),
      ]);
      this.allOilProducts = Array.isArray(pData.products) ? pData.products : [];
      this.allCategories  = Array.isArray(cData.categories) ? cData.categories : [];
    } catch (err) {
      console.warn('[POS] Failed to load products:', err);
      this.allOilProducts = [];
      this.allCategories  = [];
    }
  },

  // ── Render oil category tabs ──────────────
  renderOilCategoryTabs() {
    const container = document.getElementById('oil-cat-tabs');
    if (!container) return;
    const tabs = [{id: '', name: 'All'}].concat(this.allCategories);
    container.innerHTML = tabs.map(c => `
      <button class="btn btn-sm ${c.id === this.selectedCatId ? 'btn-primary' : 'btn-outline'}" 
              onclick="POS.filterOilCategory('${c.id}')" 
              style="font-size:11px;padding:3px 10px">${c.name}</button>
    `).join('');
  },

  // ── Filter oil products by category ───────
  filterOilCategory(catId) {
    this.selectedCatId = catId;
    this.renderOilCategoryTabs();
    this.renderOilProducts();
  },

  // ── Render oil product grid ───────────────
  renderOilProducts() {
    const grid = document.getElementById('oil-product-grid');
    if (!grid) return;
    let products = this.allOilProducts;
    if (this.selectedCatId) {
      products = products.filter(p => p.category_id === this.selectedCatId);
    }
    if (!products.length) {
      grid.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:12px;text-align:center">No products found</div>';
      return;
    }
    // Store for safe onclick reference
    this._oilProducts = products;
    grid.innerHTML = products.map((p, i) => {
      const isOut = p.stock_qty <= 0;
      const isLow = p.stock_qty > 0 && p.stock_qty <= p.low_stock_level;
      const catName = p.category?.name || '';
      return `
        <button class="oil-product-card ${isOut ? 'out-of-stock' : ''}" 
                onclick="POS.addProductToCart(POS._oilProducts[${i}])" 
                ${isOut ? 'disabled' : ''}>
          <div class="oil-card-name">${p.name}</div>
          <div class="oil-card-meta">
            <span class="oil-card-price">${App.money(p.price)}</span>
            <span class="oil-card-stock ${isOut ? 'out' : isLow ? 'low' : ''}">
              ${isOut ? 'Out' : p.stock_qty + ' ' + (p.unit || 'pcs')}
            </span>
          </div>
          ${catName ? '<div class="oil-card-cat">' + catName + '</div>' : ''}
        </button>`;
    }).join('');
  },

  // ── Update shift bar ─────────────────────
  updateShiftBar() {
    const bar   = document.getElementById('shift-bar');
    const label = document.getElementById('shift-label');
    const btn   = document.getElementById('shift-btn');
    if (!bar) return;

    if (this.activeShift) {
      bar.querySelector('.shift-dot')?.classList.remove('hidden');
      if (label) label.textContent = `Shift open since ${App.formatDateTime(this.activeShift.opened_at)}`;
      if (btn) { btn.textContent = 'Close Shift'; btn.className = 'btn btn-sm btn-outline'; }
    } else {
      bar.querySelector('.shift-dot')?.classList.add('hidden');
      if (label) label.textContent = 'No active shift';
      if (btn) { btn.textContent = 'Open Shift'; btn.className = 'btn btn-sm btn-success'; }
    }
  },

  // ── Render fuel type buttons ──────────────
  renderFuelButtons() {
    const grid = document.getElementById('fuel-grid');
    if (!grid) return;
    grid.innerHTML = this.fuelTypes.map(ft => `
      <button class="fuel-btn" onclick="POS.selectFuel('${ft.id}')" data-fuel-id="${ft.id}">
        <span class="fuel-dot" style="background:${ft.color}"></span>
        <div class="fuel-name">${ft.name}</div>
        <div class="fuel-price">${App.money(ft.price_per_liter)}/L</div>
      </button>
    `).join('');
  },

  // ── Select fuel type ─────────────────────
  selectFuel(fuelId) {
    this.selectedFuelType = this.fuelTypes.find(f => f.id === fuelId);
    document.querySelectorAll('.fuel-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-fuel-id="${fuelId}"]`)?.classList.add('active');

    // Update fuel panel labels
    const panel = document.getElementById('fuel-input-panel');
    if (panel) {
      document.getElementById('selected-fuel-name').textContent = this.selectedFuelType?.name || '';
      document.getElementById('fuel-price-display').textContent = App.money(this.selectedFuelType?.price_per_liter || 0) + '/L';
      panel.classList.remove('hidden');
    }
    this.updateFuelCalc();
  },

  // ── Toggle fuel input mode (liters/amount) ─
  setFuelMode(mode) {
    this.fuelMode = mode;
    document.querySelectorAll('.fuel-mode-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-fuel-mode="${mode}"]`)?.classList.add('active');
    document.getElementById('fuel-unit-label').textContent = mode === 'liters' ? 'Liters' : 'Amount (₱)';
    this.updateFuelCalc();
  },

  // ── Update fuel calculation display ───────
  updateFuelCalc() {
    const input = document.getElementById('fuel-input-val');
    if (!input || !this.selectedFuelType) return;
    const val   = parseFloat(input.value) || 0;
    const price = parseFloat(this.selectedFuelType.price_per_liter);

    let liters, amount;
    if (this.fuelMode === 'liters') {
      liters = val;
      amount = liters * price;
    } else {
      amount = val;
      liters = price > 0 ? amount / price : 0;
    }

    document.getElementById('fuel-calc-liters').textContent = App.num(liters, 3) + ' L';
    document.getElementById('fuel-calc-amount').textContent = App.money(amount);
  },

  // ── Add fuel to cart ───────────────────────
  addFuelToCart() {
    if (!this.selectedFuelType) { App.toast('Select a fuel type first', 'warning'); return; }
    const input   = document.getElementById('fuel-input-val');
    const pump    = document.getElementById('fuel-pump-no');
    const val     = parseFloat(input.value) || 0;
    const price   = parseFloat(this.selectedFuelType.price_per_liter);

    if (val <= 0) { App.toast('Enter liters or amount', 'warning'); input.focus(); return; }

    let liters, amount;
    if (this.fuelMode === 'liters') {
      liters = val; amount = val * price;
    } else {
      amount = val; liters = price > 0 ? val / price : 0;
    }

    this.cart.push({
      id:            `fuel-${Date.now()}`,
      item_type:     'fuel',
      fuel_type_id:  this.selectedFuelType.id,
      name:          this.selectedFuelType.name,
      pump_number:   pump?.value || '',
      qty:           liters,
      unit_price:    price,
      discount:      0,
      line_total:    parseFloat(amount.toFixed(2)),
      color:         this.selectedFuelType.color,
    });

    input.value = '';
    if (pump) pump.value = '';
    App.toast(`Added: ${App.num(liters,3)}L ${this.selectedFuelType.name}`, 'success');
    this.renderCart();
  },

  // ── Product search ─────────────────────────
  async searchProducts(query) {
    if (!query) return;
    try {
      const data = await App.get('/api_products.php?action=list', { search: query, active: 'true' });
      this.showProductSuggestions(data.products || []);
    } catch {}
  },

  async lookupBarcode(barcode) {
    try {
      const data = await App.get('/api_products.php?action=barcode', { barcode });
      this.addProductToCart(data.product);
      document.getElementById('barcode-input').value = '';
    } catch {
      App.toast('Product not found: ' + barcode, 'error');
    }
  },

  showProductSuggestions(products) {
    const list = document.getElementById('product-suggestions');
    if (!list) return;
    if (!products.length) { list.innerHTML = '<div class="p-3 text-muted" style="font-size:13px">No products found</div>'; list.classList.remove('hidden'); return; }
    // Store products so onclick can reference by index (avoids JSON-in-attribute bugs)
    this._suggestions = products.slice(0, 8);
    list.innerHTML = this._suggestions.map((p, i) => `
      <div class="suggestion-item" onmousedown="POS.addProductToCart(POS._suggestions[${i}]); document.getElementById('product-search').value='';">
        <div class="d-flex align-items-center gap-8">
          <span style="font-weight:600;font-size:13px">${p.name}</span>
          <span class="badge badge-gray">${p.unit}</span>
          ${p.stock_qty <= p.low_stock_level ? '<span class="badge badge-warning">Low</span>' : ''}
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted)">
          <span>${p.sku || ''}</span>
          <span class="text-mono fw-600" style="color:var(--text-primary)">${App.money(p.price)}</span>
        </div>
      </div>
    `).join('');
    list.classList.remove('hidden');
  },

  hideSuggestions() {
    setTimeout(() => document.getElementById('product-suggestions')?.classList.add('hidden'), 200);
  },

  addProductToCart(product) {
    if (!product) return;
    if (product.stock_qty <= 0) { App.toast(`${product.name} is out of stock`, 'error'); return; }

    const existing = this.cart.find(i => i.item_type === 'product' && i.product_id === product.id);
    if (existing) {
      if (existing.qty >= product.stock_qty) { App.toast('Insufficient stock', 'warning'); return; }
      existing.qty++;
      existing.line_total = parseFloat((existing.qty * existing.unit_price - existing.discount).toFixed(2));
    } else {
      this.cart.push({
        id:         `prod-${Date.now()}`,
        item_type:  'product',
        product_id: product.id,
        name:       product.name,
        unit:       product.unit,
        qty:        1,
        unit_price: parseFloat(product.price),
        max_qty:    parseFloat(product.stock_qty),
        discount:   0,
        line_total: parseFloat(product.price),
      });
    }

    document.getElementById('product-search')?.value && (document.getElementById('product-search').value = '');
    document.getElementById('product-suggestions')?.classList.add('hidden');
    App.toast(`Added: ${product.name}`, 'success');
    this.renderCart();
  },

  // ── Update cart item quantity ──────────────
  updateQty(cartId, delta) {
    const item = this.cart.find(i => i.id === cartId);
    if (!item) return;
    const newQty = item.item_type === 'fuel'
      ? Math.max(0.001, item.qty + delta)
      : Math.max(1, Math.min(item.max_qty || 9999, item.qty + delta));
    item.qty       = parseFloat(newQty.toFixed(3));
    item.line_total = parseFloat((item.qty * item.unit_price - item.discount).toFixed(2));
    this.renderCart();
  },

  async removeFromCart(cartId) {
    const item = this.cart.find(i => i.id === cartId);
    const name = item ? item.name : 'this item';
    const ok = await App.confirm(
      `Remove <strong>${name}</strong> from cart?`,
      'Remove Item',
      { icon: 'warning', confirmText: 'Yes, remove', confirmColor: '#ef4444' }
    );
    if (!ok) return;
    this.cart = this.cart.filter(i => i.id !== cartId);
    this.renderCart();
  },

  async clearCart() {
    if (!this.cart.length) return;
    const ok = await App.confirm(
      `Clear all <strong>${this.cart.length}</strong> item(s) from the cart?`,
      'Clear Cart',
      { icon: 'warning', confirmText: 'Yes, clear all', confirmColor: '#ef4444' }
    );
    if (!ok) return;
    this.cart = [];
    this.renderCart();
    App.toast('Cart cleared', 'info');
  },

  // ── Render cart ────────────────────────────
  renderCart() {
    const container = document.getElementById('cart-items');
    const count     = document.getElementById('cart-count');
    if (!container) return;

    if (count) count.textContent = this.cart.length;

    if (!this.cart.length) {
      container.innerHTML = `
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);padding:40px 20px;gap:12px">
          <span style="font-size:40px">🛒</span>
          <div style="font-size:13px;font-weight:500">Cart is empty</div>
          <div style="font-size:12px;text-align:center">Add fuel or scan products to start a transaction</div>
        </div>`;
      this.updateTotals(0, 0, 0);
      return;
    }

    container.innerHTML = this.cart.map(item => `
      <div class="cart-item">
        <div class="cart-item-type ${item.item_type}">
          ${item.item_type === 'fuel' ? '⛽' : '📦'}
        </div>
        <div class="cart-item-info">
          <div class="cart-item-name">${item.name}</div>
          <div class="cart-item-sub">
            ${item.item_type === 'fuel'
              ? `${App.num(item.qty,3)} L × ${App.money(item.unit_price)}/L${item.pump_number ? ' · Pump ' + item.pump_number : ''}`
              : `${item.qty} ${item.unit || 'pcs'} × ${App.money(item.unit_price)}`
            }
            ${item.discount > 0 ? ` <span class="text-success">-${App.money(item.discount)}</span>` : ''}
          </div>
          ${item.item_type !== 'fuel' ? `
          <div style="display:flex;align-items:center;gap:4px;margin-top:4px">
            <button class="numpad-btn" style="padding:2px 8px;font-size:13px;border-radius:5px" onclick="POS.updateQty('${item.id}', -1)">−</button>
            <span style="font-size:13px;font-weight:600;min-width:24px;text-align:center">${item.qty}</span>
            <button class="numpad-btn" style="padding:2px 8px;font-size:13px;border-radius:5px" onclick="POS.updateQty('${item.id}', 1)">+</button>
          </div>` : ''}
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
          <div class="cart-item-total">${App.money(item.line_total)}</div>
          <button class="cart-item-remove" onclick="POS.removeFromCart('${item.id}')">✕</button>
        </div>
      </div>
    `).join('');

    const subtotal = this.cart.reduce((s, i) => s + i.line_total + i.discount, 0);
    const discount = this.cart.reduce((s, i) => s + i.discount, 0);
    const taxRate  = parseFloat(this.settings.tax_rate || 0);
    const taxEnabled = this.settings.tax_enabled === 'true';
    const tax      = taxEnabled ? (subtotal - discount) * taxRate : 0;
    const total    = subtotal - discount + tax;
    this.updateTotals(subtotal, discount, tax, total);
  },

  updateTotals(subtotal, discount, tax, total) {
    const el = id => document.getElementById(id);
    if (el('cart-subtotal'))  el('cart-subtotal').textContent  = App.money(subtotal || 0);
    if (el('cart-discount'))  el('cart-discount').textContent  = App.money(discount || 0);
    if (el('cart-tax'))       el('cart-tax').textContent       = App.money(tax || 0);
    if (el('cart-total'))     el('cart-total').textContent     = App.money(total || subtotal || 0);
  },

  getCartTotal() {
    const subtotal = this.cart.reduce((s, i) => s + i.line_total + i.discount, 0);
    const discount = this.cart.reduce((s, i) => s + i.discount, 0);
    const taxRate  = parseFloat(this.settings.tax_rate || 0);
    const tax      = this.settings.tax_enabled === 'true' ? (subtotal - discount) * taxRate : 0;
    return { subtotal, discount, tax, total: subtotal - discount + tax };
  },

  // ── Payment modal ──────────────────────────
  openPayment() {
    if (!this.activeShift && this.settings.require_shift !== 'false') {
      App.toast('Please open a shift first', 'warning');
      return;
    }
    if (!this.cart.length) { App.toast('Cart is empty', 'warning'); return; }

    const { total } = this.getCartTotal();

    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">💵 Cash Payment</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
        <!-- Customer info -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label class="form-label">Customer Name</label>
            <input type="text" class="form-control" id="pay-customer" placeholder="Optional">
          </div>
          <div>
            <label class="form-label">Plate / Ref</label>
            <input type="text" class="form-control" id="pay-plate" placeholder="Optional">
          </div>
        </div>
        <!-- Amount due -->
        <div style="background:var(--surface2);padding:16px;border-radius:var(--radius);display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:600;color:var(--text-secondary)">Amount Due</span>
          <span style="font-size:28px;font-weight:800;font-family:'DM Mono',monospace;color:var(--accent)">${App.money(total)}</span>
        </div>
        <!-- Cash input -->
        <div>
          <label class="form-label">Cash Tendered</label>
          <input type="number" class="form-control" id="pay-cash-amount" placeholder="0.00"
            oninput="POS.updateChange(${total})"
            style="font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:center;padding:14px">
          <!-- Quick cash buttons -->
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            ${[total, Math.ceil(total/100)*100, Math.ceil(total/500)*500, 1000].filter((v,i,a) => a.indexOf(v)===i).map(v => `
              <button class="btn btn-outline btn-sm" onclick="document.getElementById('pay-cash-amount').value='${v.toFixed(2)}';POS.updateChange(${total})">${App.money(v)}</button>
            `).join('')}
          </div>
          <div id="change-display" style="text-align:center;padding:14px;margin-top:12px;background:var(--surface2);border-radius:var(--radius);display:none">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">CHANGE</div>
            <div id="change-amount" style="font-size:28px;font-weight:800;font-family:'DM Mono',monospace;color:var(--success)"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
        <button class="btn btn-success btn-lg" id="complete-pay-btn" onclick="POS.completeSale(${total})">
          ✓ Complete Sale
        </button>
      </div>
    `, 'md');

    POS._payMethod = 'cash';
    // Auto-focus cash input
    setTimeout(() => document.getElementById('pay-cash-amount')?.focus(), 120);
  },

  updateChange(total) {
    const cash     = parseFloat(document.getElementById('pay-cash-amount')?.value) || 0;
    const change   = cash - total;
    const display  = document.getElementById('change-display');
    const amount   = document.getElementById('change-amount');
    if (display && amount) {
      if (cash > 0) {
        display.style.display = 'block';
        amount.textContent    = App.money(Math.max(0, change));
        amount.style.color    = change >= 0 ? 'var(--success)' : 'var(--danger)';
      } else {
        display.style.display = 'none';
      }
    }
  },

  // ── Complete sale ──────────────────────────
  async completeSale(total) {
    if (!POS._payMethod) { App.toast('Select a payment method', 'warning'); return; }

    let payAmount = total;
    if (POS._payMethod === 'cash') {
      payAmount = parseFloat(document.getElementById('pay-cash-amount')?.value) || 0;
      if (payAmount < total) { App.toast('Insufficient cash amount', 'error'); return; }
    }

    const btn = document.getElementById('complete-pay-btn');
    App.loading.show(btn, 'Processing...');

    const { subtotal, discount, tax } = this.getCartTotal();

    const payload = {
      shift_id:       this.activeShift?.id || null,
      customer_name:  document.getElementById('pay-customer')?.value || '',
      vehicle_plate:  document.getElementById('pay-plate')?.value || '',
      subtotal:       parseFloat(subtotal.toFixed(2)),
      discount_total: parseFloat(discount.toFixed(2)),
      tax_total:      parseFloat(tax.toFixed(2)),
      total:          parseFloat(total.toFixed(2)),
      items: this.cart.map(item => ({
        item_type:    item.item_type,
        product_id:   item.product_id || null,
        fuel_type_id: item.fuel_type_id || null,
        pump_number:  item.pump_number || '',
        qty:          item.qty,
        unit_price:   item.unit_price,
        discount:     item.discount || 0,
        line_total:   item.line_total,
      })),
      payments: [{
        method:       POS._payMethod,
        amount:       payAmount,
        reference_no: document.getElementById('pay-plate')?.value || '',
      }],
    };

    try {
      const result = await App.post('/api_transactions.php?action=create', payload);
      // Save cart snapshot BEFORE clearing — receipt needs item names
      const cartSnapshot = this.cart.map(i => ({...i}));

      App.modal.close();
      App.toast(`Transaction ${result.txn_no} complete!`, 'success');

      // Clear cart first
      this.cart = [];
      this.renderCart();
      this.selectedFuelType = null;
      document.querySelectorAll('.fuel-btn').forEach(b => b.classList.remove('active'));
      document.getElementById('fuel-input-panel')?.classList.add('hidden');

      // Show receipt (cart already cleared, pass snapshot)
      setTimeout(() => this.showReceipt(result.txn_no, result.transaction, payload, payAmount, cartSnapshot), 200);

    } catch (err) {
      App.toast(err.message || 'Transaction failed', 'error');
      App.loading.hide(btn);
    }
  },

  // ── Show receipt ──────────────────────────
  showReceipt(txnNo, txn, payload, cashPaid, cartSnapshot = []) {
    const change = POS._payMethod === 'cash' ? cashPaid - payload.total : 0;
    const now    = new Date().toLocaleString('en-PH');
    const bizName = document.querySelector('meta[name="biz-name"]')?.content || 'Cassey Fuel Station';
    const bizAddr = document.querySelector('meta[name="biz-addr"]')?.content || '';
    const tin     = document.querySelector('meta[name="biz-tin"]')?.content || '';

    const itemLines = payload.items.map(item => {
      // Use cart snapshot (cart may be cleared by now)
      const snap = cartSnapshot.find(c =>
        (item.fuel_type_id && c.fuel_type_id === item.fuel_type_id) ||
        (item.product_id   && c.product_id   === item.product_id)
      );
      const name = snap?.name
        || (item.fuel_type_id
            ? (this.fuelTypes.find(f => f.id === item.fuel_type_id)?.name || 'Fuel')
            : 'Product');
      return `<div class="row"><span>${name} ${item.item_type==='fuel' ? App.num(item.qty,3)+'L' : '×'+item.qty}</span><span>${App.money(item.line_total)}</span></div>`;
    }).join('');

    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">🧾 Receipt</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body">
        <div class="receipt-preview" id="receipt-content">
          <div class="center" style="font-weight:700;font-size:14px">${bizName}</div>
          <div class="center" style="font-size:11px">${bizAddr}</div>
          ${tin ? `<div class="center" style="font-size:11px">TIN: ${tin}</div>` : ''}
          <hr>
          <div class="row"><span>TXN#</span><span>${txnNo}</span></div>
          <div class="row"><span>Date</span><span>${now}</span></div>
          ${payload.customer_name ? `<div class="row"><span>Customer</span><span>${payload.customer_name}</span></div>` : ''}
          ${payload.vehicle_plate ? `<div class="row"><span>Plate</span><span>${payload.vehicle_plate}</span></div>` : ''}
          <hr>
          ${itemLines}
          <hr>
          <div class="row"><span>Subtotal</span><span>${App.money(payload.subtotal)}</span></div>
          ${payload.discount_total > 0 ? `<div class="row"><span>Discount</span><span>-${App.money(payload.discount_total)}</span></div>` : ''}
          ${payload.tax_total > 0 ? `<div class="row"><span>Tax</span><span>${App.money(payload.tax_total)}</span></div>` : ''}
          <div class="row" style="font-weight:700;font-size:14px;padding-top:4px"><span>TOTAL</span><span>${App.money(payload.total)}</span></div>
          <hr>
          <div class="row"><span>Payment (${(POS._payMethod||'').toUpperCase()})</span><span>${App.money(cashPaid)}</span></div>
          ${change > 0 ? `<div class="row"><span>Change</span><span>${App.money(change)}</span></div>` : ''}
          <hr>
          <div class="center" style="font-size:11px">Thank you for your business!</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Close</button>
        <button class="btn btn-primary" onclick="POS.printReceipt()">🖨️ Print</button>
      </div>
    `, 'sm');
  },

  printReceipt() {
    const content = document.getElementById('receipt-content')?.innerHTML;
    if (!content) return;
    const thermalWidth = parseInt(this.settings.thermal_width || '58', 10) === 80 ? '80mm' : '58mm';
    const baseFontSize = thermalWidth === '80mm' ? 14 : 13;
    const win = window.open('', '_blank', 'width=400,height=600');
    win.document.write(`
      <html><head><title>Receipt</title>
      <style>
        body{margin:0;padding:8px;width:${thermalWidth};font-family:'Courier New',monospace}
        .receipt-print,.receipt-print *{font-family:'Courier New',monospace;font-size:${baseFontSize}px !important;font-weight:700;line-height:1.35}
        .row{display:flex;justify-content:space-between;gap:8px}
        .center{text-align:center}
        hr{border:none;border-top:1px dashed #000;margin:6px 0}
        @media print{@page{margin:0;size:${thermalWidth} auto}body{padding:4px}}
      </style></head>
      <body><div class="receipt-print">${content}</div></body></html>
    `);
    win.document.close();
    setTimeout(() => { win.print(); win.close(); }, 500);
  },

  // ── Open/close shift ──────────────────────
  async openShift() {
    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">🕐 Open Shift</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
        <p style="color:var(--text-secondary);font-size:13px">Count your starting cash before opening the shift.</p>
        <div>
          <label class="form-label">Opening Cash</label>
          <input type="number" class="form-control" id="opening-cash" value="0" min="0" step="0.01"
            style="font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:center;padding:14px">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
        <button class="btn btn-success btn-lg" onclick="POS.confirmOpenShift()">Open Shift</button>
      </div>
    `, 'sm');
    setTimeout(() => document.getElementById('opening-cash')?.focus(), 100);
  },

  async confirmOpenShift() {
    const cash = parseFloat(document.getElementById('opening-cash')?.value) || 0;
    try {
      const result = await App.post('/api_shifts.php?action=open', { opening_cash: cash });
      this.activeShift = result.shift;
      this.updateShiftBar();
      App.modal.close();
      App.toast('Shift opened successfully', 'success');
    } catch (err) {
      App.toast(err.message || 'Failed to open shift', 'error');
    }
  },

  async closeShift() {
    if (!this.activeShift) { App.toast('No active shift to close', 'warning'); return; }

    // Get summary first
    const summary = await App.get('/api_shifts.php?action=summary', { shift_id: this.activeShift.id });

    App.modal.open(`
      <div class="modal-header">
        <h5 class="modal-title">🔒 Close Shift</h5>
        <button class="modal-close" onclick="App.modal.close()">✕</button>
      </div>
      <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="stat-card" style="padding:12px">
            <div class="stat-label">Total Sales</div>
            <div class="stat-value" style="font-size:18px">${App.money(summary.total_sales)}</div>
          </div>
          <div class="stat-card" style="padding:12px">
            <div class="stat-label">Transactions</div>
            <div class="stat-value" style="font-size:18px">${summary.txn_count}</div>
          </div>
        </div>
        <div>
          <label class="form-label">Closing Cash Count</label>
          <input type="number" class="form-control" id="closing-cash" value="0" min="0" step="0.01"
            style="font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:center;padding:14px">
        </div>
        <div>
          <label class="form-label">Notes (optional)</label>
          <textarea class="form-control" id="close-notes" rows="2" placeholder="Any notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="App.modal.close()">Cancel</button>
        <button class="btn btn-danger btn-lg" onclick="POS.confirmCloseShift()">Close Shift</button>
      </div>
    `, 'sm');
  },

  async confirmCloseShift() {
    const cash  = parseFloat(document.getElementById('closing-cash')?.value) || 0;
    const notes = document.getElementById('close-notes')?.value || '';
    try {
      const result = await App.post('/api_shifts.php?action=close', {
        shift_id:     this.activeShift.id,
        closing_cash: cash,
        notes,
      });
      const variance = result.variance || 0;
      this.activeShift = null;
      this.updateShiftBar();
      App.modal.close();
      App.toast(`Shift closed. Variance: ${App.money(variance)}`, variance === 0 ? 'success' : 'warning');
    } catch (err) {
      App.toast(err.message || 'Failed to close shift', 'error');
    }
  },

  // ── Bind global events ─────────────────────
  bindEvents() {
    // Barcode input — press Enter to lookup
    const barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
      barcodeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          const val = barcodeInput.value.trim();
          if (val) this.lookupBarcode(val);
        }
      });
    }

    // Product search — debounced
    const searchInput = document.getElementById('product-search');
    let searchTimer;
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        const q = e.target.value.trim();
        if (q.length >= 2) searchTimer = setTimeout(() => this.searchProducts(q), 300);
        else document.getElementById('product-suggestions')?.classList.add('hidden');
      });
      searchInput.addEventListener('blur', () => this.hideSuggestions());
    }

    // Fuel input
    const fuelInput = document.getElementById('fuel-input-val');
    if (fuelInput) {
      fuelInput.addEventListener('input', () => this.updateFuelCalc());
      fuelInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') this.addFuelToCart();
      });
    }

    // Shift button
    document.getElementById('shift-btn')?.addEventListener('click', () => {
      this.activeShift ? this.closeShift() : this.openShift();
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      // F2 = focus search
      if (e.key === 'F2') { e.preventDefault(); document.getElementById('barcode-input')?.focus(); }
      // F4 = open payment
      if (e.key === 'F4') { e.preventDefault(); this.openPayment(); }
      // F5 = clear cart
      if (e.key === 'F5') { e.preventDefault(); if (this.cart.length) this.clearCart(); }
    });
  },
};

document.addEventListener('DOMContentLoaded', () => {
  if (!window._POS_SKIP_INIT) POS.init();
});
