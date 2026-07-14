<?php
declare(strict_types=1);

/**
 * Authentication routes: register, verify, login, logout, me, password reset.
 * Anti-enumeration: register / request-reset always return a generic success.
 */

const VG_VERIFY_TTL = 86400;   // 24h
const VG_RESET_TTL   = 3600;   // 1h

/** Hash a raw token for storage/lookup (never store the raw token). */
function vg_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function vg_route_register(): void
{
    vg_csrf_check();
    vg_rate_limit('register:' . vg_client_ip(), 10, 3600);

    $data = vg_body();
    $email = vg_req_email($data);
    $password = vg_req_password($data);
    $name = vg_req_string($data, 'display_name', 2, 80);

    $db = vg_db();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    $cfg = vg_config();
    if ($existing) {
        // Do not reveal that the account exists; nudge the real owner instead.
        vg_send_mail(
            $email,
            $name,
            $cfg['app']['name'] . ': account already exists',
            "Someone tried to register with this e-mail on {$cfg['app']['name']}.\n"
            . "You already have an account — just log in at {$cfg['app']['base_url']}/login.\n"
            . "If this wasn't you, you can safely ignore this message."
        );
        vg_json(['ok' => true, 'message' => 'Check your inbox to finish signing up.']);
    }

    $token = bin2hex(random_bytes(32));
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $expires = date('Y-m-d H:i:s', time() + VG_VERIFY_TTL);

    $ins = $db->prepare(
        'INSERT INTO users (email, password_hash, display_name, verify_token_hash, verify_expires)
         VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([$email, $hash, $name, vg_hash_token($token), $expires]);

    $link = $cfg['app']['base_url'] . '/verify?token=' . $token;
    vg_send_mail(
        $email,
        $name,
        'Welcome to ' . $cfg['app']['name'] . ' — confirm your e-mail',
        "Hi $name,\n\nConfirm your e-mail to activate your ViscosiGuide account:\n$link\n\n"
        . "This link expires in 24 hours."
    );

    vg_json(['ok' => true, 'message' => 'Check your inbox to finish signing up.']);
}

function vg_route_verify(): void
{
    $token = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';
    if ($token === '' || !ctype_xdigit($token)) {
        vg_error('Invalid verification link.', 400, 'bad_token');
    }

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, verify_expires FROM users WHERE verify_token_hash = ? AND email_verified = 0');
    $stmt->execute([vg_hash_token($token)]);
    $row = $stmt->fetch();

    if (!$row || strtotime((string) $row['verify_expires']) < time()) {
        vg_error('This verification link is invalid or has expired.', 400, 'bad_token');
    }

    $upd = $db->prepare('UPDATE users SET email_verified = 1, verify_token_hash = NULL, verify_expires = NULL WHERE id = ?');
    $upd->execute([$row['id']]);

    vg_json(['ok' => true, 'message' => 'Your e-mail is verified — you can now log in.']);
}

function vg_route_login(): void
{
    vg_csrf_check();
    $data = vg_body();
    $email = vg_req_email($data);
    $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';

    vg_rate_limit('login:' . vg_client_ip(), 15, 900);
    vg_rate_limit('login:' . $email, 8, 900);

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, password_hash, email_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Constant-ish work whether or not the user exists.
    $hash = $user['password_hash'] ?? '$argon2id$v=19$m=65536,t=4,p=1$aaaaaaaaaaaaaaaa$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $ok = password_verify($password, $hash);

    if (!$user || !$ok) {
        vg_error('Invalid e-mail or password.', 401, 'bad_credentials');
    }
    if ((int) $user['email_verified'] !== 1) {
        vg_error('Please verify your e-mail address before logging in.', 403, 'unverified');
    }

    if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
        $re = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $re->execute([password_hash($password, PASSWORD_ARGON2ID), $user['id']]);
    }

    vg_login_user((int) $user['id']);
    vg_json(['ok' => true, 'user' => vg_public_user(vg_current_user()), 'csrf' => vg_csrf_token()]);
}

function vg_route_logout(): void
{
    vg_csrf_check();
    vg_logout();
    vg_json(['ok' => true]);
}

function vg_route_me(): void
{
    $user = vg_current_user();
    vg_json([
        'user' => $user ? vg_public_user($user) : null,
        'csrf' => vg_csrf_token(),
    ]);
}

function vg_route_update_me(): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $data = vg_body();

    $name = vg_req_string($data, 'display_name', 2, 80);
    $notify = isset($data['notify_new_buddy']) ? (int) (bool) $data['notify_new_buddy'] : 1;

    $upd = vg_db()->prepare('UPDATE users SET display_name = ?, notify_new_buddy = ? WHERE id = ?');
    $upd->execute([$name, $notify, $user['id']]);

    vg_json(['ok' => true]);
}

function vg_route_request_reset(): void
{
    vg_csrf_check();
    vg_rate_limit('reset:' . vg_client_ip(), 10, 3600);

    $data = vg_body();
    $email = vg_req_email($data);

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, display_name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + VG_RESET_TTL);
        $upd = $db->prepare('UPDATE users SET reset_token_hash = ?, reset_expires = ? WHERE id = ?');
        $upd->execute([vg_hash_token($token), $expires, $user['id']]);

        $cfg = vg_config();
        $link = $cfg['app']['base_url'] . '/reset?token=' . $token;
        vg_send_mail(
            $email,
            (string) $user['display_name'],
            $cfg['app']['name'] . ': reset your password',
            "A password reset was requested for your account.\nReset it here (valid 1 hour):\n$link\n\n"
            . "If you didn't request this, you can ignore this e-mail."
        );
    }

    // Always generic — never reveal whether the address is registered.
    vg_json(['ok' => true, 'message' => 'If that address is registered, a reset link is on its way.']);
}

function vg_route_reset(): void
{
    vg_csrf_check();
    $data = vg_body();
    $token = isset($data['token']) && is_string($data['token']) ? trim($data['token']) : '';
    $password = vg_req_password($data);

    if ($token === '' || !ctype_xdigit($token)) {
        vg_error('Invalid reset link.', 400, 'bad_token');
    }

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, reset_expires FROM users WHERE reset_token_hash = ?');
    $stmt->execute([vg_hash_token($token)]);
    $user = $stmt->fetch();

    if (!$user || strtotime((string) $user['reset_expires']) < time()) {
        vg_error('This reset link is invalid or has expired.', 400, 'bad_token');
    }

    $upd = $db->prepare(
        'UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL, email_verified = 1 WHERE id = ?'
    );
    $upd->execute([password_hash($password, PASSWORD_ARGON2ID), $user['id']]);

    vg_json(['ok' => true, 'message' => 'Password updated — you can now log in.']);
}

/** Shape a user row for public output (no secrets). */
function vg_public_user(array $u): array
{
    return [
        'id'               => (int) $u['id'],
        'email'            => $u['email'],
        'display_name'     => $u['display_name'],
        'email_verified'   => (bool) $u['email_verified'],
        'notify_new_buddy' => (bool) ($u['notify_new_buddy'] ?? true),
    ];
}
