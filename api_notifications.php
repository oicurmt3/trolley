<?php
/**
 * api_notifications.php -- Notification Confirmation API
 *
 * Records when a user confirms they sent an email to a brand or LGA.
 *
 * POST JSON body:
 *   { "reportId": 123, "entityType": "Brand", "entityName": "Coles" }
 *   { "reportId": 123, "entityType": "LGA", "entityName": "City of Gosnells" }
 *
 * Version: 1.5
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/app_config.php';
applyConfiguredCors(['POST', 'OPTIONS'], ['Content-Type'], false);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit;
}

require_once __DIR__ . '/db.php';

$db = getDatabase();
if ($db === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$reportId = filter_var($input['reportId'] ?? 0, FILTER_VALIDATE_INT);
$entityType = $input['entityType'] ?? '';
$entityName = $input['entityName'] ?? '';

// Validate
if (!$reportId || $reportId < 1) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid report ID']);
    exit;
}

if (!in_array($entityType, ['Brand', 'LGA'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Entity type must be Brand or LGA']);
    exit;
}

// Sanitise entity name
$entityName = trim(strip_tags($entityName));
if (empty($entityName) || mb_strlen($entityName) > 200) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid entity name']);
    exit;
}

// Record the notification
if (recordNotification($db, $reportId, $entityType, $entityName)) {
    echo json_encode(['status' => 'success', 'message' => 'Notification recorded']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to record notification']);
}
?>
