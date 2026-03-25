<?php
// api/products.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/middleware.php';

// Suppress PHP notices/warnings so they never corrupt JSON output
error_reporting(0);
ini_set('display_errors', '0');
ob_start(); // buffer any accidental output; jsonResponse() will ob_end_clean()

header('Content-Type: application/json');
// NOTE: Do NOT call session_start() here — middleware.php handles it.
// A redundant read_and_close session prevents middleware from repairing
// the session when the profile is missing (e.g. after schema reset).

$sb     = new Supabase();
$mw     = new Middleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$profile = $mw->requireAuth();
$userId = $profile['id'];

// Helper: extract a human-readable error from Supabase response
function sbError(array $result, string $fallback = 'Unknown error'): string {
    $d = $result['data'] ?? null;
    if (is_array($d) && isset($d['message'])) return $d['message'];
    if (is_array($d) && isset($d['error']))   return $d['error'];
    if (is_string($d)) return $d;
    if (!empty($result['error'])) return $result['error'];
    return $fallback;
}

// LIST
if ($method === 'GET' && $action === 'list') {
    // Don't use sanitize() for search — it converts & to &amp; which breaks PostgREST queries
    $search = trim(strip_tags($_GET['search'] ?? ''));
    $catId  = trim(strip_tags($_GET['category_id'] ?? ''));
    $active = $_GET['active'] ?? 'true';

    $query = "select=*,category:categories(name)&order=name.asc";
    if ($active === 'true')  $query .= "&is_active=eq.true";
    if ($active === 'false') $query .= "&is_active=eq.false";
    // 'all' = no active filter
    if ($search) {
        // Escape special PostgREST chars in search term
        $s = str_replace(['%', '_'], ['\\%', '\\_'], $search);
        $query .= "&or=(name.ilike.*{$s}*,barcode.ilike.*{$s}*,sku.ilike.*{$s}*,unit.ilike.*{$s}*)";
    }
    if ($catId)  $query .= "&category_id=eq.{$catId}";

    $result = $sb->select('products', $query);

    // If the join query fails (e.g. stale PostgREST schema cache), retry without the join
    if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($result['data']) || isset($result['data']['message'])) {
        $plainQuery = str_replace('select=*,category:categories(name)', 'select=*', $query);
        $result = $sb->select('products', $plainQuery);
    }

    if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($result['data'])) {
        jsonError('Failed to fetch products: ' . sbError($result, 'Failed to fetch from database'), 500);
    }
    // Supabase may return an error object (assoc array with 'message') instead of a list
    if (isset($result['data']['message'])) {
        jsonError('Failed to fetch products: ' . $result['data']['message'], 500);
    }
    jsonResponse(['products' => $result['data']]);
}

// GET BY BARCODE
if ($method === 'GET' && $action === 'barcode') {
    $barcode = sanitize($_GET['barcode'] ?? '');
    if (!$barcode) jsonError('Barcode required');
    $result = $sb->select('products', "barcode=eq.{$barcode}&select=*,category:categories(name)&is_active=eq.true");
    if (empty($result['data'])) jsonError('Product not found', 404);
    jsonResponse(['product' => $result['data'][0]]);
}

// CREATE
if ($method === 'POST' && $action === 'create') {
    $mw->requireRole(['admin','staff']);
    $body = json_decode(file_get_contents('php://input'), true);

    // Convert empty strings to null for UNIQUE columns (PostgreSQL treats '' as a value, not NULL)
    // Use cleanInput() NOT sanitize() — htmlspecialchars would double-encode on display
    $sku     = trim(cleanInput($body['sku'] ?? ''));
    $barcode = trim(cleanInput($body['barcode'] ?? ''));

    $data = [
        'sku'             => $sku !== '' ? $sku : null,
        'barcode'         => $barcode !== '' ? $barcode : null,
        'name'            => cleanInput($body['name'] ?? ''),
        'category_id'     => !empty($body['category_id']) ? $body['category_id'] : null,
        'unit'            => cleanInput($body['unit'] ?? 'bottle'),
        'cost'            => (float)($body['cost'] ?? 0),
        'price'           => (float)($body['price'] ?? 0),
        'stock_qty'       => (float)($body['stock_qty'] ?? 0),
        'low_stock_level' => (float)($body['low_stock_level'] ?? 5),
        'is_active'       => $body['is_active'] ?? true,
    ];
    if (!$data['name']) jsonError('Product name required');

    // Check for duplicate SKU
    if ($data['sku']) {
        $dupSku = $sb->select('products', "select=id&sku=eq.{$data['sku']}&limit=1");
        if (!empty($dupSku['data']) && is_array($dupSku['data']) && count($dupSku['data']) > 0) {
            jsonError('SKU "' . $data['sku'] . '" is already used by another product. Please use a different SKU.', 409);
        }
    }
    // Check for duplicate barcode
    if ($data['barcode']) {
        $dupBarcode = $sb->select('products', "select=id&barcode=eq.{$data['barcode']}&limit=1");
        if (!empty($dupBarcode['data']) && is_array($dupBarcode['data']) && count($dupBarcode['data']) > 0) {
            jsonError('Barcode "' . $data['barcode'] . '" is already used by another product. Please use a different barcode.', 409);
        }
    }

    $result = $sb->insert('products', $data);
    if ($result['status'] !== 201) {
        jsonError('Failed to create product: ' . sbError($result, 'Database insert failed'), 500);
    }
    logAudit($sb, $userId, 'create_product', 'products', $result['data'][0]['id'], $data);
    jsonResponse(['success' => true, 'product' => $result['data'][0]]);
}

