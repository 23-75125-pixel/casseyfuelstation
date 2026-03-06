<?php
// api/transactions.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/middleware.php';

header('Content-Type: application/json');
// NOTE: Do NOT call session_start() here — middleware.php handles it.
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

$sb     = new Supabase();
$mw     = new Middleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// All endpoints require auth
$profile = $mw->requireAuth();
$jwt     = $mw->getJwt();
$userId  = $profile['id'];

// ============================================================
// CREATE TRANSACTION (POST /api/transactions.php?action=create)
// ============================================================
if ($method === 'POST' && $action === 'create') {
    $body = json_decode(file_get_contents('php://input'), true);

    // Validate shift
    $shiftId = sanitize($body['shift_id'] ?? '');
    if ($shiftId) {
        $shiftRes = $sb->select('shifts', "id=eq.{$shiftId}&status=eq.open&cashier_id=eq.{$userId}");
        if (empty($shiftRes['data'])) jsonError('No active shift found');
    }

    $items = $body['items'] ?? [];
    if (empty($items)) jsonError('No items in transaction');

    // Compute totals
    $subtotal = 0;
    $discountTotal = 0;
    foreach ($items as $item) {
        $subtotal      += (float)$item['line_total'] + (float)($item['discount'] ?? 0);
        $discountTotal += (float)($item['discount'] ?? 0);
    }
    $settings = getSettings($sb);
    $taxEnabled = ($settings['tax_enabled'] ?? 'false') === 'true';
    $taxRate    = (float)($settings['tax_rate'] ?? 0.12);
    $taxTotal   = $taxEnabled ? ($subtotal - $discountTotal) * $taxRate : 0;
    $total      = ($subtotal - $discountTotal) + $taxTotal;

    // Insert transaction
    $txnNo = generateTxnNo();
    $txnData = [
        'txn_no'         => $txnNo,
        'cashier_id'     => $userId,
        'shift_id'       => $shiftId ?: null,
        'customer_name'  => sanitize($body['customer_name'] ?? ''),
        'vehicle_plate'  => sanitize($body['vehicle_plate'] ?? ''),
        'company_name'   => sanitize($body['company_name'] ?? ''),
        'subtotal'       => round($subtotal, 2),
        'discount_total' => round($discountTotal, 2),
        'tax_total'      => round($taxTotal, 2),
        'total'          => round($total, 2),
        'payment_status' => 'paid',
        'notes'          => sanitize($body['notes'] ?? ''),
    ];

    $txnResult = $sb->insert('transactions', $txnData);
    if ($txnResult['status'] !== 201) jsonError('Failed to create transaction');
    $transaction = $txnResult['data'][0];
    $txnId = $transaction['id'];

    // Insert transaction items
    foreach ($items as $item) {
        $itemData = [
            'transaction_id' => $txnId,
            'item_type'      => $item['item_type'],
            'product_id'     => $item['product_id'] ?? null,
            'fuel_type_id'   => $item['fuel_type_id'] ?? null,
            'pump_number'    => sanitize($item['pump_number'] ?? ''),
            'qty'            => (float)$item['qty'],
            'unit_price'     => (float)$item['unit_price'],
            'discount'       => (float)($item['discount'] ?? 0),
            'line_total'     => (float)$item['line_total'],
            'meta_json'      => $item['meta_json'] ?? null,
        ];
        $sb->insert('transaction_items', $itemData);

        // Deduct product stock
        if ($item['item_type'] === 'product' && !empty($item['product_id'])) {
            $pid = $item['product_id'];
            $qty = (float)$item['qty'];
            $prodRes = $sb->select('products', "id=eq.{$pid}&select=stock_qty");
            if (!empty($prodRes['data'])) {
                $newStock = (float)$prodRes['data'][0]['stock_qty'] - $qty;
                $sb->update('products', "id=eq.{$pid}", ['stock_qty' => max(0, $newStock)]);
            }
        }

        // Deduct fuel from tank
        if ($item['item_type'] === 'fuel' && !empty($item['fuel_type_id'])) {
            $fid  = $item['fuel_type_id'];
            $qty  = (float)$item['qty'];
            $tankRes = $sb->select('fuel_tanks', "fuel_type_id=eq.{$fid}&select=id,current_liters&limit=1");
            if (!empty($tankRes['data'])) {
                $tank    = $tankRes['data'][0];
                $newLvl  = (float)$tank['current_liters'] - $qty;
                $sb->update('fuel_tanks', "id=eq.{$tank['id']}", ['current_liters' => max(0, $newLvl)]);
                $sb->insert('fuel_tank_logs', [
                    'tank_id'      => $tank['id'],
                    'liters_change' => -$qty,
                    'reason'       => 'sale',
                    'reference_no' => $txnNo,
                    'created_by'   => $userId,
                ]);
            }
        }
    }

    // Insert payments
    $payments = $body['payments'] ?? [];
    foreach ($payments as $pay) {
        $sb->insert('payments', [
            'transaction_id' => $txnId,
            'method'         => $pay['method'],
            'amount'         => (float)$pay['amount'],
            'reference_no'   => sanitize($pay['reference_no'] ?? ''),
            'notes'          => sanitize($pay['notes'] ?? ''),
        ]);
    }

    logAudit($sb, $userId, 'create_transaction', 'transactions', $txnId, ['txn_no' => $txnNo, 'total' => $total]);
    jsonResponse(['success' => true, 'transaction' => $transaction, 'txn_no' => $txnNo]);
}

