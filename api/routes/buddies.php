<?php
declare(strict_types=1);

/** Parse an optional ISO-ish datetime into 'Y-m-d H:i:s', or null. */
function vg_parse_datetime(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
}

/** Buddy request types and grill food choices (kept in sync with the frontend). */
const VG_BUDDY_TYPES = ['lunch', 'grill'];
const VG_GRILL_CHOICES = ['beef', 'pork', 'veg', 'other'];

/**
 * End of the current business day, i.e. today at 23:59:59 local time. Food-buddy
 * offers created today stay open for the rest of the day and expire overnight.
 */
function vg_end_of_business_day(): string
{
    return date('Y-m-d 23:59:59');
}

function vg_route_buddies_list(): void
{
    // Only offers that are open AND not past their expiry are listed.
    $now = date('Y-m-d H:i:s');
    $stmt = vg_db()->prepare(
        "SELECT b.id, b.type, b.title, b.craving, b.spot_id, b.desired_time, b.location_note,
                b.status, b.expires_at, b.created_at, b.user_id,
                u.display_name AS host_name,
                s.name         AS spot_name,
                (SELECT COUNT(*) FROM buddy_participants p WHERE p.request_id = b.id) AS participant_count
         FROM buddy_requests b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN food_spots s ON s.id = b.spot_id
         WHERE b.status = 'open' AND (b.expires_at IS NULL OR b.expires_at >= ?)
         ORDER BY b.created_at DESC"
    );
    $stmt->execute([$now]);

    $rows = array_map('vg_buddy_row_public', $stmt->fetchAll());
    vg_json(['buddies' => $rows]);
}

function vg_buddy_row_public(array $b): array
{
    $expiresAt = $b['expires_at'] ?? null;
    $isExpired = $expiresAt !== null && strtotime($expiresAt) < time();
    return [
        'id'                => (int) $b['id'],
        'type'              => $b['type'] ?? 'lunch',
        'title'             => $b['title'],
        'craving'           => $b['craving'],
        'spot_id'           => $b['spot_id'] !== null ? (int) $b['spot_id'] : null,
        'spot_name'         => $b['spot_name'] ?? null,
        'desired_time'      => $b['desired_time'],
        'location_note'     => $b['location_note'],
        'status'            => $b['status'],
        'expires_at'        => $expiresAt,
        'is_expired'        => $isExpired,
        'created_at'        => $b['created_at'],
        'host_id'           => (int) $b['user_id'],
        'host_name'         => $b['host_name'],
        'participant_count' => (int) ($b['participant_count'] ?? 0),
    ];
}

function vg_route_buddies_create(): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $data = vg_body();

    $title = vg_req_string($data, 'title', 3, 120);
    $craving = vg_opt_string($data, 'craving', 120);
    $locationNote = vg_opt_string($data, 'location_note', 160);
    $desiredTime = vg_parse_datetime($data['desired_time'] ?? null);

    $type = vg_opt_string($data, 'type', 16) ?? 'lunch';
    if (!in_array($type, VG_BUDDY_TYPES, true)) {
        $type = 'lunch';
    }
    // Offers expire at the end of the business day they are created.
    $expiresAt = vg_end_of_business_day();

    $spotId = null;
    if (isset($data['spot_id']) && is_numeric($data['spot_id'])) {
        $spotId = (int) $data['spot_id'];
        $chk = vg_db()->prepare('SELECT id FROM food_spots WHERE id = ?');
        $chk->execute([$spotId]);
        if (!$chk->fetch()) {
            $spotId = null;
        }
    }

    $db = vg_db();
    $ins = $db->prepare(
        'INSERT INTO buddy_requests (user_id, type, title, craving, spot_id, desired_time, location_note, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$user['id'], $type, $title, $craving, $spotId, $desiredTime, $locationNote, $expiresAt]);
    $newId = (int) $db->lastInsertId();

    // Host auto-joins their own request.
    $join = $db->prepare('INSERT INTO buddy_participants (request_id, user_id) VALUES (?, ?)');
    $join->execute([$newId, $user['id']]);

    vg_notify_new_buddy($user, $type, $title, $craving, $newId);

    vg_route_buddies_get((string) $newId);
}