// UPDATE
if ($method === 'POST' && $action === 'update') {
    $mw->requireRole(['admin','staff']);
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = cleanInput($body['id'] ?? '');
    if (!$id) jsonError('Product ID required');

    // Convert empty strings to null for UNIQUE columns
    $sku     = isset($body['sku'])     ? trim(cleanInput($body['sku']))     : null;
    $barcode = isset($body['barcode']) ? trim(cleanInput($body['barcode'])) : null;

    // Build a safe update payload (only known columns)
    $updateData = [
        'name'            => cleanInput($body['name'] ?? ''),
        'sku'             => ($sku !== '' && $sku !== null) ? $sku : null,
        'barcode'         => ($barcode !== '' && $barcode !== null) ? $barcode : null,
        'category_id'     => !empty($body['category_id']) ? $body['category_id'] : null,
        'unit'            => cleanInput($body['unit'] ?? 'bottle'),
        'price'           => (float)($body['price'] ?? 0),
        'cost'            => (float)($body['cost'] ?? 0),
        'stock_qty'       => (float)($body['stock_qty'] ?? 0),
        'low_stock_level' => (float)($body['low_stock_level'] ?? 5),
        'is_active'       => $body['is_active'] ?? true,
        'updated_at'      => date('c'),
    ];

    // Check for duplicate SKU (exclude current product)
    if ($updateData['sku']) {
        $dupSku = $sb->select('products', "select=id&sku=eq.{$updateData['sku']}&id=neq.{$id}&limit=1");
        if (!empty($dupSku['data']) && is_array($dupSku['data']) && count($dupSku['data']) > 0) {
            jsonError('SKU "' . $updateData['sku'] . '" is already used by another product. Please use a different SKU.', 409);
        }
    }
    // Check for duplicate barcode (exclude current product)
    if ($updateData['barcode']) {
        $dupBarcode = $sb->select('products', "select=id&barcode=eq.{$updateData['barcode']}&id=neq.{$id}&limit=1");
        if (!empty($dupBarcode['data']) && is_array($dupBarcode['data']) && count($dupBarcode['data']) > 0) {
            jsonError('Barcode "' . $updateData['barcode'] . '" is already used by another product. Please use a different barcode.', 409);
        }
    }

    $result = $sb->update('products', "id=eq.{$id}", $updateData);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        jsonError('Failed to update product: ' . sbError($result, 'Database update failed'), 500);
    }
    logAudit($sb, $userId, 'update_product', 'products', $id, $updateData);
    jsonResponse(['success' => true]);
}

// DELETE (soft)
if ($method === 'POST' && $action === 'delete') {
    $mw->requireRole(['admin','staff']);
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = cleanInput($body['id'] ?? '');
    if (!$id) jsonError('Product ID required');
    $result = $sb->update('products', "id=eq.{$id}", ['is_active' => false]);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        jsonError('Failed to delete product: ' . sbError($result, 'Database update failed'), 500);
    }
    logAudit($sb, $userId, 'delete_product', 'products', $id);
    jsonResponse(['success' => true]);
}

// CATEGORIES LIST
if ($method === 'GET' && $action === 'categories') {
    $result = $sb->select('categories', 'select=*&order=name.asc');
    $data = $result['data'] ?? [];
    // Guard against error object instead of array list
    if (!is_array($data) || isset($data['message']) || isset($data['error'])) $data = [];
    jsonResponse(['categories' => $data]);
}

jsonError('Invalid action', 404);
