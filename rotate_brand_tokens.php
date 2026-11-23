<?php
/**
 * rotate_brand_tokens.php — CLI brand portal token rotation
 *
 * Reads optional assignments from brand_tokens.local.json (gitignored) or generates
 * new random tokens. Never logs token values.
 *
 * Usage:
 *   php rotate_brand_tokens.php --generate
 *   php rotate_brand_tokens.php --from-file /path/to/brand_tokens.local.json
 *   php rotate_brand_tokens.php --dry-run --generate
 *
 * Optional: write new tokens to an output file (gitignored) for offline distribution:
 *   php rotate_brand_tokens.php --generate --export brand_tokens.generated.json
 *
 * Web access denied via .htaccess.
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

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/brand_token_rotation.php';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$generate = in_array('--generate', $args, true);
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
    echo "Dry run: would rotate {$count} brand account(s) (excluding ADMIN_INTERNAL).\n";
    echo $fromFile ? "Source: {$fromFile}\n" : "Source: auto-generate\n";
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

if ($exportPath !== null && $result['updated'] > 0) {
    $accounts = fetchRotatableBrandAccounts($db);
    $export = ['exported_at' => gmdate('c'), 'accounts' => []];
    foreach ($accounts as $row) {
        $export['accounts'][] = [
            'id' => (int) $row['id'],
            'brand_name' => (string) $row['brand_name'],
            'token' => (string) $row['token'],
        ];
    }
    $written = file_put_contents($exportPath, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($written === false) {
        fwrite(STDERR, "WARN: Could not write export file.\n");
    } else {
        echo "Wrote token export to {$exportPath} (keep private; do not commit).\n";
    }
}

exit($result['errors'] !== [] && $result['updated'] === 0 ? 4 : 0);