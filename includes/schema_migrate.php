<?php
declare(strict_types=1);

/**
 * Idempotent schema migration helpers (SQLite).
 */

function ensureSchemaMigrationsTable(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT PRIMARY KEY NOT NULL,
            applied_at TEXT DEFAULT (datetime('now'))
        )
    ");
}

function migrationIsApplied(PDO $db, string $version): bool {
    ensureSchemaMigrationsTable($db);
    $stmt = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = :v LIMIT 1');
    $stmt->execute([':v' => $version]);
    return (bool) $stmt->fetchColumn();
}

function markMigrationApplied(PDO $db, string $version): void {
    ensureSchemaMigrationsTable($db);
    $stmt = $db->prepare('INSERT OR IGNORE INTO schema_migrations (version) VALUES (:v)');
    $stmt->execute([':v' => $version]);
}

/**
 * Run schema steps for release 2026-11-23 (no new columns; ensures tables/indexes + migration ledger).
 *
 * @return list<string> human-readable steps performed
 */
function runMigration20261123(PDO $db): array {
    $steps = [];
    ensureSchemaMigrationsTable($db);
    if (migrationIsApplied($db, '20261123')) {
        $steps[] = 'Migration 20261123 already applied (no changes).';
        return $steps;
    }

    if (!function_exists('initTables')) {
        require_once __DIR__ . '/../db.php';
    }
    initTables($db);
    $steps[] = 'Ensured reports, notifications, brand_accounts, collections tables and indexes.';

    markMigrationApplied($db, '20261123');
    $steps[] = 'Recorded schema_migrations version 20261123.';

    return $steps;
}