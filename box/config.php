<?php
declare(strict_types=1);

// Shared config + helpers for API endpoints
require_once __DIR__ . '/../core/services/ProjectAccessService.php';

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

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));

        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        if (getenv($key) !== false) {
            continue;
        }

        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}

function env(string $key, ?string $default = null): string
{
    $v = getenv($key);
    if ($v === false || $v === null || $v === '') {
        return $default ?? '';
    }

    return (string)$v;
}

function to_bool($value): bool
{
    if ($value === null) {
        return false;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (int)$value === 1;
    }

    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'on', 'admin'], true);
}

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$workspaceRoot = realpath(__DIR__ . '/../../../') ?: dirname(__DIR__, 3);

// Optional box-specific env (folder-local), then shared auth env as fallback.
load_dotenv(__DIR__ . '/.env');
load_dotenv($projectRoot . '/auth/.env');
// Extra fallback for multi-repo workspace path.
load_dotenv($workspaceRoot . '/API/auth/.env');

$BASE_URL = env('BASE_URL', 'https://box.shinederu.ch');
$AUTH_PORTAL_URL = env('AUTH_PORTAL_URL', 'https://auth.shinederu.ch');
$AUTH_API_BASE = env('AUTH_API_BASE', 'https://api.shinederu.ch/auth/');
$BOX_API_BASE = env('BOX_API_BASE', 'https://api.shinederu.ch/box');

$UPLOAD_DIR = env('UPLOAD_DIR', '/var/www/ShinedeBoxStorage/files');
$MAX_FILE_MB = (int)(env('MAX_FILE_MB', '20480'));
$ALLOWED_EXT = array_filter(array_map('trim', explode(',', env('ALLOWED_EXT', '*'))));
$BLOCKED_EXT = array_filter(array_map('trim', explode(',', env('BLOCKED_EXT', '.php,.phtml,.phar,.cgi,.pl,.py,.sh,.bash,.exe,.bat,.cmd,.com,.msi,.dll,.so'))));
$ALLOWED_MIME = array_filter(array_map('trim', explode(',', env('ALLOWED_MIME', '*'))));

$DB_TYPE = env('MQ_DB_TYPE', env('DB_TYPE', 'mysql'));
$DB_HOST = env('MQ_DB_HOST', env('DB_HOST', '127.0.0.1'));
$DB_NAME = env('MQ_DB_NAME', env('DB_NAME', 'ShinedeCore'));
$DB_USER = env('MQ_DB_USER', env('DB_USER', 'root'));
$DB_PASS = env('MQ_DB_PASS', env('DB_PASS', ''));
$DB_PORT = env('MQ_DB_PORT', env('DB_PORT', '3306'));

if (!str_starts_with($UPLOAD_DIR, DIRECTORY_SEPARATOR)) {
    $UPLOAD_DIR = $projectRoot . DIRECTORY_SEPARATOR . $UPLOAD_DIR;
}

if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0775, true);
}

function json_response(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_success(array $data = [], int $status = 200): void
{
    json_response($status, ['success' => true] + $data);
}

function json_error(string $message, int $status = 400, array $extra = []): void
{
    json_response($status, ['success' => false, 'error' => $message] + $extra);
}

function handle_api_exception(Throwable $exception): void
{
    $message = '[box] ' . get_class($exception) . ': ' . $exception->getMessage()
        . ' in ' . $exception->getFile() . ':' . $exception->getLine();
    error_log($message);
    box_error_log($message);

    if ($exception instanceof InvalidArgumentException) {
        json_error($exception->getMessage(), 400);
    }

    json_error('Erreur applicative Box.', 500);
}

function box_error_log(string $message): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = date('c') . ' ' . $message . PHP_EOL;
    @file_put_contents($dir . '/box.log', $line, FILE_APPEND | LOCK_EX);
}

function request_data(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    return $_POST;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $DB_TYPE, $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_PORT;

    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $DB_TYPE, $DB_HOST, $DB_PORT, $DB_NAME);
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function get_session_id(): ?string
{
    if (!empty($_COOKIE['sid'])) {
        return trim((string)$_COOKIE['sid']);
    }
    if (!empty($_COOKIE['session_id'])) {
        return trim((string)$_COOKIE['session_id']);
    }
    if (!empty($_SERVER['HTTP_X_SESSION_ID'])) {
        return trim((string)$_SERVER['HTTP_X_SESSION_ID']);
    }
    if (!empty($_SERVER['HTTP_SESSION_ID'])) {
        return trim((string)$_SERVER['HTTP_SESSION_ID']);
    }

    return null;
}

function users_has_is_admin_column(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'is_admin'
         LIMIT 1"
    );
    $hasColumn = (bool)$stmt->fetch();

    return $hasColumn;
}