function vg_route_buddies_get(string $id): void
{
    $id = (int) $id;
    $db = vg_db();

    $stmt = $db->prepare(
        "SELECT b.id, b.type, b.title, b.craving, b.spot_id, b.desired_time, b.location_note,
                b.status, b.expires_at, b.created_at, b.user_id,
                u.display_name AS host_name,
                s.name AS spot_name,
                (SELECT COUNT(*) FROM buddy_participants p WHERE p.request_id = b.id) AS participant_count
         FROM buddy_requests b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN food_spots s ON s.id = b.spot_id
         WHERE b.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }

    $pstmt = $db->prepare(
        'SELECT u.display_name AS name, p.created_at
         FROM buddy_participants p JOIN users u ON u.id = p.user_id
         WHERE p.request_id = ? ORDER BY p.created_at ASC'
    );
    $pstmt->execute([$id]);
    $participants = $pstmt->fetchAll();

    $qstmt = $db->prepare(
        'SELECT pr.id, pr.message, pr.spot_id, pr.created_at,
                u.display_name AS author, s.name AS spot_name
         FROM buddy_proposals pr
         JOIN users u ON u.id = pr.user_id
         LEFT JOIN food_spots s ON s.id = pr.spot_id
         WHERE pr.request_id = ? ORDER BY pr.created_at ASC'
    );
    $qstmt->execute([$id]);
    $proposals = $qstmt->fetchAll();

    $out = vg_buddy_row_public($row);
    $out['participants'] = array_map(static fn($p) => [
        'name' => $p['name'], 'joined_at' => $p['created_at'],
    ], $participants);
    $out['proposals'] = array_map(static fn($p) => [
        'id'         => (int) $p['id'],
        'message'    => $p['message'],
        'spot_id'    => $p['spot_id'] !== null ? (int) $p['spot_id'] : null,
        'spot_name'  => $p['spot_name'] ?? null,
        'author'     => $p['author'],
        'created_at' => $p['created_at'],
    ], $proposals);

    // Grill food orders (only meaningful for grill-type requests).
    $ostmt = $db->prepare(
        'SELECT o.choice, o.custom_text, o.bring_own, o.created_at, u.display_name AS name
         FROM grill_orders o JOIN users u ON u.id = o.user_id
         WHERE o.request_id = ? ORDER BY o.created_at ASC'
    );
    $ostmt->execute([$id]);
    $orders = $ostmt->fetchAll();

    $summary = ['beef' => 0, 'pork' => 0, 'veg' => 0, 'other' => 0, 'bring_own' => 0];
    $out['orders'] = array_map(static function ($o) use (&$summary) {
        $choice = in_array($o['choice'], VG_GRILL_CHOICES, true) ? $o['choice'] : 'other';
        $summary[$choice]++;
        if ((int) $o['bring_own'] === 1) {
            $summary['bring_own']++;
        }
        return [
            'name'        => $o['name'],
            'choice'      => $choice,
            'custom_text' => $o['custom_text'],
            'bring_own'   => (int) $o['bring_own'] === 1,
            'created_at'  => $o['created_at'],
        ];
    }, $orders);
    $out['order_summary'] = $summary;

    vg_json(['buddy' => $out]);
}

function vg_route_buddies_join(string $id): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $id = (int) $id;

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, user_id, title, status, expires_at FROM buddy_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }
    vg_assert_request_open($req);

    // Idempotent join (UNIQUE constraint guards duplicates).
    $sel = $db->prepare('SELECT id FROM buddy_participants WHERE request_id = ? AND user_id = ?');
    $sel->execute([$id, $user['id']]);
    if (!$sel->fetch()) {
        $ins = $db->prepare('INSERT INTO buddy_participants (request_id, user_id) VALUES (?, ?)');
        $ins->execute([$id, $user['id']]);
        vg_notify_host($id, (int) $req['user_id'], $user, 'joined your lunch plan', (string) $req['title']);
    }

    vg_route_buddies_get((string) $id);
}

function vg_route_buddies_propose(string $id): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $id = (int) $id;
    $data = vg_body();

    $message = vg_opt_string($data, 'message', 400);
    $spotId = null;
    if (isset($data['spot_id']) && is_numeric($data['spot_id'])) {
        $spotId = (int) $data['spot_id'];
    }
    if ($message === null && $spotId === null) {
        vg_error('Add a message or pick a spot to propose.', 422);
    }

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, user_id, title, status, expires_at FROM buddy_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }
    vg_assert_request_open($req);
    if ($spotId !== null) {
        $chk = $db->prepare('SELECT id FROM food_spots WHERE id = ?');
        $chk->execute([$spotId]);
        if (!$chk->fetch()) {
            $spotId = null;
        }
    }

    $ins = $db->prepare('INSERT INTO buddy_proposals (request_id, user_id, spot_id, message) VALUES (?, ?, ?, ?)');
    $ins->execute([$id, $user['id'], $spotId, $message]);

    vg_notify_host($id, (int) $req['user_id'], $user, 'made a suggestion on your lunch plan', (string) $req['title']);

    vg_route_buddies_get((string) $id);
}

function vg_route_buddies_close(string $id): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $id = (int) $id;

    $db = vg_db();
    $stmt = $db->prepare('SELECT user_id FROM buddy_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }
    if ((int) $req['user_id'] !== (int) $user['id']) {
        vg_error('Only the host can close this request.', 403, 'forbidden');
    }

    $upd = $db->prepare("UPDATE buddy_requests SET status = 'closed' WHERE id = ?");
    $upd->execute([$id]);

    vg_route_buddies_get((string) $id);
}

