# GasPOS User Manual

Version: 1.0  
System: Cassey Fuel Station POS

## 1. Overview

GasPOS is a web-based point-of-sale and station operations system for fuel sales and oil product sales. It includes:
- Cashier POS terminal
- Shift management
- Transaction history and voiding
- Product inventory and stock receiving
- Fuel type, tank, and refill management
- Reports and low-stock monitoring
- User, role, settings, and audit management

## 2. User Roles and Access

## 2.1 Admin
- Full access to all modules
- Can manage users and settings
- Can void paid transactions
- Can view audit logs

## 2.2 Staff
- Access to inventory, fuel, transactions, shifts, and reports
- Cannot manage users, settings, or audit logs
- Cannot void transactions (admin only)

## 2.3 Cashier
- Access to POS terminal, own transactions, and shifts
- Can process sales and print receipts
- Cannot access inventory/fuel management pages

## 3. Login and Navigation

## 3.1 Login
1. Open `login.php`.
2. Enter email and password.
3. Click `Sign In`.

System behavior:
- Cashier is redirected to `pos.php`.
- Admin and Staff are redirected to `dashboard.php`.

## 3.2 Main Navigation
Use the left sidebar to open modules.
- Main: Dashboard, POS
- Inventory: Oil Products, Receiving, Adjustments, Suppliers
- Fuel: Fuel Management
- Sales: Transactions, Shifts, Reports
- Admin: Users, Audit Log, Settings

Note: Menu visibility depends on role.

## 4. Cashier Daily Workflow

## 4.1 Start of Shift
1. Go to `POS` or `Shifts`.
2. Click `Shift Action` or `Open Shift`.
3. Enter opening cash.
4. Confirm.

If `Require Open Shift for Sales` is enabled in settings, payment is blocked until a shift is open.

## 4.2 Process Sales in POS

### 4.2.1 Add Fuel Sale
1. Select a fuel type.
2. Choose mode:
- `By Liters`
- `By Amount`
3. Enter quantity or amount.
4. Optionally enter pump number.
5. Click `Add Fuel`.

### 4.2.2 Add Oil Product Sale
1. Search by product name, or
2. Scan barcode in `Scan Barcode`, then press Enter, or
3. Click a product tile in the oil product grid.

### 4.2.3 Manage Cart
- Increase/decrease quantity
- Remove line item
- Clear cart using trash icon
- Apply line discount (if allowed in your setup)

### 4.2.4 Complete Payment
1. Click `Payment`.
2. Enter optional customer name and plate/reference.
3. Enter cash tendered.
4. Verify amount due and change.
5. Click `Complete Sale`.

Validation:
- Cart cannot be empty.
- Cash tendered must be greater than or equal to total.

### 4.2.5 Receipt and Printing
After successful sale:
- A receipt modal is shown.
- Click `Print` to send to printer.

Receipt includes:
- Business info
- Transaction number and date/time
- Sold items and totals
- Payment and change

## 4.3 End of Shift
1. Click `Shift Action` (or close from POS shift bar).
2. Enter closing cash and optional notes.
3. Confirm close shift.

System calculates:
- Expected cash
- Variance (over/short)

## 5. Dashboard (Admin/Staff)

Use `Dashboard` to monitor:
- Sales totals and transaction count
- Fuel dispensed totals
- Low stock count
- Active shifts
- Fuel tank levels
- Recent transactions

Use date filters:
- Today
- This Week
- This Month
- This Year
- Custom range

## 6. Transactions Module

## 6.1 View and Filter Transactions
In `Transactions`:
- Search by transaction number, customer, plate, cashier
- Filter by date range and status
- Use quick date buttons

## 6.2 View Transaction Details
Click `View` on a row to open full details:
- Items sold
- Fuel/product quantities
- Discounts, tax, total
- Payment records

## 6.3 Void Transaction (Admin Only)
1. Click `Void` on a paid transaction.
2. Enter reason.
3. Confirm.

Important:
- Void requires reason.
- Voided transaction is marked with `payment_status = voided`.
- Action is logged in audit logs.

## 7. Shifts Module

In `Shifts`, you can:
- Open or close shift via `Shift Action`
- See active shift banner
- Review recent shifts with opening/closing/expected/variance
- Open `Summary` for payment breakdown and totals

Cashier view is scoped to own shifts; higher roles can review broader data.

## 8. Oil Products Module

In `Oil Products`:
- Add new products
- Edit product details
- Set SKU, barcode, category, unit, price, cost
- Set low-stock level
- Toggle active/inactive
- Filter by category, stock state, and active status

