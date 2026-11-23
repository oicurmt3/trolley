<?php
declare(strict_types=1);

/**
 * Brand portal token rotation helpers.
 * Never write token values to error_log.
 */

const BRAND_TOKEN_HEX_LENGTH = 64;
const BRAND_INTERNAL_TOKEN = 'ADMIN_INTERNAL';

function brand_token_is_valid(string $token): bool {
    return (bool) preg_match('/^[a-f0-9]{' . BRAND_TOKEN_HEX_LENGTH . '}$/i', $token);
}

/**
 * @return array{generate_missing: bool, accounts: list<array<string, mixed>>}|null
 */
function loadBrandTokensLocalFile(string $path): ?array {
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
 * @return list<array<string, mixed>>
 */
function fetchRotatableBrandAccounts(PDO $db): array {
    $stmt = $db->prepare('SELECT id, brand_name, token FROM brand_accounts WHERE token != :internal ORDER BY id');
    $stmt->execute([':internal' => BRAND_INTERNAL_TOKEN]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

/**
 * @param list<array<string, mixed>> $fileAccounts
 * @return array{by_id: array<int, string>, by_name: array<string, string>}
 */
function parseBrandTokenFileEntries(array $fileAccounts): array {
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
 * @return array{updated: int, skipped: int, errors: list<string>}
 */
function rotateBrandPortalTokens(PDO $db, ?array $byId, ?array $byName, bool $generateWhenUnassigned): array {
    $result = ['updated' => 0, 'skipped' => 0, 'errors' => []];
    $accounts = fetchRotatableBrandAccounts($db);
    $byId = $byId ?? [];
    $byName = $byName ?? [];
    $hasFile = $byId !== [] || $byName !== [];

    $update = $db->prepare('UPDATE brand_accounts SET token = :token WHERE id = :id');

    foreach ($accounts as $acc) {
        $id = (int) $acc['id'];
        $brandName = (string) ($acc['brand_name'] ?? '');
        $newToken = null;

        if (isset($byId[$id])) {
            $newToken = $byId[$id];
        } elseif ($brandName !== '' && isset($byName[$brandName])) {
            $newToken = $byName[$brandName];
        } elseif (!$hasFile || $generateWhenUnassigned) {
            $newToken = bin2hex(random_bytes(32));
        } else {
            $result['skipped']++;
            continue;
        }

        try {
            $update->execute([':token' => $newToken, ':id' => $id]);
            $result['updated']++;
        } catch (PDOException $e) {
            $result['errors'][] = 'Failed to update account id ' . $id . '.';
            $result['skipped']++;
            error_log('PHP Database Error: brand token rotation failed for account id ' . $id);
        }
    }

    return $result;
}

/**
 * @param array{generate_missing: bool, accounts: list<array<string, mixed>>} $fileData
 * @return array{updated: int, skipped: int, errors: list<string>}
 */
function rotateBrandPortalTokensFromFile(PDO $db, array $fileData): array {
    $parsed = parseBrandTokenFileEntries($fileData['accounts']);
    return rotateBrandPortalTokens(
        $db,
        $parsed['by_id'],
        $parsed['by_name'],
        $fileData['generate_missing']
    );
}

/**
 * @return array{updated: int, skipped: int, errors: list<string>}
 */
function rotateAllBrandPortalTokensGenerated(PDO $db): array {
    return rotateBrandPortalTokens($db, null, null, true);
}