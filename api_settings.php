<?php
// api/settings.php
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

if ($method === 'GET' && $action === 'list') {
    $result = $sb->select('settings', 'select=key,value');
    $settings = [];
    foreach ($result['data'] ?? [] as $row) {
        $settings[$row['key']] = $row['value'];
    }
    jsonResponse(['settings' => $settings]);
}

if ($method === 'POST' && $action === 'update') {
    $mw->requireRole('admin');
    $body = json_decode(file_get_contents('php://input'), true);
    foreach ($body as $key => $value) {
        $key   = sanitize($key);
        $value = sanitize($value);
        // Check if exists
        $existing = $sb->select('settings', "key=eq.{$key}&select=id");
        if (!empty($existing['data'])) {
            $sb->update('settings', "key=eq.{$key}", [
                'value'      => $value,
                'updated_by' => $userId,
                'updated_at' => date('c'),
            ]);
        } else {
            $sb->insert('settings', ['key' => $key, 'value' => $value, 'updated_by' => $userId]);
        }
    }
    logAudit($sb, $userId, 'update_settings', 'settings', '', $body);
    jsonResponse(['success' => true]);
}

jsonError('Invalid action', 404);