// ============================================================
// VOID TRANSACTION
// ============================================================
if ($method === 'POST' && $action === 'void') {
    if (!$mw->hasRole('admin')) jsonError('Admin access required', 403);

    $body   = json_decode(file_get_contents('php://input'), true);
    $txnId  = sanitize($body['transaction_id'] ?? '');
    $reason = sanitize($body['reason'] ?? '');

    if (!$txnId || !$reason) jsonError('Transaction ID and reason required');

    $result = $sb->update('transactions', "id=eq.{$txnId}", [
        'payment_status' => 'voided',
        'voided_at'      => date('c'),
        'void_reason'    => $reason,
        'voided_by'      => $userId,
    ]);

    if ($result['status'] !== 200) jsonError('Failed to void transaction');

    logAudit($sb, $userId, 'void_transaction', 'transactions', $txnId, ['reason' => $reason]);
    jsonResponse(['success' => true]);
}

// ============================================================
// LIST TRANSACTIONS
// ============================================================
if ($method === 'GET' && $action === 'list') {
    $limit    = min((int)($_GET['limit'] ?? 50), 200);
    $offset   = (int)($_GET['offset'] ?? 0);
    $date_from = sanitize($_GET['date_from'] ?? '');
    $date_to   = sanitize($_GET['date_to'] ?? '');
    $cashier   = sanitize($_GET['cashier_id'] ?? '');

    $query = "select=*,cashier:profiles!cashier_id(full_name),payments(method,amount)&order=created_at.desc&limit={$limit}&offset={$offset}";
    if ($profile['role'] === 'cashier') $query .= "&cashier_id=eq.{$userId}";
    if ($cashier && $profile['role'] !== 'cashier') $query .= "&cashier_id=eq.{$cashier}";
    if ($date_from) $query .= "&created_at=gte.{$date_from}T00:00:00";
    if ($date_to)   $query .= "&created_at=lte.{$date_to}T23:59:59";

    $result = $sb->selectWithCount('transactions', $query);
    jsonResponse([
        'transactions' => $result['data'] ?? [],
        'total'        => $result['count'],
        'limit'        => $limit,
        'offset'       => $offset,
    ]);
}

// ============================================================
// GET SINGLE TRANSACTION WITH ITEMS + PAYMENTS
// ============================================================
if ($method === 'GET' && $action === 'get') {
    $id = sanitize($_GET['id'] ?? '');
    if (!$id) jsonError('Transaction ID required');

    $txnRes  = $sb->select('transactions', "id=eq.{$id}&select=*,cashier:profiles!cashier_id(full_name)");
    $itemRes = $sb->select('transaction_items', "transaction_id=eq.{$id}&select=*,product:products(name,unit),fuel:fuel_types(name)");
    $payRes  = $sb->select('payments', "transaction_id=eq.{$id}&select=*");

    if (empty($txnRes['data'])) jsonError('Transaction not found', 404);

    jsonResponse([
        'transaction' => $txnRes['data'][0],
        'items'       => $itemRes['data'] ?? [],
        'payments'    => $payRes['data'] ?? [],
    ]);
}

jsonError('Invalid action', 404);
