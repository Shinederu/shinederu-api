<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/services/BoxFileService.php';

try {
    rate_limit('download', 240, 60);

    $service = new BoxFileService();
    $auth = null;

    if (isset($_GET['token'])) {
        $file = $service->getDownloadFileByShare((string)$_GET['token']);
    } else {
        $auth = require_admin();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_error('Parametre id requis.', 400);
        }
        $file = $service->getDownloadFileById($id);
    }

    $path = $service->physicalPath($file);
    $service->recordDownload($file, $auth);

    $filename = str_replace(["\r", "\n", '"'], '', (string)$file['name']);
    $fallbackFilename = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'download';
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header(
        'Content-Disposition: attachment; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\''
        . rawurlencode($filename)
    );
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
