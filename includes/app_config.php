<?php
declare(strict_types=1);

function app_config_defaults(): array
{
    return [
        'admin_password_hash' => '',
        'cors_allowed_origins' => [
            'https://sandgroper.net',
            'https://www.sandgroper.net',
        ],
        'site_public_base' => 'https://sandgroper.net/trolley',
    ];
}

function loadAppConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $config = app_config_defaults();
    $path = dirname(__DIR__) . '/config.local.php';
    if (is_readable($path)) {
        $local = require $path;
        if (is_array($local)) {
            if (!empty($local['admin_password_hash']) && is_string($local['admin_password_hash'])) {
                $config['admin_password_hash'] = $local['admin_password_hash'];
            }
            if (isset($local['cors_allowed_origins']) && is_array($local['cors_allowed_origins'])) {
                $origins = [];
                foreach ($local['cors_allowed_origins'] as $origin) {
                    if (is_string($origin) && $origin !== '') {
                        $origins[] = $origin;
                    }
                }
                if ($origins !== []) {
                    $config['cors_allowed_origins'] = $origins;
                }
            }
            if (!empty($local['site_public_base']) && is_string($local['site_public_base'])) {
                $config['site_public_base'] = rtrim($local['site_public_base'], '/');
            }
        }
    }
    return $config;
}

/** @param list<string> $methods @param list<string> $headers */
function applyConfiguredCors(array $methods = ['POST', 'OPTIONS'], array $headers = ['Content-Type'], bool $rejectUnknownOrigin = false): void
{
    $config = loadAppConfig();
    $allowed = $config['cors_allowed_origins'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
        header('Access-Control-Allow-Credentials: true');
        return;
    }
    if ($rejectUnknownOrigin) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Origin not allowed.']);
        exit;
    }
}
