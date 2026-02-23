<?php

/**
 * Front controller for API host fallback routing.
 *
 * Nginx currently uses: try_files $uri $uri/ /index.php?$query_string;
 * This file ensures requests are forwarded to the proper backend even
 * when the incoming path does not directly resolve as expected.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$normalized = '/' . ltrim($path, '/');

if (preg_match('#^/auth(?:/|$)#', $normalized) === 1) {
    require __DIR__ . '/auth/index.php';
    exit;
}

if (preg_match('#^/(melodyquest|melody)(?:/|$)#', $normalized) === 1) {
    require __DIR__ . '/melodyquest/index.php';
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Unknown API endpoint',
]);

