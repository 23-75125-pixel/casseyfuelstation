-- ============================================================
-- GASPOS - Gas Station POS System
-- Supabase PostgreSQL Schema + RLS Policies
-- ============================================================
-- This script is IDEMPOTENT — safe to re-run on an existing DB.
-- It drops and recreates all tables, policies, indexes, and seed data.
-- ⚠️ WARNING: This will DELETE all existing data (transactions, products, etc.)
-- ============================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================
-- DROP EXISTING TABLES (reverse dependency order)
-- ============================================================
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS stock_adjustments CASCADE;
DROP TABLE IF EXISTS inventory_receipt_items CASCADE;
DROP TABLE IF EXISTS inventory_receipts CASCADE;
DROP TABLE IF EXISTS payments CASCADE;
DROP TABLE IF EXISTS transaction_items CASCADE;
DROP TABLE IF EXISTS transactions CASCADE;
DROP TABLE IF EXISTS shifts CASCADE;
DROP TABLE IF EXISTS fuel_tank_logs CASCADE;
DROP TABLE IF EXISTS fuel_tanks CASCADE;
DROP TABLE IF EXISTS fuel_types CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS suppliers CASCADE;
DROP TABLE IF EXISTS settings CASCADE;
DROP TABLE IF EXISTS profiles CASCADE;

-- Drop helper function if exists
DROP FUNCTION IF EXISTS get_user_role();

-- ============================================================
-- PROFILES (extends auth.users)
-- ============================================================
CREATE TABLE profiles (
  id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  full_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('admin', 'staff', 'cashier')),
  is_active BOOLEAN DEFAULT TRUE,
  phone TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  name TEXT NOT NULL UNIQUE,
  description TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- SUPPLIERS
