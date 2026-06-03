<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/services/BoxFileService.php';

try {
    require_admin();
    rate_limit('rename', 30, 60);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Methode non autorisee.', 405);
    }

    $data = request_data();
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $name = (string)($data['name'] ?? $data['new_name'] ?? '');
    if ($id <= 0) {
        json_error('Parametre id requis.', 400);
    }

    $file = (new BoxFileService())->renameFile($id, $name);
    json_success(['file' => $file]);
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
