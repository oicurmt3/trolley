<?php
/**
 * log_trolley.php -- Backend API for WA Trolley Report
 *
 * Receives trolley report submissions, validates input, performs reverse
 * geocoding (with caching), looks up LGA details, and saves to JSON log.
 *
 * Security features:
 * - CORS restricted to sandgroper.net
 * - CSRF token verification
 * - Server-side rate limiting (per IP)
 * - Input sanitisation and validation
 * - Coordinate bounds checking
 * - Geocode response caching (reduces Nominatim load)
 *
 * Version: 1.4
 */

require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/data_files.php';

applyConfiguredCors(['GET', 'POST', 'OPTIONS'], ['Content-Type', 'X-CSRF-Token'], true);
header('Content-Type: application/json');

// --- Configuration ---
$logDirectory = __DIR__;
$jsonLogFilePath = $logDirectory . '/trolley_logs.json';
$brandContactsExamplePath = $logDirectory . '/brand_contacts.example.json';

// LGA data file paths
$suburbLgaMapFilePath = $logDirectory . '/suburb_lga_mapping.json';
$lgaContactsFilePath = $logDirectory . '/lga_contact_details.json';

// Rate limiting config
$rateLimitFilePath = $logDirectory . '/rate_limits.json';
$rateLimitMaxRequests = 25;        // Max submissions per window (per hashed IP)
$rateLimitWindowSeconds = 3600;    // 1 hour

// Geocode cache config
$geocacheDirPath = $logDirectory . '/geocache';
$geocacheTtlSeconds = 604800; // Cache geocode results for 7 days

// Nominatim config
$nominatimBaseUrl = 'https://nominatim.openstreetmap.org/reverse';
$nominatimUserAgent = 'TrolleyReportApp/1.4 (sandgroper.net; admin@sandgroper.net)';

// Valid brands (whitelist for server-side validation)
$validBrands = ['Coles', 'Woolworths', 'Aldi', 'IGA', 'Kmart', 'BigW', 'Bunnings', 'Spudshed', 'Other'];

// Valid condition values (whitelist)
$validConditions = [
    'Good Condition', 'Needs Cleaning', 'Rusty Frame', 'Damaged Wheel(s)',
    'Bent Frame', 'Broken Handle', 'Child Seat Issue', 'Other Damage'
];


// ==========================================================================
// HELPER FUNCTIONS
// ==========================================================================

/**
 * Send a JSON response and terminate execution
 */
function sendJsonResponse($status, $message, $httpStatusCode = 200, $returnedData = null) {
    http_response_code($httpStatusCode);
    $response = ['status' => $status, 'message' => $message];
    if ($returnedData !== null && $status === 'success') {
        $response['logData'] = $returnedData;
    }
    echo json_encode($response);
    exit;
}


/**
 * Server-side rate limiting using a JSON file keyed by IP address.
 * Returns true if the request is allowed, false if rate-limited.
 */
function checkRateLimit($filePath, $maxRequests, $windowSeconds) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // Hash the IP for privacy (we do not store raw IPs)
    $ipHash = hash('sha256', $ip . 'trolley_salt_2026');

    $now = time();
    $limits = [];

    // Read existing rate limit data
    if (file_exists($filePath) && filesize($filePath) > 0) {
        $json = file_get_contents($filePath);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $limits = $decoded;
            }
        }
    }

    // Clean up expired entries (older than window)
    foreach ($limits as $key => $entry) {
        if (($now - ($entry['first'] ?? 0)) > $windowSeconds) {
            unset($limits[$key]);
        }
    }

    // Check current IP
    if (isset($limits[$ipHash])) {
        $entry = $limits[$ipHash];
        if (($now - $entry['first']) <= $windowSeconds) {
            if ($entry['count'] >= $maxRequests) {
                // Save cleaned data before returning
                file_put_contents($filePath, json_encode($limits, JSON_PRETTY_PRINT), LOCK_EX);
                return false; // Rate limited
            }
            $limits[$ipHash]['count'] = $entry['count'] + 1;
        } else {
            // Window expired, reset
            $limits[$ipHash] = ['first' => $now, 'count' => 1];
        }
    } else {
        // New IP
        $limits[$ipHash] = ['first' => $now, 'count' => 1];
    }

    // Save updated rate limit data
    file_put_contents($filePath, json_encode($limits, JSON_PRETTY_PRINT), LOCK_EX);
    return true;
}


/**
 * Generate a CSRF token and store it in the session.
 * Called via GET request to provide a token to the frontend.
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


/**
 * Verify the CSRF token from the request header.
 */
function verifyCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($headerToken) || empty($sessionToken)) {
        return false;
    }
    return hash_equals($sessionToken, $headerToken);
}


