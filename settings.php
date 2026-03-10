<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle  = 'Settings';
$activePage = 'settings.php';
include __DIR__ . '/layout.php';
?>

<div style="display:flex;flex-direction:column;gap:24px">

  <h1 style="margin:0;font-size:22px;font-weight:800">Settings</h1>

  <!-- Business Info -->
  <div class="card">
    <div class="card-header"><span>🏪</span><h3 class="card-title">Business Information</h3></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div style="grid-column:1/-1">
        <label class="form-label">Business Name</label>
        <input type="text" class="form-control" id="s-business_name" value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>">
      </div>
      <div style="grid-column:1/-1">
        <label class="form-label">Address</label>
        <input type="text" class="form-control" id="s-business_address" value="<?= htmlspecialchars($settings['business_address'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">Phone</label>
        <input type="text" class="form-control" id="s-business_phone" value="<?= htmlspecialchars($settings['business_phone'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">TIN (optional)</label>
        <input type="text" class="form-control" id="s-business_tin" value="<?= htmlspecialchars($settings['business_tin'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">Currency Symbol</label>
        <input type="text" class="form-control" id="s-currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '₱') ?>" maxlength="3">
      </div>
    </div>
  </div>

  <!-- Receipt Settings -->
  <div class="card">
    <div class="card-header"><span>🧾</span><h3 class="card-title">Receipt Settings</h3></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div>
        <label class="form-label">Receipt Header</label>
        <input type="text" class="form-control" id="s-receipt_header" value="<?= htmlspecialchars($settings['receipt_header'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">Receipt Footer</label>
        <input type="text" class="form-control" id="s-receipt_footer" value="<?= htmlspecialchars($settings['receipt_footer'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">Thermal Width</label>
        <input type="hidden" id="s-thermal_width" value="58">
        <input type="text" class="form-control" value="58mm" readonly>
      </div>
    </div>
  </div>

  <!-- Tax Settings -->
  <div class="card">
    <div class="card-header"><span>💹</span><h3 class="card-title">Tax Settings</h3></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div>
        <label class="form-label">Enable Tax</label>
        <select class="form-select" id="s-tax_enabled">
          <option value="false" <?= ($settings['tax_enabled'] ?? 'false') === 'false' ? 'selected' : '' ?>>Disabled</option>
          <option value="true"  <?= ($settings['tax_enabled'] ?? 'false') === 'true'  ? 'selected' : '' ?>>Enabled</option>
        </select>
      </div>
      <div>
        <label class="form-label">Tax Rate (e.g. 0.12 for 12%)</label>
        <input type="number" class="form-control" id="s-tax_rate" value="<?= htmlspecialchars($settings['tax_rate'] ?? '0.12') ?>" min="0" max="1" step="0.01">
      </div>
    </div>
  </div>

  <!-- POS Settings -->
  <div class="card">
    <div class="card-header"><span>🖥️</span><h3 class="card-title">POS Settings</h3></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div>
        <label class="form-label">Require Open Shift for Sales</label>
        <select class="form-select" id="s-require_shift">
          <option value="true"  <?= ($settings['require_shift'] ?? 'true') === 'true'  ? 'selected' : '' ?>>Required</option>
          <option value="false" <?= ($settings['require_shift'] ?? 'true') === 'false' ? 'selected' : '' ?>>Optional</option>
        </select>
      </div>
      <div>
        <label class="form-label">Allow Cashier to Apply Discounts</label>
        <select class="form-select" id="s-allow_cashier_discount">
          <option value="false" <?= ($settings['allow_cashier_discount'] ?? 'false') === 'false' ? 'selected' : '' ?>>No (Admin Only)</option>
          <option value="true"  <?= ($settings['allow_cashier_discount'] ?? 'false') === 'true'  ? 'selected' : '' ?>>Yes</option>
        </select>
      </div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end">
    <button class="btn btn-primary btn-lg" id="save-settings-btn" onclick="saveSettings()">
      Save Settings
    </button>
  </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
<script>
async function saveSettings() {
  const btn = document.getElementById('save-settings-btn');
  const confirmed = await App.confirm(
    'Save all settings changes? This will affect the entire system.',
    'Save Settings',
    { icon: 'question', confirmText: 'Yes, save', confirmColor: '#f97316' }
  );
  if (!confirmed) return;

  const keys = ['business_name','business_address','business_phone','business_tin','currency_symbol',
                 'receipt_header','receipt_footer','thermal_width',
                 'tax_enabled','tax_rate','require_shift','allow_cashier_discount'];

  const payload = {};
  keys.forEach(k => {
    const el = document.getElementById('s-' + k);
    if (el) payload[k] = el.value;
  });

  App.loading.show(btn, 'Saving...');
  try {
    await App.post('/api_settings.php?action=update', payload);
    App.toast('Settings saved successfully', 'success');
  } catch (err) {
    App.toast(err.message, 'error');
  } finally {
    App.loading.hide(btn);
  }
}
</script>
