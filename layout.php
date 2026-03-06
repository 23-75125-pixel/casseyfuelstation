<?php
// includes/layout.php
// Usage: include after session + auth check
// $pageTitle, $activePage, $profile must be set

$role     = $profile['role'] ?? 'cashier';
$userName = $profile['full_name'] ?? 'User';
$roleLabel = ucfirst($role);
$initial  = strtoupper(substr($userName, 0, 1));

// Nav items per role
$nav = [
  [
    'section' => 'Main',
    'items'   => [
      ['page' => 'dashboard.php', 'label' => 'Dashboard',   'icon' => '📊', 'roles' => ['admin','staff','cashier']],
      ['page' => 'pos.php',       'label' => 'POS',          'icon' => '🖥️', 'roles' => ['admin','cashier']],
    ],
  ],
  [
    'section' => 'Inventory',
    'items'   => [
      ['page' => 'products.php',    'label' => 'Oil Products',    'icon' => '🛢️', 'roles' => ['admin','staff']],
      ['page' => 'receiving.php',   'label' => 'Receiving',   'icon' => '📥', 'roles' => ['admin','staff']],
      ['page' => 'adjustments.php', 'label' => 'Adjustments', 'icon' => '⚖️', 'roles' => ['admin','staff']],
      ['page' => 'suppliers.php',   'label' => 'Suppliers',   'icon' => '🏭', 'roles' => ['admin','staff']],
    ],
  ],
  [
    'section' => 'Fuel',
    'items'   => [
      ['page' => 'fuel.php', 'label' => 'Fuel Mgmt', 'icon' => '⛽', 'roles' => ['admin','staff']],
    ],
  ],
  [
    'section' => 'Sales',
    'items'   => [
      ['page' => 'transactions.php', 'label' => 'Transactions', 'icon' => '🧾', 'roles' => ['admin','staff','cashier']],
      ['page' => 'shifts.php',       'label' => 'Shifts',        'icon' => '🕐', 'roles' => ['admin','staff','cashier']],
      ['page' => 'reports.php',      'label' => 'Reports',       'icon' => '📈', 'roles' => ['admin','staff']],
    ],
  ],
  [
    'section' => 'Admin',
    'items'   => [
      ['page' => 'users.php',     'label' => 'Users',      'icon' => '👥', 'roles' => ['admin']],
      ['page' => 'audit.php',     'label' => 'Audit Log',  'icon' => '🔍', 'roles' => ['admin']],
      ['page' => 'settings.php',  'label' => 'Settings',   'icon' => '⚙️', 'roles' => ['admin']],
    ],
  ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Cassey Fuel Station') ?> — Cassey Fuel Station</title>
  <?php
    $settings = $settings ?? [];
    $bizName  = $settings['business_name'] ?? 'Cassey Fuel Station';
    $bizAddr  = $settings['business_address'] ?? '';
    $bizTin   = $settings['business_tin'] ?? '';
  ?>
  <meta name="biz-name" content="<?= htmlspecialchars($bizName) ?>">
  <meta name="biz-addr" content="<?= htmlspecialchars($bizAddr) ?>">
  <meta name="biz-tin"  content="<?= htmlspecialchars($bizTin) ?>">
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <!-- App styles -->
  <link rel="stylesheet" href="/style.css">
  <!-- Supabase public config for JS realtime -->
  <meta name="sb-url"  content="<?= htmlspecialchars(defined('SUPABASE_URL') ? SUPABASE_URL : '') ?>">
  <meta name="sb-anon" content="<?= htmlspecialchars(defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '') ?>">
  <style>
    .suggestion-item {
      padding: 10px 14px;
      cursor: pointer;
      border-bottom: 1px solid var(--border-light);
      transition: background .1s;
    }
    .suggestion-item:hover { background: var(--surface2); }
    .suggestion-item:last-child { border-bottom: none; }
    #product-suggestions {
      position: absolute;
      top: 100%; left: 0; right: 0;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 0 0 var(--radius) var(--radius);
      z-index: 50;
      max-height: 320px;
      overflow-y: auto;
      box-shadow: var(--shadow);
    }
    .search-wrapper { position: relative; flex: 1; }
    .sidebar-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.45);
      backdrop-filter: blur(2px);
      z-index: 1049;   /* below sidebar (1050) on mobile */
    }
  </style>
</head>
<body class="is-app">
<div id="sidebar-overlay" class="sidebar-overlay hidden" onclick="document.getElementById('sidebar').classList.remove('mobile-open');this.classList.add('hidden')"></div>

<div id="app">
  <!-- ── Sidebar ── -->
  <nav class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="/dashboard.php">
      <img src="/logo1.png" alt="Logo" style="width:32px;height:32px;object-fit:cover;border-radius:50%;flex-shrink:0;">
      <div class="brand-name"><?= htmlspecialchars($bizName) ?><small>Gas &amp; Fuel POS</small></div>
    </a>

    <div class="sidebar-nav">
      <?php foreach ($nav as $section): ?>
        <?php $visibleItems = array_filter($section['items'], fn($i) => in_array($role, $i['roles'])); ?>
        <?php if (empty($visibleItems)) continue; ?>
        <div class="nav-section">
          <div class="nav-section-label"><?= $section['section'] ?></div>
          <?php foreach ($visibleItems as $item): ?>
            <a href="/<?= $item['page'] ?>" class="nav-item" data-page="<?= $item['page'] ?>">
              <span class="nav-icon"><?= $item['icon'] ?></span>
              <span class="nav-label"><?= $item['label'] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
      <div class="user-info" onclick="document.getElementById('user-menu')?.classList.toggle('hidden')">
        <div class="user-avatar"><?= $initial ?></div>
        <div class="user-info-text">
          <div class="user-name"><?= htmlspecialchars($userName) ?></div>
          <div class="user-role"><?= $roleLabel ?></div>
        </div>
      </div>
      <div id="user-menu" class="hidden" style="margin-top:4px;padding:4px 0">
        <a href="#" class="nav-item" style="font-size:12.5px;padding:7px 10px;background:#dc2626;color:#ffffff;border-radius:8px;margin-top:4px" onclick="logout()">🚪 Logout</a>
      </div>
    </div>
  </nav>

  <!-- ── Main ── -->
  <div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
      <button class="btn-icon" id="sidebar-toggle" aria-label="Toggle sidebar">
        <i class="bi bi-list" style="font-size:18px"></i>
      </button>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      <div class="topbar-actions">
        <span style="font-size:12px;color:var(--text-muted)" id="topbar-time"></span>
        <div class="d-none d-md-block" style="font-size:12px;color:var(--text-muted)"><?= date('M d, Y') ?></div>
        <button onclick="logout()" title="Logout" class="btn-logout">
          <i class="bi bi-box-arrow-right"></i>
          <span class="d-none d-sm-inline">Logout</span>
        </button>
      </div>
    </div>
    <!-- Page content starts here -->
    <div class="page-body">
