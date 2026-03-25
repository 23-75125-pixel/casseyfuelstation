<?php
// api/inventory.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/middleware.php';

// Suppress PHP notices/warnings so they never corrupt JSON output
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

header('Content-Type: application/json');
// NOTE: Do NOT call session_start() here — middleware.php handles it.

$sb     = new Supabase();
$mw     = new Middleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$profile = $mw->requireRole(['admin','staff']);
$userId = $profile['id'];

// RECEIVE STOCK
if ($method === 'POST' && $action === 'receive') {
    $body = json_decode(file_get_contents('php://input'), true);
    $items = $body['items'] ?? [];
    if (empty($items)) jsonError('No items to receive');

    $totalAmount = array_sum(array_map(fn($i) => (float)$i['qty'] * (float)$i['cost'], $items));

    $receiptData = [
        'supplier_id'  => $body['supplier_id'] ?? null,
        'received_by'  => $userId,
        'reference_no' => cleanInput($body['reference_no'] ?? ''),
        'notes'        => cleanInput($body['notes'] ?? ''),
        'total_amount' => $totalAmount,
    ];

    $receiptRes = $sb->insert('inventory_receipts', $receiptData);
    if ($receiptRes['status'] !== 201) jsonError('Failed to create receipt');
    $receiptId = $receiptRes['data'][0]['id'];

    foreach ($items as $item) {
        $pid  = $item['product_id'];
        $qty  = (float)$item['qty'];
        $cost = (float)$item['cost'];

        $sb->insert('inventory_receipt_items', [
            'receipt_id' => $receiptId,
            'product_id' => $pid,
            'qty'        => $qty,
            'cost'       => $cost,
            'line_total' => round($qty * $cost, 2),
        ]);

        // Update stock
        $prodRes = $sb->select('products', "id=eq.{$pid}&select=stock_qty");
        if (!empty($prodRes['data'])) {
            $newStock = (float)$prodRes['data'][0]['stock_qty'] + $qty;
            $sb->update('products', "id=eq.{$pid}", [
                'stock_qty'  => $newStock,
                'cost'       => $cost, // update cost with latest
                'updated_at' => date('c'),
            ]);
        }
    }

    logAudit($sb, $userId, 'stock_received', 'inventory_receipts', $receiptId, [
        'items' => count($items),
        'total' => $totalAmount,
    ]);
    jsonResponse(['success' => true, 'receipt_id' => $receiptId]);
}

// LIST RECEIPTS
if ($method === 'GET' && $action === 'receipts') {
    $limit  = min((int)($_GET['limit'] ?? 30), 100);
    $query  = "select=*,supplier:suppliers(name),received_by_user:profiles!received_by(full_name)&order=created_at.desc&limit={$limit}";
    $result = $sb->select('inventory_receipts', $query);
    // Fallback without joins if schema cache is stale
    if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($result['data']) || isset($result['data']['message'])) {
        $result = $sb->select('inventory_receipts', "select=*&order=created_at.desc&limit={$limit}");
    }
    jsonResponse(['receipts' => $result['data'] ?? []]);
}

// STOCK ADJUSTMENT
if ($method === 'POST' && $action === 'adjust') {
    $body = json_decode(file_get_contents('php://input'), true);
    $pid  = cleanInput($body['product_id'] ?? '');
    $qty  = (float)($body['qty_change'] ?? 0);
    $reason = cleanInput($body['reason'] ?? '');

    if (!$pid || !$reason) jsonError('Product and reason required');
    if ($qty === 0.0) jsonError('Quantity change cannot be zero');

    // Get current stock
    $prodRes = $sb->select('products', "id=eq.{$pid}&select=stock_qty,name");
    if (empty($prodRes['data'])) jsonError('Product not found');
    $product  = $prodRes['data'][0];
    $newStock = max(0, (float)$product['stock_qty'] + $qty);

    $sb->update('products', "id=eq.{$pid}", ['stock_qty' => $newStock, 'updated_at' => date('c')]);
    $sb->insert('stock_adjustments', [
        'product_id' => $pid,
        'qty_change' => $qty,
        'reason'     => $reason,
        'notes'      => cleanInput($body['notes'] ?? ''),
        'created_by' => $userId,
    ]);

    logAudit($sb, $userId, 'stock_adjusted', 'products', $pid, [
        'product' => $product['name'],
        'change'  => $qty,
        'new_qty' => $newStock,
    ]);
    jsonResponse(['success' => true, 'new_stock' => $newStock]);
}

// LIST ADJUSTMENTS
if ($method === 'GET' && $action === 'adjustments') {
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $query = "select=*,product:products(name),created_by_user:profiles!created_by(full_name)&order=created_at.desc&limit={$limit}";
    $result = $sb->select('stock_adjustments', $query);
    // Fallback without joins if schema cache is stale
    if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($result['data']) || isset($result['data']['message'])) {
        $result = $sb->select('stock_adjustments', "select=*&order=created_at.desc&limit={$limit}");
    }
    jsonResponse(['adjustments' => $result['data'] ?? []]); 
}

// LIST SUPPLIERS
if ($method === 'GET' && $action === 'suppliers') {
    $result = $sb->select('suppliers', 'select=*&order=name.asc');
    jsonResponse(['suppliers' => $result['data'] ?? []]);
}

// UPSERT SUPPLIER
if ($method === 'POST' && $action === 'supplier') {
    $body = json_decode(file_get_contents('php://input'), true);
    $data = [
        'name'           => cleanInput($body['name'] ?? ''),
        'contact_person' => cleanInput($body['contact_person'] ?? ''),
        'phone'          => cleanInput($body['phone'] ?? ''),
        'email'          => cleanInput($body['email'] ?? ''),
        'address'        => cleanInput($body['address'] ?? ''),
    ];
    if (!$data['name']) jsonError('Supplier name required');
    $id = $body['id'] ?? null;
    if ($id) {
        $sb->update('suppliers', "id=eq.{$id}", $data);
        jsonResponse(['success' => true]);
    } else {
        $result = $sb->insert('suppliers', $data);
        jsonResponse(['success' => true, 'supplier' => $result['data'][0] ?? null]);
    }
}

// USERS LIST (admin only)
if ($method === 'GET' && $action === 'users') {
    $mw->requireRole('admin');
    $result = $sb->select('profiles', 'select=*&order=full_name.asc');
    jsonResponse(['users' => $result['data'] ?? []]);
}

// AUDIT LOGS
if ($method === 'GET' && $action === 'audit') {
    $mw->requireRole('admin');
    $limit  = min((int)($_GET['limit'] ?? 100), 500);
    $offset = (int)($_GET['offset'] ?? 0);
    $result = $sb->select('audit_logs',
        "select=*,user:profiles!user_id(full_name)&order=created_at.desc&limit={$limit}&offset={$offset}");
    jsonResponse(['logs' => $result['data'] ?? []]);
}

jsonError('Invalid action', 404);
