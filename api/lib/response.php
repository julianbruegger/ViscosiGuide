<?php
declare(strict_types=1);

/** Send a JSON response and stop. */
function vg_json($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Send a JSON error envelope { error: { message, code } } and stop. */
function vg_error(string $message, int $status = 400, ?string $code = null): never
{
    vg_json(['error' => ['message' => $message, 'code' => $code ?? (string) $status]], $status);
}

/**
 * Decode a JSON request body into an array. Rejects oversized or malformed input.
 */
function vg_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    if (strlen($raw) > 64 * 1024) {
        vg_error('Request body too large.', 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        vg_error('Invalid JSON body.', 400);
    }
    return $data;
}
