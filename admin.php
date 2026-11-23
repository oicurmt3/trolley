<?php
/**
 * admin.php -- WA Trolley Report Admin Panel
 *
 * Password-protected admin interface for:
 * - Viewing submission statistics (reports per day, top brands, top suburbs)
 * - Deleting individual log entries (spam/test removal)
 * - Viewing and managing brand contact details
 * - Viewing LGA contact details
 * - Viewing recent error log entries
 *
 * Authentication: simple password hash check (no database required).
 * Change the password hash below after first deployment.
 *
 * Version: 1.4
 */

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

session_start();

if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

require_once __DIR__ . '/includes/admin_security.php';
$adminCsrfToken = admin_ensure_csrf_token();

// =============================================================================
// CONFIGURATION
// =============================================================================

// File paths
$logDirectory = __DIR__;

// Admin password hash is loaded from config.local.php (never commit the real hash).
$configLocalPath = $logDirectory . '/config.local.php';
$adminPasswordHash = '';
if (is_readable($configLocalPath)) {
    $localConfig = require $configLocalPath;
    if (is_array($localConfig) && !empty($localConfig['admin_password_hash']) && is_string($localConfig['admin_password_hash'])) {
        $adminPasswordHash = $localConfig['admin_password_hash'];
    }
}
$adminConfigMissing = ($adminPasswordHash === '');
$brandContactsFilePath = $logDirectory . '/brand_contacts.json';
$lgaContactsFilePath = $logDirectory . '/lga_contact_details.json';
$errorLogFilePath = $logDirectory . '/error_log';
$rateLimitFilePath = $logDirectory . '/rate_limits.json';

// =============================================================================
// AUTHENTICATION
// =============================================================================

$loginError = '';
$isAuthenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

