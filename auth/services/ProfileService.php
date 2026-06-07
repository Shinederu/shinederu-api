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

    public function saveUploadedAvatar(int $userId, string $avatarBytes)
    {
        $v = time();
        $url = BASE_API . '?action=getAvatar&user_id=' . $userId . '&v=' . $v;

        $stmt = $this->db->update('users', [
            'avatar_image' => $avatarBytes,
            'avatar_url' => $url
        ], [
            'id' => $userId
        ]);

        if ($stmt === false) {
            return false;
        }
        return true;
    }

    public function saveUploadedPng(int $userId, string $pngBytes)
    {
        return $this->saveUploadedAvatar($userId, $pngBytes);
    }

    public function getAvatar(int $userId)
    {
        $bytes = $this->db->get('users', 'avatar_image', ['id' => $userId]);
        return $bytes ?: null;
    }

    public function detectImageMime(string $bytes): ?string
    {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo ? $finfo->buffer($bytes) : null;
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($bytes);
            if (is_array($info) && isset($info['mime'])) {
                return $info['mime'];
            }
        }

        return null;
    }

    public function isSupportedImageMime(?string $mime): bool
    {
        return $mime !== null && in_array($mime, ALLOWED_MIME, true);
    }

    public function isReadableImage(string $bytes): bool
    {
        if (!function_exists('getimagesizefromstring')) {
            return true;
        }

        return @getimagesizefromstring($bytes) !== false;
    }

    public function prepareUploadedAvatar(string $bytes, string $mime): string
    {
        if (!$this->canNormalizeWithGd()) {
            return $bytes;
        }

        if (!$this->isSupportedImageMime($mime)) {
            return $bytes;
        }

        return $this->normalizeToPng($bytes);
    }

    public function normalizeToPng(string $bytes, int $maxSize = 512): string
    {
        // 1) CrÃ©e une ressource GD
        if (!$this->canNormalizeWithGd()) {
            return $bytes;
        }

        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return $bytes;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            return $bytes;
        }

        $size = min($w, $h);
        $x = (int) (($w - $size) / 2);
        $y = (int) (($h - $size) / 2);

        // Crop carrÃ©
        $dst = imagecreatetruecolor($maxSize, $maxSize);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $dstTransparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $maxSize, $maxSize, $dstTransparent);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $maxSize, $maxSize, $size, $size);

        ob_start();
        $ok = imagepng($dst);
        $png = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (!$ok || !is_string($png) || $png === '') {
            return $bytes;
        }

        return $png;
    }

    private function canNormalizeWithGd(): bool
    {
        $functions = [
            'imagecreatefromstring',
            'imagecreatetruecolor',
            'imagealphablending',
            'imagesavealpha',
            'imagecolorallocatealpha',
            'imagefilledrectangle',
            'imagecopyresampled',
            'imagesx',
            'imagesy',
            'imagepng',
            'imagedestroy'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }

        return true;
    }

}
