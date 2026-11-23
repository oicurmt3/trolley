<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/http_response.php';

/**
 * Append one log entry to trolley_logs.json with exclusive file lock.
 *
 * @param array<string, mixed> $entry
 */
function trolley_append_log(string $webRoot, array $entry): void
{
  $logPath = $webRoot . '/' . TROLLEY_JSON_LOG_FILE;

  if (!is_writable($webRoot)) {
    error_log('Trolley: web root not writable: ' . $webRoot);
    trolley_send_json('error', 'Server configuration error.', 500);
  }

  if (is_file($logPath) && (!is_readable($logPath) || !is_writable($logPath))) {
    error_log('Trolley: log file not readable/writable: ' . $logPath);
    trolley_send_json('error', 'Server configuration error.', 500);
  }

  $fh = fopen($logPath, 'c+');
  if ($fh === false) {
    error_log('Trolley: cannot open log file: ' . $logPath);
    trolley_send_json('error', 'Server error.', 500);
  }

  $success = false;
  if (flock($fh, LOCK_EX)) {
    rewind($fh);
    $existing = stream_get_contents($fh);
    $logs = [];
    if ($existing !== false && strlen($existing) > 0) {
      $decoded = json_decode($existing, true);
      if (is_array($decoded)) {
        $logs = $decoded;
      } else {
        error_log('Trolley: log file JSON corrupt; appending to empty array');
      }
    }
    $logs[] = $entry;
    $encoded = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
      ftruncate($fh, 0);
      rewind($fh);
      if (fwrite($fh, $encoded) !== false) {
        fflush($fh);
        $success = true;
      }
    }
    flock($fh, LOCK_UN);
  } else {
    error_log('Trolley: could not lock log file');
  }
  fclose($fh);

  if (!$success) {
    trolley_send_json('error', 'Server error.', 500);
  }
}