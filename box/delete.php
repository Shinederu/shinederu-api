<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/services/BoxFileService.php';

try {
    require_admin();
    rate_limit('delete', 60, 60);

    $data = request_data() + $_GET;
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        json_error('Parametre id requis.', 400);
    }

    (new BoxFileService())->deleteFile($id);
    json_success();
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
