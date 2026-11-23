<?php
declare(strict_types=1);

/**
 * Brand portal token rotation — table brand_accounts only (never brand_accounts_old).
 * Never write token values to error_log.
 */

const BRAND_TOKEN_HEX_LENGTH = 64;
const BRAND_INTERNAL_TOKEN = 'ADMIN_INTERNAL';

function brand_token_is_valid(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{' . BRAND_TOKEN_HEX_LENGTH . '}$/i', $token);
}

/**
 * @return array{updated: int, skipped: int, skip_reasons: array<string, int>, errors: list<string>}
 */
function brand_rotation_result_template(): array
{
    return [
        'updated' => 0,
        'skipped' => 0,
        'skip_reasons' => [],
        'errors' => [],
    ];
}

/**
 * @param array{updated: int, skipped: int, skip_reasons: array<string, int>, errors: list<string>} $result
 */
function brand_rotation_record_skip(array &$result, string $reason): void
{
    $result['skipped']++;
    if (!isset($result['skip_reasons'][$reason])) {
        $result['skip_reasons'][$reason] = 0;
    }
    $result['skip_reasons'][$reason]++;
}

function brand_rotation_skip_summary(array $skipReasons): string
{
    if ($skipReasons === []) {
        return '';
    }
    $parts = [];
    foreach ($skipReasons as $reason => $count) {
        $parts[] = $reason . '=' . (int) $count;
    }
    return implode(', ', $parts);
}

/**
 * Rows from brand_accounts only (legacy brand_accounts_old is never queried).
 *
 * @return list<array<string, mixed>>
 */
function fetchBrandAccountsMainTable(PDO $db): array
{
    $stmt = $db->query('SELECT id, brand_name, token, is_active FROM brand_accounts ORDER BY id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

/**
 * @return string|null skip reason or null if eligible
 */
function brand_rotation_ineligible_reason(array $account): ?string
{
    if ((string) ($account['token'] ?? '') === BRAND_INTERNAL_TOKEN) {
        return 'admin_internal';
    }
    if ((int) ($account['is_active'] ?? 0) !== 1) {
        return 'inactive';
    }
    return null;
}

/**
 * Active portal accounts for export (post-rotation link map).
 *
 * @return list<array<string, mixed>>
 */
function fetchRotatableBrandAccounts(PDO $db): array
{
    $stmt = $db->prepare(
        'SELECT id, brand_name, token FROM brand_accounts
         WHERE token != :internal AND is_active = 1 ORDER BY id'
    );
    $stmt->execute([':internal' => BRAND_INTERNAL_TOKEN]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

/**
 * @return array{generate_missing: bool, accounts: list<array<string, mixed>>}|null
 */
function loadBrandTokensLocalFile(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    $generateMissing = !array_key_exists('generate_missing', $data) || (bool) $data['generate_missing'];
    $accounts = $data['accounts'] ?? $data['tokens'] ?? [];
    if (!is_array($accounts)) {
        return null;
    }
    return [
        'generate_missing' => $generateMissing,
        'accounts' => $accounts,
    ];
}

/**
 * @param list<array<string, mixed>> $fileAccounts
 * @return array{by_id: array<int, string>, by_name: array<string, string>}
 */
function parseBrandTokenFileEntries(array $fileAccounts): array
{
    $byId = [];
    $byName = [];
    foreach ($fileAccounts as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $token = isset($entry['token']) ? trim((string) $entry['token']) : '';
        if ($token === '' || !brand_token_is_valid($token)) {
            continue;
        }
        $token = strtolower($token);
        if (isset($entry['id']) && (int) $entry['id'] > 0) {
            $byId[(int) $entry['id']] = $token;
        }
        if (!empty($entry['brand_name'])) {
            $byName[trim((string) $entry['brand_name'])] = $token;
        }
    }
    return ['by_id' => $byId, 'by_name' => $byName];
}

/**
 * @return array{updated: int, skipped: int, skip_reasons: array<string, int>, errors: list<string>}
 */
function rotateBrandPortalTokens(PDO $db, ?array $byId, ?array $byName, bool $generateWhenUnassigned): array
{
    $result = brand_rotation_result_template();
    $accounts = fetchBrandAccountsMainTable($db);
    $byId = $byId ?? [];
    $byName = $byName ?? [];
    $hasFile = $byId !== [] || $byName !== [];

    $update = $db->prepare('UPDATE brand_accounts SET token = :token WHERE id = :id');

    foreach ($accounts as $acc) {
        $id = (int) $acc['id'];
        $skip = brand_rotation_ineligible_reason($acc);
        if ($skip !== null) {
            brand_rotation_record_skip($result, $skip);
            continue;
        }

        $brandName = (string) ($acc['brand_name'] ?? '');
        $newToken = null;

        if (isset($byId[$id])) {
            $newToken = $byId[$id];
        } elseif ($brandName !== '' && isset($byName[$brandName])) {
            $newToken = $byName[$brandName];
        } elseif (!$hasFile || $generateWhenUnassigned) {
            $newToken = bin2hex(random_bytes(32));
        } else {
            brand_rotation_record_skip($result, 'no_token_in_file');
            continue;
        }

        try {
            $update->execute([':token' => $newToken, ':id' => $id]);
            $result['updated']++;
        } catch (PDOException $e) {
            $result['errors'][] = 'Failed to update account id ' . $id . '.';
            brand_rotation_record_skip($result, 'db_error');
            error_log('PHP Database Error: brand token rotation failed for account id ' . $id);
        }
    }

    return $result;
}

/**
 * @param array{generate_missing: bool, accounts: list<array<string, mixed>>} $fileData
 * @return array{updated: int, skipped: int, skip_reasons: array<string, int>, errors: list<string>}
 */
function rotateBrandPortalTokensFromFile(PDO $db, array $fileData): array
{
    $parsed = parseBrandTokenFileEntries($fileData['accounts']);
    return rotateBrandPortalTokens(
        $db,
        $parsed['by_id'],
        $parsed['by_name'],
        $fileData['generate_missing']
    );
}

/**
 * @return array{updated: int, skipped: int, skip_reasons: array<string, int>, errors: list<string>}
 */
function rotateAllBrandPortalTokensGenerated(PDO $db): array
{
    return rotateBrandPortalTokens($db, null, null, true);
}