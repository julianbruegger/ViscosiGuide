<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

/** Return the session CSRF token, creating one if needed. */
function vg_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Verify the CSRF token sent by the client on state-changing requests.
 * Token is read from the X-CSRF-Token header and compared in constant time.
 */
function vg_csrf_check(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
        vg_error('Invalid or missing CSRF token.', 403, 'csrf');
    }
}
