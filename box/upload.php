<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/services/BoxFileService.php';

try {
    $auth = require_admin();
    rate_limit('upload', 30, 60);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Methode non autorisee.', 405);
    }

    if (!isset($_FILES['files'])) {
        json_error('Aucun fichier recu.', 400);
    }

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    $service = new BoxFileService();
    $results = [];

    for ($i = 0; $i < $count; $i++) {
        $entry = [
            'name' => $files['name'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];

        try {
            $file = $service->createFromUpload($entry, $auth);
            $results[] = [
                'name' => (string)$entry['name'],
                'success' => true,
                'file' => $file,
            ];
        } catch (Throwable $exception) {
            $results[] = [
                'name' => (string)$entry['name'],
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    json_success(['results' => $results]);
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
