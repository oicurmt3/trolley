<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/**
 * Apply CORS headers for API responses when the request Origin is allowed.
 */
function trolley_apply_cors(): void
{
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  header('Content-Type: application/json');

  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if ($origin !== '' && in_array($origin, TROLLEY_ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
  }
}