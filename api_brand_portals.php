<?php
/**
 * api_brand_portals.php -- Public API for brand portal URLs
 *
 * Returns portal URLs keyed by base brand name for active accounts.
 * Used by the email composition in index.html to include portal links.
 * Only returns the first active account per base brand name.
 *
 * GET /api_brand_portals.php
 * Returns: { "Coles": "https://sandgroper.net/trolley/brand_portal.php?token=...", ... }
 *
 * Version: 1.0
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// CORS
$allowedOrigins = ['https://sandgroper.net', 'https://www.sandgroper.net'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET only']);
    exit;
}

require_once __DIR__ . '/db.php';

$db = getDatabase();
if ($db === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

// Build base URL for portal links
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'sandgroper.net';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$portalBaseUrl = $scheme . '://' . $host . $scriptDir . '/brand_portal.php?token=';

// Get all active brand accounts and return first match per base brand
$accounts = getAllBrandAccounts($db);
$portalUrls = [];

foreach ($accounts as $acc) {
    if (!(int)$acc['is_active']) continue;
    if ($acc['token'] === 'ADMIN_INTERNAL') continue;

    // Extract base brand (e.g. "Coles 1" -> "Coles")
    $baseBrand = preg_replace('/\s+\d+$/', '', $acc['brand_name']);

    // Only keep the first active account per base brand
    if (!isset($portalUrls[$baseBrand])) {
        $portalUrls[$baseBrand] = $portalBaseUrl . $acc['token'];
    }
}

echo json_encode($portalUrls);
?>