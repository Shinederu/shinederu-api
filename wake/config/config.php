<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/request.php';

function load_dotenv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }

        $key = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) !== false) {
            continue;
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

function env(string $key, ?string $default = null): string
{
    $value = getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default ?? '';
    }

    return (string)$value;
}

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$workspaceRoot = realpath(__DIR__ . '/../../../') ?: dirname(__DIR__, 3);

load_dotenv(__DIR__ . '/../.env');
load_dotenv($projectRoot . '/auth/.env');
load_dotenv($workspaceRoot . '/API/auth/.env');
load_dotenv(__DIR__ . '/../.env.example');

define('BASE_URL', env('BASE_URL', 'https://wake.shinederu.ch'));
define('AUTH_API_BASE', env('AUTH_API_BASE', 'https://api.shinederu.ch/auth/'));
define('AUTH_PORTAL_URL', env('AUTH_PORTAL_URL', 'https://auth.shinederu.ch'));
define('WAKE_DEFAULT_BROADCAST', env('WAKE_DEFAULT_BROADCAST', '255.255.255.255'));
define('WAKE_DEFAULT_PORT', (int)env('WAKE_DEFAULT_PORT', '9'));

define('DB_TYPE', env('MQ_DB_TYPE', env('DB_TYPE', 'mysql')));
define('DB_HOST', env('MQ_DB_HOST', env('DB_HOST', '127.0.0.1')));
define('DB_PORT', env('MQ_DB_PORT', env('DB_PORT', '3306')));
define('DB_NAME', env('MQ_DB_NAME', env('DB_NAME', 'ShinedeCore')));
define('DB_USER', env('MQ_DB_USER', env('DB_USER', 'root')));
define('DB_PASS', env('MQ_DB_PASS', env('DB_PASS', '')));

function apply_cors(): void
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))));

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Session-Id, X-Session-Id');
    }

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
