<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/supabase.php';

$mw      = new Middleware();
$profile = $mw->requireSession(['admin','staff']);
$sb      = new Supabase();
$settings = getSettings($sb);

$pageTitle = 'Gas Tank Products';
$activePage = 'gas_products.php';
$extraScripts = ['/products.js'];
$inlineScript = 'window.__canDelete = ' . json_encode(in_array($profile['role'], ['admin','staff'])) . ';'
  . 'window.INVENTORY_CONFIG = ' . json_encode([
      'allowedCategoryNames' => ['LPG Tanks', 'LPG Accessories'],
      'productLabel' => 'Gas Tank Product',
      'nameLabel' => 'Gas Product Name',
      'namePlaceholder' => 'e.g. Regasco 11kg LPG Tank - Filled',
      'skuPlaceholder' => 'LPG-001',
      'skuPrefix' => 'LPG',
      'barcodePlaceholder' => 'Optional gas tank barcode',
      'categoryPlaceholder' => '— Select Gas Category —',
      'filterCategoryLabel' => 'All Gas Categories',
      'allowedUnits' => ['tank', 'set', 'pack', 'pcs', 'kg'],
      'defaultUnit' => 'tank',
    ]) . ';';
include __DIR__ . '/layout.php';
?>
<div style="display:flex;flex-direction:column;gap:20px">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h1 style="margin:0;font-size:22px;font-weight:800;flex:1">Gas Tank Products</h1>
    <button class="btn btn-primary" onclick="openProductModal()">+ Add Gas Tank Product</button>
  </div>
  <div class="card" style="padding:14px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input type="text" class="form-control" id="search-input" placeholder="Search gas tank name, barcode, SKU..." style="max-width:280px" oninput="filterProducts()">
      <select class="form-select" id="cat-filter" style="max-width:180px" onchange="filterProducts()"><option value="">All Gas Categories</option></select>
      <select class="form-select" id="stock-filter" style="max-width:160px" onchange="filterProducts()"><option value="">All Stock</option><option value="low">Low Stock</option><option value="out">Out of Stock</option></select>
      <select class="form-select" id="active-filter" style="max-width:140px" onchange="filterProducts()"><option value="active">Active Only</option><option value="">All (incl. Inactive)</option><option value="inactive">Inactive Only</option></select>
      <span id="product-count" style="font-size:13px;color:var(--text-muted);margin-left:auto"></span>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Product</th><th>SKU / Barcode</th><th>Category</th><th>Price</th><th>Cost</th><th>Stock</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody id="products-tbody"><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Loading gas tank products...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/layout_end.php'; ?>
