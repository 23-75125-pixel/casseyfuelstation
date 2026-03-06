<?php
// ============================================================
// includes/helpers.php
// ============================================================

function jsonResponse(array $data, int $code = 200): void {
    // Discard any accidental output (PHP notices/warnings) that would corrupt JSON
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['error' => $message], $code);
}

function generateTxnNo(): string {
    $date = date('Ymd');
    $rand = strtoupper(substr(uniqid(), -6));
    return "TXN-{$date}-{$rand}";
}

function sanitize(mixed $val): mixed {
    if (is_string($val)) return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
    return $val;
}

/**
 * Clean input for DATABASE storage.
 * Unlike sanitize(), this does NOT apply htmlspecialchars — that's for HTML output only.
 * Using htmlspecialchars before DB storage causes double-encoding when the value is
 * later escaped again for display (e.g. & → &amp; in DB → &amp;amp; on screen).
 */
function cleanInput(mixed $val): mixed {
    if (is_string($val)) return trim(strip_tags($val));
    return $val;
}

function loadEnv(string $file = null): void {
    $file = $file ?? __DIR__ . '/.env';
    if (!file_exists($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) < 2) continue; // skip malformed lines
        $key = trim($parts[0]);
        $val = trim($parts[1]);
        // Strip surrounding quotes ("..." or '...')
        if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        $_ENV[$key] = $val;
        putenv("{$key}={$val}");
    }
}

function getSettings(Supabase $sb): array {
    $result = $sb->select('settings', 'select=key,value');
    $settings = [];
    foreach ($result['data'] ?? [] as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function logAudit(Supabase $sb, string $userId, string $action, string $table = '', string $recordId = '', array $details = []): void {
    try {
        $sb->insert('audit_logs', [
            'user_id'      => $userId,
            'action'       => $action,
            'table_name'   => $table,
            'record_id'    => $recordId,
            'details_json' => $details,
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (\Throwable $e) {
        // Audit logging should never crash the main operation
        error_log('logAudit failed: ' . $e->getMessage());
    }
}
