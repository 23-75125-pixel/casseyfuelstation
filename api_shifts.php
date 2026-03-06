<?php
// api/shifts.php
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

// OPEN SHIFT
if ($method === 'POST' && $action === 'open') {
    // Check no open shift
    $existing = $sb->select('shifts', "cashier_id=eq.{$userId}&status=eq.open");
    if (!empty($existing['data'])) {
        jsonResponse(['success' => true, 'shift' => $existing['data'][0], 'existing' => true]);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $data = [
        'cashier_id'   => $userId,
        'opening_cash' => (float)($body['opening_cash'] ?? 0),
        'status'       => 'open',
    ];

    $result = $sb->insert('shifts', $data);
    if ($result['status'] !== 201) jsonError('Failed to open shift');
    logAudit($sb, $userId, 'open_shift', 'shifts', $result['data'][0]['id']);
    jsonResponse(['success' => true, 'shift' => $result['data'][0]]);
}

// CLOSE SHIFT
if ($method === 'POST' && $action === 'close') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $shiftId = sanitize($body['shift_id'] ?? '');
    if (!$shiftId) jsonError('Shift ID required');

    // Get shift
    $shiftRes = $sb->select('shifts', "id=eq.{$shiftId}&cashier_id=eq.{$userId}&status=eq.open");
    if (empty($shiftRes['data'])) jsonError('Shift not found or already closed');
    $shift = $shiftRes['data'][0];

    // Get total cash sales for this shift (PostgREST does not support SQL subqueries)
    // Step 1: Get paid transaction IDs for this shift
    $shiftTxnRes = $sb->select('transactions', "select=id&shift_id=eq.{$shiftId}&payment_status=eq.paid");
    $shiftTxnIds = array_column($shiftTxnRes['data'] ?? [], 'id');
    $cashSales   = 0;
    if (!empty($shiftTxnIds)) {
        $idsStr  = implode(',', $shiftTxnIds);
        $txnRes  = $sb->select('payments', "select=amount&transaction_id=in.({$idsStr})&method=eq.cash");
        $cashSales = array_sum(array_column($txnRes['data'] ?? [], 'amount'));
    }

    $closingCash = (float)($body['closing_cash'] ?? 0);
    $expectedCash = (float)$shift['opening_cash'] + (float)$shift['cash_in'] - (float)$shift['cash_out'] + $cashSales;
    $variance = $closingCash - $expectedCash;

    $result = $sb->update('shifts', "id=eq.{$shiftId}", [
        'status'        => 'closed',
        'closed_at'     => date('c'),
        'closing_cash'  => $closingCash,
        'expected_cash' => round($expectedCash, 2),
        'variance'      => round($variance, 2),
        'notes'         => sanitize($body['notes'] ?? ''),
    ]);

    if ($result['status'] !== 200) jsonError('Failed to close shift');
    logAudit($sb, $userId, 'close_shift', 'shifts', $shiftId, ['variance' => $variance]);
    jsonResponse(['success' => true, 'variance' => $variance, 'expected_cash' => $expectedCash]);
}

// GET ACTIVE SHIFT
if ($method === 'GET' && $action === 'active') {
    $cashierId = sanitize($_GET['cashier_id'] ?? $userId);
    if ($profile['role'] !== 'admin' && $cashierId !== $userId) $cashierId = $userId;
    $result = $sb->select('shifts', "cashier_id=eq.{$cashierId}&status=eq.open&limit=1");
    jsonResponse(['shift' => $result['data'][0] ?? null]);
}

// LIST SHIFTS
if ($method === 'GET' && $action === 'list') {
    $limit = min((int)($_GET['limit'] ?? 30), 100);
    $query = "select=*,cashier:profiles!cashier_id(full_name)&order=opened_at.desc&limit={$limit}";
    if ($profile['role'] === 'cashier') $query .= "&cashier_id=eq.{$userId}";
    $result = $sb->select('shifts', $query);
    jsonResponse(['shifts' => $result['data'] ?? []]);
}

// SHIFT SUMMARY
if ($method === 'GET' && $action === 'summary') {
    $shiftId = sanitize($_GET['shift_id'] ?? '');
    if (!$shiftId) jsonError('Shift ID required');

    $shiftRes = $sb->select('shifts', "id=eq.{$shiftId}&select=*,cashier:profiles!cashier_id(full_name)");
    if (empty($shiftRes['data'])) jsonError('Shift not found');

    $txnRes = $sb->select('transactions',
        "shift_id=eq.{$shiftId}&payment_status=eq.paid&select=id,total,subtotal,discount_total,tax_total"
    );
    $transactions = $txnRes['data'] ?? [];
    $totalSales  = array_sum(array_column($transactions, 'total'));
    $txnCount    = count($transactions);

    // Use fetched transaction IDs (avoids unsupported SQL subquery in PostgREST)
    $payBreakdown = [];
    $txnIds = array_column($transactions, 'id');
    if (!empty($txnIds)) {
        $idsStr = implode(',', $txnIds);
        $payRes = $sb->select('payments',
            "select=method,amount&transaction_id=in.({$idsStr})"
        );
        foreach ($payRes['data'] ?? [] as $pay) {
            $payBreakdown[$pay['method']] = ($payBreakdown[$pay['method']] ?? 0) + (float)$pay['amount'];
        }
    }

    jsonResponse([
        'shift'           => $shiftRes['data'][0],
        'total_sales'     => $totalSales,
        'txn_count'       => $txnCount,
        'payment_breakdown' => $payBreakdown,
    ]);
}

jsonError('Invalid action', 404);
