<?php
declare(strict_types=1);

function admin_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'POST required for this action.']);
        exit;
    }
}

/**
 * @return array<string, mixed>
 */
function admin_read_json_body(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $cached = [];
        return $cached;
    }
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : [];
    return $cached;
}