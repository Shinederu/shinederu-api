<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

require_admin();
rate_limit('delete', 10, 60); // 10 req/min/IP

global $UPLOAD_DIR, $ALLOWED_EXT;

// Accept id via POST (preferred) or query for flexibility
$id = $_POST['id'] ?? $_GET['id'] ?? '';
if (!is_string($id) || $id === '') {
    json_response(400, ['success' => false, 'error' => 'Paramètre id requis']);
}

// Validate filename security (supports renamed files)
if (!is_ascii_name($id) || is_double_ext_danger($id)) {
    json_response(400, ['success' => false, 'error' => 'Identifiant invalide']);
}
$ext = get_ext($id);
if ($ext === '' || !in_array($ext, $ALLOWED_EXT, true)) {
    json_response(400, ['success' => false, 'error' => 'Extension non autorisée']);
}

$path = $UPLOAD_DIR . DIRECTORY_SEPARATOR . $id;
if (!is_file($path)) {
    json_response(404, ['success' => false, 'error' => 'Fichier introuvable']);
}

if (!@unlink($path)) {
    json_response(500, ['success' => false, 'error' => 'Échec suppression']);
}

json_response(200, ['success' => true]);
