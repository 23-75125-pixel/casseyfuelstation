<?php
// ============================================================
// includes/middleware.php
// Auth + Role middleware
// ============================================================

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/helpers.php';

class Middleware {
    private Supabase $sb;
    public ?array $user = null;
    public ?array $profile = null;

    public function __construct() {
        $this->sb = new Supabase();
    }

    // -------------------------------------------------------
    // Require authenticated session
    // Returns profile array or sends 401
    // -------------------------------------------------------
    public function requireAuth(): array {
        // ── Fast path: browser session request (no Authorization header) ──
        $headers   = function_exists('getallheaders') ? getallheaders() : [];
        $hasBearer = !empty($headers['Authorization']) || !empty($headers['authorization']);

        if (!$hasBearer) {
            // Start session only if not already open
            if (session_status() === PHP_SESSION_NONE) {
                session_start(['read_and_close' => true]);
            }

            $sessionProfile = $_SESSION['profile'] ?? null;

            if ($sessionProfile && !empty($sessionProfile['id'])) {
                if (!$sessionProfile['is_active']) {
                    $this->unauthorized('Account is deactivated');
                }
                $this->profile = $sessionProfile;
                return $sessionProfile;
            }

            // Session profile missing — try access_token stored in session as a fallback
            // (covers edge cases where the session profile key was lost but the token is still valid)
            $sessionJwt = $_SESSION['access_token'] ?? null;
            if (!$sessionJwt) {
                $this->unauthorized('No active session. Please log in again.');
            }

            // Fall through to JWT verification using the session token
            $user = $this->sb->verifyJwt($sessionJwt);
            if (!$user) {
                $this->unauthorized('Session expired. Please log in again.');
            }

            $result = $this->sb->select('profiles', "id=eq.{$user['id']}&select=*");
            $profile = null;
            if (!empty($result['data']) && is_array($result['data']) && !isset($result['data']['message'])) {
                $profile = $result['data'][0];
            }

            // Auto-create profile if missing (e.g. after schema reset)
            if (!$profile) {
                $newProfile = [
                    'id'        => $user['id'],
                    'full_name' => $user['user_metadata']['full_name']
                                   ?? $user['email']
                                   ?? 'User',
                    'role'      => 'admin',
                    'is_active' => true,
                ];
                $createResult = $this->sb->insert('profiles', $newProfile);
                if ($createResult['status'] === 201 && !empty($createResult['data'])) {
                    $profile = $createResult['data'][0];
                } elseif ($createResult['status'] === 409) {
                    // Profile was just created by a parallel request — re-fetch it
                    $retry = $this->sb->select('profiles', "id=eq.{$user['id']}&select=*");
                    if (!empty($retry['data']) && is_array($retry['data']) && !isset($retry['data']['message'])) {
                        $profile = $retry['data'][0];
                    }
                }
                if (!$profile) {
                    $this->unauthorized('Profile not found and could not be created.');
                }
            }

            if (!$profile['is_active']) {
                $this->unauthorized('Account is deactivated.');
            }

            // Repair the session profile so subsequent requests are fast again
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['profile'] = $profile;
            session_write_close();

            $this->profile = $profile;
            return $profile;
        }

        // ── Standard path: Bearer token (external / mobile API) ──
        $jwt = $this->getJwt();

        $user = null;
        if ($jwt) {
            $user = $this->sb->verifyJwt($jwt);

            if (!$user) {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $refreshToken = $_SESSION['refresh_token'] ?? null;
                if ($refreshToken) {
                    $refreshResult = $this->sb->refreshToken($refreshToken);
                    if ($refreshResult['status'] === 200 && !empty($refreshResult['data']['access_token'])) {
                        $newJwt     = $refreshResult['data']['access_token'];
                        $newRefresh = $refreshResult['data']['refresh_token'];
                        $_SESSION['access_token']  = $newJwt;
                        $_SESSION['refresh_token'] = $newRefresh;
                        $jwt  = $newJwt;
                        $user = $this->sb->verifyJwt($newJwt);
                    }
                }
            }
        }

        if (!$user) $this->unauthorized('Invalid or expired token');

        $result = $this->sb->select('profiles', "id=eq.{$user['id']}&select=*");
        $profile = null;
        if (!empty($result['data']) && is_array($result['data']) && !isset($result['data']['message'])) {
            $profile = $result['data'][0];
        }

        // Auto-create profile if missing (e.g. after schema reset)
        if (!$profile) {
            $newProfile = [
                'id'        => $user['id'],
                'full_name' => $user['user_metadata']['full_name']
                               ?? $user['email']
                               ?? 'User',
                'role'      => 'admin',
                'is_active' => true,
            ];
            $createResult = $this->sb->insert('profiles', $newProfile);
            if ($createResult['status'] === 201 && !empty($createResult['data'])) {
                $profile = $createResult['data'][0];
            } elseif ($createResult['status'] === 409) {
                // Profile was just created by a parallel request — re-fetch it
                $retry = $this->sb->select('profiles', "id=eq.{$user['id']}&select=*");
                if (!empty($retry['data']) && is_array($retry['data']) && !isset($retry['data']['message'])) {
                    $profile = $retry['data'][0];
                }
            }
            if (!$profile) {
                $this->unauthorized('Profile not found and could not be created');
            }
        }

        if (!$profile['is_active']) $this->unauthorized('Account is deactivated');

        $this->user    = $user;
        $this->profile = $profile;

        return $profile;
    }

    // -------------------------------------------------------
    // Require specific role(s)
    // -------------------------------------------------------
    public function requireRole(array|string $roles): array {
        $profile = $this->requireAuth();
        $roles = (array) $roles;
        if (!in_array($profile['role'], $roles)) {
            $this->forbidden('Insufficient permissions');
        }
        return $profile;
    }

    // -------------------------------------------------------
    // Check role without dying
    // -------------------------------------------------------
    public function hasRole(string|array $roles): bool {
        if (!$this->profile) return false;
        return in_array($this->profile['role'], (array)$roles);
    }

    // -------------------------------------------------------
    // Get JWT from header or session
    // -------------------------------------------------------
    public function getJwt(): ?string {
        // Check Authorization header
        $headers = getallheaders();
        if (!empty($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }
        // Check session (for web pages)
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['access_token'] ?? null;
    }

    // -------------------------------------------------------
    // Session-based auth for web pages (redirect on fail)
    // -------------------------------------------------------
    public function requireSession(array|string $roles = []): array {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['access_token']) || empty($_SESSION['profile'])) {
            header('Location: /login.php');
            exit;
        }

        $profile = $_SESSION['profile'];

        if (!$profile['is_active']) {
            session_destroy();
            header('Location: /login.php?error=deactivated');
            exit;
        }

        if (!empty($roles) && !in_array($profile['role'], (array)$roles)) {
            header('Location: /dashboard.php?error=forbidden');
            exit;
        }

        return $profile;
    }

    private function unauthorized(string $msg): void {
        if (ob_get_level()) ob_end_clean();
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg]);
        exit;
    }

    private function forbidden(string $msg): void {
        if (ob_get_level()) ob_end_clean();
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg]);
        exit;
    }
}
