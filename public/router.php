<?php
// Router for PHP built-in web server to enable pretty URLs.
// Usage: php -S localhost:8000 -t public public/router.php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = realpath(__DIR__ . $uri);

// If the requested file exists under public/, let the server handle it
if ($path && strpos($path, realpath(__DIR__)) === 0 && is_file($path)) {
    return false;
}

// Otherwise, delegate to the front controller
require __DIR__ . '/index.php';

