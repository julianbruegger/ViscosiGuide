<?php
declare(strict_types=1);

function vg_route_ratings_list(string $spotId): void
{
    $stmt = vg_db()->prepare(
        'SELECT r.id, r.rating, r.price_rating, r.bang_for_buck, r.comment, r.created_at,
                u.display_name AS author
         FROM ratings r
         JOIN users u ON u.id = r.user_id
         WHERE r.spot_id = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([(int) $spotId]);

    $ratings = array_map(static function (array $r): array {
        return [
            'id'            => (int) $r['id'],
            'rating'        => (int) $r['rating'],
            'price_rating'  => (int) $r['price_rating'],
            'bang_for_buck' => (int) $r['bang_for_buck'],
            'comment'       => $r['comment'],
            'author'        => $r['author'],
            'created_at'    => $r['created_at'],
        ];
    }, $stmt->fetchAll());

    vg_json(['ratings' => $ratings]);
}

/**
 * Create or update the current user's rating for a spot (one rating per user).
 * Done as SELECT-then-UPDATE/INSERT in a transaction so it is driver-agnostic.
 */
function vg_route_ratings_upsert(string $spotId): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $data = vg_body();

    $spotId = (int) $spotId;
    $rating = vg_req_int($data, 'rating', 1, 5);
    $priceRating = vg_req_int($data, 'price_rating', 1, 5);
    $bang = vg_req_int($data, 'bang_for_buck', 1, 5);
    $comment = vg_opt_string($data, 'comment', 600);

    $db = vg_db();
    $check = $db->prepare('SELECT id FROM food_spots WHERE id = ?');
    $check->execute([$spotId]);
    if (!$check->fetch()) {
        vg_error('Spot not found.', 404, 'not_found');
    }

    $db->beginTransaction();
    try {
        $sel = $db->prepare('SELECT id FROM ratings WHERE spot_id = ? AND user_id = ?');
        $sel->execute([$spotId, $user['id']]);
        $existing = $sel->fetch();

        if ($existing) {
            $upd = $db->prepare(
                'UPDATE ratings SET rating = ?, price_rating = ?, bang_for_buck = ?, comment = ? WHERE id = ?'
            );
            $upd->execute([$rating, $priceRating, $bang, $comment, $existing['id']]);
        } else {
            $ins = $db->prepare(
                'INSERT INTO ratings (spot_id, user_id, rating, price_rating, bang_for_buck, comment)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$spotId, $user['id'], $rating, $priceRating, $bang, $comment]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    vg_route_ratings_list((string) $spotId);
}
