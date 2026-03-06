<?php
// api/fuel.php
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

// GET FUEL TYPES
if ($method === 'GET' && $action === 'types') {
    $activeOnly = ($_GET['active'] ?? 'true') === 'true';
    $query = 'select=*&order=name.asc';
    if ($activeOnly) $query .= '&is_active=eq.true';
    $result = $sb->select('fuel_types', $query);
    jsonResponse(['fuel_types' => $result['data'] ?? []]);
}

// GET FUEL TANKS
if ($method === 'GET' && $action === 'tanks') {
    $result = $sb->select('fuel_tanks', 'select=*,fuel_type:fuel_types(name,price_per_liter,color)&order=tank_name.asc');
    jsonResponse(['tanks' => $result['data'] ?? []]);
}

// UPDATE FUEL PRICE (admin only)
if ($method === 'POST' && $action === 'update_price') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    $price = (float)($body['price_per_liter'] ?? 0);
    $cost  = (float)($body['cost_per_liter'] ?? 0);

    if (!$id || $price <= 0) jsonError('Invalid price data');

    // Get old price for audit
    $old = $sb->select('fuel_types', "id=eq.{$id}&select=price_per_liter");
    $oldPrice = $old['data'][0]['price_per_liter'] ?? 0;

    $result = $sb->update('fuel_types', "id=eq.{$id}", [
        'price_per_liter' => $price,
        'cost_per_liter'  => $cost,
        'updated_at'      => date('c'),
    ]);

    logAudit($sb, $userId, 'update_fuel_price', 'fuel_types', $id, [
        'old_price' => $oldPrice,
        'new_price' => $price,
    ]);
    jsonResponse(['success' => true]);
}

// FUEL TANK REFILL
if ($method === 'POST' && $action === 'refill') {
    $mw->requireRole(['admin','staff']);
    $body   = json_decode(file_get_contents('php://input'), true);
    $tankId = sanitize($body['tank_id'] ?? '');
    $liters = (float)($body['liters'] ?? 0);
    $reason = sanitize($body['reason'] ?? 'delivery');

    if (!$tankId || $liters <= 0) jsonError('Invalid refill data');

    $tankRes = $sb->select('fuel_tanks', "id=eq.{$tankId}&select=current_liters,capacity_liters");
    if (empty($tankRes['data'])) jsonError('Tank not found');
    $tank = $tankRes['data'][0];

    $newLevel = min((float)$tank['current_liters'] + $liters, (float)$tank['capacity_liters']);
    $sb->update('fuel_tanks', "id=eq.{$tankId}", [
        'current_liters' => $newLevel,
        'updated_at'     => date('c'),
    ]);
    $sb->insert('fuel_tank_logs', [
        'tank_id'       => $tankId,
        'liters_change' => $liters,
        'reason'        => $reason,
        'reference_no'  => sanitize($body['reference_no'] ?? ''),
        'created_by'    => $userId,
    ]);

    logAudit($sb, $userId, 'fuel_refill', 'fuel_tanks', $tankId, ['liters' => $liters]);
    jsonResponse(['success' => true, 'new_level' => $newLevel]);
}

// TANK LOGS
if ($method === 'GET' && $action === 'tank_logs') {
    $tankId = sanitize($_GET['tank_id'] ?? '');
    $limit  = min((int)($_GET['limit'] ?? 50), 200);
    $query  = "select=*,tank:fuel_tanks(tank_name),created_by_profile:profiles!created_by(full_name)&order=created_at.desc&limit={$limit}";
    if ($tankId) $query .= "&tank_id=eq.{$tankId}";
    $result = $sb->select('fuel_tank_logs', $query);
    jsonResponse(['logs' => $result['data'] ?? []]);
}

// CREATE FUEL TYPE (admin)
if ($method === 'POST' && $action === 'create_type') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $data = [
        'name'            => sanitize($body['name'] ?? ''),
        'price_per_liter' => (float)($body['price_per_liter'] ?? 0),
        'cost_per_liter'  => (float)($body['cost_per_liter'] ?? 0),
        'color'           => sanitize($body['color'] ?? '#3b82f6'),
        'is_active'       => true,
    ];
    if (!$data['name']) jsonError('Fuel type name required');
    $result = $sb->insert('fuel_types', $data);
    if ($result['status'] !== 201) jsonError('Failed to create fuel type');
    jsonResponse(['success' => true, 'fuel_type' => $result['data'][0]]);
}