/** Reject requests that are closed or past their expiry (shared by join/propose/order). */
function vg_assert_request_open(array $req): void
{
    if (($req['status'] ?? '') !== 'open') {
        vg_error('This buddy request is closed.', 409, 'closed');
    }
    $expiresAt = $req['expires_at'] ?? null;
    if ($expiresAt !== null && strtotime($expiresAt) < time()) {
        vg_error('This offer has expired (offers end at the close of the business day).', 409, 'expired');
    }
}

/** Place or update the current user's grill food order, and join them in. */
function vg_route_buddies_order(string $id): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $id = (int) $id;
    $data = vg_body();

    $choice = vg_opt_string($data, 'choice', 16) ?? 'beef';
    if (!in_array($choice, VG_GRILL_CHOICES, true)) {
        vg_error('Pick a valid food option (beef, pork, veg or other).', 422);
    }
    $customText = vg_opt_string($data, 'custom_text', 120);
    if ($choice === 'other' && $customText === null) {
        vg_error('Describe what you want when choosing "other".', 422);
    }
    if ($choice !== 'other') {
        $customText = null; // custom text only applies to "other"
    }
    $bringOwn = !empty($data['bring_own']) ? 1 : 0;

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, user_id, type, title, status, expires_at FROM buddy_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }
    if (($req['type'] ?? 'lunch') !== 'grill') {
        vg_error('Food orders are only for grill offers.', 409, 'not_grill');
    }
    vg_assert_request_open($req);

    // Upsert the order (one per user per request).
    $sel = $db->prepare('SELECT id FROM grill_orders WHERE request_id = ? AND user_id = ?');
    $sel->execute([$id, $user['id']]);
    if ($existing = $sel->fetch()) {
        $upd = $db->prepare('UPDATE grill_orders SET choice = ?, custom_text = ?, bring_own = ? WHERE id = ?');
        $upd->execute([$choice, $customText, $bringOwn, (int) $existing['id']]);
    } else {
        $ins = $db->prepare(
            'INSERT INTO grill_orders (request_id, user_id, choice, custom_text, bring_own) VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$id, $user['id'], $choice, $customText, $bringOwn]);
        vg_notify_host($id, (int) $req['user_id'], $user, 'placed a grill order on your plan', (string) $req['title']);
    }

    // Ordering implies joining the grill.
    $psel = $db->prepare('SELECT id FROM buddy_participants WHERE request_id = ? AND user_id = ?');
    $psel->execute([$id, $user['id']]);
    if (!$psel->fetch()) {
        $pins = $db->prepare('INSERT INTO buddy_participants (request_id, user_id) VALUES (?, ?)');
        $pins->execute([$id, $user['id']]);
    }

    vg_route_buddies_get((string) $id);
}

/** E-mail all opted-in verified users (except the host) about a new lunch request. */
function vg_notify_new_buddy(array $host, string $type, string $title, ?string $craving, int $requestId): void
{
    $cfg = vg_config();
    $db = vg_db();
    $stmt = $db->prepare(
        'SELECT email, display_name FROM users
         WHERE email_verified = 1 AND notify_new_buddy = 1 AND id <> ?'
    );
    $stmt->execute([$host['id']]);

    $isGrill = $type === 'grill';
    $kind = $isGrill ? 'grill' : 'lunch plan';
    $action = $isGrill ? 'Join and place your food order' : 'Join or suggest a spot';
    $link = $cfg['app']['base_url'] . '/buddies?id=' . $requestId;
    $cravingLine = $craving ? "Craving: $craving\n" : '';
    foreach ($stmt->fetchAll() as $u) {
        vg_send_mail(
            $u['email'],
            $u['display_name'],
            $cfg['app']['name'] . ": new $kind — " . $title,
            "{$host['display_name']} started a new $kind:\n\n$title\n$cravingLine\n"
            . "$action:\n$link\n\n"
            . "(This offer expires at the end of the business day. "
            . "You can turn these notifications off in your profile.)"
        );
    }
}

/** E-mail the host that someone joined or made a suggestion. */
function vg_notify_host(int $requestId, int $hostId, array $actor, string $what, string $title): void
{
    if ($hostId === (int) $actor['id']) {
        return; // don't notify about your own action
    }
    $db = vg_db();
    $stmt = $db->prepare('SELECT email, display_name FROM users WHERE id = ?');
    $stmt->execute([$hostId]);
    $host = $stmt->fetch();
    if (!$host) {
        return;
    }

    $cfg = vg_config();
    $link = $cfg['app']['base_url'] . '/buddies?id=' . $requestId;
    vg_send_mail(
        $host['email'],
        $host['display_name'],
        $cfg['app']['name'] . ': update on "' . $title . '"',
        "{$actor['display_name']} $what \"$title\".\n\nView it here:\n$link"
    );
}
