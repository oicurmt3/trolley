<?php
/**
 * Disabled: brand portal tokens must not be exposed to public visitors.
 * Portal URLs are shared directly with brand contacts via the admin panel only.
 *
 * Previously this endpoint returned tokenised portal URLs to index.html for
 * reporter-composed emails. That leaked secrets to any visitor.
 */
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
http_response_code(403);
echo json_encode(['error' => 'Forbidden']);