/**
 * Sanitise a string value: trim, strip tags, limit length, ASCII-safe.
 */
function sanitiseString($value, $maxLength = 500) {
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    $value = strip_tags($value);
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}


/**
 * Get cached geocode result for given coordinates.
 * Returns associative array or null if not cached / expired.
 */
function getGeocodeCache($lat, $lon, $cacheDir, $ttlSeconds) {
    if (!is_dir($cacheDir)) {
        return null;
    }
    // Round coordinates to 3 decimal places (~111m precision) for cache key
    $cacheKey = round($lat, 3) . '_' . round($lon, 3);
    $cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $cacheKey) . '.json';

    if (file_exists($cacheFile)) {
        $mtime = filemtime($cacheFile);
        if ((time() - $mtime) < $ttlSeconds) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        } else {
            // Expired, delete
            @unlink($cacheFile);
        }
    }
    return null;
}


/**
 * Save geocode result to cache.
 */
function setGeocodeCache($lat, $lon, $cacheDir, $data) {
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheKey = round($lat, 3) . '_' . round($lon, 3);
    $cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $cacheKey) . '.json';
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}


// ==========================================================================
// REQUEST HANDLING
// ==========================================================================

// --- Handle OPTIONS request (CORS preflight) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Handle GET request (CSRF token + brand contacts endpoint) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = generateCsrfToken();
    $response = ['csrf_token' => $token];

    $brandContactsFilePath = $logDirectory . '/brand_contacts.json';
    ensure_brand_contacts_file($brandContactsFilePath, $brandContactsExamplePath);
    $brandContacts = read_json_file($brandContactsFilePath);
    if ($brandContacts !== null) {
        $response['brandContacts'] = reporter_safe_brand_contacts($brandContacts);
    }

    echo json_encode($response);
    exit;
}


// --- Check Request Method (POST only beyond this point) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is accepted.', 405);
}

// --- Verify CSRF Token (log failures) ---
if (!verifyCsrfToken()) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    error_log("PHP Security: CSRF token verification failed from IP $ip at " . gmdate('Y-m-d H:i:s') . " UTC");
    sendJsonResponse('error', 'Invalid or missing security token. Please reload the page and try again.', 403);
}

// --- Validate Referer Header ---
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer)) {
    $refererHost = parse_url($referer, PHP_URL_HOST);
    if ($refererHost && !in_array($refererHost, ['sandgroper.net', 'www.sandgroper.net'], true)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("PHP Security: Invalid Referer '$refererHost' from IP $ip");
        sendJsonResponse('error', 'Request origin not permitted.', 403);
    }
}

// --- Server-Side Rate Limiting ---
if (!checkRateLimit($rateLimitFilePath, $rateLimitMaxRequests, $rateLimitWindowSeconds)) {
    sendJsonResponse('error', 'Submission limit reached (20 per 12 hours). Please try again later.', 429);
}

// --- Get & Decode JSON Data ---
$jsonPayload = file_get_contents('php://input');
if (empty($jsonPayload)) {
    sendJsonResponse('error', 'No data received.', 400);
}

// Limit payload size (prevent abuse via large payloads)
if (strlen($jsonPayload) > 10000) {
    sendJsonResponse('error', 'Request payload too large.', 413);
}

$logDataFromClient = json_decode($jsonPayload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse('error', 'Invalid JSON data received: ' . json_last_error_msg(), 400);
}

// --- Honeypot Check (silent rejection) ---
// If the hidden field 'website_url' has any value, it was filled by a bot
if (!empty($logDataFromClient['website_url'])) {
    // Silently reject -- return success to not tip off the bot
    $fakeEntry = $logDataFromClient;
    $fakeEntry['serverTimestampUTC'] = gmdate(DateTime::ATOM);
    sendJsonResponse('success', 'Log saved successfully.', 200, $fakeEntry);
}


// ==========================================================================
// INPUT VALIDATION AND SANITISATION
// ==========================================================================

// --- Validate required fields ---
if (!isset($logDataFromClient['latitude']) || !isset($logDataFromClient['longitude']) || !isset($logDataFromClient['timestamp'])) {
    sendJsonResponse('error', 'Missing required location data (latitude, longitude, timestamp).', 400);
}
if (!isset($logDataFromClient['brands']) || !is_array($logDataFromClient['brands']) || empty($logDataFromClient['brands'])) {
    sendJsonResponse('error', 'Missing or invalid brand data.', 400);
}

// --- Validate and sanitise latitude/longitude ---
$latitude = filter_var($logDataFromClient['latitude'], FILTER_VALIDATE_FLOAT);
$longitude = filter_var($logDataFromClient['longitude'], FILTER_VALIDATE_FLOAT);

