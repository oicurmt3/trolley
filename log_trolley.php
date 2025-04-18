<?php
// Allow requests from any origin (adjust in production if needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Allow OPTIONS for preflight
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// --- Configuration ---
$logDirectory = __DIR__; // Log in the same directory as the script
$jsonLogFileName = 'trolley_logs.json';
$jsonLogFilePath = $logDirectory . '/' . $jsonLogFileName;
// $csvLogFilePath = $logDirectory . '/trolley_logs.csv'; // Optional CSV logging

// --- Function to Send JSON Response ---
function sendJsonResponse($status, $message, $httpStatusCode = 200) {
    http_response_code($httpStatusCode);
    echo json_encode(['status' => $status, 'message' => $message]);
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
$newLogData = json_decode($jsonPayload, true); // Decode as associative array
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse('error', 'Invalid JSON data received: ' . json_last_error_msg(), 400);
}

// --- Basic Data Validation (Server-Side) ---
if (!isset($newLogData['latitude']) || !isset($newLogData['longitude']) || !isset($newLogData['timestamp'])) {
    sendJsonResponse('error', 'Missing required location data (latitude, longitude, timestamp).', 400);
}
if (!isset($newLogData['brands']) || !is_array($newLogData['brands']) || empty($newLogData['brands'])) {
    sendJsonResponse('error', 'Missing or invalid brand data.', 400);
}
// Optional: Validate condition structure if present
if (isset($newLogData['conditions']) && !is_array($newLogData['conditions'])) {
     sendJsonResponse('error', 'Invalid condition data format.', 400);
}


// --- Add Server-Side Metadata ---
try {
    // Use UTC for server timestamp consistency
    $dateTime = new DateTime('now', new DateTimeZone('UTC'));
    // Format in ISO 8601 for better machine readability
    $newLogData['serverTimestampUTC'] = $dateTime->format(DateTime::ATOM); // e.g., 2025-04-11T12:54:25+00:00
} catch (Exception $e) {
    // Fallback if timezone fails
     $newLogData['serverTimestampUTC'] = gmdate(DateTime::ATOM);
}
$newLogData['ipAddress'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';


// --- JSON Logging ---

// Check if directory is writable (important!)
if (!is_writable($logDirectory)) {
     error_log("PHP Error: Log directory not writable: " . $logDirectory);
     sendJsonResponse('error', 'Server configuration error: Cannot write to log directory.', 500);
}

// Check file permissions if it exists
if (file_exists($jsonLogFilePath) && (!is_readable($jsonLogFilePath) || !is_writable($jsonLogFilePath))) {
    error_log("PHP Error: JSON log file not readable/writable: " . $jsonLogFilePath);
    sendJsonResponse('error', 'Server config error: Cannot read/write JSON log file.', 500);
}

$logs = [];
// Read existing data safely
if (file_exists($jsonLogFilePath) && filesize($jsonLogFilePath) > 0) {
    $existingJson = file_get_contents($jsonLogFilePath);
    if ($existingJson !== false) {
        $decodedLogs = json_decode($existingJson, true);
        // Check if decoding was successful and resulted in an array
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedLogs)) {
            $logs = $decodedLogs;
        } else {
            // File exists but is corrupt or not valid JSON array - log error, start fresh
            error_log("PHP Warning: JSON log file '$jsonLogFilePath' might be corrupted or not an array. Resetting log. JSON Error: " . json_last_error_msg());
            // Consider backing up the corrupted file here before overwriting
            // rename($jsonLogFilePath, $jsonLogFilePath . '.corrupt.' . time());
            $logs = [];
        }
    } else {
         error_log("PHP Warning: Could not read existing JSON log file: " . $jsonLogFilePath);
         // Decide if to proceed with empty array or fail
         $logs = []; // Proceed with empty array
    }
}
// Add the new log entry
$logs[] = $newLogData;

// Write back to the file with locking
$fileHandle = fopen($jsonLogFilePath, 'c'); // 'c' - create if not exist, truncate if exists, pointer at start
if ($fileHandle === false) {
    error_log("PHP Error: Could not open JSON log file for writing: " . $jsonLogFilePath);
    sendJsonResponse('error', 'Server error: Could not open JSON log file.', 500);
}

$logWriteSuccess = false;
if (flock($fileHandle, LOCK_EX)) { // Exclusive lock
    if (ftruncate($fileHandle, 0)) { // Clear the file content
        rewind($fileHandle); // Move pointer to the beginning
        $updatedJson = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($updatedJson !== false && fwrite($fileHandle, $updatedJson) !== false) {
            fflush($fileHandle); // Ensure data is written to disk
            $logWriteSuccess = true;
        } else {
            error_log("PHP Error: Failed to write or encode JSON to: " . $jsonLogFilePath . " JSON Error: " . json_last_error_msg());
        }
    } else {
        error_log("PHP Error: Failed to truncate JSON file: " . $jsonLogFilePath);
    }
    flock($fileHandle, LOCK_UN); // Release lock
} else {
    error_log("PHP Warning: Could not lock JSON file for writing: " . $jsonLogFilePath);
    // Don't send error to user usually, just log it, maybe try again later?
    // For now, we'll indicate failure if lock fails.
}
fclose($fileHandle);

if (!$logWriteSuccess) {
    // Don't expose detailed server error to client, but log it.
    sendJsonResponse('error', 'Server error: Failed to save log data.', 500);
}


// --- Optional: CSV Logging (Add if needed) ---
/*
// Define CSV fields carefully, especially for nested 'conditions'
$csvFields = [
    'timestamp', 'latitude', 'longitude', 'brands', // Keep brands as JSON string? Or comma-separated?
    // How to represent conditions? Maybe flatten? E.g., condition_Coles, condition_Woolies?
    'comments', 'serverTimestampUTC', 'ipAddress', 'clientSubmissionTimestamp'
];

// Implement CSV writing logic similar to JSON (fopen 'a', flock, fputcsv, fclose)
// Need to decide how to flatten the $newLogData['conditions'] array for CSV.
// Example flattening:
$csvRow = [];
foreach ($csvFields as $field) {
    if ($field === 'brands' && isset($newLogData['brands'])) {
         $csvRow[] = implode(', ', $newLogData['brands']); // Simple comma separation
    } elseif (isset($newLogData[$field])) {
         $csvRow[] = $newLogData[$field];
    } // Add logic here for conditions if needed
     else {
        $csvRow[] = '';
    }
}
// ... fputcsv logic ...
*/


// --- Send Final Success Response ---
sendJsonResponse('success', 'Log saved successfully.', 200);
?>