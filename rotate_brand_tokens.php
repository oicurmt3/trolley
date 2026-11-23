<?php
/**
 * rotate_brand_tokens.php — CLI brand portal token rotation
 *
 * Never prints token values to stdout/stderr (export file only, mode 0600).
 *
 * Usage:
 *   php rotate_brand_tokens.php --generate
 *   php rotate_brand_tokens.php --generate --export /home/user/trolley_private/portals.json
 *   php rotate_brand_tokens.php --generate --export /path/file.json --force
 *   php rotate_brand_tokens.php --from-file /path/to/brand_tokens.local.json
 *   php rotate_brand_tokens.php --dry-run --generate
 *
 * Web access denied via .htaccess. Uses __DIR__ for all app paths (cron-safe).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo "Forbidden: CLI only.\n";
    exit(1);
}

chdir(__DIR__);

require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/brand_token_rotation.php';

/**
 * @return string
 */
function rotate_portal_token_prefix(): string
{
    $cfg = loadAppConfig();
    $base = isset($cfg['site_public_base']) && is_string($cfg['site_public_base'])
        ? $cfg['site_public_base']
        : 'https://sandgroper.net/trolley';
    return rtrim($base, '/') . '/brand_portal.php?token=';
}

/**
 * Write brand → portal_url map. Tokens appear only inside portal_url in the file.
 *
 * @return int 0 on success, non-zero on failure
 */
function rotate_write_portal_export(PDO $db, string $exportPath, bool $force): int
{
    if ($exportPath === '') {
        fwrite(STDERR, "ERROR: Export path is empty.\n");
        return 5;
    }

    if (is_file($exportPath) && !$force) {
        fwrite(STDERR, "ERROR: Export file exists; use --force to overwrite.\n");
        return 5;
    }

    $exportDir = dirname($exportPath);
    if (!is_dir($exportDir)) {
        if (!@mkdir($exportDir, 0700, true) && !is_dir($exportDir)) {
            fwrite(STDERR, "ERROR: Could not create export directory.\n");
            return 6;
        }
    }

    $prefix = rotate_portal_token_prefix();
    $accounts = fetchRotatableBrandAccounts($db);
    $payload = [
        'exported_at' => gmdate('c'),
        'brand_portal_links' => [],
    ];
    foreach ($accounts as $row) {
        $payload['brand_portal_links'][] = [
            'id' => (int) $row['id'],
            'brand_name' => (string) $row['brand_name'],
            'portal_url' => $prefix . (string) $row['token'],
        ];
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "ERROR: Could not encode export JSON.\n");
        return 7;
    }

    $oldUmask = umask(0077);
    $written = @file_put_contents($exportPath, $json . "\n", LOCK_EX);
    umask($oldUmask);

    if ($written === false) {
        fwrite(STDERR, "ERROR: Could not write export file.\n");
        return 7;
    }

    if (!@chmod($exportPath, 0600)) {
        fwrite(STDERR, "WARN: Export written but chmod 0600 failed.\n");
    }

    return 0;
}

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$generate = in_array('--generate', $args, true);
$force = in_array('--force', $args, true);
$fromFile = null;
$exportPath = null;

foreach ($args as $i => $arg) {
    if ($arg === '--from-file' && isset($args[$i + 1])) {
        $fromFile = $args[$i + 1];
    }
    if ($arg === '--export' && isset($args[$i + 1])) {
        $exportPath = $args[$i + 1];
    }
}

if ($fromFile !== null && $fromFile !== '' && $fromFile[0] !== '/') {
    $fromFile = __DIR__ . '/' . $fromFile;
}

if ($exportPath !== null && $exportPath !== '' && $exportPath[0] !== '/') {
    $exportPath = __DIR__ . '/' . $exportPath;
}

if ($exportPath !== null && !$generate && $fromFile === null) {
    $generate = true;
}

if (!$generate && $fromFile === null) {
    $defaultLocal = __DIR__ . '/brand_tokens.local.json';
    if (is_file($defaultLocal)) {
        $fromFile = $defaultLocal;
    } else {
        $generate = true;
    }
}

$db = getDatabase();
if ($db === null) {
    $detail = getDatabaseError() ?? 'Database unavailable.';
    fwrite(STDERR, "ERROR: {$detail}\n");
    exit(2);
}

if ($dryRun) {
    $count = count(fetchRotatableBrandAccounts($db));
    echo "Dry run: would rotate {$count} brand account(s).\n";
    if ($exportPath !== null) {
        echo "Dry run: would write export after rotation.\n";
    }
    exit(0);
}

if ($fromFile !== null) {
    $fileData = loadBrandTokensLocalFile($fromFile);
    if ($fileData === null) {
        fwrite(STDERR, "ERROR: Could not read or parse token file.\n");
        exit(3);
    }
    $result = rotateBrandPortalTokensFromFile($db, $fileData);
} else {
    $result = rotateAllBrandPortalTokensGenerated($db);
}

echo 'Updated: ' . $result['updated'] . ', skipped: ' . $result['skipped'] . ".\n";

if ($result['errors'] !== []) {
    foreach ($result['errors'] as $err) {
        fwrite(STDERR, "WARN: {$err}\n");
    }
}

if ($result['updated'] < 1) {
    fwrite(STDERR, "ERROR: No accounts updated.\n");
    exit(4);
}

if ($exportPath !== null) {
    $exportCode = rotate_write_portal_export($db, $exportPath, $force);
    if ($exportCode !== 0) {
        exit($exportCode);
    }
    echo 'Export written: ' . $exportPath . "\n";
}

exit(0);