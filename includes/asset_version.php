<?php
/**
 * Static asset cache-bust token for local CSS/JS.
 *
 * Bump TROLLEY_ASSET_VERSION when any local busted asset changes (CSS, JS,
 * favicon, images, web manifest). See DEPLOYMENT.md (Asset cache busting).
 */

declare(strict_types=1);

if (!defined('TROLLEY_ASSET_VERSION')) {
    define('TROLLEY_ASSET_VERSION', '20261124b');
}

function trolley_asset_version(): string
{
    return TROLLEY_ASSET_VERSION;
}

/**
 * @return string Relative URL with ?v= query (escaped for HTML attributes)
 */
function trolley_asset_href(string $path): string
{
    $path = ltrim($path, '/');
    $sep = (strpos($path, '?') === false) ? '?' : '&';

    return htmlspecialchars($path . $sep . 'v=' . rawurlencode(trolley_asset_version()), ENT_QUOTES, 'UTF-8');
}