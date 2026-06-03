<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/services/BoxFileService.php';

try {
    require_admin();
    rate_limit('list', 120, 60);

    $service = new BoxFileService();
    json_success([
        'files' => $service->listFiles(),
        'stats' => $service->stats(),
    ]);
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
