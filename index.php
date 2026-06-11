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

function arcadiaFallbackEnv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[$key] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $env;
}

function arcadiaFallbackPdo(): PDO
{
    $env = arcadiaFallbackEnv(__DIR__ . '/arcadia/.env')
        ?: arcadiaFallbackEnv(__DIR__ . '/box/.env')
        ?: arcadiaFallbackEnv(__DIR__ . '/auth/.env');

    if (isset($env['DATABASE_URL'])) {
        $url = parse_url($env['DATABASE_URL']);
        if ($url === false || !isset($url['host'], $url['user'], $url['pass'], $url['path'])) {
            throw new RuntimeException('Invalid Arcadia DATABASE_URL');
        }

        $host = $url['host'];
        $port = (int) ($url['port'] ?? 3306);
        $name = rawurldecode(ltrim($url['path'], '/'));
        $user = rawurldecode($url['user']);
        $pass = rawurldecode($url['pass']);
    } else {
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredKey) {
            if (!isset($env[$requiredKey])) {
                throw new RuntimeException('Missing Arcadia database configuration');
            }
        }

        $host = $env['DB_HOST'];
        $port = (int) ($env['DB_PORT'] ?? 3306);
        $name = $env['DB_NAME'];
        $user = $env['DB_USER'];
        $pass = $env['DB_PASS'];
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function arcadiaFallbackNormalizeServer(array $server, array $resources): array
{
    return [
        'id' => (int) $server['id'],
        'slug' => $server['slug'],
        'name' => $server['name'],
        'description' => $server['description'],
        'publicAddress' => $server['public_address'],
        'host' => $server['host'],
        'port' => $server['port'] !== null ? (int) $server['port'] : null,
        'queryProvider' => $server['query_provider'],
        'mapUrl' => $server['map_url'],
        'websiteUrl' => $server['website_url'],
        'visibility' => $server['visibility'],
        'game' => [
            'id' => (int) $server['game_id'],
            'code' => $server['game_code'],
            'name' => $server['game_name'],
            'description' => $server['game_description'],
            'iconUrl' => $server['game_icon_url'],
            'color' => $server['game_color'],
        ],
        'resources' => $resources[(int) $server['id']] ?? [],
    ];
}

function arcadiaFallbackResources(PDO $pdo, array $serverIds): array
{
    if ($serverIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($serverIds), '?'));
    $statement = $pdo->prepare("
        SELECT id, server_id, resource_type, label, url, payload
        FROM arcadia_server_resources
        WHERE enabled = 1 AND server_id IN ($placeholders)
        ORDER BY sort_order ASC, label ASC
    ");
    $statement->execute($serverIds);

    $resources = [];
    foreach ($statement->fetchAll() as $row) {
        $payload = null;
        if ($row['payload'] !== null && $row['payload'] !== '') {
            $decodedPayload = json_decode($row['payload'], true);
            $payload = is_array($decodedPayload) ? $decodedPayload : null;
        }

        $resources[(int) $row['server_id']][] = [
            'id' => (int) $row['id'],
            'type' => $row['resource_type'],
            'label' => $row['label'],
            'url' => $row['url'],
            'payload' => $payload,
        ];
    }

    return $resources;
}

function arcadiaFallbackJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function arcadiaFallbackServerCatalog(?string $slug): void
{
    try {
        $pdo = arcadiaFallbackPdo();
        $params = [];
        $slugFilter = '';

        if ($slug !== null && $slug !== '') {
            $slugFilter = ' AND s.slug = :slug';
            $params['slug'] = $slug;
        }

        $statement = $pdo->prepare("
            SELECT
                s.id,
                s.slug,
                s.name,
                s.description,
                s.public_address,
                s.host,
                s.port,
                s.query_provider,
                s.map_url,
                s.website_url,
                s.visibility,
                g.id AS game_id,
                g.code AS game_code,
                g.name AS game_name,
                g.description AS game_description,
                g.icon_url AS game_icon_url,
                g.color AS game_color
            FROM arcadia_servers s
            INNER JOIN arcadia_games g ON g.id = s.game_id
            WHERE s.enabled = 1 AND s.visibility = 'public'$slugFilter
            ORDER BY s.sort_order ASC, s.name ASC
        ");
        $statement->execute($params);
        $servers = $statement->fetchAll();

        if ($slug !== null && $servers === []) {
            arcadiaFallbackJson(['message' => 'Serveur introuvable'], 404);
            return;
        }

        $resources = arcadiaFallbackResources($pdo, array_map(fn (array $server): int => (int) $server['id'], $servers));
        $normalizedServers = array_map(
            fn (array $server): array => arcadiaFallbackNormalizeServer($server, $resources),
            $servers
        );

        if ($slug !== null) {
            arcadiaFallbackJson(['server' => $normalizedServers[0]]);
            return;
        }

        arcadiaFallbackJson(['servers' => $normalizedServers]);
    } catch (Throwable) {
        arcadiaFallbackJson(['message' => 'Impossible de charger le catalogue Arcadia'], 500);
    }
}

if (preg_match('#^/auth(?:/|$)#', $normalized) === 1) {
    require __DIR__ . '/auth/index.php';
    exit;
}

if (preg_match('#^/melodyquest(?:/|$)#', $normalized) === 1) {
    require __DIR__ . '/melodyquest/index.php';
    exit;
}

if (preg_match('#^/arcadia/servers(?:/([^/]+))?/?$#', $normalized, $matches) === 1) {
    arcadiaFallbackServerCatalog(isset($matches[1]) ? rawurldecode($matches[1]) : null);
    exit;
}

if (preg_match('#^/arcadia(?:/|$)#', $normalized) === 1) {
    $frontController = __DIR__ . '/arcadia/public/index.php';

    if (is_file($frontController)) {
        $_SERVER['SCRIPT_FILENAME'] = $frontController;
        $_SERVER['SCRIPT_NAME'] = '/arcadia/index.php';
        $_SERVER['PHP_SELF'] = '/arcadia/index.php';

        require $frontController;
        exit;
    }
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Unknown API endpoint',
]);