if ($latitude === false || $longitude === false) {
    sendJsonResponse('error', 'Invalid coordinate format.', 400);
}

// Coordinate bounds check (valid GPS range)
if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
    sendJsonResponse('error', 'Coordinates out of valid GPS range.', 400);
}

// Optional: Restrict to approximate Australian bounds (loose)
// Latitude: -10 to -45, Longitude: 110 to 155
if ($latitude < -45.0 || $latitude > -10.0 || $longitude < 110.0 || $longitude > 155.0) {
    error_log("PHP Warning: Coordinates outside Australian bounds: $latitude, $longitude. Allowing submission but flagging.");
    // We allow it but log it -- could be legitimate (e.g., near borders)
}

// Sanitise latitude/longitude to string with 6 decimal places
$logDataFromClient['latitude'] = number_format($latitude, 6, '.', '');
$logDataFromClient['longitude'] = number_format($longitude, 6, '.', '');

// --- Validate timestamp ---
$timestamp = $logDataFromClient['timestamp'];
if (!is_string($timestamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $timestamp)) {
    sendJsonResponse('error', 'Invalid timestamp format. Expected ISO 8601 (YYYY-MM-DDTHH:MM:SS).', 400);
}

// --- Validate and whitelist brands ---
$sanitisedBrands = [];
foreach ($logDataFromClient['brands'] as $brand) {
    if (is_string($brand) && in_array($brand, $validBrands, true)) {
        $sanitisedBrands[] = $brand;
    }
}
if (empty($sanitisedBrands)) {
    sendJsonResponse('error', 'No valid brands provided.', 400);
}
$logDataFromClient['brands'] = $sanitisedBrands;

// --- Validate and whitelist conditions ---
if (isset($logDataFromClient['conditions']) && is_array($logDataFromClient['conditions'])) {
    $sanitisedConditions = [];
    foreach ($logDataFromClient['conditions'] as $brand => $conditionArray) {
        // Only accept conditions for valid brands that were submitted
        if (!in_array($brand, $sanitisedBrands, true)) {
            continue;
        }
        if (is_array($conditionArray)) {
            $validBrandConditions = [];
            foreach ($conditionArray as $condition) {
                if (is_string($condition) && in_array($condition, $validConditions, true)) {
                    $validBrandConditions[] = $condition;
                }
            }
            if (!empty($validBrandConditions)) {
                $sanitisedConditions[$brand] = $validBrandConditions;
            }
        }
    }
    $logDataFromClient['conditions'] = $sanitisedConditions;
} else {
    // Remove conditions if not a valid array
    unset($logDataFromClient['conditions']);
}

// --- Sanitise comments (server-side enforcement) ---
if (isset($logDataFromClient['comments'])) {
    $logDataFromClient['comments'] = sanitiseString($logDataFromClient['comments'], 200);
    if (empty($logDataFromClient['comments'])) {
        unset($logDataFromClient['comments']);
    }
} else {
    unset($logDataFromClient['comments']);
}

// --- Strip any unexpected fields from client data ---
$allowedClientFields = ['latitude', 'longitude', 'timestamp', 'brands', 'conditions', 'comments'];
$cleanedClientData = [];
foreach ($allowedClientFields as $field) {
    if (isset($logDataFromClient[$field])) {
        $cleanedClientData[$field] = $logDataFromClient[$field];
    }
}


// ==========================================================================
// REVERSE GEOCODING (with caching)
// ==========================================================================

$suburbName = null;
$stateName = null;
$lgaName = null;
$lgaEmail = null;

// Check geocode cache first
$cachedGeo = getGeocodeCache($latitude, $longitude, $geocacheDirPath, $geocacheTtlSeconds);

if ($cachedGeo !== null) {
    // Use cached result
    $suburbName = $cachedGeo['suburb'] ?? null;
    $stateName = $cachedGeo['state'] ?? null;
} else {
    // Call Nominatim API
    $nominatimApiUrl = $nominatimBaseUrl . '?format=jsonv2&addressdetails=1&lat=' . $latitude . '&lon=' . $longitude . '&accept-language=en';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: " . $nominatimUserAgent . "\r\n",
            'ignore_errors' => true,
            'timeout' => 7
        ]
    ]);
    $geoResponseJson = @file_get_contents($nominatimApiUrl, false, $context);

    if ($geoResponseJson !== false) {
        $geo_http_response_header = $http_response_header ?? [];
        $geoStatusCode = 0;
        if (!empty($geo_http_response_header[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $geo_http_response_header[0], $match);
            if ($match) { $geoStatusCode = (int)$match[1]; }
        }

        if ($geoStatusCode === 200) {
            $geoData = json_decode($geoResponseJson, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($geoData['address'])) {
                $address = $geoData['address'];
                $suburbName = $address['suburb'] ?? $address['village'] ?? $address['town'] ?? $address['city_district'] ?? $address['hamlet'] ?? null;
                $stateName = $address['state'] ?? null;

                // Cache the result
                setGeocodeCache($latitude, $longitude, $geocacheDirPath, [
                    'suburb' => $suburbName,
                    'state' => $stateName
                ]);
            } else {
                error_log("PHP Nominatim JSON decode error or missing address for $latitude, $longitude. Response: " . substr($geoResponseJson, 0, 250));
            }
        } else {
            error_log("PHP Nominatim API request failed ($geoStatusCode) for $latitude, $longitude. Response: " . substr($geoResponseJson, 0, 250));
        }
    } else {
        error_log("PHP Failed to contact Nominatim API for $latitude, $longitude. Error: " . (error_get_last()['message'] ?? 'Unknown file_get_contents error'));
    }
}


