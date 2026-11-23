<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/http_response.php';

/**
 * Validate and sanitize client JSON into a normalized log entry (without server-enriched fields).
 *
 * @return array<string, mixed>
 */
function trolley_validate_client_payload(array $input): array
{
  $latitude = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
  $longitude = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
  if ($latitude === false || $longitude === false) {
    trolley_send_json('error', 'Invalid latitude or longitude.', 400);
  }

  if ($latitude < TROLLEY_WA_LAT_MIN || $latitude > TROLLEY_WA_LAT_MAX
      || $longitude < TROLLEY_WA_LON_MIN || $longitude > TROLLEY_WA_LON_MAX) {
    trolley_send_json('error', 'Location must be within Western Australia.', 400);
  }

  if (!isset($input['timestamp']) || !is_string($input['timestamp']) || $input['timestamp'] === '') {
    trolley_send_json('error', 'Missing or invalid timestamp.', 400);
  }
  $timestamp = substr(trim($input['timestamp']), 0, TROLLEY_MAX_TIMESTAMP_LENGTH);

  if (!isset($input['brands']) || !is_array($input['brands']) || count($input['brands']) === 0) {
    trolley_send_json('error', 'Missing or invalid brand data.', 400);
  }

  $brands = [];
  foreach ($input['brands'] as $brand) {
    if (!is_string($brand)) {
      continue;
    }
    $brand = trim($brand);
    if (in_array($brand, TROLLEY_ALLOWED_BRANDS, true) && !in_array($brand, $brands, true)) {
      $brands[] = $brand;
    }
  }
  if (count($brands) === 0) {
    trolley_send_json('error', 'No valid brands supplied.', 400);
  }

  $conditions = [];
  if (isset($input['conditions']) && is_array($input['conditions'])) {
    foreach ($input['conditions'] as $brandKey => $condList) {
      if (!is_string($brandKey) || !in_array($brandKey, $brands, true)) {
        continue;
      }
      if (!is_array($condList)) {
        continue;
      }
      $normalized = [];
      foreach ($condList as $cond) {
        if (!is_string($cond)) {
          continue;
        }
        $cond = trim($cond);
        if (in_array($cond, TROLLEY_ALLOWED_CONDITIONS, true) && !in_array($cond, $normalized, true)) {
          $normalized[] = $cond;
        }
      }
      if (count($normalized) > 0) {
        $conditions[$brandKey] = $normalized;
      }
    }
  }

  $comments = '';
  if (isset($input['comments']) && is_string($input['comments'])) {
    $comments = mb_substr(trim($input['comments']), 0, TROLLEY_MAX_COMMENT_LENGTH);
  }

  $entry = [
    'latitude' => (string)$latitude,
    'longitude' => (string)$longitude,
    'timestamp' => $timestamp,
    'brands' => $brands,
    'conditions' => $conditions,
  ];

  if ($comments !== '') {
    $entry['comments'] = $comments;
  }

  if (isset($input['clientSubmissionTimestamp']) && is_string($input['clientSubmissionTimestamp'])) {
    $cst = trim($input['clientSubmissionTimestamp']);
    if ($cst !== '') {
      $entry['clientSubmissionTimestamp'] = mb_substr($cst, 0, TROLLEY_MAX_CLIENT_SUBMISSION_TS_LENGTH);
    }
  }

  return $entry;
}