// UPDATE FUEL TYPE (admin)
if ($method === 'POST' && $action === 'update_type') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    if (!$id) jsonError('Fuel type ID required');
    $data = [
        'name'            => sanitize($body['name'] ?? ''),
        'price_per_liter' => (float)($body['price_per_liter'] ?? 0),
        'cost_per_liter'  => (float)($body['cost_per_liter'] ?? 0),
        'color'           => sanitize($body['color'] ?? '#3b82f6'),
        'is_active'       => $body['is_active'] ?? true,
        'updated_at'      => date('c'),
    ];
    if (!$data['name']) jsonError('Fuel type name required');
    $result = $sb->update('fuel_types', "id=eq.{$id}", $data);
    if ($result['status'] < 200 || $result['status'] >= 300) jsonError('Failed to update fuel type');
    logAudit($sb, $userId, 'update_fuel_type', 'fuel_types', $id, $data);
    jsonResponse(['success' => true]);
}

// DELETE FUEL TYPE (admin — soft delete by deactivating)
if ($method === 'POST' && $action === 'delete_type') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    if (!$id) jsonError('Fuel type ID required');
    $result = $sb->update('fuel_types', "id=eq.{$id}", ['is_active' => false, 'updated_at' => date('c')]);
    if ($result['status'] < 200 || $result['status'] >= 300) jsonError('Failed to delete fuel type');
    logAudit($sb, $userId, 'delete_fuel_type', 'fuel_types', $id, []);
    jsonResponse(['success' => true]);
}

// CREATE TANK (admin)
if ($method === 'POST' && $action === 'create_tank') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $data = [
        'fuel_type_id'    => $body['fuel_type_id'] ?? null,
        'tank_name'       => sanitize($body['tank_name'] ?? ''),
        'capacity_liters' => (float)($body['capacity_liters'] ?? 0),
        'current_liters'  => (float)($body['current_liters'] ?? 0),
        'low_level_liters'=> (float)($body['low_level_liters'] ?? 500),
        'pump_numbers'    => sanitize($body['pump_numbers'] ?? ''),
    ];
    if (!$data['tank_name'] || !$data['fuel_type_id']) jsonError('Tank name and fuel type required');
    $result = $sb->insert('fuel_tanks', $data);
    jsonResponse(['success' => true, 'tank' => $result['data'][0] ?? null]);
}

// UPDATE TANK (admin)
if ($method === 'POST' && $action === 'update_tank') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    if (!$id) jsonError('Tank ID required');
    $data = [
        'fuel_type_id'    => $body['fuel_type_id'] ?? null,
        'tank_name'       => sanitize($body['tank_name'] ?? ''),
        'capacity_liters' => (float)($body['capacity_liters'] ?? 0),
        'low_level_liters'=> (float)($body['low_level_liters'] ?? 500),
        'pump_numbers'    => sanitize($body['pump_numbers'] ?? ''),
        'updated_at'      => date('c'),
    ];
    if (!$data['tank_name'] || !$data['fuel_type_id']) jsonError('Tank name and fuel type required');
    $result = $sb->update('fuel_tanks', "id=eq.{$id}", $data);
    logAudit($sb, $userId, 'update_tank', 'fuel_tanks', $id, $data);
    jsonResponse(['success' => true]);
}

// DELETE TANK (admin)
if ($method === 'POST' && $action === 'delete_tank') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    if (!$id) jsonError('Tank ID required');
    // Check if tank has logs — soft approach: just delete the tank
    $result = $sb->delete('fuel_tanks', "id=eq.{$id}");
    logAudit($sb, $userId, 'delete_tank', 'fuel_tanks', $id, []);
    jsonResponse(['success' => true]);
}

jsonError('Invalid action', 404);
