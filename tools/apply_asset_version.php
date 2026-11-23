#!/usr/bin/env php
<?php
/**
 * Sync ?v= on local CSS/JS in *.html from includes/asset_version.php.
 * Run from repo root: php tools/apply_asset_version.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/asset_version.php';

$version = trolley_asset_version();
$pattern = '/\b(styles\.css|map_my_location\.css|map_my_location\.js)(\?v=[^"\'\s>]+)?/';

$updated = 0;
foreach (glob($root . '/*.html') ?: [] as $file) {
    $before = (string) file_get_contents($file);
    $after = preg_replace($pattern, '$1?v=' . $version, $before);
    if ($after !== null && $after !== $before) {
        file_put_contents($file, $after);
        $updated++;
        echo "Updated: " . basename($file) . "\n";
    }
}

echo "Asset version {$version}; {$updated} HTML file(s) updated.\n";