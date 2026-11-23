<?php
/**
 * api_collections.php -- Collection Management API
 *
 * Allows authenticated brand accounts to mark reports as collected.
 * Allows admin to reverse collection claims.
 *
 * POST /api_collections.php
 *   { "action": "collect", "reportId": 123, "token": "brand_token_here" }
 *
 * POST /api_collections.php (admin - requires session auth)
 *   { "action": "reverse", "collectionId": 456, "notes": "Trolley still present" }
 *
 * Version: 1.5
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS
$allowedOrigins = ['https://sandgroper.net', 'https://www.sandgroper.net'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
}

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

$action = $input['action'] ?? '';


// =============================================================================
// ACTION: COLLECT (brand marks a report as collected)
// =============================================================================
if ($action === 'collect') {
    $token = $input['token'] ?? '';
    $reportId = filter_var($input['reportId'] ?? 0, FILTER_VALIDATE_INT);

    if (empty($token) || !$reportId || $reportId < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing token or report ID']);
        exit;
    }

    // Authenticate brand
    $account = getBrandAccountByToken($db, $token);
    if (!$account) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
        exit;
    }
    if (!(int)$account['is_active']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Account is suspended']);
        exit;
    }

    // Verify the report exists and contains this brand
    // Match by base brand name (e.g., "Coles 1" matches reports with "Coles")
    $report = getReportById($db, $reportId);
    if (!$report) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Report not found']);
        exit;
    }
    // Extract base brand from account name (e.g., "Coles 1" -> "Coles", "Woolworths" -> "Woolworths")
    $baseBrand = preg_replace('/\s+\d+$/', '', $account['brand_name']);
    if (!in_array($baseBrand, $report['brands'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'This report does not involve your brand']);
        exit;
    }

    // Record the collection
    $result = recordCollection($db, $reportId, (int)$account['id']);
    if (is_array($result)) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => $result['error'] === 'duplicate' ? 'Report already marked as collected' : 'Collection failed: ' . $result['message']]);
        exit;
    }
    if (!is_int($result)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unknown error recording collection']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Report #' . $reportId . ' marked as collected by ' . $account['brand_name'],
        'collectionId' => $result
    ]);
    exit;
}


// =============================================================================
// ACTION: UNCOLLECT (brand undoes their own collection claim)
// =============================================================================
if ($action === 'uncollect') {
    $token = $input['token'] ?? '';
    $reportId = filter_var($input['reportId'] ?? 0, FILTER_VALIDATE_INT);

    if (empty($token) || !$reportId || $reportId < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing token or report ID']);
        exit;
    }

    // Authenticate brand
    $account = getBrandAccountByToken($db, $token);
    if (!$account || !(int)$account['is_active']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid token or account suspended']);
        exit;
    }

    // Find the active collection for this report by this brand
    try {
        $stmt = $db->prepare("
            DELETE FROM collections
            WHERE report_id = :report_id
              AND brand_account_id = :account_id
              AND reversed_by_admin = 0
        ");
        $stmt->execute([
            ':report_id'  => $reportId,
            ':account_id' => (int)$account['id']
        ]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Collection undone for report #' . $reportId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No active collection found for this report']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit;
}


// =============================================================================
// ACTION: REVERSE (admin reverses a collection claim)
// =============================================================================
if ($action === 'reverse') {
    // Verify admin session
    session_start();
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Admin authentication required']);
        exit;
    }

    $reportId = filter_var($input['reportId'] ?? 0, FILTER_VALIDATE_INT);
    $notes = trim(strip_tags($input['notes'] ?? ''));

    if (!$reportId || $reportId < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid report ID']);
        exit;
    }

    if (reverseCollection($db, $reportId, $notes)) {
        echo json_encode(['status' => 'success', 'message' => 'Collection reversed for report #' . $reportId]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'No active collection found for this report']);
    }
    exit;
}


// Unknown action
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action. Use: collect, uncollect, reverse, undo_reversal']);
?>