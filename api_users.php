<?php
// api/users.php
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
$profile = $mw->requireRole('admin');
$jwt    = $mw->getJwt();
$userId = $profile['id'];

// LIST USERS
if ($method === 'GET' && $action === 'list') {
    $result = $sb->select('profiles', 'select=*&order=full_name.asc');
    jsonResponse(['users' => $result['data'] ?? []]);
}

// INSERT PROFILE
if ($method === 'POST' && $action === 'insert') {
    $body = json_decode(file_get_contents('php://input'), true);
    $data = [
        'id'        => sanitize($body['id'] ?? ''),
        'full_name' => sanitize($body['full_name'] ?? ''),
        'role'      => sanitize($body['role'] ?? 'cashier'),
        'phone'     => sanitize($body['phone'] ?? ''),
        'is_active' => true,
    ];
    if (!$data['id'] || !$data['full_name']) jsonError('ID and name required');
    if (!in_array($data['role'], ['admin','staff','cashier'])) jsonError('Invalid role');

    $result = $sb->insert('profiles', $data);
    if ($result['status'] !== 201) jsonError('Failed to create profile. Make sure the UUID exists in Supabase Auth.');

    logAudit($sb, $userId, 'create_user', 'profiles', $data['id'], ['role' => $data['role']]);
    jsonResponse(['success' => true, 'profile' => $result['data'][0] ?? null]);
}

// UPDATE PROFILE
if ($method === 'POST' && $action === 'update') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    if (!$id) jsonError('User ID required');

    $allowed = ['full_name', 'role', 'phone', 'is_active'];
    $data    = [];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $data[$field] = is_bool($body[$field]) ? $body[$field] : sanitize($body[$field]);
        }
    }
    if (isset($data['role']) && !in_array($data['role'], ['admin','staff','cashier'])) jsonError('Invalid role');
    $data['updated_at'] = date('c');

    $result = $sb->update('profiles', "id=eq.{$id}", $data);
    if ($result['status'] !== 200) jsonError('Failed to update user');

    logAudit($sb, $userId, 'update_user', 'profiles', $id, $data);
    jsonResponse(['success' => true]);
}

// DEACTIVATE USER
if ($method === 'POST' && $action === 'deactivate') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = sanitize($body['id'] ?? '');
    if (!$id) jsonError('User ID required');
    if ($id === $userId) jsonError('Cannot deactivate yourself');

    $sb->update('profiles', "id=eq.{$id}", ['is_active' => false, 'updated_at' => date('c')]);
    logAudit($sb, $userId, 'deactivate_user', 'profiles', $id);
    jsonResponse(['success' => true]);
}

jsonError('Invalid action', 404);
