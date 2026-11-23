<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/http_response.php';

/**
 * File-based per-IP rate limiting. IPs are hashed (SHA-256); only hashes are stored on disk.
 */
function trolley_rate_limit_check(string $webRoot): void
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if ($ip === '' || $ip === 'Unknown') {
    return;
  }

  $hash = hash('sha256', $ip);
  $dir = $webRoot . '/data/rate_limit';
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  if (!is_dir($dir) || !is_writable($dir)) {
    error_log('Trolley rate limit: data/rate_limit not writable');
    trolley_send_json('error', 'Server configuration error.', 500);
  }

  trolley_rate_limit_prune($dir);

  $bucketFile = $dir . '/' . $hash . '.json';
  $now = time();
  $windowStart = $now - TROLLEY_RATE_LIMIT_WINDOW_SECONDS;

  $count = 0;
  $firstSeen = $now;
  if (is_file($bucketFile)) {
    $raw = @file_get_contents($bucketFile);
    if ($raw !== false) {
      $data = json_decode($raw, true);
      if (is_array($data)) {
        $count = (int)($data['count'] ?? 0);
        $firstSeen = (int)($data['window_start'] ?? $now);
        if ($firstSeen < $windowStart) {
          $count = 0;
          $firstSeen = $now;
        }
      }
    }
  }

  if ($count >= TROLLEY_RATE_LIMIT_MAX_REQUESTS) {
    trolley_send_json('error', 'Too many submissions from your network. Please try again later.', 429);
  }

  $count++;
  $payload = json_encode([
    'window_start' => $firstSeen,
    'count' => $count,
    'updated_at' => $now,
  ], JSON_UNESCAPED_SLASHES);

  $fh = @fopen($bucketFile, 'c');
  if ($fh === false) {
    error_log('Trolley rate limit: cannot open bucket ' . $bucketFile);
    return;
  }
  if (flock($fh, LOCK_EX)) {
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, $payload !== false ? $payload : '{"count":1}');
    fflush($fh);
    flock($fh, LOCK_UN);
  }
  fclose($fh);
}

function trolley_rate_limit_prune(string $dir): void
{
  $cutoff = time() - TROLLEY_RATE_LIMIT_RETENTION_SECONDS;
  foreach (glob($dir . '/*.json') ?: [] as $file) {
    if (@filemtime($file) !== false && filemtime($file) < $cutoff) {
      @unlink($file);
    }
  }
}