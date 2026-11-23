#!/usr/bin/env php
<?php
/**
 * Sync ?v= on local static assets in root *.html and *.php from includes/asset_version.php.
 * Run from repo root: php tools/apply_asset_version.php
 *
 * Idempotent: replaces an existing ?v= value (does not append a second v=).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/asset_version.php';

$version = trolley_asset_version();

/** @var list<string> */
$assetExtensions = ['css', 'js', 'svg', 'png', 'ico', 'webmanifest'];

/**
 * @return bool
 */
function is_local_asset_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (preg_match('~^(https?:|//)~i', $url)) {
        return false;
    }
    if ($url !== '' && ($url[0] === '/' || $url[0] === '#')) {
        return false;
    }
    if (preg_match('~^(mailto:|data:|javascript:)~i', $url)) {
        return false;
    }
    if (strpos($url, '<?') !== false || strpos($url, 'trolley_asset_href') !== false) {
        return false;
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = strtok($url, '?') ?: $url;
    }
    if (!preg_match('/\.([a-z0-9]+)$/i', $path, $m)) {
        return false;
    }
    global $assetExtensions;
    return in_array(strtolower($m[1]), $assetExtensions, true);
}

function versioned_asset_url(string $url, string $version): string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $qpos = strpos($url, '?');
        $path = $qpos === false ? $url : substr($url, 0, $qpos);
    }
    return $path . '?v=' . rawurlencode($version);
}

/**
 * @return string
 */
function apply_asset_versions_to_content(string $content, string $version): string
{
    $pattern = '/\b(href|src)\s*=\s*("|\')([^"\'<>]+)\2/i';
    return (string) preg_replace_callback(
        $pattern,
        static function (array $m) use ($version): string {
            $url = $m[3];
            if (!is_local_asset_url($url)) {
                return $m[0];
            }
            return $m[1] . '=' . $m[2] . versioned_asset_url($url, $version) . $m[2];
        },
        $content
    );
}

/** @var list<string> */
$targets = array_merge(
    glob($root . '/*.html') ?: [],
    glob($root . '/*.php') ?: []
);

$updated = 0;
foreach ($targets as $file) {
    $base = basename($file);
    if ($base === 'apply_asset_version.php') {
        continue;
    }
    $before = (string) file_get_contents($file);
    $after = apply_asset_versions_to_content($before, $version);
    if ($after !== $before) {
        file_put_contents($file, $after);
        $updated++;
        echo "Updated: {$base}\n";
    }
}

echo "Asset version {$version}; {$updated} file(s) updated.\n";