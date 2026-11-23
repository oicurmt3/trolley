<?php
declare(strict_types=1);

/**
 * Strip brand contact records to reporter-safe fields (no portal URLs/tokens).
 *
 * @param mixed $contacts
 * @return array<string, array{email: string, phone: string}>
 */
function normalize_mailto_recipients(string $emailField): string
{
    $parts = preg_split('/[;,]+/', $emailField) ?: [];
    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
            $clean[] = $part;
        }
    }
    return implode(',', $clean);
}

function reporter_safe_brand_contacts($contacts): array
{
    if (!is_array($contacts)) {
        return [];
    }
    $safe = [];
    foreach ($contacts as $brand => $info) {
        if (!is_string($brand) || !is_array($info)) {
            continue;
        }
        $emailRaw = isset($info['email']) ? (string) $info['email'] : '';
        $safe[$brand] = [
            'email' => normalize_mailto_recipients($emailRaw),
            'phone' => isset($info['phone']) ? (string) $info['phone'] : '',
        ];
    }
    return $safe;
}

function read_json_file(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : null;
}

/**
 * Create brand_contacts.json from example template when missing (never overwrites live data).
 */
function ensure_brand_contacts_file(string $targetPath, string $examplePath): bool
{
    if (is_file($targetPath)) {
        return true;
    }
    if (!is_file($examplePath) || !is_readable($examplePath)) {
        return false;
    }
    $example = read_json_file($examplePath);
    if ($example === null) {
        return false;
    }
    return file_put_contents(
        $targetPath,
        json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    ) !== false;
}

/**
 * Ensure legacy trolley_logs.json exists as empty array (SQLite is primary; file optional).
 */
function ensure_trolley_logs_json(string $path): bool
{
    if (is_file($path)) {
        return true;
    }
    return file_put_contents($path, "[]\n", LOCK_EX) !== false;
}