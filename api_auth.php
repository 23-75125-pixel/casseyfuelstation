<?php
// api/auth.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/middleware.php';

header('Content-Type: application/json');
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$sb     = new Supabase();
$mw     = new Middleware();

// LOGIN
if ($method === 'POST' && $action === 'login') {
    $body = json_decode(file_get_contents('php://input'), true);
    $email    = sanitize($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) jsonError('Email and password required');

    $result = $sb->signIn($email, $password);
    if ($result['status'] !== 200) {
        jsonError('Invalid email or password', 401);
    }

    $authData = $result['data'];
    $userId   = $authData['user']['id'];
    $jwt      = $authData['access_token'];

    // Get profile (auto-create if missing — e.g. after schema reset)
    $profileResult = $sb->select('profiles', "id=eq.{$userId}&select=*");
    if (empty($profileResult['data']) || !is_array($profileResult['data']) || isset($profileResult['data']['message'])) {
        // Profile row doesn't exist yet — create one automatically
        $newProfile = [
            'id'        => $userId,
            'full_name' => $authData['user']['user_metadata']['full_name']
                           ?? $authData['user']['email']
                           ?? 'User',
            'role'      => 'admin', // first user gets admin; change manually for others
            'is_active' => true,
        ];
        $createResult = $sb->insert('profiles', $newProfile);
        if ($createResult['status'] !== 201 || empty($createResult['data'])) {
            jsonError('Could not create profile. Check Supabase connection and that auth user exists.', 500);
        }
        $profileResult = ['data' => $createResult['data']];
    }

    $profile = $profileResult['data'][0];
    if (!$profile['is_active']) jsonError('Your account is deactivated. Contact admin.', 403);

    // Store in session
    $_SESSION['access_token']  = $jwt;
    $_SESSION['refresh_token'] = $authData['refresh_token'];
    $_SESSION['user_id']       = $userId;
    $_SESSION['profile']       = $profile;

    // Log login
    logAudit($sb, $userId, 'login', 'profiles', $userId, ['email' => $email]);

    jsonResponse([
        'success' => true,
        'profile' => $profile,
        'token'   => $jwt,
    ]);
}

// LOGOUT
if ($method === 'POST' && $action === 'logout') {
    $profile = $_SESSION['profile'] ?? null;
    if ($profile) logAudit($sb, $profile['id'], 'logout', 'profiles', $profile['id']);
    session_destroy();
    jsonResponse(['success' => true]);
}

// GET SESSION
if ($method === 'GET' && $action === 'session') {
    if (empty($_SESSION['profile'])) jsonError('Not authenticated', 401);
    jsonResponse(['profile' => $_SESSION['profile'], 'token' => $_SESSION['access_token']]);
}

jsonError('Invalid action', 404);
