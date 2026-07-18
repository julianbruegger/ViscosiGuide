<?php
declare(strict_types=1);

/**
 * ViscosiGuide API front controller.
 * All /api/* requests are routed here. Emits JSON only.
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/response.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/ratelimit.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/spots.php';
require_once __DIR__ . '/routes/ratings.php';
require_once __DIR__ . '/routes/buddies.php';

// --- Baseline security headers for every API response --------------------------
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header_remove('X-Powered-By');

// --- Global error handling: never leak internals to the client -----------------
set_exception_handler(function (\Throwable $e): void {
    error_log('[ViscosiGuide] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $msg = vg_is_dev() ? $e->getMessage() : 'Internal server error.';
    echo json_encode(['error' => ['message' => $msg, 'code' => '500']]);
    exit;
});

vg_session_start();

// --- Resolve the route path (portion after "/api") -----------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri = rawurldecode($uri);
$path = preg_replace('#^.*?/api#', '', $uri);   // strip everything up to and incl. /api
$path = '/' . trim((string) $path, '/');         // normalise → "/auth/login", "/spots/3", ...

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Route table: [method, regex, handler] -------------------------------------
$routes = [
    ['POST', '#^/auth/register$#',        'vg_route_register'],
    ['GET',  '#^/auth/verify$#',          'vg_route_verify'],
    ['POST', '#^/auth/login$#',           'vg_route_login'],
    ['POST', '#^/auth/logout$#',          'vg_route_logout'],
    ['GET',  '#^/auth/me$#',              'vg_route_me'],
    ['POST', '#^/auth/request-reset$#',   'vg_route_request_reset'],
    ['POST', '#^/auth/reset$#',           'vg_route_reset'],
    ['PATCH','#^/me$#',                   'vg_route_update_me'],

    ['GET',  '#^/spots$#',                'vg_route_spots_list'],
    ['POST', '#^/spots$#',                'vg_route_spots_create'],
    ['GET',  '#^/spots/(\d+)$#',          'vg_route_spots_get'],
    ['PATCH','#^/spots/(\d+)$#',          'vg_route_spots_update'],

    ['GET',  '#^/spots/(\d+)/ratings$#',  'vg_route_ratings_list'],
    ['POST', '#^/spots/(\d+)/ratings$#',  'vg_route_ratings_upsert'],

    ['GET',  '#^/buddies$#',              'vg_route_buddies_list'],
    ['POST', '#^/buddies$#',              'vg_route_buddies_create'],
    ['GET',  '#^/buddies/(\d+)$#',        'vg_route_buddies_get'],
    ['POST', '#^/buddies/(\d+)/join$#',   'vg_route_buddies_join'],
    ['POST', '#^/buddies/(\d+)/order$#',  'vg_route_buddies_order'],
    ['POST', '#^/buddies/(\d+)/propose$#','vg_route_buddies_propose'],
    ['POST', '#^/buddies/(\d+)/close$#',  'vg_route_buddies_close'],
];

foreach ($routes as [$m, $regex, $handler]) {
    if ($m === $method && preg_match($regex, $path, $matches)) {
        $args = array_slice($matches, 1);
        $handler(...$args);
        exit;
    }
}

// Distinguish "wrong method" from "unknown route" for clearer client errors.
foreach ($routes as [$m, $regex]) {
    if (preg_match($regex, $path)) {
        vg_error('Method not allowed.', 405, 'method_not_allowed');
    }
}
vg_error('Not found.', 404, 'not_found');
