<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_api.php';

const ADMIN_LOGIN_MAX_ATTEMPTS = 5;
const ADMIN_LOGIN_LOCKOUT_SECONDS = 900;

function admin_ensure_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function admin_validate_csrf(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['admin_csrf_token'], $token);
}

function admin_extract_csrf_token(): string
{
    $header = $_SERVER['HTTP_X_ADMIN_CSRF'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    if (isset($_POST['admin_csrf']) && is_string($_POST['admin_csrf'])) {
        return $_POST['admin_csrf'];
    }
    $body = admin_read_json_body();
    if (isset($body['admin_csrf']) && is_string($body['admin_csrf'])) {
        return $body['admin_csrf'];
    }
    return '';
}

function admin_api_require_csrf(): void
{
    if (!admin_validate_csrf(admin_extract_csrf_token())) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing CSRF token. Reload the admin page and try again.']);
        exit;
    }
}

function admin_login_throttle_path(): string
{
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/admin_login_throttle.json';
}

/**
 * @return array{locked: bool, retry_after?: int}
 */
function admin_login_throttle_status(): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return ['locked' => false];
    }
    $hash = hash('sha256', $ip . '|admin_login');
    $path = admin_login_throttle_path();
    $now = time();
    $data = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    foreach ($data as $key => $entry) {
        if (!is_array($entry) || ($now - (int)($entry['first'] ?? 0)) > ADMIN_LOGIN_LOCKOUT_SECONDS) {
            unset($data[$key]);
        }
    }
    if (!isset($data[$hash])) {
        return ['locked' => false];
    }
    $count = (int)($data[$hash]['count'] ?? 0);
    $first = (int)($data[$hash]['first'] ?? $now);
    if ($count >= ADMIN_LOGIN_MAX_ATTEMPTS) {
        $retry = max(0, ADMIN_LOGIN_LOCKOUT_SECONDS - ($now - $first));
        return ['locked' => true, 'retry_after' => $retry];
    }
    return ['locked' => false];
}

function admin_login_record_failure(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return;
    }
    $hash = hash('sha256', $ip . '|admin_login');
    $path = admin_login_throttle_path();
    $now = time();
    $data = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    foreach ($data as $key => $entry) {
        if (!is_array($entry) || ($now - (int)($entry['first'] ?? 0)) > ADMIN_LOGIN_LOCKOUT_SECONDS) {
            unset($data[$key]);
        }
    }
    if (!isset($data[$hash])) {
        $data[$hash] = ['first' => $now, 'count' => 0];
    }
    $data[$hash]['count'] = (int)($data[$hash]['count'] ?? 0) + 1;
    $data[$hash]['last'] = $now;
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function admin_login_clear_failures(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return;
    }
    $hash = hash('sha256', $ip . '|admin_login');
    $path = admin_login_throttle_path();
    if (!is_file($path)) {
        return;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data[$hash])) {
        return;
    }
    unset($data[$hash]);
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
}