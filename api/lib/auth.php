<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

/**
 * Start the PHP session with hardened cookie flags.
 * Secure flag is enabled outside dev; SameSite=Strict; HttpOnly always.
 */
function vg_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !vg_is_dev(); // localhost dev is plain http
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('VGSESSID');
    session_start();

    // Idle-timeout: destroy sessions unused for longer than configured.
    $timeout = (int) (vg_config()['security']['session_idle_timeout'] ?? 43200);
    $now = time();
    if (isset($_SESSION['last_seen']) && ($now - (int) $_SESSION['last_seen']) > $timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = $now;
}

/** Mark the session as authenticated for a user id (regenerates the session id). */
function vg_login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['last_seen'] = time();
}

/** Destroy the current session entirely. */
function vg_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Return the current user row, or null if not logged in. */
function vg_current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = vg_db()->prepare(
        'SELECT id, email, display_name, email_verified, notify_new_buddy, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $user = $row ?: null;
    return $user;
}

/** Require an authenticated + verified user, or send 401. Returns the user row. */
function vg_require_user(): array
{
    $user = vg_current_user();
    if (!$user) {
        vg_error('Authentication required.', 401, 'unauthenticated');
    }
    if ((int) $user['email_verified'] !== 1) {
        vg_error('Please verify your e-mail address first.', 403, 'unverified');
    }
    return $user;
}
