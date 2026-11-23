<?php
declare(strict_types=1);

/**
 * Send a JSON API response and exit. Never exposes internal log entries on errors.
 */
function trolley_send_json(string $status, string $message, int $httpStatus = 200, ?array $logData = null): void
{
  http_response_code($httpStatus);
  $response = ['status' => $status, 'message' => $message];
  if ($logData !== null && $status === 'success') {
    $response['logData'] = $logData;
  }
  echo json_encode($response, JSON_UNESCAPED_SLASHES);
  exit;
}