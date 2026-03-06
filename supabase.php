<?php
// ============================================================
// supabase.php — Supabase REST API Helper
// ============================================================

require_once __DIR__ . '/config.php';

class Supabase {
    private string $url;
    private string $serviceKey;
    private string $anonKey;

    public function __construct() {
        $this->url        = SUPABASE_URL;
        $this->serviceKey = SUPABASE_SERVICE_KEY;
        $this->anonKey    = SUPABASE_ANON_KEY;
    }

    // -------------------------------------------------------
    // Generic REST request
    // -------------------------------------------------------
    private function request(string $method, string $path, array $data = [], string $jwt = null, bool $useServiceKey = false): array {
        $key = $useServiceKey ? $this->serviceKey : ($jwt ?? $this->anonKey);

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . ($useServiceKey ? $this->serviceKey : $this->anonKey),
            'Authorization: Bearer ' . $key,
            'Prefer: return=representation',
        ];

        $ch = curl_init($this->url . '/rest/v1' . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // seconds to establish connection
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);         // seconds for the whole request
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if (!empty($data) && in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['status' => 0, 'data' => null, 'error' => $curlError ?: 'cURL request failed'];
        }

        $decoded = json_decode($response, true);
        return ['status' => $httpCode, 'data' => $decoded];
    }

    // -------------------------------------------------------
    // Auth: Verify JWT and get user
    // -------------------------------------------------------
    public function verifyJwt(string $jwt): ?array {
        $ch = curl_init($this->url . '/auth/v1/user');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->anonKey,
            'Authorization: Bearer ' . $jwt,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $code !== 200) return null;
        return json_decode($response, true);
    }

    // Refresh an expired access token using a refresh token
    public function refreshToken(string $refreshToken): array {
        $ch = curl_init($this->url . '/auth/v1/token?grant_type=refresh_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['refresh_token' => $refreshToken]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->anonKey,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'data' => $response ? json_decode($response, true) : null];
    }

    // -------------------------------------------------------
    // Auth: Sign in with email/password
    // -------------------------------------------------------
    public function signIn(string $email, string $password): array {
        $ch = curl_init($this->url . '/auth/v1/token?grant_type=password');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->anonKey,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'data' => $response ? json_decode($response, true) : null];
    }

    // -------------------------------------------------------
    // Shorthand CRUD helpers (service role - server-side)
    // -------------------------------------------------------
    public function select(string $table, string $query = '', string $jwt = null): array {
        return $this->request('GET', "/{$table}?{$query}", [], $jwt, $jwt === null);
    }

    // Select with total count (uses Prefer: count=exact, parses Content-Range header)
    public function selectWithCount(string $table, string $query = '', string $jwt = null): array {
        $key = $jwt ?? $this->serviceKey;

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . ($jwt ? $this->anonKey : $this->serviceKey),
            'Authorization: Bearer ' . $key,
            'Prefer: count=exact',
        ];

        $ch = curl_init($this->url . '/rest/v1/' . $table . '?' . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true); // include response headers

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerStr = substr($raw, 0, $headerSize);
        $body      = substr($raw, $headerSize);
        $decoded   = json_decode($body, true);

        // Parse Content-Range: 0-49/1234
        $count = null;
        if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $headerStr, $m)) {
            $count = (int)$m[1];
        }

        return ['status' => $httpCode, 'data' => $decoded, 'count' => $count ?? count($decoded ?? [])];
    }

    public function insert(string $table, array $data, string $jwt = null): array {
        return $this->request('POST', "/{$table}", $data, $jwt, $jwt === null);
    }

    public function update(string $table, string $filter, array $data, string $jwt = null): array {
        return $this->request('PATCH', "/{$table}?{$filter}", $data, $jwt, $jwt === null);
    }

    public function delete(string $table, string $filter, string $jwt = null): array {
        return $this->request('DELETE', "/{$table}?{$filter}", [], $jwt, $jwt === null);
    }

    // -------------------------------------------------------
    // RPC (Postgres functions)
    // -------------------------------------------------------
    public function rpc(string $fn, array $params = [], string $jwt = null): array {
        $key = $jwt ?? $this->serviceKey;
        $ch = curl_init($this->url . '/rest/v1/rpc/' . $fn);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $key,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'data' => $response ? json_decode($response, true) : null];
    }

    public function getUrl(): string { return $this->url; }
    public function getAnonKey(): string { return $this->anonKey; }
}
