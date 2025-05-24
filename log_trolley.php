<?php
// Allow requests from any origin (adjust in production if needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Allow OPTIONS for preflight
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// --- Configuration ---
$logDirectory = __DIR__;
$jsonLogFileName = 'trolley_logs.json';
$jsonLogFilePath = $logDirectory . '/' . $jsonLogFileName;

// New file paths for LGA data
$suburbLgaMapFilePath = $logDirectory . '/suburb_lga_mapping.json';
$lgaContactsFilePath = $logDirectory . '/lga_contact_details.json';

$nominatimBaseUrl = 'https://nominatim.openstreetmap.org/reverse';
$nominatimUserAgent = 'TrolleyReportApp/1.3 (sandgroper.net; admin@sandgroper.net)'; // Updated version

// --- Function to Send JSON Response ---
function sendJsonResponse($status, $message, $httpStatusCode = 200, $returnedData = null) {
    http_response_code($httpStatusCode);
    $response = ['status' => $status, 'message' => $message];
    if ($returnedData !== null) {
        $response['logData'] = $returnedData;
    }
    echo json_encode($response);
    exit;
}

// --- Handle OPTIONS request (for CORS preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    sendJsonResponse('success', 'OPTIONS request allowed', 200);
}

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is accepted.', 405);
}

// --- Get & Decode JSON Data ---
$jsonPayload = file_get_contents('php://input');
if (empty($jsonPayload)) {
    sendJsonResponse('error', 'No data received.', 400);
}
$logDataFromClient = json_decode($jsonPayload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse('error', 'Invalid JSON data received: ' . json_last_error_msg(), 400);
}

// --- Basic Data Validation (Server-Side) ---
if (!isset($logDataFromClient['latitude']) || !isset($logDataFromClient['longitude']) || !isset($logDataFromClient['timestamp'])) {
    sendJsonResponse('error', 'Missing required location data (latitude, longitude, timestamp).', 400);
}
if (!isset($logDataFromClient['brands']) || !is_array($logDataFromClient['brands']) || empty($logDataFromClient['brands'])) {
    sendJsonResponse('error', 'Missing or invalid brand data.', 400);
}

// --- Perform Reverse Geocoding using Nominatim ---
$latitude = filter_var($logDataFromClient['latitude'], FILTER_VALIDATE_FLOAT);
$longitude = filter_var($logDataFromClient['longitude'], FILTER_VALIDATE_FLOAT);
$suburbName = null;
$stateName = null;
$lgaName = null; // This will be the LGAFullName from lga_contact_details.json
$lgaEmail = null;