-- ============================================================
CREATE TABLE suppliers (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  name TEXT NOT NULL,
  contact_person TEXT,
  phone TEXT,
  email TEXT,
  address TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE products (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  sku TEXT UNIQUE,
  barcode TEXT UNIQUE,
  name TEXT NOT NULL,
  category_id UUID REFERENCES categories(id),
  unit TEXT DEFAULT 'pcs',
  cost NUMERIC(10,2) DEFAULT 0,
  price NUMERIC(10,2) NOT NULL DEFAULT 0,
  stock_qty NUMERIC(10,3) DEFAULT 0,
  low_stock_level NUMERIC(10,3) DEFAULT 5,
  is_active BOOLEAN DEFAULT TRUE,
  image_url TEXT,
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- FUEL TYPES
-- ============================================================
CREATE TABLE fuel_types (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  name TEXT NOT NULL UNIQUE,
  price_per_liter NUMERIC(10,4) NOT NULL,
  cost_per_liter NUMERIC(10,4) DEFAULT 0,
  color TEXT DEFAULT '#3b82f6',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- FUEL TANKS
-- ============================================================
CREATE TABLE fuel_tanks (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  fuel_type_id UUID NOT NULL REFERENCES fuel_types(id),
  tank_name TEXT NOT NULL,
  capacity_liters NUMERIC(10,2) NOT NULL,
  current_liters NUMERIC(10,2) DEFAULT 0,
  low_level_liters NUMERIC(10,2) DEFAULT 500,
  pump_numbers TEXT,
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- FUEL TANK LOGS
-- ============================================================
CREATE TABLE fuel_tank_logs (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tank_id UUID NOT NULL REFERENCES fuel_tanks(id),
  liters_change NUMERIC(10,3) NOT NULL,
  reason TEXT NOT NULL,
  reference_no TEXT,
  created_by UUID REFERENCES profiles(id),
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- SHIFTS
-- ============================================================
CREATE TABLE shifts (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  cashier_id UUID NOT NULL REFERENCES profiles(id),
  opened_at TIMESTAMPTZ DEFAULT NOW(),
  closed_at TIMESTAMPTZ,
  opening_cash NUMERIC(10,2) DEFAULT 0,
  closing_cash NUMERIC(10,2),
  cash_in NUMERIC(10,2) DEFAULT 0,
  cash_out NUMERIC(10,2) DEFAULT 0,
  expected_cash NUMERIC(10,2),
  variance NUMERIC(10,2),
  notes TEXT,
  status TEXT DEFAULT 'open' CHECK (status IN ('open', 'closed'))
);

-- ============================================================
-- TRANSACTIONS
-- ============================================================
CREATE TABLE transactions (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  txn_no TEXT NOT NULL UNIQUE,
  cashier_id UUID NOT NULL REFERENCES profiles(id),
  shift_id UUID REFERENCES shifts(id),
  customer_name TEXT,
  vehicle_plate TEXT,
  company_name TEXT,
  subtotal NUMERIC(10,2) NOT NULL DEFAULT 0,
  discount_total NUMERIC(10,2) DEFAULT 0,
  tax_total NUMERIC(10,2) DEFAULT 0,
  total NUMERIC(10,2) NOT NULL DEFAULT 0,
  payment_status TEXT DEFAULT 'paid' CHECK (payment_status IN ('paid', 'pending', 'voided')),
  voided_at TIMESTAMPTZ,
  void_reason TEXT,
  voided_by UUID REFERENCES profiles(id),
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- TRANSACTION ITEMS
-- ============================================================
CREATE TABLE transaction_items (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
  item_type TEXT NOT NULL CHECK (item_type IN ('product', 'fuel')),
  product_id UUID REFERENCES products(id),
  fuel_type_id UUID REFERENCES fuel_types(id),
  pump_number TEXT,
  qty NUMERIC(10,3) NOT NULL,
  unit_price NUMERIC(10,4) NOT NULL,
  discount NUMERIC(10,2) DEFAULT 0,
  line_total NUMERIC(10,2) NOT NULL,
  meta_json JSONB
);

-- ============================================================
-- PAYMENTS
-- ============================================================
CREATE TABLE payments (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
  method TEXT NOT NULL DEFAULT 'cash' CHECK (method IN ('cash')),
  amount NUMERIC(10,2) NOT NULL,
  reference_no TEXT,
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- INVENTORY RECEIPTS
-- ============================================================
CREATE TABLE inventory_receipts (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  supplier_id UUID REFERENCES suppliers(id),
  received_by UUID NOT NULL REFERENCES profiles(id),
  reference_no TEXT,
  notes TEXT,
  total_amount NUMERIC(10,2) DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- INVENTORY RECEIPT ITEMS
-- ============================================================
CREATE TABLE inventory_receipt_items (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  receipt_id UUID NOT NULL REFERENCES inventory_receipts(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES products(id),
  qty NUMERIC(10,3) NOT NULL,
  cost NUMERIC(10,2) NOT NULL,
  line_total NUMERIC(10,2) NOT NULL
);

-- ============================================================
-- STOCK ADJUSTMENTS
-- ============================================================
CREATE TABLE stock_adjustments (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  product_id UUID NOT NULL REFERENCES products(id),
  qty_change NUMERIC(10,3) NOT NULL,
  reason TEXT NOT NULL,
  notes TEXT,
  created_by UUID NOT NULL REFERENCES profiles(id),
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES profiles(id),
  action TEXT NOT NULL,
  table_name TEXT,
  record_id TEXT,
  details_json JSONB,
  ip_address TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE settings (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  key TEXT NOT NULL UNIQUE,
  value TEXT,
  description TEXT,
  updated_by UUID REFERENCES profiles(id),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_transactions_cashier ON transactions(cashier_id);
CREATE INDEX idx_transactions_shift ON transactions(shift_id);
CREATE INDEX idx_transactions_created ON transactions(created_at);
CREATE INDEX idx_transactions_txn_no ON transactions(txn_no);
CREATE INDEX idx_txn_items_transaction ON transaction_items(transaction_id);
CREATE INDEX idx_payments_transaction ON payments(transaction_id);
CREATE INDEX idx_shifts_cashier ON shifts(cashier_id);
CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_fuel_tank_logs_tank ON fuel_tank_logs(tank_id);

-- ============================================================
-- RLS POLICIES
-- ============================================================

-- Enable RLS on all tables
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE suppliers ENABLE ROW LEVEL SECURITY;
ALTER TABLE fuel_types ENABLE ROW LEVEL SECURITY;
ALTER TABLE fuel_tanks ENABLE ROW LEVEL SECURITY;
ALTER TABLE fuel_tank_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE shifts ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE transaction_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
ALTER TABLE inventory_receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE inventory_receipt_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE stock_adjustments ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE settings ENABLE ROW LEVEL SECURITY;

-- Helper function to get current user role
CREATE OR REPLACE FUNCTION get_user_role()
RETURNS TEXT AS $$
  SELECT role FROM profiles WHERE id = auth.uid()
$$ LANGUAGE SQL SECURITY DEFINER;

-- PROFILES
CREATE POLICY "Users can view own profile" ON profiles FOR SELECT USING (id = auth.uid());
CREATE POLICY "Admin can view all profiles" ON profiles FOR SELECT USING (get_user_role() = 'admin');
CREATE POLICY "Admin can update profiles" ON profiles FOR UPDATE USING (get_user_role() = 'admin');
CREATE POLICY "Admin can insert profiles" ON profiles FOR INSERT WITH CHECK (get_user_role() = 'admin');
CREATE POLICY "User can insert own profile" ON profiles FOR INSERT WITH CHECK (id = auth.uid());

-- CATEGORIES (all authenticated can read; staff/admin can write)
CREATE POLICY "Auth read categories" ON categories FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Staff admin write categories" ON categories FOR ALL USING (get_user_role() IN ('admin','staff'));

-- PRODUCTS (all authenticated can read; staff/admin can write)
CREATE POLICY "Auth read products" ON products FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Staff admin write products" ON products FOR ALL USING (get_user_role() IN ('admin','staff'));

-- SUPPLIERS
CREATE POLICY "Staff admin read suppliers" ON suppliers FOR SELECT USING (get_user_role() IN ('admin','staff'));
CREATE POLICY "Staff admin write suppliers" ON suppliers FOR ALL USING (get_user_role() IN ('admin','staff'));

-- FUEL TYPES (all read; admin write)
CREATE POLICY "Auth read fuel_types" ON fuel_types FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Admin write fuel_types" ON fuel_types FOR ALL USING (get_user_role() = 'admin');

-- FUEL TANKS (all read; admin/staff write)
CREATE POLICY "Auth read fuel_tanks" ON fuel_tanks FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Admin write fuel_tanks" ON fuel_tanks FOR ALL USING (get_user_role() IN ('admin','staff'));

-- FUEL TANK LOGS
CREATE POLICY "Auth read fuel_tank_logs" ON fuel_tank_logs FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Auth insert fuel_tank_logs" ON fuel_tank_logs FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);

-- SHIFTS
CREATE POLICY "Cashier view own shifts" ON shifts FOR SELECT USING (cashier_id = auth.uid() OR get_user_role() IN ('admin','staff'));
CREATE POLICY "Cashier insert own shift" ON shifts FOR INSERT WITH CHECK (cashier_id = auth.uid());
CREATE POLICY "Cashier update own open shift" ON shifts FOR UPDATE USING (cashier_id = auth.uid() OR get_user_role() = 'admin');

-- TRANSACTIONS
CREATE POLICY "Cashier view own transactions" ON transactions FOR SELECT USING (cashier_id = auth.uid() OR get_user_role() IN ('admin','staff'));
CREATE POLICY "Cashier insert transaction" ON transactions FOR INSERT WITH CHECK (cashier_id = auth.uid());
CREATE POLICY "Admin void transaction" ON transactions FOR UPDATE USING (get_user_role() = 'admin' OR cashier_id = auth.uid());

-- TRANSACTION ITEMS
CREATE POLICY "Auth read txn_items" ON transaction_items FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Cashier insert txn_items" ON transaction_items FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);

-- PAYMENTS
CREATE POLICY "Auth read payments" ON payments FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Cashier insert payments" ON payments FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);

-- INVENTORY RECEIPTS
CREATE POLICY "Staff admin manage receipts" ON inventory_receipts FOR ALL USING (get_user_role() IN ('admin','staff'));

-- INVENTORY RECEIPT ITEMS
CREATE POLICY "Staff admin manage receipt_items" ON inventory_receipt_items FOR ALL USING (get_user_role() IN ('admin','staff'));

-- STOCK ADJUSTMENTS
CREATE POLICY "Staff admin manage adjustments" ON stock_adjustments FOR ALL USING (get_user_role() IN ('admin','staff'));

-- AUDIT LOGS
CREATE POLICY "Admin read audit_logs" ON audit_logs FOR SELECT USING (get_user_role() = 'admin');
CREATE POLICY "Auth insert audit_logs" ON audit_logs FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);

-- SETTINGS
CREATE POLICY "Auth read settings" ON settings FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Admin write settings" ON settings FOR ALL USING (get_user_role() = 'admin');

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default settings
INSERT INTO settings (key, value, description) VALUES
  ('business_name', 'Cassey Fuel Station', 'Business name'),
  ('business_address', '123 Main Street, City', 'Business address'),
  ('business_phone', '+1-555-0100', 'Business phone'),
  ('business_tin', '123-456-789', 'Tax Identification Number'),
  ('receipt_header', 'Thank you for your purchase!', 'Receipt footer message'),
  ('receipt_footer', 'Please come again!', 'Receipt footer'),
  ('tax_enabled', 'false', 'Enable tax computation'),
  ('tax_rate', '0.12', 'Tax rate (12%)'),
  ('require_shift', 'true', 'Require open shift for sales'),
  ('thermal_width', '58', 'Thermal printer width mm (58 or 80)'),
  ('currency_symbol', '₱', 'Currency symbol'),
  ('allow_cashier_discount', 'false', 'Allow cashier to apply discounts');

-- Default categories
INSERT INTO categories (name) VALUES
  ('Engine Oil'),
  ('Gear Oil'),
  ('ATF/Fluids');

-- Default fuel types
INSERT INTO fuel_types (name, price_per_liter, cost_per_liter, color) VALUES
  ('Diesel', 58.50, 52.00, '#f59e0b'),
  ('Gasoline 91', 62.00, 55.00, '#3b82f6'),
  ('Gasoline 95', 65.50, 58.00, '#10b981'),
  ('Premium 97', 70.00, 63.00, '#8b5cf6');

-- Sample products: Engine Oil
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-001', NULL, 'Shell Advance 4T 10W-40 1L', id, 'bottle', 190, 250, 30, 5 FROM categories WHERE name='Engine Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-002', NULL, 'Shell Advance AX5 15W-40 1L', id, 'bottle', 160, 220, 25, 5 FROM categories WHERE name='Engine Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-003', NULL, 'Motul 3100 4T 10W-40 1L', id, 'bottle', 280, 380, 15, 5 FROM categories WHERE name='Engine Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-004', NULL, 'Petron Rev-X 4T 10W-40 1L', id, 'bottle', 170, 230, 20, 5 FROM categories WHERE name='Engine Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-005', NULL, 'Castrol Power1 4T 10W-40 1L', id, 'bottle', 200, 270, 18, 5 FROM categories WHERE name='Engine Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-006', NULL, 'Shell Helix HX5 15W-40 1L', id, 'bottle', 280, 380, 12, 5 FROM categories WHERE name='Engine Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'EO-007', NULL, 'Motul 7100 4T 10W-40 1L', id, 'bottle', 480, 600, 10, 3 FROM categories WHERE name='Engine Oil';

-- Sample products: Gear Oil
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'GO-001', NULL, 'Shell Spirax S2 A 80W-90 1L', id, 'bottle', 180, 250, 15, 5 FROM categories WHERE name='Gear Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'GO-002', NULL, 'Motul Gear Oil 80W-90 120ml', id, 'bottle', 45, 65, 40, 10 FROM categories WHERE name='Gear Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'GO-003', NULL, 'Petron Gear Oil 90 1L', id, 'bottle', 150, 210, 20, 5 FROM categories WHERE name='Gear Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'GO-004', NULL, 'Shell Advance Gear Oil 120ml', id, 'bottle', 40, 55, 50, 10 FROM categories WHERE name='Gear Oil';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'GO-005', NULL, 'Castrol Gear Oil 80W-90 1L', id, 'bottle', 190, 260, 12, 5 FROM categories WHERE name='Gear Oil';

-- Sample products: ATF/Fluids
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'ATF-001', NULL, 'Shell ATF Dexron III 1L', id, 'bottle', 220, 300, 10, 3 FROM categories WHERE name='ATF/Fluids';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'ATF-002', NULL, 'Petron ATF Dexron III 1L', id, 'bottle', 180, 250, 12, 3 FROM categories WHERE name='ATF/Fluids';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'ATF-003', NULL, 'Shell Brake Fluid DOT 4 500ml', id, 'bottle', 150, 210, 15, 5 FROM categories WHERE name='ATF/Fluids';
INSERT INTO products (sku, barcode, name, category_id, unit, cost, price, stock_qty, low_stock_level)
SELECT 'ATF-004', NULL, 'Prestone Coolant 1L', id, 'bottle', 130, 190, 20, 5 FROM categories WHERE name='ATF/Fluids';

-- ============================================================
-- FORCE PostgREST to reload schema cache
-- (required after DROP / CREATE so foreign-key joins work)
-- ============================================================
NOTIFY pgrst, 'reload schema';

-- Note: Create admin user through Supabase Auth dashboard first, then run:
-- INSERT INTO profiles (id, full_name, role) VALUES ('<auth_user_uuid>', 'Admin User', 'admin');
