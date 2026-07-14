<?php
declare(strict_types=1);

/** Allowed food-spot categories (kept in sync with the frontend). */
const VG_CATEGORIES = [
    'canteen', 'restaurant', 'fastfood', 'italian', 'asian',
    'bakery', 'cafe', 'bar', 'vegetarian', 'other',
];

/** SQL fragment selecting a spot plus its aggregate ratings. */
function vg_spot_select(): string
{
    return
        'SELECT s.id, s.name, s.description, s.category, s.lat, s.lng, s.address,
                s.price_level, s.created_by, s.created_at,
                u.display_name AS created_by_name,
                COUNT(r.id)            AS rating_count,
                AVG(r.rating)         AS avg_rating,
                AVG(r.price_rating)   AS avg_price,
                AVG(r.bang_for_buck)  AS avg_bang
         FROM food_spots s
         LEFT JOIN users u   ON u.id = s.created_by
         LEFT JOIN ratings r ON r.spot_id = s.id';
}

/** Normalise a raw spot row (with aggregates) into the public JSON shape. */
function vg_spot_public(array $row): array
{
    return [
        'id'             => (int) $row['id'],
        'name'           => $row['name'],
        'description'    => $row['description'],
        'category'       => $row['category'],
        'lat'            => (float) $row['lat'],
        'lng'            => (float) $row['lng'],
        'address'        => $row['address'],
        'price_level'    => (int) $row['price_level'],
        'created_by'     => $row['created_by'] !== null ? (int) $row['created_by'] : null,
        'created_by_name' => $row['created_by_name'],
        'created_at'     => $row['created_at'],
        'rating_count'   => (int) $row['rating_count'],
        'avg_rating'     => $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 2) : null,
        'avg_price'      => $row['avg_price'] !== null ? round((float) $row['avg_price'], 2) : null,
        'avg_bang'       => $row['avg_bang'] !== null ? round((float) $row['avg_bang'], 2) : null,
    ];
}

function vg_route_spots_list(): void
{
    $db = vg_db();
    $sql = vg_spot_select() . " WHERE s.status = 'active'";
    $params = [];

    // Optional bounding-box filter: ?bbox=minLng,minLat,maxLng,maxLat
    if (isset($_GET['bbox']) && is_string($_GET['bbox'])) {
        $parts = array_map('floatval', explode(',', $_GET['bbox']));
        if (count($parts) === 4) {
            [$minLng, $minLat, $maxLng, $maxLat] = $parts;
            $sql .= ' AND s.lat BETWEEN ? AND ? AND s.lng BETWEEN ? AND ?';
            array_push($params, $minLat, $maxLat, $minLng, $maxLng);
        }
    }

    $sql .= ' GROUP BY s.id, s.name, s.description, s.category, s.lat, s.lng, s.address,
                       s.price_level, s.created_by, s.created_at, u.display_name
              ORDER BY s.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $spots = array_map('vg_spot_public', $stmt->fetchAll());
    vg_json(['spots' => $spots]);
}

function vg_route_spots_get(string $id): void
{
    $stmt = vg_db()->prepare(vg_spot_select() . ' WHERE s.id = ? GROUP BY s.id, s.name, s.description,
        s.category, s.lat, s.lng, s.address, s.price_level, s.created_by, s.created_at, u.display_name');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    if (!$row) {
        vg_error('Spot not found.', 404, 'not_found');
    }
    vg_json(['spot' => vg_spot_public($row)]);
}

function vg_route_spots_create(): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $data = vg_body();

    $name = vg_req_string($data, 'name', 2, 120);
    $lat = vg_req_float($data, 'lat', -90, 90);
    $lng = vg_req_float($data, 'lng', -180, 180);
    $category = vg_opt_string($data, 'category', 40) ?? 'other';
    if (!in_array($category, VG_CATEGORIES, true)) {
        $category = 'other';
    }
    $priceLevel = vg_req_int($data, 'price_level', 1, 4);
    $description = vg_opt_string($data, 'description', 2000);
    $address = vg_opt_string($data, 'address', 255);

    $ins = vg_db()->prepare(
        'INSERT INTO food_spots (name, description, category, lat, lng, address, price_level, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$name, $description, $category, $lat, $lng, $address, $priceLevel, $user['id']]);
    $newId = (int) vg_db()->lastInsertId();

    vg_route_spots_get((string) $newId);
}

function vg_route_spots_update(string $id): void
{
    vg_csrf_check();
    $user = vg_require_user();
    $data = vg_body();

    $db = vg_db();
    $stmt = $db->prepare('SELECT created_by FROM food_spots WHERE id = ?');
    $stmt->execute([(int) $id]);
    $spot = $stmt->fetch();
    if (!$spot) {
        vg_error('Spot not found.', 404, 'not_found');
    }
    if ((int) $spot['created_by'] !== (int) $user['id']) {
        vg_error('You can only edit spots you created.', 403, 'forbidden');
    }

    $name = vg_req_string($data, 'name', 2, 120);
    $category = vg_opt_string($data, 'category', 40) ?? 'other';
    if (!in_array($category, VG_CATEGORIES, true)) {
        $category = 'other';
    }
    $priceLevel = vg_req_int($data, 'price_level', 1, 4);
    $description = vg_opt_string($data, 'description', 2000);
    $address = vg_opt_string($data, 'address', 255);

    $upd = $db->prepare(
        'UPDATE food_spots SET name = ?, description = ?, category = ?, address = ?, price_level = ? WHERE id = ?'
    );
    $upd->execute([$name, $description, $category, $address, $priceLevel, (int) $id]);

    vg_route_spots_get($id);
}
