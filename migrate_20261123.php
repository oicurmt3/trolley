<?php
/**
 * migrate_20261123.php — Idempotent SQLite schema migration (WA Trolley Report)
 *
 * CSRF, admin login throttling, and token rotation do not require new DB columns.
 * This script ensures the live database matches the expected schema (same logic
 * as db.php initTables) and records the migration in schema_migrations.
 *
 * RUN ON SERVER ONLY (never commit trolley_data.db):
 *   php migrate_20261123.php
 *   php migrate_20261123.php --check   # exit 0 if applied, 1 if pending
 *
 * Web access is denied via .htaccess; this file refuses HTTP even if misconfigured.
 *
 * Version: 1.0
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo "Forbidden: run this script from the command line (cPanel Terminal: php migrate_20261123.php).\n";
    exit(1);
}

$checkOnly = in_array('--check', $argv ?? [], true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/schema_migrate.php';

$db = getDatabase();
if ($db === null) {
    $detail = getDatabaseError() ?? 'Database unavailable.';
    fwrite(STDERR, "ERROR: {$detail}\n");
    exit(2);
}

if ($checkOnly) {
    exit(migrationIsApplied($db, '20261123') ? 0 : 1);
}

$steps = runMigration20261123($db);
echo "Migration 20261123 complete.\n";
foreach ($steps as $line) {
    echo " - {$line}\n";
}
exit(0);