<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/supabase.php';
session_start();

// Redirect if already logged in
if (!empty($_SESSION['profile'])) {
    header('Location: /dashboard.php');
    exit;
}

$error    = htmlspecialchars($_GET['error'] ?? '');
$errorMsg = match($error) {
    'deactivated' => 'Your account has been deactivated. Contact admin.',
    'forbidden'   => 'Access denied for that page.',
    default        => '',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Cassey Fuel Station</title>
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  <style>
    /* ── Login two-column layout ── */

    /* Full-screen backdrop */
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
      padding: 24px;
      overflow-y: auto;     /* let backdrop scroll if box is taller than viewport */
    }

    /* 75% centered container — no fixed height so form never clips */
    .login-box {
      display: flex;
      flex-direction: row;
      width: 75%;
      min-height: 60vh;
      max-height: 92vh;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.09);
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.04);
      flex-shrink: 0;
    }

    /* LEFT: form panel */
    .login-form-panel {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 48px 40px;
      background: var(--surface);
      overflow-y: auto;
      min-height: 0;   /* allow shrinking inside flex */
    }
    .login-card {
      width: 100%;
      max-width: 400px;
    }

    /* RIGHT: branding panel */
    .login-brand-panel {
      width: 42%;
      flex-shrink: 0;
      background: linear-gradient(150deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      position: relative;
      overflow: hidden;
    }
    .login-brand-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 20% 20%, rgba(249,115,22,.18) 0%, transparent 55%),
        radial-gradient(circle at 80% 80%, rgba(59,130,246,.12) 0%, transparent 55%);
      pointer-events: none;
    }
    .login-brand-panel .logo-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      filter: brightness(.85) saturate(1.1);
      z-index: 0;
    }
    .login-brand-panel .brand-tagline {
      color: rgba(255,255,255,.95);
      font-size: 20px;
      font-weight: 800;
      text-align: center;
      letter-spacing: -.3px;
      text-shadow: 0 2px 12px rgba(0,0,0,.7);
      position: relative;
      z-index: 2;
    }
    .login-brand-panel .brand-sub {
      color: rgba(255,255,255,.7);
      font-size: 13px;
      text-align: center;
      margin-top: 8px;
      text-shadow: 0 1px 6px rgba(0,0,0,.6);
      position: relative;
      z-index: 2;
    }
    .login-brand-dots {
      display: flex;
      gap: 8px;
      margin-top: 20px;
      position: relative;
      z-index: 2;
    }
    .login-brand-dots span {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: rgba(255,255,255,.25);
    }
    .login-brand-dots span:first-child { background: var(--accent); }

    /* Responsive: tablet */
    @media (max-width: 1100px) {
      .login-box { width: 88%; }
    }
    @media (max-width: 992px) {
      .login-box { width: 94%; min-height: 50vh; }
    }
    /* Mobile: stack logo on top, form below */
    @media (max-width: 768px) {
      .login-page  { padding: 0; align-items: flex-start; }
      .login-box   {
        flex-direction: column;
        width: 100%;
        min-height: 100vh;
        max-height: none;
        border-radius: 0;
        border: none;
        box-shadow: none;
      }
      .login-brand-panel {
        width: 100%;
        min-height: 230px;
        max-height: 280px;
        flex-shrink: 0;
        padding: 28px 24px;
      }
      .login-brand-panel .logo-img { object-position: center center; }
      .login-brand-panel .brand-tagline { font-size: 16px; }
      .login-form-panel {
        padding: 36px 28px;
        flex: 1;
        justify-content: flex-start;
        padding-top: 40px;
      }
    }
    @media (max-width: 480px) {
      .login-brand-panel { min-height: 190px; max-height: 220px; padding: 20px 16px; }
      .login-form-panel  { padding: 28px 20px; }
      .login-card h2      { font-size: 22px !important; }
    }
  </style>
</head>
<body>
<div class="login-page">
<div class="login-box">

  <!-- ── LEFT: Auth Form ── -->
  <div class="login-form-panel">
    <div class="login-card anim-fade">

      <div style="margin-bottom:32px">
        <h2 style="font-size:26px;font-weight:800;margin:0 0 6px;letter-spacing:-.5px;color:var(--text-primary)">Welcome back</h2>
        <p style="color:var(--text-muted);font-size:13.5px;margin:0">Sign in to your Cassey Fuel Station account</p>
      </div>

      <?php if ($errorMsg): ?>
        <div class="alert-strip danger" style="margin-bottom:16px">⚠️ <?= $errorMsg ?></div>
      <?php endif; ?>

      <div id="login-error" class="alert-strip danger hidden" style="margin-bottom:16px"></div>

      <form id="login-form" style="display:flex;flex-direction:column;gap:18px" novalidate>
        <div>
          <label class="form-label">Email Address</label>
          <input type="email" class="form-control" id="email" name="email"
            placeholder="admin@example.com" required autocomplete="email"
            style="padding:11px 14px;font-size:15px">
        </div>
        <div>
          <label class="form-label">Password</label>
          <div style="position:relative">
            <input type="password" class="form-control" id="password" name="password"
              placeholder="••••••••" required autocomplete="current-password"
              style="padding:11px 14px;font-size:15px;padding-right:48px">
            <button type="button" onclick="togglePwd()"
              style="position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;cursor:pointer;color:var(--text-muted);font-size:17px;line-height:1;padding:4px"
              id="pwd-toggle" title="Show/hide password">
              <i class="bi bi-eye" id="pwd-icon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="login-btn"
          style="margin-top:4px;padding:13px;font-size:15px;font-weight:700;border-radius:var(--radius)">
          <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
      </form>

      <div style="margin-top:32px;padding-top:20px;border-top:1px solid var(--border);text-align:center;font-size:12px;color:var(--text-muted)">
        Cassey Fuel Station v1.0 &middot; Powered by Supabase
      </div>
    </div>
  </div>

  <!-- ── RIGHT: Branding Panel ── -->
  <div class="login-brand-panel">
    <img src="/logo1.png" alt="Cassey Fuel Station Logo" class="logo-img">
    <div class="brand-tagline"></div>
    <div class="brand-sub"></div>
    <div class="login-brand-dots">
      <span></span><span></span><span></span>
    </div>
  </div>

</div><!-- /.login-box -->
</div><!-- /.login-page -->

<div id="toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFPVZtHDJhNt3zSEYgasJW5EQo" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/app.js"></script>
<script>
function togglePwd() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('pwd-icon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn   = document.getElementById('login-btn');
  const errEl = document.getElementById('login-error');
  errEl.classList.add('hidden');

  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;

  if (!email || !password) {
    errEl.textContent = 'Please enter email and password';
    errEl.classList.remove('hidden');
    return;
  }

  App.loading.show(btn, 'Signing in...');

  try {
    const result = await App.post('/api_auth.php?action=login', { email, password });
    if (result.success) {
      App.toast('Login successful! Redirecting...', 'success');
      const role = result.profile.role;
      setTimeout(() => {
        window.location.href = role === 'cashier' ? '/pos.php' : '/dashboard.php';
      }, 500);
    }
  } catch (err) {
    errEl.textContent = err.message || 'Invalid email or password';
    errEl.classList.remove('hidden');
    App.loading.hide(btn);
  }
});

document.getElementById('email').focus();
</script>
</body>
</html>