function get_current_auth_state(): array
{
    global $AUTH_PORTAL_URL, $AUTH_API_BASE;

    $empty = [
        'authenticated' => false,
        'is_admin' => false,
        'user' => null,
        'login_url' => $AUTH_PORTAL_URL,
        'logout_url' => rtrim($AUTH_API_BASE, '/') . '/?action=logout',
    ];

    $sid = get_session_id();
    if (!$sid) {
        return $empty;
    }

    $pdo = db();

    $hasIsAdmin = users_has_is_admin_column($pdo);
    if ($hasIsAdmin) {
        $sql = 'SELECT u.id, u.username, u.email, u.role, u.is_admin
                FROM auth_sessions s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.id = :sid
                  AND s.expires_at > NOW()
                LIMIT 1';
    } else {
        $sql = 'SELECT u.id, u.username, u.email, u.role, NULL AS is_admin
                FROM auth_sessions s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.id = :sid
                  AND s.expires_at > NOW()
                LIMIT 1';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sid' => $sid]);
    $row = $stmt->fetch();

    if (!$row || empty($row['id'])) {
        return $empty;
    }

    $role = strtolower((string)($row['role'] ?? ''));
    $accessService = new ProjectAccessService($pdo);
    $isAdmin = $accessService->hasPermission((int)$row['id'], 'box', 'files.manage');

    return [
        'authenticated' => true,
        'is_admin' => $isAdmin,
        'user' => [
            'id' => (int)$row['id'],
            'username' => (string)($row['username'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'role' => $isAdmin ? 'admin' : ($role !== '' ? $role : 'user'),
            'is_admin' => $isAdmin,
        ],
        'login_url' => $AUTH_PORTAL_URL,
        'logout_url' => rtrim($AUTH_API_BASE, '/') . '/?action=logout',
    ];
}

function require_admin(): array
{
    $auth = get_current_auth_state();
    if (!$auth['authenticated']) {
        json_response(401, ['success' => false, 'error' => 'Non authentifie']);
    }
    if (!$auth['is_admin']) {
        json_response(403, ['success' => false, 'error' => 'Admin requis']);
    }

    return $auth;
}

function rate_limit(string $key, int $limit, int $windowSeconds): void
{
    $dir = __DIR__ . '/_ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $bucket = $dir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key . '_' . $ip) . '.json';
    $data = ['ts' => [], 'limit' => $limit, 'window' => $windowSeconds];

    if (is_file($bucket)) {
        $raw = @file_get_contents($bucket);
        if ($raw !== false) {
            $tmp = @json_decode($raw, true);
            if (is_array($tmp) && isset($tmp['ts']) && is_array($tmp['ts'])) {
                $data = $tmp;
            }
        }
    }

    $data['ts'] = array_values(array_filter($data['ts'], fn($t) => (int)$t > $now - $windowSeconds));
    if (count($data['ts']) >= $limit) {
        json_response(429, ['success' => false, 'error' => 'Trop de requetes, reessayez plus tard']);
    }

    $data['ts'][] = $now;
    @file_put_contents($bucket, json_encode($data));
}

function is_ascii_name(string $name): bool
{
    if ($name === '' || preg_match('/[\\\/\x00-\x1F\x7F]/', $name)) {
        return false;
    }

    return (bool)preg_match('/^[\x20-\x7E]+$/', $name);
}

function is_safe_display_name(string $name): bool
{
    $name = trim($name);
    if ($name === '' || strlen($name) > 255) {
        return false;
    }

    return !preg_match('/[\\\/\x00-\x1F\x7F]/u', $name);
}

function get_ext(string $name): string
{
    $ext = strtolower('.' . (pathinfo($name, PATHINFO_EXTENSION) ?: ''));
    return $ext === '.' ? '' : $ext;
}

function is_double_ext_danger(string $name): bool
{
    global $BLOCKED_EXT;

    $lower = strtolower($name);
    foreach ($BLOCKED_EXT as $blockedExt) {
        $blockedExt = strtolower(trim($blockedExt));
        if ($blockedExt === '') {
            continue;
        }
        if (!str_starts_with($blockedExt, '.')) {
            $blockedExt = '.' . $blockedExt;
        }
        if (str_ends_with($lower, $blockedExt) || str_contains($lower, $blockedExt . '.')) {
            return true;
        }
    }

    return false;
}

function is_extension_allowed(string $ext): bool
{
    global $ALLOWED_EXT, $BLOCKED_EXT;

    $ext = strtolower(trim($ext));
    if ($ext !== '' && !str_starts_with($ext, '.')) {
        $ext = '.' . $ext;
    }

    foreach ($BLOCKED_EXT as $blockedExt) {
        $blockedExt = strtolower(trim($blockedExt));
        if ($blockedExt !== '' && !str_starts_with($blockedExt, '.')) {
            $blockedExt = '.' . $blockedExt;
        }
        if ($ext !== '' && $ext === $blockedExt) {
            return false;
        }
    }

    if (in_array('*', $ALLOWED_EXT, true)) {
        return true;
    }

    return $ext !== '' && in_array($ext, array_map('strtolower', $ALLOWED_EXT), true);
}

function is_mime_allowed(string $mime): bool
{
    global $ALLOWED_MIME;

    if (in_array('*', $ALLOWED_MIME, true)) {
        return $mime !== '';
    }

    return $mime !== '' && in_array($mime, $ALLOWED_MIME, true);
}

function mime_of(string $tmp): string
{
    $f = new finfo(FILEINFO_MIME_TYPE);
    $m = $f->file($tmp) ?: '';
    return $m;
}

function unique_filename(string $ext): string
{
    $ts = date('Ymd-His');
    $rand = bin2hex(random_bytes(8));
    $ext = ltrim($ext, '.');
    if ($ext === '') {
        return sprintf('%s-%s', $ts, $rand);
    }

    return sprintf('%s-%s.%s', $ts, $rand, $ext);
}

function random_public_id(): string
{
    return rtrim(strtr(base64_encode(random_bytes(19)), '+/', '-_'), '=');
}

function random_share_token(): string
{
    return bin2hex(random_bytes(20));
}

function storage_path(string $stored): string
{
    global $UPLOAD_DIR;

    return rtrim($UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stored;
}

function api_url(string $path, array $query = []): string
{
    global $BOX_API_BASE;

    $base = $BOX_API_BASE;
    $url = rtrim($base, '/') . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function share_page_url(string $token): string
{
    global $BASE_URL;

    return rtrim($BASE_URL, '/') . '/?share=' . rawurlencode($token);
}
