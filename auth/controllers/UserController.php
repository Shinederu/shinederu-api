<?php

require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ProfileService.php';
require_once __DIR__ . '/../config/config.php';

class UserController
{
    private function ensureAdmin(int $userId): void
    {
        $authService = new AuthService();
        if (!$authService->isUserAdmin($userId)) {
            json_error('Acces refuse', 403);
        }
    }

    public function listUsers(int $requestUserId): void
    {
        $this->ensureAdmin($requestUserId);

        $authService = new AuthService();
        $users = $authService->listUsersForAdmin();

        json_success(null, ['users' => $users]);
    }

    public function updateManagedUser(array $data, int $requestUserId): void
    {
        $this->ensureAdmin($requestUserId);

        $input = sanitizeArray($data);
        $targetUserId = $this->extractTargetUserId($input);
        if ($targetUserId <= 0) {
            json_error('Utilisateur cible invalide', 400);
        }

        $authService = new AuthService();
        if (!$authService->getUserForAdmin($targetUserId)) {
            json_error('Utilisateur non trouve', 404);
        }

        if (array_key_exists('username', $input)) {
            $username = trim((string)$input['username']);
            $this->validateUsername($username);

            $profileService = new ProfileService();
            if (!$profileService->updateProfile($targetUserId, $username)) {
                json_error("Nom d'utilisateur deja pris", 400);
            }
        }

        if (array_key_exists('is_banned', $input)) {
            $isBanned = filter_var($input['is_banned'], FILTER_VALIDATE_BOOLEAN);
            $reason = isset($input['ban_reason']) ? trim((string)$input['ban_reason']) : '';

            if ($targetUserId === $requestUserId && $isBanned) {
                json_error('Impossible de bloquer votre propre compte', 400);
            }

            if (!$authService->setUserBanStatus($targetUserId, $isBanned, $requestUserId, $reason)) {
                json_error('Erreur serveur pendant la mise a jour du blocage', 500);
            }

            if ($isBanned) {
                (new SessionService())->deleteAllSessionsForUser($targetUserId);
            }
        }

        json_success('Utilisateur mis a jour', [
            'user' => $authService->getUserForAdmin($targetUserId),
        ]);
    }

    public function updateManagedUserAvatar(array $data, int $requestUserId): void
    {
        $this->ensureAdmin($requestUserId);

        $input = sanitizeArray($data);
        $targetUserId = $this->extractTargetUserId($input);
        if ($targetUserId <= 0) {
            json_error('Utilisateur cible invalide', 400);
        }

        $authService = new AuthService();
        if (!$authService->getUserForAdmin($targetUserId)) {
            json_error('Utilisateur non trouve', 404);
        }

        $avatarBytes = $this->extractAvatarBytes($data);
        $this->saveAvatarBytes($targetUserId, $avatarBytes);

        json_success('Avatar utilisateur mis a jour', [
            'user' => $authService->getUserForAdmin($targetUserId),
        ]);
    }

    public function updateUserRole(array $data, int $requestUserId): void
    {
        $this->ensureAdmin($requestUserId);

        $input = sanitizeArray($data);
        $targetUserId = isset($input['userId']) ? (int)$input['userId'] : 0;
        $role = $input['role'] ?? '';
        $isAdminInput = $input['is_admin'] ?? null;

        if ($targetUserId <= 0) {
            json_error('Utilisateur cible invalide', 400);
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            if ($isAdminInput !== null) {
                $role = filter_var($isAdminInput, FILTER_VALIDATE_BOOLEAN) ? 'admin' : 'user';
            } else {
                json_error('Role invalide', 400);
            }
        }

        $authService = new AuthService();
        $targetUser = $authService->getUserById($targetUserId);

        if (!$targetUser) {
            json_error('Utilisateur non trouve', 404);
        }

        if (!$authService->updateUserRole($targetUserId, $role)) {
            json_error('Erreur serveur pendant la mise a jour du role', 500);
        }

        json_success('Role utilisateur mis a jour');
    }

    public function deleteAccount(array $data, int $userId)
    {
        $sessionService = new SessionService();

        $password = $data['password'] ?? ($_REQUEST['password'] ?? '');
        $authService = new AuthService();
        $user = $authService->getUserById($userId);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            json_error('Mot de passe incorrect', 403);
        }

        $authService = new AuthService();
        $authService->deleteUser($userId);

        $sessionService->deleteAllSessionsForUser($userId);

        setcookie('sid', '', time() - 3600, '/', '.shinederu.ch', true, true);
        setcookie('session_id', '', time() - 3600, '/', '.shinederu.ch', true, true);

        json_success('Compte supprime et deconnecte');
    }

    public function updateProfile(array $data, int $userId)
    {
        $array = sanitizeArray($data);
        $username = $array['username'];
        $this->validateUsername($username);

        $profileService = new ProfileService();
        if (!$profileService->updateProfile($userId, $username)) {
            json_error("Nom d'utilisateur deja pris", 400);
        }
        json_success('Profil mis a jour');
    }

    public function getAvatar(array $data)
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;

        $profileService = new ProfileService();
        $avatar = $profileService->getAvatar($userId);
        if (!$avatar) {
            json_error('Avatar non trouve', 404);
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

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
        $avatarBytes = $this->extractAvatarBytes($data);
        $this->saveAvatarBytes($userId, $avatarBytes);

        json_success('Image de profil mise a jour');
    }

    private function extractTargetUserId(array $data): int
    {
        if (isset($data['userId'])) {
            return (int)$data['userId'];
        }
        if (isset($data['user_id'])) {
            return (int)$data['user_id'];
        }
        if (isset($data['id'])) {
            return (int)$data['id'];
        }

        return 0;
    }

    private function validateUsername(string $username): void
    {
        if (strlen($username) < USERNAME_MIN_LENGTH) {
            json_error("Nom d'utilisateur trop court (minimum " . USERNAME_MIN_LENGTH . " caracteres)", 400);
        }

        if (strlen($username) > USERNAME_MAX_LENGTH) {
            json_error("Nom d'utilisateur trop long (maximum " . USERNAME_MAX_LENGTH . " caracteres)", 400);
        }
    }

    private function extractAvatarBytes(array $data): string
    {
        $avatarBytes = null;

        if (!empty($data['image_base64'])) {
            $avatarBytes = base64_decode(
                preg_replace('#^data:image/\w+;base64,#', '', $data['image_base64']),
                true
            );
        } elseif (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $avatarBytes = file_get_contents($_FILES['file']['tmp_name']);
        }

        if (!$avatarBytes) {
            json_error('Aucune image recue', 400);
        }

        if (strlen($avatarBytes) > 5 * 1024 * 1024) {
            json_error('Image trop lourde (max 5 Mo).', 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo ? $finfo->buffer($avatarBytes) : null;
        $allowed = ALLOWED_MIME;
        if (!$mime || !in_array($mime, $allowed, true)) {
            json_error('Type non autorise (PNG, JPEG ou WebP).', 400);
        }

        return $avatarBytes;
    }

    private function saveAvatarBytes(int $userId, string $avatarBytes): void
    {
        $profile = new ProfileService();
        $png = $profile->normalizeToPng($avatarBytes);
        $result = $profile->saveUploadedPng($userId, $png);
        if (!$result) {
            json_error("Echec de la mise a jour de l'image de profil dans la base de donnees", 500);
        }
    }
}