Stock indicators:
- Out-of-stock
- Low stock

## 9. Inventory Receiving Module

Use `Receiving` for incoming stock:
1. Select supplier (optional).
2. Enter delivery reference number.
3. Add one or more items with quantity and unit cost.
4. Click `Save Receipt and Update Stock`.

System behavior:
- Creates receipt record
- Increases product stock quantities
- Updates product cost to latest received cost
- Logs action in audit

## 10. Stock Adjustments Module

Use `Adjustments` for stock corrections:
1. Click `New Adjustment`.
2. Select product.
3. Select adjustment type (add/subtract).
4. Enter quantity.
5. Select reason and notes.
6. Save.

Rules:
- Quantity change cannot be zero.
- Negative adjustments will not push stock below zero.
- All adjustments are logged.

## 11. Suppliers Module

In `Suppliers`:
- Add supplier
- Edit supplier information

Fields:
- Name (required)
- Contact person
- Phone
- Email
- Address

Suppliers are used in stock receiving records.

## 12. Fuel Management Module

## 12.1 Fuel Types (Admin/Staff view, Admin edits)
- View fuel type, selling price, cost, margin, status
- Admin can create, edit, deactivate fuel types
- Admin can update fuel price/cost

## 12.2 Fuel Tanks
- View tank level and utilization percentage
- See low-level alerts
- Refill tank with liters and reference note
- Admin can create/edit/delete tanks

System behavior:
- Refill is capped by tank capacity
- Every refill and fuel sale updates tank logs

## 12.3 Tank Activity
`Recent Tank Activity` shows:
- Liter change (+/-)
- Reason
- Reference number
- User and date

## 13. Reports Module (Admin/Staff)

Use date range controls to load reports for a period.

Available report blocks:
- Total sales
- Transaction count
- Fuel revenue
- Product revenue
- Fuel sales by type
- Payment method breakdown
- Sales by cashier
- Top selling products (with estimated profit)
- Low stock products
- Low fuel tanks

Notes:
- Cashiers are restricted from this module.
- For cashier-level users, APIs scope data to their own records where applicable.

## 14. Users and Roles (Admin)

In `Users and Roles`:
- View all user profiles
- Create profile for existing Supabase Auth user UUID
- Edit full name, role, phone, active status

Important:
- User must exist in Supabase Auth before profile creation.
- Roles: `admin`, `staff`, `cashier`.
- Deactivated users cannot log in.

## 15. Settings (Admin)

Settings groups:
- Business information
- Receipt settings (header, footer, thermal width)
- Tax settings (enable/disable and rate)
- POS settings (require shift, cashier discount allowance)

How to save:
1. Edit fields.
2. Click `Save Settings`.
3. Confirm.

Changes are system-wide and logged.

## 16. Audit Log (Admin)

`Audit Log` tracks important actions, including:
- User and timestamp
- Action type
- Affected table/record
- Details payload
- IP address

Use `Load More` for older entries.

## 17. Keyboard Shortcuts (POS)

- `F2`: focus barcode input
- `F4`: open payment modal
- `F5`: clear cart
- `Enter` on barcode field: lookup barcode
- `Enter` on fuel input: add fuel to cart

## 18. Common Errors and Fixes

## 18.1 "No active shift found"
- Open a shift first, then retry payment.
- Check `Require Open Shift for Sales` setting.

## 18.2 "Insufficient cash amount"
- Enter cash tendered greater than or equal to amount due.

## 18.3 "Admin access required"
- You are performing an admin-only action while logged in as Staff/Cashier.

## 18.4 Login failure with active credentials
- Confirm profile exists in `profiles` table.
- Confirm profile is active.

## 18.5 Product/Fuel stock concerns after sale
- Sales deduct stock automatically.
- For corrections, use `Adjustments` (products) or `Refill` (fuel tanks).

## 19. Good Operating Practices

- Open shift before first sale and close shift at end of day.
- Always record void reasons clearly.
- Keep supplier and product data clean (SKU/barcode uniqueness).
- Review low-stock and low-fuel sections daily.
- Limit admin credentials to trusted users.
- Review audit logs for unusual activity.

## 20. End-of-Day Checklist

1. Confirm all cashiers closed shifts.
2. Check shift variance for each cashier.
3. Review voided transactions.
4. Review low stock and low fuel alerts.
5. Export or capture key report totals as needed.
6. Verify critical settings were not changed unexpectedly.

---

For technical deployment/setup, use `SETUP.md` and `THERMAL_PRINTER_SETUP.md`.