// Verify session is bound to the same user-agent (prevents session hijacking)
if ($isAuthenticated && isset($_SESSION['admin_user_agent'])) {
    if ($_SESSION['admin_user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        session_start();
        $isAuthenticated = false;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password']) && !$isAuthenticated) {
    $throttle = admin_login_throttle_status();
    if ($throttle['locked']) {
        $mins = (int)ceil(($throttle['retry_after'] ?? ADMIN_LOGIN_LOCKOUT_SECONDS) / 60);
        $loginError = 'Too many failed login attempts. Try again in about ' . $mins . ' minute(s).';
    } elseif (!admin_validate_csrf($_POST['admin_csrf'] ?? null)) {
        $loginError = 'Invalid security token. Please reload this page and try again.';
    } elseif ($adminConfigMissing) {
        $loginError = 'Admin is not configured. Create config.local.php on the server (see config.local.example.php and DEPLOYMENT.md).';
    } elseif (password_verify($_POST['admin_password'], $adminPasswordHash)) {
        admin_login_clear_failures();
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        $adminCsrfToken = $_SESSION['admin_csrf_token'];
        $isAuthenticated = true;
    } else {
        admin_login_record_failure();
        $loginError = 'Invalid password.';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("PHP Security: Failed admin login attempt from IP $ip at " . gmdate('Y-m-d H:i:s') . " UTC");
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Session timeout (2 hours)
if ($isAuthenticated && isset($_SESSION['admin_login_time'])) {
    if ((time() - $_SESSION['admin_login_time']) > 7200) {
        session_destroy();
        session_start();
        header('Location: admin.php');
        exit;
    }
}

// =============================================================================
// API ACTIONS (authenticated only, JSON responses)
// =============================================================================

if ($isAuthenticated && isset($_GET['action'])) {

    // --- Delete a log entry ---
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) {
            echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']);
            exit;
        }

        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit;
        }

        if (deleteReport($db, $id)) {
            echo json_encode(['status' => 'success', 'message' => 'Entry deleted.', 'remaining' => getReportCount($db)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Entry not found or could not be deleted.']);
        }
        exit;
    }

    // --- Get stats (JSON) ---
    if ($_GET['action'] === 'stats') {
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) {
            echo json_encode(['error' => 'Database unavailable']);
            exit;
        }

        $reports = getReports($db);
        $totalReports = count($reports);
        $brandCounts = [];
        $suburbCounts = [];
        $dailyCounts = [];
        $conditionCounts = [];

        foreach ($reports as $log) {
            if (isset($log['brands']) && is_array($log['brands'])) {
                foreach ($log['brands'] as $brand) {
                    $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                }
            }
            $suburb = $log['suburb'] ?? 'Unknown';
            $suburbCounts[$suburb] = ($suburbCounts[$suburb] ?? 0) + 1;
            $ts = $log['serverTimestampUTC'] ?? $log['timestamp'] ?? '';
            $date = substr($ts, 0, 10);
            if ($date) {
                $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
            }
            if (isset($log['conditions']) && is_array($log['conditions'])) {
                foreach ($log['conditions'] as $brand => $conditions) {
                    if (is_array($conditions)) {
                        foreach ($conditions as $cond) {
                            $conditionCounts[$cond] = ($conditionCounts[$cond] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        arsort($brandCounts);
        arsort($suburbCounts);
        arsort($conditionCounts);
        ksort($dailyCounts);

        echo json_encode([
            'totalReports' => $totalReports,
            'brandCounts' => $brandCounts,
            'suburbCounts' => array_slice($suburbCounts, 0, 20, true),
            'dailyCounts' => $dailyCounts,
            'conditionCounts' => $conditionCounts
        ]);
        exit;
    }

    // --- Create brand account ---
    if ($_GET['action'] === 'create_brand' && isset($_GET['brand_name'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']); exit; }

        $brandName = trim(strip_tags($_GET['brand_name']));
        if (empty($brandName)) { echo json_encode(['status' => 'error', 'message' => 'Brand name required.']); exit; }

        $result = createBrandAccount($db, $brandName);
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Brand account created.', 'account' => $result]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create account. Brand may already exist.']);
        }
        exit;
    }

    // --- Toggle brand account active/inactive ---
    if ($_GET['action'] === 'toggle_brand' && isset($_GET['id']) && isset($_GET['active'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']); exit; }

        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        $active = $_GET['active'] === '1';
        if ($id && toggleBrandAccount($db, $id, $active)) {
            echo json_encode(['status' => 'success', 'message' => $active ? 'Account activated.' : 'Account suspended.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update account.']);
        }
        exit;
    }

    // --- Delete brand account ---
    if ($_GET['action'] === 'delete_brand' && isset($_GET['id'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']); exit; }

        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if ($id && deleteBrandAccount($db, $id)) {
            echo json_encode(['status' => 'success', 'message' => 'Brand account deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete account.']);
        }
        exit;
    }

    // --- Regenerate brand token ---
    if ($_GET['action'] === 'regen_token' && isset($_GET['id'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']); exit; }

        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        $newToken = $id ? regenerateBrandToken($db, $id) : false;
        if ($newToken) {
            echo json_encode(['status' => 'success', 'message' => 'Token regenerated.', 'token' => $newToken]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to regenerate token.']);
        }
        exit;
    }

    // --- Regenerate ALL brand tokens (excludes ADMIN_INTERNAL) ---
    if ($_GET['action'] === 'regen_all_tokens') {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) {
            echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']);
            exit;
        }
        $count = regenerateAllBrandTokens($db);
        if ($count < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to regenerate tokens.']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Regenerated tokens for ' . $count . ' brand account(s).', 'count' => $count]);
        }
        exit;
    }

    // --- Get brand accounts list ---
    if ($_GET['action'] === 'brand_accounts') {
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode([]); exit; }
        echo json_encode(getAllBrandAccounts($db));
        exit;
    }

    // --- Get collection stats ---
    if ($_GET['action'] === 'collection_stats') {
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['error' => 'Database unavailable']); exit; }

        $stats = getCollectionStats($db);

        // Per-brand stats
        $accounts = getAllBrandAccounts($db);
        $brandStats = [];
        foreach ($accounts as $acc) {
            $bs = getCollectionStats($db, (int)$acc['id']);
            $bs['brandName'] = $acc['brand_name'];
            $brandStats[] = $bs;
        }
        $stats['perBrand'] = $brandStats;

        echo json_encode($stats);
        exit;
    }

    // --- Reverse a collection (via API, uses reportId) ---
    if ($_GET['action'] === 'reverse_collection' && isset($_GET['id'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']); exit; }

        $reportId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        $notes = trim(strip_tags($_GET['notes'] ?? 'Reversed by admin'));
        if ($reportId && reverseCollection($db, $reportId, $notes)) {
            echo json_encode(['status' => 'success', 'message' => 'Collection reversed for report #' . $reportId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No active collection found for this report.']);
        }
        exit;
    }

    // --- Admin: mark a report as collected (creates collection without brand account) ---
    if ($_GET['action'] === 'mark_collected' && isset($_GET['id'])) {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        require_once __DIR__ . '/db.php';
        $db = getDatabase();
        if ($db === null) { echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']); exit; }

        $reportId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$reportId || $reportId < 1) { echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']); exit; }

        // Check for an existing active collection whose brand account may have been deleted
        $stmt = $db->prepare("
            SELECT c.id, ba.id AS ba_id
            FROM collections c
            LEFT JOIN brand_accounts ba ON c.brand_account_id = ba.id
            WHERE c.report_id = :rid AND c.reversed_by_admin = 0
            ORDER BY c.id DESC LIMIT 1
        ");
        $stmt->execute([':rid' => $reportId]);
        $existing = $stmt->fetch();

        // If an orphaned collection exists (brand account deleted), reverse it first
        if ($existing && $existing['ba_id'] === null) {
            reverseCollection($db, $reportId, 'Auto-reversed: orphaned collection (brand account deleted)');
        }

        // Find or create an "Admin" brand account to attribute the collection to
        $adminAccount = getBrandAccountByToken($db, 'ADMIN_INTERNAL');
        if (!$adminAccount) {
            try {
                $db->exec("INSERT OR IGNORE INTO brand_accounts (brand_name, token, is_active) VALUES ('Admin', 'ADMIN_INTERNAL', 1)");
                $adminAccount = getBrandAccountByToken($db, 'ADMIN_INTERNAL');
            } catch (PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Could not create admin account: ' . $e->getMessage()]);
                exit;
            }
        }

        if (!$adminAccount) {
            echo json_encode(['status' => 'error', 'message' => 'Admin account could not be found or created.']);
            exit;
        }

        // Verify both the report and the admin account exist before attempting collection
        $verifyReport = $db->prepare("SELECT id FROM reports WHERE id = :id");
        $verifyReport->execute([':id' => $reportId]);
        if (!$verifyReport->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Report #' . $reportId . ' not found in database.']);
            exit;
        }

        $verifyAccount = $db->prepare("SELECT id FROM brand_accounts WHERE id = :id");
        $verifyAccount->execute([':id' => (int)$adminAccount['id']]);
        if (!$verifyAccount->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Admin account ID ' . $adminAccount['id'] . ' missing from brand_accounts table.']);
            exit;
        }

        $result = recordCollection($db, $reportId, (int)$adminAccount['id']);
        if (is_int($result)) {
            echo json_encode(['status' => 'success', 'message' => 'Report #' . $reportId . ' marked as collected by admin.']);
        } elseif (is_array($result)) {
            echo json_encode(['status' => 'error', 'message' => 'Collection failed: ' . $result['message']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Unknown error recording collection.']);
        }
        exit;
    }

    // --- Save brand contacts ---
    if ($_GET['action'] === 'save_brand_contacts' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { echo json_encode(['status' => 'error', 'message' => 'Invalid data']); exit; }

        // Sanitise each entry
        $clean = [];
        foreach ($input as $brand => $details) {
            $b = trim(strip_tags($brand));
            if (empty($b)) continue;
            $clean[$b] = [
                'email' => filter_var($details['email'] ?? '', FILTER_SANITIZE_EMAIL),
                'phone' => trim(strip_tags($details['phone'] ?? ''))
            ];
        }

        if (file_put_contents($brandContactsFilePath, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
            echo json_encode(['status' => 'success', 'message' => 'Brand contacts saved.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not write file.']);
        }
        exit;
    }

    // --- Save LGA contacts ---
    if ($_GET['action'] === 'save_lga_contacts' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        admin_api_require_csrf();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { echo json_encode(['status' => 'error', 'message' => 'Invalid data']); exit; }

        // Sanitise
        $clean = [];
        foreach ($input as $entry) {
            $name = trim(strip_tags($entry['LGAFullName'] ?? ''));
            $email = filter_var($entry['email'] ?? '', FILTER_SANITIZE_EMAIL);
            if (empty($name)) continue;
            $clean[] = ['LGAFullName' => $name, 'email' => $email];
        }

        if (file_put_contents($lgaContactsFilePath, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
            echo json_encode(['status' => 'success', 'message' => 'LGA contacts saved.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not write file.']);
        }
        exit;
    }
}

// =============================================================================
// HTML OUTPUT
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin - WA Trolley Report</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css"
     integrity="sha256-+bdWuWOXMFkX0v9Cvr3OWClPiYefDQz9GGZP/7xZxdc="
     crossorigin="anonymous" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css"
     integrity="sha256-LWhzWaQGZRsWFrrJxg+6Zn8TT84k0/trtiHBc6qcGpY="
     crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"
     integrity="sha256-WL6HHfYfbFEkZOFdsJQeY7lJG/E5airjvqbznghUzRw="
     crossorigin="anonymous"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { padding: 10px; }
        .container { max-width: 1000px; padding: 20px; }
        h1 { font-size: 1.6em; text-align: center; margin-bottom: 15px; color: var(--highlight); }
        h2 { font-size: 1.2em; margin: 20px 0 10px 0; color: var(--text-color); border-bottom: 1px solid var(--border-color); padding-bottom: 5px; }

        /* Admin map */
        #admin-map { width: 100%; height: 350px; border-radius: 5px; border: 1px solid var(--border-color); margin-bottom: 15px; display: none; }
        .marker-cluster-small { background-color: rgba(181, 226, 140, 0.6); }
        .marker-cluster-small div { background-color: rgba(110, 204, 57, 0.6); }
        .marker-cluster-medium { background-color: rgba(241, 211, 87, 0.6); }
        .marker-cluster-medium div { background-color: rgba(240, 194, 12, 0.6); }
        .marker-cluster-large { background-color: rgba(253, 156, 115, 0.6); }
        .marker-cluster-large div { background-color: rgba(241, 128, 23, 0.6); }
        .marker-cluster div { width: 30px; height: 30px; margin-left: 5px; margin-top: 5px; text-align: center; border-radius: 15px; font: 12px "Helvetica Neue", Arial, Helvetica, sans-serif; color: #fff; line-height: 30px; text-shadow: 1px 1px 1px #000; }
        .marker-cluster { border-radius: 50%; box-shadow: 0 0 5px rgba(0,0,0,0.5); }

        /* Login form */
        .login-form { max-width: 300px; margin: 50px auto; text-align: center; }
        .login-form input[type="password"] {
            width: 100%; padding: 12px; margin-bottom: 10px;
            border: 1px solid var(--border-color); border-radius: 5px;
            background-color: var(--input-bg); color: var(--input-text); font-size: 1em;
        }
        .login-error { color: var(--error-color); margin-bottom: 10px; }

        /* Tabs */
        .tab-bar { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
        .tab-btn {
            padding: 8px 16px; border: none; border-radius: 5px 5px 0 0;
            cursor: pointer; font-size: 0.9em;
            background-color: var(--button-secondary-bg); color: var(--button-text);
            transition: background-color 0.2s;
        }
        .tab-btn:hover { background-color: var(--button-secondary-hover-bg); }
        .tab-btn.active { background-color: var(--button-bg); font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Stats */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 15px; }
        .stat-card { background-color: var(--input-bg); padding: 15px; border-radius: 5px; text-align: center; border: 1px solid var(--border-color); }
        .stat-card .stat-value { font-size: 2em; font-weight: bold; color: var(--highlight); }
        .stat-card .stat-label { font-size: 0.85em; color: #aaa; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.9em; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background-color: #444; color: var(--highlight); position: sticky; top: 0; font-size: 0.85em; }
        td { background-color: var(--card-bg); vertical-align: middle; }
        .delete-btn {
            padding: 4px 10px; font-size: 0.8em;
            background-color: var(--error-color); color: white;
            border: none; border-radius: 3px; cursor: pointer;
        }
        .delete-btn:hover { background-color: #a71d2a; }

        /* Log viewer */
        .log-table-container { border: 1px solid var(--border-color); border-radius: 5px; }

        /* Error log */
        .error-log-pre {
            background-color: var(--input-bg); padding: 10px; border-radius: 5px;
            font-family: monospace; font-size: 0.8em; white-space: pre-wrap; word-wrap: break-word;
            max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color);
        }

        /* Toolbar */
        .toolbar { display: flex; gap: 8px; margin-bottom: 10px; align-items: center; flex-wrap: wrap; }
        .toolbar a, .toolbar button {
            padding: 6px 12px; font-size: 0.85em; border-radius: 4px;
            text-decoration: none; display: inline-block;
        }
        .nav-link { background-color: var(--button-bg); color: var(--button-text); }
        .nav-link:hover { background-color: var(--button-hover-bg); color: var(--button-text); text-decoration: none; }

        /* Bar chart (simple CSS bars) */
        .bar-chart { margin: 10px 0; }
        .bar-row { display: flex; align-items: center; margin-bottom: 4px; }
        .bar-label { width: 100px; font-size: 0.85em; text-align: right; padding-right: 8px; flex-shrink: 0; }
        .bar-fill { height: 20px; border-radius: 3px; background-color: var(--button-bg); min-width: 2px; transition: width 0.3s; }
        .bar-value { margin-left: 6px; font-size: 0.8em; color: #aaa; }

        /* Toast notification */
        .admin-toast {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background-color: #28a745; color: white; padding: 8px 20px;
            border-radius: 5px; font-size: 0.85em; z-index: 9999;
            opacity: 0; transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .admin-toast.show { opacity: 1; }

        /* Ensure all tab content panels stretch full width */
        .tab-content { width: 100%; }
        .tab-content .log-table-container { width: 100%; overflow-x: auto; }

        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            table { font-size: 0.8em; }
            th, td { padding: 6px; }
            .bar-label { width: 70px; font-size: 0.75em; }
        }
    </style>
</head>
<body>
<div class="container">
<!-- Toast notification -->
<div id="admin-toast" class="admin-toast"></div>

<?php if (!$isAuthenticated): ?>
    <!-- LOGIN FORM -->
    <h1>Admin Login</h1>
    <div class="login-form">
        <?php if ($loginError): ?>
            <p class="login-error"><?php echo htmlspecialchars($loginError); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($adminCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="password" name="admin_password" placeholder="Admin password" required autofocus autocomplete="current-password">
            <button type="submit" class="primary" style="margin-top:5px;">Login</button>
        </form>
        <a href="index.html" class="back-link" style="margin-top:20px;">Back to Report</a>
    </div>

<?php else: ?>
    <!-- ADMIN PANEL -->
    <h1>Trolley Report Admin</h1>

    <?php
    // Surface database/extension problems (e.g. pdo_sqlite disabled after a
    // PHP version change) instead of silently reporting "Database unavailable".
    require_once __DIR__ . '/db.php';
    if (getDatabase() === null):
        $dbErrorReason = getDatabaseError();
    ?>
    <div class="login-error" style="border:1px solid var(--error-color); border-radius:6px; padding:10px 12px; margin-bottom:14px; background-color:#fff5f5;">
        <strong>Database unavailable.</strong>
        <?php echo htmlspecialchars($dbErrorReason ?: 'The database could not be opened.'); ?>
        <br>
        Fix: cPanel &gt; Select PHP Version &gt; Extensions &rarr; enable <code>pdo_sqlite</code> and <code>sqlite3</code>, then save.
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <a href="index.html" class="nav-link">Report</a>
        <a href="view_logs.php" class="nav-link">List</a>
        <a href="map_view.html" class="nav-link">Map</a>
        <a href="admin.php?action=logout" class="nav-link" style="background-color:var(--error-color);">Logout</a>
    </div>

    <!-- Map (visible only on Manage Logs tab) -->
    <div id="admin-map"></div>

    <!-- TABS -->
    <div class="tab-bar">
        <button class="tab-btn active" data-tab="stats">Stats</button>
        <button class="tab-btn" data-tab="logs">Manage Logs</button>
        <button class="tab-btn" data-tab="brand_accounts">Brand Accounts</button>
        <button class="tab-btn" data-tab="brands">Brand Contacts</button>
        <button class="tab-btn" data-tab="lga">LGA Contacts</button>
        <button class="tab-btn" data-tab="errors">Error Log</button>
    </div>

    <!-- STATS TAB -->
    <div class="tab-content active" id="tab-stats">
        <div class="stat-grid" id="stat-cards">
            <div class="stat-card"><div class="stat-value" id="stat-total">-</div><div class="stat-label">Total Reports</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-brands">-</div><div class="stat-label">Brands Reported</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-suburbs">-</div><div class="stat-label">Suburbs Covered</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-today">-</div><div class="stat-label">Today</div></div>
        </div>
        <h2>Reports by Brand</h2>
        <div id="brand-chart" class="bar-chart"></div>
        <h2>Top 10 Suburbs</h2>
        <div id="suburb-chart" class="bar-chart"></div>
        <h2>Top Conditions</h2>
        <div id="condition-chart" class="bar-chart"></div>
        <h2>Reports per Day (last 30 days)</h2>
        <div id="daily-chart" class="bar-chart"></div>
        <h2>Collection Statistics</h2>
        <div class="stat-grid" id="collection-stat-cards">
            <div class="stat-card"><div class="stat-value" id="stat-collections-active">-</div><div class="stat-label">Active Collections</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-collections-reversed">-</div><div class="stat-label">Reversed</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-collections-avg">-</div><div class="stat-label">Avg Days to Collect</div></div>
        </div>
        <h2>Per-Brand Collection Performance</h2>
        <div class="log-table-container">
            <table>
                <thead>
                    <tr><th>Brand</th><th>Collections</th><th>Reversed</th><th>Avg Days</th></tr>
                </thead>
                <tbody id="collection-stats-body"></tbody>
            </table>
        </div>
    </div>

    <!-- LOGS TAB -->
    <div class="tab-content" id="tab-logs">
        <h2>All Log Entries</h2>
        <p style="font-size:0.85em;color:#aaa;margin-bottom:10px;">Click a brand button to expand details. "Del" permanently removes an entry.</p>
        <div class="log-table-container">
            <table id="admin-logs-table">
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="admin-logs-body"></tbody>
            </table>
        </div>
    </div>

    <!-- BRAND ACCOUNTS TAB -->
    <div class="tab-content" id="tab-brand_accounts">
        <h2>Brand Portal Accounts</h2>
        <p style="font-size:0.85em;color:#aaa;margin-bottom:10px;">Create token-based login links for brands to mark trolleys as collected.</p>

        <!-- Create new account -->
        <div style="margin-bottom:15px; padding:10px; background-color:#333; border-radius:5px; border:1px solid var(--border-color);">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <label style="font-size:0.85em; font-weight:bold;">New Account:</label>
                <input type="text" id="new-brand-name" placeholder="e.g. Coles 1, Woolworths Perth" style="padding:6px 10px; font-size:0.85em; border-radius:4px; border:1px solid var(--border-color); background-color:var(--input-bg); color:var(--input-text); width:200px;">
                <button type="button" id="create-brand-btn" style="padding:6px 12px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:#28a745; color:white;">Create</button>
            </div>
        </div>
        <div style="margin-bottom:15px;">
            <button type="button" id="regen-all-tokens-btn" class="destructive" style="padding:8px 14px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer;">Regenerate all brand tokens</button>
            <p style="font-size:0.8em;color:#aaa;margin-top:6px;">Rotates every portal link at once (except the internal admin account). Existing links stop working immediately.</p>
        </div>

        <div class="log-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Status</th>
                        <th>Portal Link</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="brand-accounts-body"></tbody>
            </table>
        </div>
    </div>

    <!-- BRANDS TAB -->
    <div class="tab-content" id="tab-brands">
        <h2>Brand Contact Details</h2>
        <p style="font-size:0.85em;color:#aaa;margin-bottom:10px;">Edit brand email and phone details below. Changes auto-save on edit.</p>
        <div class="log-table-container">
            <table id="brand-contacts-table">
                <thead><tr><th>Brand</th><th>Email</th><th>Phone</th><th>Action</th></tr></thead>
                <tbody id="brand-contacts-body"></tbody>
            </table>
        </div>
        <div style="margin-top:10px; padding:10px; background-color:#333; border-radius:5px; border:1px solid var(--border-color);">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="new-bc-brand" placeholder="Brand name" style="padding:5px 8px; font-size:0.85em; border-radius:4px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--input-text); width:120px;">
                <input type="email" id="new-bc-email" placeholder="Email" style="padding:5px 8px; font-size:0.85em; border-radius:4px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--input-text); width:200px;">
                <input type="text" id="new-bc-phone" placeholder="Phone (optional)" style="padding:5px 8px; font-size:0.85em; border-radius:4px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--input-text); width:150px;">
                <button type="button" id="add-brand-contact-btn" style="padding:5px 12px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background:#28a745; color:white;">Add</button>
            </div>
        </div>
    </div>

    <!-- LGA TAB -->
    <div class="tab-content" id="tab-lga">
        <h2>LGA Contact Details</h2>
        <p style="font-size:0.85em;color:#aaa;margin-bottom:10px;">Edit LGA email details below. Changes auto-save on edit.</p>
        <div class="log-table-container">
            <table id="lga-contacts-table">
                <thead><tr><th>LGA</th><th>Email</th><th>Action</th></tr></thead>
                <tbody id="lga-contacts-body"></tbody>
            </table>
        </div>
        <div style="margin-top:10px; padding:10px; background-color:#333; border-radius:5px; border:1px solid var(--border-color);">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="new-lga-name" placeholder="LGA name" style="padding:5px 8px; font-size:0.85em; border-radius:4px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--input-text); width:200px;">
                <input type="email" id="new-lga-email" placeholder="Email" style="padding:5px 8px; font-size:0.85em; border-radius:4px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--input-text); width:200px;">
                <button type="button" id="add-lga-contact-btn" style="padding:5px 12px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background:#28a745; color:white;">Add</button>
            </div>
        </div>
    </div>

    <!-- ERROR LOG TAB -->
    <div class="tab-content" id="tab-errors">
        <h2>Recent Error Log (last 50 lines)</h2>
        <pre class="error-log-pre" id="error-log-content">Loading...</pre>
    </div>

<script>window.ADMIN_CSRF = <?php echo json_encode($adminCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script>
'use strict';
document.addEventListener('DOMContentLoaded', () => {

    const ADMIN_CSRF = window.ADMIN_CSRF || '';

    function adminMutate(url, options) {
        options = options || {};
        const headers = Object.assign({}, options.headers || {});
        headers['X-Admin-CSRF'] = ADMIN_CSRF;
        options.headers = headers;
        return fetch(url, options);
    }

    // --- Tab switching ---
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');

            // Show map only on logs tab
            const mapEl = document.getElementById('admin-map');
            if (btn.dataset.tab === 'logs') {
                mapEl.style.display = 'block';
                if (!logsLoaded) loadLogs();
                // Invalidate map size after display change
                if (adminMap) { setTimeout(() => { adminMap.invalidateSize(); }, 200); }
            } else {
                mapEl.style.display = 'none';
            }

            // Lazy load other tabs
            if (btn.dataset.tab === 'brands' && !brandsLoaded) loadBrandContacts();
            if (btn.dataset.tab === 'lga' && !lgaLoaded) loadLgaContacts();
            if (btn.dataset.tab === 'errors' && !errorsLoaded) loadErrorLog();
            if (btn.dataset.tab === 'brand_accounts' && !brandAccountsLoaded) loadBrandAccounts();
        });
    });

    /** Escape HTML */
    function esc(text) {
        if (!text) return '';
        const el = document.createElement('span');
        el.textContent = text;
        return el.innerHTML;
    }

    /** Show a brief fade-in/out toast notification */
    function showToast(msg, duration) {
        duration = duration || 1500;
        const toast = document.getElementById('admin-toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => { toast.classList.remove('show'); }, duration);
    }

    // =====================================================================
    // ADMIN MAP (Leaflet) -- shown on Manage Logs tab
    // =====================================================================

    // Fix Leaflet default icon paths
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
    });

    const adminMap = L.map('admin-map').setView([-31.9523, 115.8613], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(adminMap);

    const adminCluster = L.markerClusterGroup();
    adminMap.addLayer(adminCluster);
    const adminMarkerMap = {}; // Maps rowId to Leaflet marker

    /** Coloured marker icons */
    const adminMarkerIcons = {
        blue: new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        }),
        orange: new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        }),
        green: new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        }),
        red: new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        })
    };

    function getAdminMarkerIcon(log) {
        if (log.collectedBy && !log.collectionReversed) return adminMarkerIcons.green;
        if ((log.notifiedBrands && log.notifiedBrands.length > 0) || log.notifiedLga) return adminMarkerIcons.orange;
        return adminMarkerIcons.blue;
    }

    /** Populate admin map markers from log data */
    function populateAdminMap(logs) {
        adminCluster.clearLayers();
        // Clear old marker refs
        Object.keys(adminMarkerMap).forEach(k => delete adminMarkerMap[k]);

        let markersFound = 0;
        logs.forEach((log, index) => {
            const lat = parseFloat(log.latitude);
            const lon = parseFloat(log.longitude);
            if (isNaN(lat) || isNaN(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) return;

            const marker = L.marker([lat, lon], { icon: getAdminMarkerIcon(log) });
            const brands = Array.isArray(log.brands) ? log.brands.map(b => esc(b)).join(', ') : 'N/A';
            const ts = log.serverTimestampUTC || log.timestamp || '';
            const dateStr = ts ? formatAdminDate(ts) + ' ' + formatAdminTime(ts) : 'N/A';
            const suburb = log.suburb ? esc(log.suburb) : '';
            const state = log.state ? esc(log.state) : '';
            const location = suburb && state ? suburb + ', ' + state : suburb || state || '';

            let popup = '<b>Brand:</b> ' + brands + '<br>';
            popup += '<b>Listed:</b> ' + dateStr + '<br>';

            // Status
            const hasNotified = (log.notifiedBrands && log.notifiedBrands.length > 0) || log.notifiedLga;
            const isCollected = log.collectedBy && !log.collectionReversed;
            let statusParts = [];
            if (log.notifiedBrands && log.notifiedBrands.length > 0) {
                const d = formatAdminDate(log.serverTimestampUTC);
                statusParts.push('Reported to ' + log.notifiedBrands.join(', ') + (d !== 'N/A' ? ' ' + d : ''));
            }
            if (log.notifiedLga && log.lgaName) {
                const d = formatAdminDate(log.serverTimestampUTC);
                statusParts.push('Reported to ' + esc(log.lgaName) + (d !== 'N/A' ? ' ' + d : ''));
            }
            if (isCollected) {
                const d = log.collectedAt ? formatAdminDate(log.collectedAt) : '';
                statusParts.push('Collected by ' + esc(log.collectedBy) + (d ? ' ' + d : ''));
            }
            if (statusParts.length > 0) {
                popup += '<b>Status:</b> ' + statusParts.join('; ') + '<br>';
            }
            if (location) { popup += '<b>Location:</b> ' + location + '<br>'; }

            marker.bindPopup(popup);
            adminCluster.addLayer(marker);

            const rowId = 'admin-log-' + (log.id || index);
            adminMarkerMap[rowId] = marker;
            markersFound++;
        });

        if (markersFound > 0) {
            const bounds = adminCluster.getBounds();
            if (bounds.isValid()) { adminMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 }); }
        }
        adminMap.invalidateSize();
    }

    /** Render a horizontal bar chart */
    function renderBarChart(containerId, data, maxBars) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const entries = Object.entries(data).slice(0, maxBars || 20);
        if (entries.length === 0) { container.innerHTML = '<p style="color:#aaa;font-size:0.85em;">No data.</p>'; return; }
        const maxVal = Math.max(...entries.map(e => e[1]));
        let html = '';
        entries.forEach(([label, value]) => {
            const pct = maxVal > 0 ? (value / maxVal) * 100 : 0;
            html += '<div class="bar-row">';
            html += '<span class="bar-label">' + esc(label) + '</span>';
            html += '<div class="bar-fill" style="width:' + pct + '%;"></div>';
            html += '<span class="bar-value">' + value + '</span>';
            html += '</div>';
        });
        container.innerHTML = html;
    }

    // --- Load Stats (auto on page load) ---
    async function loadStats() {
        try {
            const resp = await fetch('admin.php?action=stats');
            const data = await resp.json();
            document.getElementById('stat-total').textContent = data.totalReports;
            document.getElementById('stat-brands').textContent = Object.keys(data.brandCounts).length;
            document.getElementById('stat-suburbs').textContent = Object.keys(data.suburbCounts).length;

            // Today's count
            const today = new Date().toISOString().slice(0, 10);
            document.getElementById('stat-today').textContent = data.dailyCounts[today] || 0;

            renderBarChart('brand-chart', data.brandCounts, 15);
            renderBarChart('suburb-chart', data.suburbCounts, 10);
            renderBarChart('condition-chart', data.conditionCounts, 10);

            // Last 30 days only
            const dailyEntries = Object.entries(data.dailyCounts);
            const last30 = {};
            dailyEntries.slice(-30).forEach(([date, count]) => {
                last30[date.slice(5)] = count; // MM-DD format
            });
            renderBarChart('daily-chart', last30, 30);
        } catch (e) { console.error('Stats load error:', e); }

        // Also load collection stats into the same tab
        try {
            const statsResp = await fetch('admin.php?action=collection_stats');
            const cs = await statsResp.json();
            document.getElementById('stat-collections-active').textContent = cs.activeCollections;
            document.getElementById('stat-collections-reversed').textContent = cs.reversedCount;
            document.getElementById('stat-collections-avg').textContent = cs.avgDaysToCollect !== null ? cs.avgDaysToCollect + 'd' : '-';

            const brandBody = document.getElementById('collection-stats-body');
            brandBody.innerHTML = '';
            if (cs.perBrand && cs.perBrand.length > 0) {
                cs.perBrand.forEach(bs => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + esc(bs.brandName) + '</td>'
                        + '<td>' + bs.activeCollections + '</td>'
                        + '<td>' + bs.reversedCount + '</td>'
                        + '<td>' + (bs.avgDaysToCollect !== null ? bs.avgDaysToCollect + 'd' : '-') + '</td>';
                    brandBody.appendChild(tr);
                });
            } else {
                brandBody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#aaa;">No collections yet.</td></tr>';
            }
        } catch (e) { console.error('Collection stats load error:', e); }
    }
    loadStats();

    // --- Load Logs ---
    let logsLoaded = false;
    let adminLogs = []; // Store for detail row lookups

    /** Brand abbreviation mapping */
    const customBrandText = {
        'Coles': 'CO', 'Woolworths': 'WO', 'Aldi': 'AL', 'IGA': 'IG',
        'Kmart': 'KM', 'BigW': 'BI', 'Bunnings': 'BU', 'Spudshed': 'SP', 'Other': 'OT'
    };

    /** State abbreviation mapping */
    const stateAbbreviations = {
        'Western Australia': 'WA', 'South Australia': 'SA', 'New South Wales': 'NSW',
        'Queensland': 'QLD', 'Victoria': 'VIC', 'Tasmania': 'TAS',
        'Northern Territory': 'NT', 'Australian Capital Territory': 'ACT'
    };

    function getStateAbbr(name) { return name ? (stateAbbreviations[name] || name) : ''; }

    function formatAdminDate(ts) {
        if (!ts) return 'N/A';
        try {
            const d = new Date(ts);
            if (isNaN(d.getTime())) return ts;
            return d.toLocaleString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short' });
        } catch (e) { return ts; }
    }

    function formatAdminTime(ts) {
        if (!ts) return '';
        try {
            const d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return d.toLocaleString('en-AU', { timeZone: 'Australia/Perth', timeStyle: 'short', hour12: true });
        } catch (e) { return ''; }
    }

    async function loadLogs() {
        try {
            const resp = await fetch('api_logs.php');
            const logs = await resp.json();
            adminLogs = logs;
            const tbody = document.getElementById('admin-logs-body');
            tbody.innerHTML = '';

            logs.forEach((log, index) => {
                const rowId = 'admin-log-' + (log.id || index);
                const reportId = log.id || 0;
                const tr = document.createElement('tr');
                tr.dataset.rowId = rowId;

                // Brand cell -- toggle buttons (matching view_logs.php)
                const tdBrand = document.createElement('td');
                const brandGroup = document.createElement('div');
                brandGroup.className = 'brand-button-group';
                const brands = Array.isArray(log.brands) ? log.brands : [];
                if (brands.length > 0) {
                    brands.forEach(brand => {
                        const btn = document.createElement('button');
                        btn.className = 'brand-toggle';
                        btn.textContent = customBrandText[brand] || brand;
                        btn.dataset.brand = brand;
                        btn.dataset.targetRowId = rowId;
                        btn.type = 'button';
                        brandGroup.appendChild(btn);
                    });
                } else { brandGroup.textContent = 'N/A'; }
                tdBrand.appendChild(brandGroup);

                // Date cell
                const tdDate = document.createElement('td');
                const timestamp = log.serverTimestampUTC || log.timestamp;
                tdDate.textContent = formatAdminDate(timestamp);
                const timeSpan = document.createElement('span');
                timeSpan.style.cssText = 'margin-left:5px;font-size:0.9em;opacity:0.8;';
                timeSpan.textContent = formatAdminTime(timestamp);
                tdDate.appendChild(timeSpan);

                // Location cell -- map link (matching view_logs.php)
                const tdMap = document.createElement('td');
                if (log.latitude && log.longitude) {
                    const lat = log.latitude;
                    const lon = log.longitude;
                    if (Math.abs(parseFloat(lat)) <= 90 && Math.abs(parseFloat(lon)) <= 180) {
                        const mapLink = document.createElement('a');
                        mapLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lon);
                        mapLink.target = '_blank';
                        mapLink.rel = 'noopener noreferrer';
                        mapLink.className = 'map-btn';
                        const suburb = log.suburb || '';
                        const stateAbbr = getStateAbbr(log.state || '');
                        let btnText = 'Map';
                        if (suburb && stateAbbr) { btnText = suburb + ', ' + stateAbbr; }
                        else if (suburb) { btnText = suburb; }
                        else if (stateAbbr) { btnText = stateAbbr; }
                        mapLink.textContent = btnText;
                        tdMap.appendChild(mapLink);
                    } else { tdMap.textContent = 'Invalid'; }
                } else { tdMap.textContent = ''; }

                // Action cell
                const tdAction = document.createElement('td');
                const isCollected = ((log.collectedBy && log.collectedBy !== null) || (log.collectedAt && log.collectedAt !== null)) && !log.collectionReversed;
                const bs = 'margin:1px;padding:3px 6px;font-size:0.7em;border:none;border-radius:3px;cursor:pointer;color:white;';
                let actions = '<button class="aa" data-id="' + reportId + '" data-act="delete" style="' + bs + 'background:var(--error-color);">Del</button>';
                if (isCollected) {
                    actions += ' <button class="aa" data-id="' + reportId + '" data-act="uncollect" style="' + bs + 'background:#ff9800;">Uncollect</button>';
                } else {
                    actions += ' <button class="aa" data-id="' + reportId + '" data-act="collect" style="' + bs + 'background:#28a745;">Mark Collected</button>';
                }
                tdAction.innerHTML = actions;

                tr.appendChild(tdBrand);
                tr.appendChild(tdDate);
                tr.appendChild(tdMap);
                tr.appendChild(tdAction);
                tbody.appendChild(tr);
            });
            logsLoaded = true;

            // Populate admin map with markers
            populateAdminMap(logs);
            setTimeout(() => { adminMap.invalidateSize(); }, 200);
        } catch (e) { console.error('Logs load error:', e); }
    }

    /** Toggle detail row for admin logs (matching view_logs.php) */
    function toggleAdminDetailRow(button) {
        const brand = button.dataset.brand;
        const targetRowId = button.dataset.targetRowId;
        const mainRow = document.querySelector('#admin-logs-body tr[data-row-id="' + targetRowId + '"]');
        if (!mainRow) return;

        const detailRowId = 'detail-' + targetRowId + '-' + brand;
        const existing = document.getElementById(detailRowId);

        // Close all detail rows
        document.querySelectorAll('#admin-logs-body .detail-row').forEach(row => {
            if (row.id !== detailRowId) row.remove();
        });
        if (existing) { existing.remove(); return; }

        // Find the log by rowId
        const index = parseInt(targetRowId.replace('admin-log-', ''));
        const logData = adminLogs.find(l => l.id === index) || adminLogs[index];
        if (!logData) return;

        // Pan map to this marker and open its popup
        const marker = adminMarkerMap[targetRowId];
        if (marker) {
            adminCluster.zoomToShowLayer(marker, () => {
                marker.openPopup();
            });
        }

        try {
            const detailRow = document.createElement('tr');
            detailRow.id = detailRowId;
            detailRow.classList.add('detail-row');
            const detailCell = document.createElement('td');
            detailCell.colSpan = 4;

            const conditions = (typeof logData.conditions === 'object' && logData.conditions !== null) ? logData.conditions : {};
            const brandConditions = conditions[brand] || [];
            const comments = logData.comments || '';

            let html = '<div class="detail-content">';
            html += '<h4>Details for ' + esc(brand) + ' Trolley</h4>';
            if (brandConditions.length > 0) {
                html += '<span class="conditions_yes"><strong>Conditions Reported:</strong> ' + esc(brandConditions.join(', ')) + '</span>';
            } else {
                html += '<span class="no-details">No specific conditions reported.</span>';
            }
            if (comments) {
                html += '<span class="add_comments"><strong>General Comments:</strong> ' + esc(comments).replace(/\n/g, '<br>') + '</span>';
            }

            // Status -- only if notified or collected
            const hasNotifiedBrands = logData.notifiedBrands && logData.notifiedBrands.length > 0;
            const hasNotifiedLga = logData.notifiedLga;
            const isCollected = logData.collectedBy && !logData.collectionReversed;
            let statusParts = [];
            if (hasNotifiedBrands) {
                const d = formatAdminDate(logData.serverTimestampUTC);
                statusParts.push('Reported to ' + logData.notifiedBrands.join(', ') + (d !== 'N/A' ? ' at ' + d : ''));
            }
            if (hasNotifiedLga && logData.lgaName) {
                const d = formatAdminDate(logData.serverTimestampUTC);
                statusParts.push('Reported to ' + esc(logData.lgaName) + (d !== 'N/A' ? ' at ' + d : ''));
            }
            if (isCollected) {
                const d = logData.collectedAt ? formatAdminDate(logData.collectedAt) : '';
                statusParts.push('Reported as "collected" by ' + esc(logData.collectedBy) + (d ? ' at ' + d : ''));
            }
            if (statusParts.length > 0) {
                html += '<span class="report_status"><strong>Status:</strong> ' + statusParts.join('; ') + '</span>';
            }

            html += '</div>';
            detailCell.innerHTML = html;
            detailRow.appendChild(detailCell);
            mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
        } catch (error) { console.error('Detail row error:', error); }
    }

    // Delegated action handler -- attached once, outside loadLogs
    document.getElementById('admin-logs-body').addEventListener('click', async (e) => {
        // Brand toggle for detail rows
        const toggleBtn = e.target.closest('.brand-toggle');
        if (toggleBtn) { toggleAdminDetailRow(toggleBtn); return; }

        const btn = e.target.closest('.aa');
        if (!btn) return;
        const id = btn.dataset.id;
        const act = btn.dataset.act;

        if (act === 'delete') {
            if (!confirm('Delete report #' + id + '? This cannot be undone.')) return;
            try {
                const resp = await adminMutate('admin.php?action=delete&id=' + id);
                const result = await resp.json();
                if (result.status === 'success') { logsLoaded = false; loadLogs(); loadStats(); }
                else { alert('Error: ' + result.message); }
            } catch (err) { alert('Failed: ' + err.message); }
            return;
        }

        if (act === 'uncollect') {
            try {
                const resp = await adminMutate('admin.php?action=reverse_collection&id=' + id + '&notes=Uncollected%20by%20admin');
                const result = await resp.json();
                if (result.status === 'success') { logsLoaded = false; loadLogs(); loadStats(); }
                else { alert('Error: ' + result.message); }
            } catch (err) { alert('Failed: ' + err.message); }
            return;
        }

        if (act === 'collect') {
            try {
                const resp = await adminMutate('admin.php?action=mark_collected&id=' + id);
                const result = await resp.json();
                if (result.status === 'success') { logsLoaded = false; loadLogs(); loadStats(); }
                else { alert('Error: ' + result.message); }
            } catch (err) { alert('Failed: ' + err.message); }
        }
    });

    // --- Load Brand Contacts (editable) ---
    let brandsLoaded = false;
    let brandContactsData = {};
    async function loadBrandContacts() {
        try {
            const resp = await fetch('brand_contacts.json?_=' + Date.now());
            brandContactsData = await resp.json();
            renderBrandContacts();
            brandsLoaded = true;
        } catch (e) { console.error('Brand contacts load error:', e); }
    }

    function renderBrandContacts() {
        const tbody = document.getElementById('brand-contacts-body');
        tbody.innerHTML = '';
        Object.entries(brandContactsData).forEach(([key, info]) => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + esc(info.displayName || key) + '</td>'
                + '<td><input type="email" value="' + esc(info.email || '') + '" data-brand="' + esc(key) + '" data-field="email" style="width:200px;padding:3px 6px;font-size:0.8em;border:1px solid var(--border-color);border-radius:3px;background:var(--input-bg);color:var(--input-text);"></td>'
                + '<td><input type="text" value="' + esc(info.phone || '') + '" data-brand="' + esc(key) + '" data-field="phone" style="width:140px;padding:3px 6px;font-size:0.8em;border:1px solid var(--border-color);border-radius:3px;background:var(--input-bg);color:var(--input-text);"></td>'
                + '<td><button onclick="deleteBrandContact(\'' + esc(key) + '\')" style="padding:3px 8px;font-size:0.75em;border:none;border-radius:3px;cursor:pointer;background:var(--error-color);color:white;">Del</button></td>';
            tbody.appendChild(tr);
        });
        // Save on input change
        tbody.querySelectorAll('input').forEach(input => {
            input.addEventListener('change', () => {
                const brand = input.dataset.brand;
                const field = input.dataset.field;
                if (brandContactsData[brand]) {
                    brandContactsData[brand][field] = input.value;
                    saveBrandContacts();
                }
            });
        });
    }

    async function saveBrandContacts() {
        try {
            const resp = await adminMutate('admin.php?action=save_brand_contacts', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(brandContactsData)
            });
            const result = await resp.json();
            if (result.status === 'success') { showToast('Saved'); }
            else { console.error('Save brand contacts failed:', result.message); showToast('Save failed'); }
        } catch (e) { console.error('Save brand contacts error:', e); showToast('Save error'); }
    }

    window.deleteBrandContact = function(key) {
        if (!confirm('Delete brand contact for ' + key + '?')) return;
        delete brandContactsData[key];
        saveBrandContacts();
        renderBrandContacts();
    };

    document.getElementById('add-brand-contact-btn').addEventListener('click', () => {
        const brand = document.getElementById('new-bc-brand').value.trim();
        const email = document.getElementById('new-bc-email').value.trim();
        const phone = document.getElementById('new-bc-phone').value.trim();
        if (!brand || !email) { alert('Brand name and email are required.'); return; }
        brandContactsData[brand] = { displayName: brand, email: email, phone: phone };
        saveBrandContacts();
        renderBrandContacts();
        document.getElementById('new-bc-brand').value = '';
        document.getElementById('new-bc-email').value = '';
        document.getElementById('new-bc-phone').value = '';
    });

    // --- Load LGA Contacts (editable) ---
    let lgaLoaded = false;
    let lgaContactsData = [];
    async function loadLgaContacts() {
        try {
            const resp = await fetch('lga_contact_details.json?_=' + Date.now());
            const raw = await resp.json();
            // Normalise: could be object or array
            if (Array.isArray(raw)) { lgaContactsData = raw; }
            else { lgaContactsData = Object.values(raw); }
            renderLgaContacts();
            lgaLoaded = true;
        } catch (e) { console.error('LGA contacts load error:', e); }
    }

    function renderLgaContacts() {
        const tbody = document.getElementById('lga-contacts-body');
        tbody.innerHTML = '';
        lgaContactsData.forEach((info, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + esc(info.LGAFullName || '') + '</td>'
                + '<td><input type="email" value="' + esc(info.email || info.Email || '') + '" data-idx="' + idx + '" style="width:250px;padding:3px 6px;font-size:0.8em;border:1px solid var(--border-color);border-radius:3px;background:var(--input-bg);color:var(--input-text);"></td>'
                + '<td><button onclick="deleteLgaContact(' + idx + ')" style="padding:3px 8px;font-size:0.75em;border:none;border-radius:3px;cursor:pointer;background:var(--error-color);color:white;">Del</button></td>';
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('input').forEach(input => {
            input.addEventListener('change', () => {
                const idx = parseInt(input.dataset.idx);
                lgaContactsData[idx].email = input.value;
                lgaContactsData[idx].Email = input.value;
                saveLgaContacts();
            });
        });
    }

    async function saveLgaContacts() {
        try {
            const resp = await adminMutate('admin.php?action=save_lga_contacts', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(lgaContactsData)
            });
            const result = await resp.json();
            if (result.status === 'success') { showToast('Saved'); }
            else { console.error('Save LGA contacts failed:', result.message); showToast('Save failed'); }
        } catch (e) { console.error('Save LGA contacts error:', e); showToast('Save error'); }
    }

    window.deleteLgaContact = function(idx) {
        if (!confirm('Delete LGA contact for ' + (lgaContactsData[idx]?.LGAFullName || 'this entry') + '?')) return;
        lgaContactsData.splice(idx, 1);
        saveLgaContacts();
        renderLgaContacts();
    };

    document.getElementById('add-lga-contact-btn').addEventListener('click', () => {
        const name = document.getElementById('new-lga-name').value.trim();
        const email = document.getElementById('new-lga-email').value.trim();
        if (!name || !email) { alert('LGA name and email are required.'); return; }
        lgaContactsData.push({ LGAFullName: name, email: email, Email: email });
        saveLgaContacts();
        renderLgaContacts();
        document.getElementById('new-lga-name').value = '';
        document.getElementById('new-lga-email').value = '';
    });

    // --- Load Error Log ---
    let errorsLoaded = false;
    async function loadErrorLog() {
        try {
            const resp = await fetch('admin.php?action=errorlog');
            const pre = document.getElementById('error-log-content');
            pre.textContent = document.getElementById('error-log-data')?.textContent || 'Error log not available via AJAX. See the inline content below.';
            errorsLoaded = true;
        } catch (e) { console.error('Error log load error:', e); }
    }

    // --- Load Brand Accounts ---
    let brandAccountsLoaded = false;
    async function loadBrandAccounts() {
        try {
            const resp = await fetch('admin.php?action=brand_accounts');
            const accounts = await resp.json();
            const tbody = document.getElementById('brand-accounts-body');
            tbody.innerHTML = '';

            const baseUrl = window.location.origin + '/trolley/brand_portal.php?token=';

            accounts.forEach(acc => {
                const tr = document.createElement('tr');
                const portalUrl = baseUrl + acc.token;
                const isActive = parseInt(acc.is_active) === 1;
                const lastLogin = acc.last_login ? new Date(acc.last_login + 'Z').toLocaleString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short', timeStyle: 'short', hour12: true }) : 'Never';

                tr.innerHTML = '<td>' + esc(acc.brand_name) + '</td>'
                    + '<td><span style="padding:2px 6px;border-radius:3px;font-size:0.75em;font-weight:bold;'
                    + (isActive ? 'background:#103010;color:#4caf50;"' : 'background:#401010;color:#f44336;"')
                    + '>' + (isActive ? 'Active' : 'Suspended') + '</span></td>'
                    + '<td><input type="text" value="' + esc(portalUrl) + '" readonly style="width:200px;padding:3px 6px;font-size:0.75em;border:1px solid var(--border-color);border-radius:3px;background:var(--input-bg);color:var(--input-text);">'
                    + ' <button onclick="copyToClipboard(\'' + esc(portalUrl) + '\')" style="padding:3px 8px;font-size:0.7em;border:none;border-radius:3px;cursor:pointer;background:var(--copy-button-bg);color:white;">Copy</button></td>'
                    + '<td style="font-size:0.8em;">' + lastLogin + '</td>'
                    + '<td>'
                    + '<button onclick="toggleBrand(' + acc.id + ',' + (isActive ? '0' : '1') + ')" style="padding:3px 8px;font-size:0.75em;border:none;border-radius:3px;cursor:pointer;background:' + (isActive ? 'var(--error-color)' : '#28a745') + ';color:white;margin-right:4px;">' + (isActive ? 'Suspend' : 'Activate') + '</button>'
                    + '<button onclick="regenToken(' + acc.id + ')" style="padding:3px 8px;font-size:0.75em;border:none;border-radius:3px;cursor:pointer;background:var(--button-bg);color:white;margin-right:4px;">New Token</button>'
                    + '<button onclick="deleteBrand(' + acc.id + ')" style="padding:3px 8px;font-size:0.75em;border:none;border-radius:3px;cursor:pointer;background:#666;color:white;">Delete</button>'
                    + '</td>';
                tbody.appendChild(tr);
            });

            if (accounts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#aaa;">No brand accounts created yet.</td></tr>';
            }
            brandAccountsLoaded = true;
        } catch (e) { console.error('Brand accounts load error:', e); }
    }

    // Copy portal link to clipboard with toast feedback
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard');
        }).catch(() => {
            showToast('Copy failed');
        });
    };

    // Create brand account
    document.getElementById('create-brand-btn').addEventListener('click', async () => {
        const brandName = document.getElementById('new-brand-name').value.trim();
        if (!brandName) { alert('Please enter an account name (e.g. Coles 1).'); return; }
        if (!confirm('Create portal account "' + brandName + '"?')) return;

        try {
            const resp = await adminMutate('admin.php?action=create_brand&brand_name=' + encodeURIComponent(brandName));
            const result = await resp.json();
            if (result.status === 'success') {
                alert('Account created: ' + brandName + '\n\nPortal link:\n' + window.location.origin + '/trolley/brand_portal.php?token=' + result.account.token);
                document.getElementById('new-brand-name').value = '';
                brandAccountsLoaded = false;
                loadBrandAccounts();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) { alert('Error: ' + e.message); }
    });

    // Toggle brand active/suspended
    window.toggleBrand = async function(id, active) {
        if (!confirm(active ? 'Activate this account?' : 'Suspend this account?')) return;
        try {
            const resp = await adminMutate('admin.php?action=toggle_brand&id=' + id + '&active=' + active);
            const result = await resp.json();
            if (result.status === 'success') { brandAccountsLoaded = false; loadBrandAccounts(); }
            else { alert('Error: ' + result.message); }
        } catch (e) { alert('Error: ' + e.message); }
    };

    // Regenerate token
    window.regenToken = async function(id) {
        if (!confirm('Regenerate access token? The old link will stop working.')) return;
        try {
            const resp = await adminMutate('admin.php?action=regen_token&id=' + id);
            const result = await resp.json();
            if (result.status === 'success') {
                alert('New token generated. New portal link:\n' + window.location.origin + '/trolley/brand_portal.php?token=' + result.token);
                brandAccountsLoaded = false;
                loadBrandAccounts();
            } else { alert('Error: ' + result.message); }
        } catch (e) { alert('Error: ' + e.message); }
    };

    // Delete brand account
    window.deleteBrand = async function(id) {
        if (!confirm('Delete this brand account permanently? This cannot be undone.')) return;
        try {
            const resp = await adminMutate('admin.php?action=delete_brand&id=' + id);
            const result = await resp.json();
            if (result.status === 'success') { brandAccountsLoaded = false; loadBrandAccounts(); }
            else { alert('Error: ' + result.message); }
        } catch (e) { alert('Error: ' + e.message); }
    };

    const regenAllBtn = document.getElementById('regen-all-tokens-btn');
    if (regenAllBtn) regenAllBtn.addEventListener('click', async () => {
        const msg = 'Regenerate tokens for ALL brand portal accounts?\n\nEvery existing portal link will stop working. You will need to copy and send new links to each brand.';
        if (!confirm(msg)) return;
        try {
            const resp = await adminMutate('admin.php?action=regen_all_tokens');
            const result = await resp.json();
            if (result.status === 'success') {
                alert(result.message || ('Regenerated ' + (result.count || 0) + ' token(s).'));
                brandAccountsLoaded = false;
                loadBrandAccounts();
            } else {
                alert('Error: ' + (result.message || 'Unknown error'));
            }
        } catch (e) { alert('Error: ' + e.message); }
    });


});
</script>

<?php
// Inline error log data (last 50 lines) so we don't need an AJAX endpoint
$errorLogContent = '';
if (file_exists($errorLogFilePath) && is_readable($errorLogFilePath)) {
    $lines = file($errorLogFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $lastLines = array_slice($lines, -50);
        $errorLogContent = htmlspecialchars(implode("\n", $lastLines), ENT_QUOTES, 'UTF-8');
    }
}
?>
<script>
// Inject error log data from PHP
document.addEventListener('DOMContentLoaded', () => {
    const pre = document.getElementById('error-log-content');
    if (pre) {
        pre.textContent = <?php echo json_encode(strip_tags($errorLogContent ?: 'No errors logged.')); ?>;
    }
});
</script>

<?php endif; ?>

</div>
</body>
</html>