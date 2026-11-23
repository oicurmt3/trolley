<?php
declare(strict_types=1);

$webRoot = __DIR__;

require_once $webRoot . '/includes/app_config.php';
require_once $webRoot . '/includes/cors.php';
require_once $webRoot . '/includes/http_response.php';
require_once $webRoot . '/includes/rate_limit.php';
require_once $webRoot . '/includes/validation.php';
require_once $webRoot . '/includes/lga_lookup.php';
require_once $webRoot . '/includes/json_log.php';

trolley_apply_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if ($origin !== '' && in_array($origin, TROLLEY_ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
  }
  http_response_code(403);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  trolley_send_json('error', 'Invalid request method. Only POST is accepted.', 405);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, TROLLEY_ALLOWED_ORIGINS, true)) {
  trolley_send_json('error', 'Forbidden.', 403);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > TROLLEY_MAX_BODY_BYTES) {
  trolley_send_json('error', 'Request body too large.', 413);
}

trolley_rate_limit_check($webRoot);

$jsonPayload = file_get_contents('php://input');
if ($jsonPayload === false || $jsonPayload === '') {
  trolley_send_json('error', 'No data received.', 400);
}

if (strlen($jsonPayload) > TROLLEY_MAX_BODY_BYTES) {
  trolley_send_json('error', 'Request body too large.', 413);
}

$decoded = json_decode($jsonPayload, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
  trolley_send_json('error', 'Invalid JSON data received.', 400);
}

$entry = trolley_validate_client_payload($decoded);

$lat = (float)$entry['latitude'];
$lon = (float)$entry['longitude'];

$geo = trolley_reverse_geocode($lat, $lon);
$entry['suburb'] = $geo['suburb'];
$entry['state'] = $geo['state'];

$lga = trolley_resolve_lga($webRoot, $geo['suburb']);
$entry['lgaName'] = $lga['lgaName'];
$entry['lgaEmail'] = $lga['lgaEmail'];

try {
  $dateTime = new DateTime('now', new DateTimeZone('UTC'));
  $entry['serverTimestampUTC'] = $dateTime->format(DateTime::ATOM);
} catch (Exception $e) {
  $entry['serverTimestampUTC'] = gmdate('c');
}

trolley_append_log($webRoot, $entry);

trolley_send_json('success', 'Log saved successfully.', 200, $entry);