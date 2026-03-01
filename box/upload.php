<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

require_admin();
rate_limit('upload', 10, 60); // 10 req/min/IP

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Méthode non autorisée']);
}

global $UPLOAD_DIR, $BASE_URL, $MAX_FILE_MB, $ALLOWED_EXT, $ALLOWED_MIME;

if (!isset($_FILES['files'])) {
    json_response(400, ['success' => false, 'error' => 'Aucun fichier reçu']);
}

$files = $_FILES['files'];
// Normalize structure to iterable array
$count = is_array($files['name']) ? count($files['name']) : 0;
$results = [];
$maxBytes = max(1, (int)$MAX_FILE_MB) * 1024 * 1024;

for ($i = 0; $i < $count; $i++) {
    $origName = (string)$files['name'][$i];
    $tmp = (string)$files['tmp_name'][$i];
    $err = (int)$files['error'][$i];
    $size = (int)$files['size'][$i];

    if ($err !== UPLOAD_ERR_OK) {
        $msg = match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux',
            UPLOAD_ERR_PARTIAL => 'Téléversement partiel',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier',
            default => 'Erreur d\'upload',
        };
        $results[] = ['name' => $origName, 'success' => false, 'error' => $msg];
        continue;
    }

    if (!is_uploaded_file($tmp)) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Fichier invalide'];
        continue;
    }

    if ($size <= 0) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Fichier vide'];
        continue;
    }

    if ($size > $maxBytes) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Dépasse la taille maximale'];
        continue;
    }

    if (!is_ascii_name($origName)) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Nom de fichier non ASCII ou invalide'];
        continue;
    }

    if (is_double_ext_danger($origName)) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Extension interdite'];
        continue;
    }

    $ext = get_ext($origName);
    if ($ext === '' || !in_array($ext, $ALLOWED_EXT, true)) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Extension non autorisée'];
        continue;
    }

    $mime = mime_of($tmp);
    if ($mime === '' || !in_array($mime, $ALLOWED_MIME, true)) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'MIME non autorisé'];
        continue;
    }

    $stored = unique_filename($ext);
    $dest = rtrim($UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stored;

    if (!@move_uploaded_file($tmp, $dest)) {
        $results[] = ['name' => $origName, 'success' => false, 'error' => 'Échec d\'écriture'];
        continue;
    }

    @chmod($dest, 0644);

    $results[] = [
        'name' => $origName,
        'stored' => $stored,
        'url' => storage_url($BASE_URL, $stored),
        'size' => filesize($dest) ?: $size,
        'mime' => $mime,
        'success' => true,
    ];
}

json_response(200, ['success' => true, 'results' => $results]);

