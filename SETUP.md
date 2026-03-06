# GasPOS — Setup & Deployment Guide
## Gas Station POS System

---

## 📋 Requirements

- PHP 8.1+ with `curl` extension enabled
- Apache/Nginx with `.htaccess` support (Apache) or custom config (Nginx)
- Supabase account (free tier works for testing)
- Shared hosting, VPS, or Railway/Render (PHP-supported)

---

## 🚀 Quick Setup

### Step 1: Supabase Project Setup

1. Go to [supabase.com](https://supabase.com) and create a new project
2. Go to **SQL Editor** and run the full contents of `supabase_schema.sql`
3. Go to **Project Settings → API** and copy:
   - `Project URL` → `SUPABASE_URL`
   - `anon public` key → `SUPABASE_ANON_KEY`
   - `service_role secret` key → `SUPABASE_SERVICE_KEY`

### Step 2: Create Admin User

1. In Supabase, go to **Authentication → Users → Add User**
2. Create a user with email/password (e.g., `admin@yourstore.com`)
3. Copy the generated **User UUID**
4. In **SQL Editor**, run:
```sql
INSERT INTO profiles (id, full_name, role, is_active)
VALUES ('<YOUR_UUID_HERE>', 'Admin User', 'admin', true);
```

### Step 3: Configure Environment

```bash
cp .env.example .env
```

Edit `.env`:
```env
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_ANON_KEY=eyJhbGc...
SUPABASE_SERVICE_KEY=eyJhbGc...
```

> ⚠️ **NEVER** expose `SUPABASE_SERVICE_KEY` in frontend code or public files.

### Step 4: Upload to Server

Upload all files to your web server's document root (e.g., `public_html/` or `/var/www/html/`).

Make sure `.htaccess` is enabled (Apache: `AllowOverride All` in your VirtualHost config).

### Step 5: Verify Installation

Visit `https://yourdomain.com` — you should see the login page.

Log in with your admin credentials.

---

## 🏗️ Folder Structure

```
gaspos/
├── .env                    # Environment variables (never commit)
├── .env.example            # Template
├── .htaccess               # Apache routing rules
├── supabase_schema.sql     # Full database schema + RLS
│
├── public/                 # Web-accessible PHP pages
│   ├── index.php           # Root redirect
│   ├── login.php           # Login page
│   ├── dashboard.php       # Main dashboard
│   ├── pos.php             # POS Terminal
│   ├── products.php        # Product management
│   ├── fuel.php            # Fuel types & tanks
│   ├── receiving.php       # Inventory receiving
│   ├── adjustments.php     # Stock adjustments
│   ├── suppliers.php       # Supplier directory
│   ├── transactions.php    # Transaction history
│   ├── shifts.php          # Shift management
│   ├── reports.php         # Analytics & reports
│   ├── users.php           # User management (admin)
│   ├── audit.php           # Audit logs (admin)
│   └── settings.php        # System settings (admin)
│
├── api/                    # REST API endpoints
│   ├── auth.php            # Login/logout/session
│   ├── transactions.php    # Create/void/list transactions
│   ├── products.php        # Product CRUD
│   ├── fuel.php            # Fuel types, tanks, prices
│   ├── shifts.php          # Open/close shifts
│   ├── inventory.php       # Receiving, adjustments, suppliers
│   ├── reports.php         # Analytics queries
│   ├── users.php           # User/profile management
│   └── settings.php        # App configuration
│
├── includes/               # Shared PHP modules
│   ├── supabase.php        # Supabase REST client
│   ├── middleware.php      # Auth & role enforcement
│   ├── helpers.php         # Utility functions
│   ├── layout.php          # HTML header + sidebar
│   └── layout_end.php      # HTML footer + scripts
│
└── assets/
    ├── css/style.css       # Complete custom stylesheet
    └── js/
        ├── app.js          # Core JS (API, toast, modal)
        └── pos.js          # POS cart & payment logic
```

---

## 👥 Default User Roles

| Role    | Level | Access |
|---------|-------|--------|
| Admin   | 3     | Full access — users, settings, reports, void |
| Staff   | 2     | Inventory, receiving, limited reports |
| Cashier | 1     | POS only, own shifts, own transactions |

---

## 💰 POS Workflow

```
1. Login as Cashier
2. Open Shift (enter opening cash)
3. POS Screen:
   a. Select fuel type → enter liters OR amount → Add to cart
   b. Scan barcode OR search product → auto-added
4. Click Payment (or press F4)
5. Select payment method (Cash/Card/E-Wallet/Charge)
6. Complete Sale → receipt generated
7. Close Shift (enter closing cash)
8. View Shift Summary
```

### Keyboard Shortcuts (POS)
- `F2` — Focus barcode scanner input
- `F4` — Open payment modal
- `F5` — Clear cart
- `Enter` (on fuel input) — Add fuel to cart
- `Escape` — Close modal

---

## 🔐 Security Notes

1. **Service Key**: Only used server-side in PHP. Never in frontend JS.
2. **RLS Policies**: Enforced at database level. Even if API is bypassed, data is protected.
3. **Session**: PHP sessions with JWT stored server-side. Token validated on every API call.
4. **Input Sanitization**: All user inputs are sanitized before processing.
5. **HTTPS**: Always deploy with SSL/TLS (free via Let's Encrypt).

---

## 🌐 Hosting Options

### Shared Hosting (Recommended for small stores)
- Hostinger, SiteGround, Namecheap
- Upload files via FTP/cPanel
- Set `.env` variables (or hardcode in `supabase.php` if env vars not supported)

### VPS (More control)
```bash
# Ubuntu setup
apt install php8.2 php8.2-curl apache2
a2enmod rewrite
# Upload files to /var/www/html
# Configure Apache VirtualHost with AllowOverride All
```

### Railway / Render (PaaS)
- Create a PHP project
- Set environment variables in their dashboard
- Deploy via Git

---

## 🔧 Common Issues

**"Profile not found" on login**
→ Make sure you inserted the profile row in the `profiles` table after creating the Supabase Auth user.

**API returns 401 Unauthorized**
→ Check that `SUPABASE_URL`, `SUPABASE_ANON_KEY`, and `SUPABASE_SERVICE_KEY` are set correctly.

**curl not working**
→ Enable the PHP curl extension: `extension=curl` in `php.ini`

**Blank page**
→ Check PHP error logs. Set `APP_DEBUG=true` temporarily.

**Stock not deducting after sale**
→ Ensure the product ID in transaction items matches a real product in the `products` table.

---

## 📦 Adding Sample Data

Run these in Supabase SQL Editor after setup:

```sql
-- Add a cashier user (after creating in Auth first)
INSERT INTO profiles (id, full_name, role)
VALUES ('<cashier_uuid>', 'Maria Santos', 'cashier');

-- Add a staff user
INSERT INTO profiles (id, full_name, role)
VALUES ('<staff_uuid>', 'Jose Reyes', 'staff');

-- Verify fuel tanks
SELECT ft.name, tk.tank_name, tk.current_liters, tk.capacity_liters
FROM fuel_tanks tk
JOIN fuel_types ft ON ft.id = tk.fuel_type_id;
```

---

## 📱 Mobile Use

The system is fully responsive. On mobile:
- Sidebar collapses to a hamburger menu
- POS stacks vertically (cart below items)
- All modals are scroll-friendly

For cashier-only mobile use, consider bookmarking `/pos.php` directly.

---

*GasPOS v1.0 — Built with PHP 8+, Supabase, Bootstrap 5, Vanilla JS*
