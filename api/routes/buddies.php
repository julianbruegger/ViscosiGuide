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

function vg_route_buddies_list(): void
{
    $stmt = vg_db()->query(
        "SELECT b.id, b.title, b.craving, b.spot_id, b.desired_time, b.location_note,
                b.status, b.created_at, b.user_id,
                u.display_name AS host_name,
                s.name         AS spot_name,
                (SELECT COUNT(*) FROM buddy_participants p WHERE p.request_id = b.id) AS participant_count
         FROM buddy_requests b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN food_spots s ON s.id = b.spot_id
         WHERE b.status = 'open'
         ORDER BY b.created_at DESC"
    );

    $rows = array_map('vg_buddy_row_public', $stmt->fetchAll());
    vg_json(['buddies' => $rows]);
}

function vg_buddy_row_public(array $b): array
{
    return [
        'id'                => (int) $b['id'],
        'title'             => $b['title'],
        'craving'           => $b['craving'],
        'spot_id'           => $b['spot_id'] !== null ? (int) $b['spot_id'] : null,
        'spot_name'         => $b['spot_name'] ?? null,
        'desired_time'      => $b['desired_time'],
        'location_note'     => $b['location_note'],
        'status'            => $b['status'],
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
        'INSERT INTO buddy_requests (user_id, title, craving, spot_id, desired_time, location_note)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$user['id'], $title, $craving, $spotId, $desiredTime, $locationNote]);
    $newId = (int) $db->lastInsertId();

    // Host auto-joins their own request.
    $join = $db->prepare('INSERT INTO buddy_participants (request_id, user_id) VALUES (?, ?)');
    $join->execute([$newId, $user['id']]);

    vg_notify_new_buddy($user, $title, $craving, $newId);

    vg_route_buddies_get((string) $newId);
}

function vg_route_buddies_get(string $id): void
{
    $id = (int) $id;
    $db = vg_db();

    $stmt = $db->prepare(
        "SELECT b.id, b.title, b.craving, b.spot_id, b.desired_time, b.location_note,
                b.status, b.created_at, b.user_id,
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

    vg_json(['buddy' => $out]);
}

function vg_route_buddies_join(string $id): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $id = (int) $id;

    $db = vg_db();
    $stmt = $db->prepare('SELECT id, user_id, title, status FROM buddy_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }
    if ($req['status'] !== 'open') {
        vg_error('This buddy request is closed.', 409, 'closed');
    }

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
    $stmt = $db->prepare('SELECT id, user_id, title, status FROM buddy_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        vg_error('Buddy request not found.', 404, 'not_found');
    }
    if ($req['status'] !== 'open') {
        vg_error('This buddy request is closed.', 409, 'closed');
    }
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

/** E-mail all opted-in verified users (except the host) about a new lunch request. */
function vg_notify_new_buddy(array $host, string $title, ?string $craving, int $requestId): void
{
    $cfg = vg_config();
    $db = vg_db();
    $stmt = $db->prepare(
        'SELECT email, display_name FROM users
         WHERE email_verified = 1 AND notify_new_buddy = 1 AND id <> ?'
    );
    $stmt->execute([$host['id']]);

    $link = $cfg['app']['base_url'] . '/buddies?id=' . $requestId;
    $cravingLine = $craving ? "Craving: $craving\n" : '';
    foreach ($stmt->fetchAll() as $u) {
        vg_send_mail(
            $u['email'],
            $u['display_name'],
            $cfg['app']['name'] . ': new lunch plan — ' . $title,
            "{$host['display_name']} started a new lunch plan:\n\n$title\n$cravingLine\n"
            . "Join or suggest a spot:\n$link\n\n"
            . "(You can turn these notifications off in your profile.)"
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
