<?php

require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ProfileService.php';
require_once __DIR__ . '/../config/config.php';

class UserController
{
    /**
     * Supprime le compte utilisateur et déconnecte.
     * Attend un tableau de données avec 'password' pour confirmation.
     */
    public function deleteAccount(array $data = [], int $userId)
    {
        $sessionService = new SessionService();

        // Vérifie le mot de passe pour confirmer la suppression
        $password = $data['password'] ?? ($_REQUEST['password'] ?? '');
        $authService = new AuthService();
        $user = $authService->getUserById($userId);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            json_error('Mot de passe incorrect', 403);
        }

        // Supprime l’utilisateur
        $authService = new AuthService();
        $authService->deleteUser($userId);

        // Supprime toutes ses sessions
        $sessionService->deleteAllSessionsForUser($userId);

        // Efface les cookies de session (nouveau et legacy)
        setcookie('sid', '', time() - 3600, '/', '.shinederu.lol', true, true);
        setcookie('session_id', '', time() - 3600, '/', '.shinederu.lol', true, true);

        json_success('Compte supprimé et déconnecté');
    }


    public function updateProfile(array $data, int $userId)
    {
        $array = sanitizeArray($data);
        $username = $array['username'];

        if (strlen($username) < 4) {
            json_error("Nom d’utilisateur trop court (minimum 4 caractères)", 400);
        }

        if (strlen($username) > 64) {
            json_error("Nom d’utilisateur trop long (maximum 64 caractères)", 400);
        }

        $profileService = new ProfileService();
        if (!$profileService->updateProfile($userId, $username)) {
            json_error('Nom d’utilisateur déjà pris', 400);
        }
        json_success('Profil mis à jour');
    }


    public function getAvatar(array $data)
    {
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;

        $profileService = new ProfileService();
        $avatar = $profileService->getAvatar($userId);
        if (!$avatar) {
            json_error('Avatar non trouvé', 404);
        }

        // Purge tout buffer de sortie éventuel pour éviter de corrompre l'image
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        // En-têtes explicites pour un rendu fiable cross-origin
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: cross-origin');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . strlen($avatar));

        echo $avatar;
        exit;
    }

    public function updateAvatar(array $data, int $userId)
    {
        $avatarBytes = null;

        // JSON base64 (PUT/POST application/json)
        if (!empty($data['image_base64'])) {
            $avatarBytes = base64_decode(
                preg_replace('#^data:image/\w+;base64,#', '', $data['image_base64']),
                true
            );
        }
        // Multipart (POST form-data)
        elseif (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $avatarBytes = file_get_contents($_FILES['file']['tmp_name']);
        }

        if (!$avatarBytes) {
            json_error('Aucune image reçue', 400);
        }

        // limite de taille
        if (strlen($avatarBytes) > 5 * 1024 * 1024) { // 5 MB
            json_error('Image trop lourde (max 5 Mo).', 400);
        }
        // Vérif MIME (depuis les octets, pas le nom de fichier)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo ? $finfo->buffer($avatarBytes) : null;
        $allowed = ALLOWED_MIME; // configurable via config.php / .env
        if (!$mime || !in_array($mime, $allowed, true)) {
            json_error('Type non autorisé (PNG, JPEG ou WebP).', 400);
        }

        $profile = new ProfileService();
        $png = $profile->normalizeToPng($avatarBytes);
        $result = $profile->saveUploadedPng($userId, $png);

        if (!$result) {
            json_error("Échec de la mise à jour de l’image de profil dans la base de données", 500);
        }
        json_success('Image de profil mise à jour');
    }
}

?>