if ($latitude !== false && $longitude !== false) {
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

                // --- LGA Lookup (Two-step process) ---
                if ($suburbName) {
                    $lgaIdentifier = null;
                    // Step 1: Get LGA Identifier from suburb mapping
                    if (file_exists($suburbLgaMapFilePath)) {
                        $suburbMapJson = file_get_contents($suburbLgaMapFilePath);
                        $suburbLgaList = json_decode($suburbMapJson, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($suburbLgaList)) {
                            foreach ($suburbLgaList as $mappingEntry) {
                                if (isset($mappingEntry['Suburb']) && strcasecmp($mappingEntry['Suburb'], $suburbName) == 0) {
                                    $lgaIdentifier = $mappingEntry['LGAKey'] ?? null;
                                    break;
                                }
                            }
                        } else {
                            error_log("PHP Warning: Suburb-LGA mapping file '$suburbLgaMapFilePath' might be corrupted. JSON Error: " . json_last_error_msg());
                        }
                    } else {
                        error_log("PHP Warning: Suburb-LGA mapping file not found at: " . $suburbLgaMapFilePath);
                    }

                    // Step 2: Get LGA details using the identifier
                    if ($lgaIdentifier && file_exists($lgaContactsFilePath)) {
                        $lgaContactsJson = file_get_contents($lgaContactsFilePath);
                        $lgaContacts = json_decode($lgaContactsJson, true); // Contacts are an associative array (object)
                        if (json_last_error() === JSON_ERROR_NONE && is_array($lgaContacts)) {
                            if (isset($lgaContacts[$lgaIdentifier])) {
                                $lgaDetails = $lgaContacts[$lgaIdentifier];
                                $lgaName = $lgaDetails['LGAFullName'] ?? $lgaIdentifier; // Fallback to identifier if FullName is missing
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
                        error_log("PHP Info: No LGAKey found for suburb '$suburbName' in '$suburbLgaMapFilePath'.");
                    }
                }
                // --- End LGA Lookup ---

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

// Create the final log entry object
$finalLogEntry = $logDataFromClient;
$finalLogEntry['suburb'] = $suburbName;
$finalLogEntry['state'] = $stateName;
$finalLogEntry['lgaName'] = $lgaName;   // This is the LGAFullName
$finalLogEntry['lgaEmail'] = $lgaEmail;

// --- Add Server-Side Metadata ---
try {
    $dateTime = new DateTime('now', new DateTimeZone('UTC'));
    $finalLogEntry['serverTimestampUTC'] = $dateTime->format(DateTime::ATOM);
} catch (Exception $e) {
     $finalLogEntry['serverTimestampUTC'] = gmdate(DateTime::ATOM);
}
// $finalLogEntry['ipAddress'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';


// --- JSON Logging ---
// (The logging part remains the same as your previous version)
if (!is_writable($logDirectory)) {
     error_log("PHP Error: Log directory not writable: " . $logDirectory);
     sendJsonResponse('error', 'Server configuration error: Cannot write to log directory.', 500, $finalLogEntry);
}
if (file_exists($jsonLogFilePath) && (!is_readable($jsonLogFilePath) || !is_writable($jsonLogFilePath))) {
    error_log("PHP Error: JSON log file not readable/writable: " . $jsonLogFilePath);
    sendJsonResponse('error', 'Server config error: Cannot read/write JSON log file.', 500, $finalLogEntry);
}

$logs = [];
if (file_exists($jsonLogFilePath) && filesize($jsonLogFilePath) > 0) {
    $existingJson = file_get_contents($jsonLogFilePath);
    if ($existingJson !== false) {
        $decodedLogs = json_decode($existingJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedLogs)) {
            $logs = $decodedLogs;
        } else {
            error_log("PHP Warning: JSON log file '$jsonLogFilePath' might be corrupted. Resetting. Error: " . json_last_error_msg());
            $logs = [];
        }
    } else {
         error_log("PHP Warning: Could not read existing JSON log file: " . $jsonLogFilePath);
         $logs = [];
    }
}

$logs[] = $finalLogEntry;

$fileHandle = fopen($jsonLogFilePath, 'c');
if ($fileHandle === false) {
    error_log("PHP Error: Could not open JSON log file for writing: " . $jsonLogFilePath);
    sendJsonResponse('error', 'Server error: Could not open JSON log file.', 500, $finalLogEntry);
}
$logWriteSuccess = false;
if (flock($fileHandle, LOCK_EX)) {
    if (ftruncate($fileHandle, 0)) {
        rewind($fileHandle);
        $updatedJson = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($updatedJson !== false && fwrite($fileHandle, $updatedJson) !== false) {
            fflush($fileHandle);
            $logWriteSuccess = true;
        } else {
            error_log("PHP Error: Failed to write/encode JSON to: " . $jsonLogFilePath . ". JSON Error: " . json_last_error_msg());
        }
    } else {
        error_log("PHP Error: Failed to truncate JSON file: " . $jsonLogFilePath);
    }
    flock($fileHandle, LOCK_UN);
} else {
    error_log("PHP Warning: Could not lock JSON file for writing: " . $jsonLogFilePath);
}
fclose($fileHandle);

if (!$logWriteSuccess) {
    sendJsonResponse('error', 'Server error: Failed to save log data.', 500, $finalLogEntry);
}

sendJsonResponse('success', 'Log saved successfully.', 200, $finalLogEntry);
?>