<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin','staff','cashier']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle   = 'POS Terminal';
$activePage  = 'pos.php';
$extraScripts = ['/pos.js'];

include __DIR__ . '/layout.php';
?>

<!-- Shift Bar -->
<div class="shift-bar" id="shift-bar" style="margin:-24px -24px 16px">
  <div class="shift-dot"></div>
  <span id="shift-label" style="flex:1;font-size:13px;color:var(--text-secondary)">Checking shift status...</span>
  <button class="btn btn-sm btn-outline" id="shift-btn">Loading...</button>
</div>

<!-- Keyboard shortcut hints -->
<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
  <span style="font-size:11px;color:var(--text-muted);background:var(--surface2);padding:2px 8px;border-radius:4px;border:1px solid var(--border)">F2 Focus Scan</span>
  <span style="font-size:11px;color:var(--text-muted);background:var(--surface2);padding:2px 8px;border-radius:4px;border:1px solid var(--border)">F5 Clear Cart</span>
  <span style="font-size:11px;color:var(--text-muted);background:var(--surface2);padding:2px 8px;border-radius:4px;border:1px solid var(--border)">Enter Add Fuel</span>
</div>

<!-- POS Layout -->
<div class="pos-layout">

  <!-- ── LEFT PANEL ── -->
  <div class="pos-left">

    <!-- Barcode / Product Search -->
    <div class="card" style="padding:14px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <!-- Barcode scanner input -->
        <div>
          <label class="form-label">📷 Scan Barcode</label>
          <div class="input-group">
            <input type="text" class="form-control" id="barcode-input"
              placeholder="Scan or type barcode, press Enter"
              autocomplete="off" style="font-family:'DM Mono',monospace">
            <button class="btn btn-dark" onclick="POS.lookupBarcode(document.getElementById('barcode-input').value)">Go</button>
          </div>
        </div>
        <!-- Product search -->
        <div>
          <label class="form-label">🔍 Search Products</label>
          <div class="search-wrapper">
            <input type="text" class="form-control" id="product-search"
              placeholder="Type product name..." autocomplete="off">
            <div id="product-suggestions" class="hidden"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fuel Types -->
    <div class="card" style="padding:14px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="font-size:16px">⛽</span>
        <span style="font-weight:700;font-size:14px">Select Fuel Type</span>
        <span style="font-size:12px;color:var(--text-muted)">(click to select)</span>
      </div>
      <div class="fuel-grid" id="fuel-grid">
        <div style="color:var(--text-muted);font-size:13px">Loading...</div>
      </div>
    </div>

    <!-- Oil Products Quick-Select Grid -->
    <div class="card" style="padding:14px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap">
        <span style="font-size:16px">🛢️</span>
        <span style="font-weight:700;font-size:14px">Oil Products</span>
        <span style="font-size:12px;color:var(--text-muted)">(click to add to cart)</span>
        <div id="oil-cat-tabs" style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap"></div>
      </div>
      <div class="oil-product-grid" id="oil-product-grid">
        <div style="color:var(--text-muted);font-size:13px">Loading products...</div>
      </div>
    </div>

    <!-- Fuel Input Panel (shows after selecting fuel type) -->
    <div class="card hidden" id="fuel-input-panel" style="padding:14px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <span style="font-size:16px">⛽</span>
        <div>
          <div style="font-weight:700;font-size:15px" id="selected-fuel-name">Diesel</div>
          <div style="font-size:12px;color:var(--text-secondary)" id="fuel-price-display"></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:end">
        <div>
          <!-- Liters / Amount toggle -->
          <div class="fuel-toggle" style="margin-bottom:10px">
            <button class="fuel-mode-btn active" data-fuel-mode="liters" onclick="POS.setFuelMode('liters')">By Liters</button>
            <button class="fuel-mode-btn" data-fuel-mode="amount" onclick="POS.setFuelMode('amount')">By Amount</button>
          </div>
          <label class="form-label" id="fuel-unit-label">Liters</label>
          <input type="number" class="form-control" id="fuel-input-val" min="0" step="0.001"
            placeholder="0.000"
            style="font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:center;padding:12px">
        </div>
        <div style="text-align:center;padding-bottom:8px">
          <div style="color:var(--text-muted);font-size:20px">→</div>
        </div>
        <div>
          <label class="form-label">Pump # (optional)</label>
          <input type="text" class="form-control" id="fuel-pump-no" placeholder="1" maxlength="5">
          <div style="margin-top:10px;padding:10px;background:var(--surface2);border-radius:8px">
            <div style="font-size:11px;color:var(--text-muted)">LITERS</div>
            <div style="font-weight:700;font-family:'DM Mono',monospace" id="fuel-calc-liters">0.000 L</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">AMOUNT</div>
            <div style="font-weight:700;font-family:'DM Mono',monospace;color:var(--accent)" id="fuel-calc-amount">₱0.00</div>
          </div>
        </div>
      </div>

      <button class="btn btn-primary btn-block" style="margin-top:14px;padding:12px" onclick="POS.addFuelToCart()">
        Add Fuel
      </button>
    </div>

  </div>

  <!-- ── RIGHT PANEL: Cart ── -->
  <div class="pos-right">
    <!-- Cart Header -->
    <div class="cart-header">
      <span style="font-size:16px">🛒</span>
      <span style="font-weight:700;font-size:15px">Cart</span>
      <span class="badge badge-orange ms-auto" id="cart-count" style="font-size:12px">0</span>
      <button class="btn btn-sm btn-outline" style="margin-left:8px" onclick="if(POS.cart.length) POS.clearCart()" title="Clear cart (F5)">🗑️</button>
    </div>

    <!-- Cart Items -->
    <div class="cart-items" id="cart-items">
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);padding:40px 20px;gap:12px">
        <span style="font-size:40px">🛒</span>
        <div style="font-size:13px;font-weight:500">Cart is empty</div>
        <div style="font-size:12px;text-align:center">Add fuel or scan products to start a transaction</div>
      </div>
    </div>

    <!-- Totals -->
    <div class="cart-totals">
      <div class="totals-row"><span>Subtotal</span><span id="cart-subtotal">₱0.00</span></div>
      <div class="totals-row"><span>Discount</span><span id="cart-discount" style="color:var(--success)">₱0.00</span></div>
      <?php if (($settings['tax_enabled'] ?? 'false') === 'true'): ?>
      <div class="totals-row">
        <span>Tax (<?= round(($settings['tax_rate'] ?? 0.12) * 100, 1) ?>%)</span>
        <span id="cart-tax">₱0.00</span>
      </div>
      <?php endif; ?>
      <div class="totals-row grand"><span>TOTAL</span><span id="cart-total">₱0.00</span></div>
    </div>

    <!-- Cart Actions -->
    <div class="cart-actions">
      <button class="btn btn-success btn-block btn-lg" onclick="POS.openPayment()" style="font-size:16px;padding:14px">
        Payment
      </button>
    </div>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
// POS: suppress realtime toast noise; silently reload fuel+products on price/stock changes
document.body.dataset.realtimeQuiet = 'true';
document.addEventListener('DOMContentLoaded', () => {
  let _rtPOS;
  const debounce = (fn, ms = 2000) => { clearTimeout(_rtPOS); _rtPOS = setTimeout(fn, ms); };
  AppRealtime.onTable('fuel_types', () => debounce(() => { if (typeof POS !== 'undefined') POS.loadFuelTypes?.().then(() => POS.renderFuelButtons?.()); }));
  AppRealtime.onTable('products',   () => debounce(() => { if (typeof POS !== 'undefined') POS.loadProducts?.().then(() => { POS.renderOilCategoryTabs?.(); POS.renderOilProducts?.(); }); }));
  AppRealtime.onTable('fuel_tanks', () => debounce(() => { if (typeof POS !== 'undefined') POS.loadFuelTypes?.().then(() => POS.renderFuelButtons?.()); }));
});
</script>
