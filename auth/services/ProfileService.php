<?php
require_once __DIR__ . '/DatabaseService.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/response.php';


class ProfileService
{

    private $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    public function updateProfile(int $userId, string $username): bool
    {
        $exists = $this->db->has('users', [
            'AND' => [
                'username' => $username,
                'id[!]' => $userId
            ]
        ]);
        if ($exists)
            return false;

        $this->db->update('users', ['username' => $username], ['id' => $userId]);
        return true;
    }


    public function setDefaultAvatarUrl(int $userId, string $url)
    {
        $this->db->update('users', [
            'avatar_url' => $url,
            'avatar_image' => null
        ], [
            'id' => $userId
        ]);
    }

    public function saveUploadedPng(int $userId, string $pngBytes)
    {
        $v = time();
        $url = BASE_API . '?action=getAvatar&user_id=' . $userId . '&v=' . $v;

        $stmt = $this->db->update('users', [
            'avatar_image' => $pngBytes,
            'avatar_url' => $url
        ], [
            'id' => $userId
        ]);

        if ($stmt === false) {
            return false;
        }
        return true;
    }

    public function getAvatar(int $userId)
    {
        $bytes = $this->db->get('users', 'avatar_image', ['id' => $userId]);
        return $bytes ?: null;
    }

    public function normalizeToPng(string $bytes, int $maxSize = 512): string
    {
        // 1) Crée une ressource GD
        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            json_error('Fichier d\'image invalide ou corrompu', 400);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $size = min($w, $h);
        $x = (int) (($w - $size) / 2);
        $y = (int) (($h - $size) / 2);

        // Crop carré
        $crop = imagecreatetruecolor($size, $size);
        imagealphablending($crop, false);
        imagesavealpha($crop, true);
        $transparent = imagecolorallocatealpha($crop, 0, 0, 0, 127);
        imagefilledrectangle($crop, 0, 0, $size, $size, $transparent);
        imagecopy($crop, $src, 0, 0, $x, $y, $size, $size);

        // Resize
        $dst = imagecreatetruecolor($maxSize, $maxSize);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $maxSize, $maxSize, $transparent);
        imagecopyresampled($dst, $crop, 0, 0, 0, 0, $maxSize, $maxSize, $size, $size);

        ob_start();
        imagepng($dst);
        $png = ob_get_clean();

        imagedestroy($src);
        imagedestroy($crop);
        imagedestroy($dst);

        return $png;
    }

}
?>
