<?php
/**
 * api_logs.php -- Read API for WA Trolley Report
 *
 * Serves report data from SQLite with optional filtering.
 * Replaces direct access to trolley_logs.json for frontend pages.
 *
 * GET parameters:
 *   brand  -- filter by brand name (e.g., ?brand=Coles)
 *   after  -- ISO date, return reports after this date (e.g., ?after=2025-01-01)
 *   before -- ISO date, return reports before this date
 *   limit  -- max number of results (default: all)
 *   offset -- pagination offset
 *
 * Returns JSON array of report objects matching the original trolley_logs.json format.
 *
 * Version: 1.5
 */

if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// CORS -- allow same-origin and sandgroper.net
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

// Build filters from query parameters
$filters = [];

$brand = $_GET['brand'] ?? '';
if ($brand !== '' && preg_match('/^[A-Za-z]+$/', $brand)) {
    $filters['brand'] = $brand;
}

$after = $_GET['after'] ?? '';
if ($after !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $after)) {
    $filters['after'] = $after;
}

$before = $_GET['before'] ?? '';
if ($before !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $before)) {
    $filters['before'] = $before;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
if ($limit > 0 && $limit <= 10000) {
    $filters['limit'] = $limit;
}

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
if ($offset > 0) {
    $filters['offset'] = $offset;
}

// Fetch and return
$reports = getReports($db, $filters);
echo json_encode($reports);
?>
