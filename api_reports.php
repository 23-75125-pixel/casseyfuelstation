<?php
// api/reports.php
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
$profile = $mw->requireAuth();
$jwt    = $mw->getJwt();
$userId = $profile['id'];
$isAdmin = in_array($profile['role'], ['admin','staff']);

$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-d'));
$dateTo   = sanitize($_GET['date_to'] ?? date('Y-m-d'));

// DAILY SALES SUMMARY
if ($method === 'GET' && $action === 'daily') {
    $query = "select=id,total,subtotal,discount_total,tax_total,payment_status,created_at" .
             "&payment_status=eq.paid" .
             "&created_at=gte.{$dateFrom}T00:00:00" .
             "&created_at=lte.{$dateTo}T23:59:59" .
             "&order=created_at.asc";
    if (!$isAdmin) $query .= "&cashier_id=eq.{$userId}";

    $txnRes  = $sb->select('transactions', $query);
    $txns    = $txnRes['data'] ?? [];

    $totalSales = array_sum(array_column($txns, 'total'));
    $txnCount   = count($txns);
    $payBreakdown = [];
    if (!empty($txns)) {
        $txnIdsStr = implode(',', array_column($txns, 'id'));
        $payRes = $sb->select('payments',
            "select=method,amount" .
            "&transaction_id=in.({$txnIdsStr})"
        );
        foreach ($payRes['data'] ?? [] as $pay) {
            $payBreakdown[$pay['method']] = ($payBreakdown[$pay['method']] ?? 0) + (float)$pay['amount'];
        }
    }

    jsonResponse([
        'total_sales'       => $totalSales,
        'txn_count'         => $txnCount,
        'payment_breakdown' => $payBreakdown,
        'transactions'      => $txns,
    ]);
}

// SALES BY FUEL TYPE
if ($method === 'GET' && $action === 'fuel_sales') {
    // Step 1: Get paid transaction IDs in the date range
    $txnQuery = "select=id&payment_status=eq.paid" .
        "&created_at=gte.{$dateFrom}T00:00:00" .
        "&created_at=lte.{$dateTo}T23:59:59";
    if (!$isAdmin) $txnQuery .= "&cashier_id=eq.{$userId}";
    $txnIdsRes = $sb->select('transactions', $txnQuery);
    $txnIds = array_column($txnIdsRes['data'] ?? [], 'id');
    if (empty($txnIds)) {
        jsonResponse(['fuel_sales' => []]);
    }
    $idsStr = implode(',', $txnIds);

    // Step 2: Get fuel transaction items
    $result = $sb->select('transaction_items',
        "select=*,fuel_type:fuel_types(name,color)&item_type=eq.fuel&transaction_id=in.({$idsStr})"
    );
    $items  = $result['data'] ?? [];

    $summary = [];
    foreach ($items as $item) {
        $ftId = $item['fuel_type_id'];
        if (!isset($summary[$ftId])) {
            $summary[$ftId] = [
                'name'        => $item['fuel_type']['name'] ?? 'Unknown',
                'color'       => $item['fuel_type']['color'] ?? '#888',
                'total_liters'=> 0,
                'total_sales' => 0,
            ];
        }
        $summary[$ftId]['total_liters'] += (float)$item['qty'];
        $summary[$ftId]['total_sales']  += (float)$item['line_total'];
    }
    jsonResponse(['fuel_sales' => array_values($summary)]);
}

// SALES BY PRODUCT
if ($method === 'GET' && $action === 'product_sales') {
    // Step 1: Get paid transaction IDs in the date range
    $txnQuery = "select=id&payment_status=eq.paid" .
        "&created_at=gte.{$dateFrom}T00:00:00" .
        "&created_at=lte.{$dateTo}T23:59:59";
    if (!$isAdmin) $txnQuery .= "&cashier_id=eq.{$userId}";
    $txnIdsRes = $sb->select('transactions', $txnQuery);
    $txnIds = array_column($txnIdsRes['data'] ?? [], 'id');
    if (empty($txnIds)) {
        jsonResponse(['product_sales' => []]);
    }
    $idsStr = implode(',', $txnIds);

    // Step 2: Get product transaction items
    $result = $sb->select('transaction_items',
        "select=*,product:products(name,cost,unit)&item_type=eq.product&transaction_id=in.({$idsStr})&order=line_total.desc"
    );
    $items  = $result['data'] ?? [];

    $summary = [];
    foreach ($items as $item) {
        $pid = $item['product_id'] ?? 'unknown';
        if (!isset($summary[$pid])) {
            $summary[$pid] = [
                'name'       => $item['product']['name'] ?? 'Unknown',
                'unit'       => $item['product']['unit'] ?? 'pcs',
                'cost'       => (float)($item['product']['cost'] ?? 0),
                'total_qty'  => 0,
                'total_sales'=> 0,
                'profit_est' => 0,
            ];
        }
        $summary[$pid]['total_qty']   += (float)$item['qty'];
        $summary[$pid]['total_sales'] += (float)$item['line_total'];
        $summary[$pid]['profit_est']  += (float)$item['line_total'] - ((float)($item['product']['cost'] ?? 0) * (float)$item['qty']);
    }

    usort($summary, fn($a,$b) => $b['total_sales'] <=> $a['total_sales']);
    jsonResponse(['product_sales' => array_values($summary)]);
}

// SALES BY CASHIER
if ($method === 'GET' && $action === 'by_cashier') {
    if (!$isAdmin) jsonError('Insufficient permissions', 403);
    $query = "select=cashier_id,total,cashier:profiles!cashier_id(full_name)" .
             "&payment_status=eq.paid" .
             "&created_at=gte.{$dateFrom}T00:00:00" .
             "&created_at=lte.{$dateTo}T23:59:59";

    $result = $sb->select('transactions', $query);
    $txns   = $result['data'] ?? [];

    $summary = [];
    foreach ($txns as $txn) {
        $cid = $txn['cashier_id'];
        if (!isset($summary[$cid])) {
            $summary[$cid] = [
                'name'        => $txn['cashier']['full_name'] ?? 'Unknown',
                'total_sales' => 0,
                'txn_count'   => 0,
            ];
        }
        $summary[$cid]['total_sales'] += (float)$txn['total'];
        $summary[$cid]['txn_count']++;
    }
    jsonResponse(['cashier_sales' => array_values($summary)]);
}

// LOW STOCK REPORT (products only)
if ($method === 'GET' && $action === 'low_stock') {
    $result = $sb->select('products',
        "select=*,category:categories(name)&is_active=eq.true&order=stock_qty.asc"
    );
    $products  = $result['data'] ?? [];
    $lowStock  = array_filter($products, fn($p) => (float)$p['stock_qty'] <= (float)$p['low_stock_level']);
    jsonResponse(['low_stock' => array_values($lowStock)]);
}

// FUEL LOW STOCK REPORT
if ($method === 'GET' && $action === 'fuel_low_stock') {
    $result = $sb->select('fuel_tanks',
        "select=*,fuel_type:fuel_types(name,color)&order=current_liters.asc"
    );
    $tanks   = $result['data'] ?? [];
    $lowFuel = array_filter($tanks, fn($t) => (float)$t['current_liters'] <= (float)$t['low_level_liters']);
    jsonResponse(['fuel_low_stock' => array_values($lowFuel)]);
}

jsonError('Invalid action', 404);
