<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

require_admin();
rate_limit('rename', 10, 60); // 10 req/min/IP

global $UPLOAD_DIR, $BASE_URL, $ALLOWED_EXT;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Méthode non autorisée']);
}

$id = $_POST['id'] ?? '';
$newBase = $_POST['name'] ?? ($_POST['new_name'] ?? '');

if (!is_string($id) || $id === '') {
    json_response(400, ['success' => false, 'error' => 'Paramètre id requis']);
}
if (!is_string($newBase) || trim($newBase) === '') {
    json_response(400, ['success' => false, 'error' => 'Nouveau nom requis']);
}

// Validate current file identifier (must be a safe filename we manage)
if (!is_ascii_name($id) || is_double_ext_danger($id)) {
    json_response(400, ['success' => false, 'error' => 'Identifiant invalide']);
}
$oldPath = rtrim($UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $id;
if (!is_file($oldPath)) {
    json_response(404, ['success' => false, 'error' => 'Fichier introuvable']);
}

// Only allow changing the base name; keep the original extension
$ext = get_ext($id);
if ($ext === '' || !in_array($ext, $ALLOWED_EXT, true)) {
    json_response(400, ['success' => false, 'error' => 'Extension non autorisée']);
}

// Sanitize new base (no extension expected here)
$newBase = trim($newBase);
// Forbid trailing/leading dots/spaces to avoid weird names
$newBase = trim($newBase, ". \t\r\n");
if ($newBase === '' || !is_ascii_name($newBase)) {
    json_response(400, ['success' => false, 'error' => 'Nom invalide (ASCII uniquement, sans séparateurs)']);
}

$newName = $newBase . $ext;
if (is_double_ext_danger($newName)) {
    json_response(400, ['success' => false, 'error' => 'Nom cible interdit']);
}

// No-op
if ($newName === $id) {
    json_response(200, [
        'success' => true,
        'renamed' => [
            'old' => $id,
            'new' => $id,
            'url' => storage_url($BASE_URL, $id),
        ],
    ]);
}

$newPath = rtrim($UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
if (file_exists($newPath)) {
    json_response(409, ['success' => false, 'error' => 'Un fichier portant ce nom existe déjà']);
}

if (!@rename($oldPath, $newPath)) {
    json_response(500, ['success' => false, 'error' => 'Échec du renommage']);
}

@chmod($newPath, 0644);

json_response(200, [
    'success' => true,
    'renamed' => [
        'old' => $id,
        'new' => $newName,
        'url' => storage_url($BASE_URL, $newName),
    ],
]);

