<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/services/BoxFileService.php';

try {
    rate_limit('share', 120, 60);
    $service = new BoxFileService();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET' && isset($_GET['token'])) {
        $share = $service->getPublicShare((string)$_GET['token']);
        json_success(['share' => $share]);
    }

    $auth = require_admin();

    if ($method === 'GET') {
        $fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($fileId <= 0) {
            json_error('Parametre id requis.', 400);
        }

        json_success(['shares' => $service->listShares($fileId)]);
    }

    if ($method !== 'POST') {
        json_error('Methode non autorisee.', 405);
    }

    $data = request_data();
    $action = (string)($data['action'] ?? 'create');

    if ($action === 'revoke') {
        $service->revokeShare((string)($data['token'] ?? ''));
        json_success();
    }

    $fileId = isset($data['file_id']) ? (int)$data['file_id'] : (int)($data['id'] ?? 0);
    if ($fileId <= 0) {
        json_error('Parametre file_id requis.', 400);
    }

    $share = $service->createShare($fileId, $data, $auth);
    json_success(['share' => $share], 201);
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
