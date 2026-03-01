<?php
declare(strict_types=1);

// Shared config + helpers for API endpoints

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

$BASE_URL = env('BASE_URL', 'https://box.shinederu.lol');
$AUTH_PORTAL_URL = env('AUTH_PORTAL_URL', 'https://auth.shinederu.lol');
$AUTH_API_BASE = env('AUTH_API_BASE', 'https://api.shinederu.lol/auth/');

$UPLOAD_DIR = env('UPLOAD_DIR', '/var/www/html/box.shinederu.lol/public/uploads');
$MAX_FILE_MB = (int)(env('MAX_FILE_MB', '20480'));
$ALLOWED_EXT = array_filter(array_map('trim', explode(',', env('ALLOWED_EXT', '.zip,.jar,.png,.jpg,.jpeg,.pdf,.rar'))));
$ALLOWED_MIME = array_filter(array_map('trim', explode(',', env('ALLOWED_MIME', 'application/zip,application/x-zip-compressed,application/x-zip,application/java-archive,application/x-java-archive,image/png,image/jpeg,application/pdf,application/vnd.rar,application/x-rar-compressed'))));

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
                FROM sessions s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.id = :sid
                  AND s.expires_at > NOW()
                LIMIT 1';
    } else {
        $sql = 'SELECT u.id, u.username, u.email, u.role, NULL AS is_admin
                FROM sessions s
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
    $isAdmin = to_bool($row['is_admin'] ?? null) || $role === 'admin';

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

function get_ext(string $name): string
{
    $ext = strtolower('.' . (pathinfo($name, PATHINFO_EXTENSION) ?: ''));
    return $ext === '.' ? '' : $ext;
}

function is_double_ext_danger(string $name): bool
{
    return (bool)preg_match('/\.(php|phtml|phar|pl|py|sh|exe|bat|cmd|com)(\..*)?$/i', $name);
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
    return sprintf('%s-%s.%s', $ts, $rand, $ext);
}

function storage_url(string $baseUrl, string $stored): string
{
    return rtrim($baseUrl, '/') . '/uploads/' . rawurlencode($stored);
}
