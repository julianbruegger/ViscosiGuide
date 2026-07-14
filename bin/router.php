<?php
declare(strict_types=1);

/**
 * Dev router for the PHP built-in server. Mirrors the production Apache setup:
 *   - /api/*  → the PHP API front controller (api/index.php)
 *   - anything else → static files from the Astro build (frontend/dist)
 *
 * Usage:
 *   php -S localhost:8000 -t frontend/dist bin/router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// API requests → front controller.
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/../api/index.php';
    return true;
}

$docroot = $_SERVER['DOCUMENT_ROOT'] ?: (__DIR__ . '/../frontend/dist');
$path = realpath($docroot . $uri);

// Serve an existing static file directly.
if ($path && is_file($path) && str_starts_with($path, realpath($docroot))) {
    return false;
}

// Try directory index (Astro builds /page/index.html).
foreach ([$uri . '/index.html', $uri . '.html'] as $candidate) {
    $try = realpath($docroot . $candidate);
    if ($try && is_file($try) && str_starts_with($try, realpath($docroot))) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($try);
        return true;
    }
}

// Fallback to the home page.
$home = $docroot . '/index.html';
if (is_file($home)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($home);
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
