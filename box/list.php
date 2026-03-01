<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

require_admin();
rate_limit('list', 30, 60); // 30 req/min/IP

global $UPLOAD_DIR, $BASE_URL, $ALLOWED_EXT;

if (!is_dir($UPLOAD_DIR)) {
    json_response(200, ['success' => true, 'files' => []]);
}

$dh = opendir($UPLOAD_DIR);
if ($dh === false) {
    json_response(500, ['success' => false, 'error' => 'Impossible de lire le dossier']);
}

$files = [];
while (($f = readdir($dh)) !== false) {
    if ($f === '.' || $f === '..') continue;
    $path = $UPLOAD_DIR . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;
    $ext = strtolower('.' . (pathinfo($f, PATHINFO_EXTENSION) ?: ''));
    if (!in_array($ext, $ALLOWED_EXT, true)) continue;
    $files[] = [
        'id' => $f,
        'name' => $f,
        'size' => filesize($path) ?: 0,
        'mtime' => filemtime($path) ?: time(),
        'url' => storage_url($BASE_URL, $f),
    ];
}
closedir($dh);

usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

json_response(200, ['success' => true, 'files' => $files]);