// ==========================================================================
// LGA LOOKUP (two-step: suburb -> LGA key -> LGA details)
// ==========================================================================

if ($suburbName) {
    require_once __DIR__ . '/db.php';
    $lgaIdentifier = getSuburbLgaKeyByName($suburbName);

    // Step 2: Get LGA details from contact details file
    if ($lgaIdentifier && file_exists($lgaContactsFilePath)) {
        $lgaContactsJson = file_get_contents($lgaContactsFilePath);
        $lgaContacts = json_decode($lgaContactsJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($lgaContacts)) {
            if (isset($lgaContacts[$lgaIdentifier])) {
                $lgaDetails = $lgaContacts[$lgaIdentifier];
                $lgaName = $lgaDetails['LGAFullName'] ?? $lgaIdentifier;
                $lgaEmail = $lgaDetails['Email'] ?? null;
            } else {
                error_log("PHP Warning: LGA Identifier '$lgaIdentifier' for suburb '$suburbName' not found in '$lgaContactsFilePath'.");
            }
        } else {
            error_log("PHP Warning: LGA contacts file '$lgaContactsFilePath' might be corrupted. JSON Error: " . json_last_error_msg());
        }
    } elseif ($lgaIdentifier && !file_exists($lgaContactsFilePath)) {
        error_log("PHP Warning: LGA contacts file not found at: " . $lgaContactsFilePath);
    } elseif (!$lgaIdentifier) {
        error_log("PHP Info: No LGAKey found for suburb '$suburbName'.");
    }
}


// ==========================================================================
// BUILD FINAL LOG ENTRY
// ==========================================================================

$finalLogEntry = $cleanedClientData;
$finalLogEntry['suburb'] = $suburbName;
$finalLogEntry['state'] = $stateName;
$finalLogEntry['lgaName'] = $lgaName;
$finalLogEntry['lgaEmail'] = $lgaEmail;

// Server-side timestamp (UTC, ATOM format)
try {
    $dateTime = new DateTime('now', new DateTimeZone('UTC'));
    $finalLogEntry['serverTimestampUTC'] = $dateTime->format(DateTime::ATOM);
} catch (Exception $e) {
    $finalLogEntry['serverTimestampUTC'] = gmdate(DateTime::ATOM);
}


// ==========================================================================
// SAVE TO SQLITE DATABASE
// ==========================================================================

require_once __DIR__ . '/db.php';

$db = getDatabase();
if ($db === null) {
    error_log("PHP Error: Could not connect to SQLite database.");
    sendJsonResponse('error', 'Server error: Database unavailable.', 500);
}

// --- Check for nearby duplicate reports (within 50m, last 7 days) ---
$nearbyDuplicates = findNearbyDuplicates($db, $latitude, $longitude, 50, 604800);

// Insert the new report
$newId = insertReport($db, $finalLogEntry);
if ($newId === false) {
    sendJsonResponse('error', 'Server error: Failed to save log data.', 500);
}
$finalLogEntry['id'] = $newId;

// Regenerate CSRF token after successful submission (one-time use)
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Build response with optional duplicate info and brand contacts
$response = ['status' => 'success', 'message' => 'Log saved successfully.', 'logData' => $finalLogEntry];

if (!empty($nearbyDuplicates)) {
    $response['nearbyDuplicates'] = $nearbyDuplicates;
    $response['message'] = 'Log saved. Note: ' . count($nearbyDuplicates) . ' similar report(s) found nearby in the last 7 days.';
}

$brandContactsFilePath2 = $logDirectory . '/brand_contacts.json';
ensure_brand_contacts_file($brandContactsFilePath2, $brandContactsExamplePath);
$bc = read_json_file($brandContactsFilePath2);
if ($bc !== null) {
    $response['brandContacts'] = reporter_safe_brand_contacts($bc);
}

http_response_code(200);
echo json_encode($response);
exit;
?>
