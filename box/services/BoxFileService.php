<?php
declare(strict_types=1);

class BoxFileService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function listFiles(): array
    {
        $stmt = $this->db->query(
            "SELECT f.*,
                    u.username AS owner_username,
                    COUNT(s.id) AS active_share_count
             FROM box_files f
             LEFT JOIN users u ON u.id = f.owner_user_id
             LEFT JOIN box_shares s ON s.file_id = f.id
                AND s.is_active = 1
                AND (s.expires_at IS NULL OR s.expires_at > NOW())
                AND (s.max_downloads IS NULL OR s.download_count < s.max_downloads)
             WHERE f.deleted_at IS NULL
             GROUP BY f.id, u.username
             ORDER BY f.created_at DESC, f.id DESC"
        );

        return array_map(fn(array $row): array => $this->mapFile($row), $stmt->fetchAll() ?: []);
    }

    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS file_count,
                    COALESCE(SUM(size_bytes), 0) AS total_size,
                    COALESCE(SUM(download_count), 0) AS total_downloads
             FROM box_files
             WHERE deleted_at IS NULL"
        )->fetch() ?: [];

        $shares = $this->db->query(
            "SELECT COUNT(*) AS active_share_count
             FROM box_shares s
             JOIN box_files f ON f.id = s.file_id AND f.deleted_at IS NULL
             WHERE s.is_active = 1
               AND (s.expires_at IS NULL OR s.expires_at > NOW())
               AND (s.max_downloads IS NULL OR s.download_count < s.max_downloads)"
        )->fetch() ?: [];

        return [
            'file_count' => (int)($row['file_count'] ?? 0),
            'total_size' => (int)($row['total_size'] ?? 0),
            'total_downloads' => (int)($row['total_downloads'] ?? 0),
            'active_share_count' => (int)($shares['active_share_count'] ?? 0),
        ];
    }

    public function createFromUpload(array $file, array $auth): array
    {
        global $MAX_FILE_MB;

        $originalName = (string)($file['name'] ?? '');
        $tmp = (string)($file['tmp_name'] ?? '');
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int)($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->uploadErrorMessage($error));
        }
        if (!is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Fichier invalide.');
        }
        if ($size <= 0) {
            throw new InvalidArgumentException('Fichier vide.');
        }

        $maxBytes = max(1, (int)$MAX_FILE_MB) * 1024 * 1024;
        if ($size > $maxBytes) {
            throw new InvalidArgumentException('Depasse la taille maximale.');
        }

        $displayName = $this->normalizeDisplayName($originalName);
        if (is_double_ext_danger($displayName)) {
            throw new InvalidArgumentException('Extension interdite.');
        }

        $ext = get_ext($displayName);
        if (!is_extension_allowed($ext)) {
            throw new InvalidArgumentException('Extension non autorisee.');
        }

        $mime = mime_of($tmp) ?: 'application/octet-stream';
        if (!is_mime_allowed($mime)) {
            throw new InvalidArgumentException('MIME non autorise.');
        }

        $stored = unique_filename($ext);
        $dest = storage_path($stored);
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!@move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Echec d ecriture du fichier.');
        }

        @chmod($dest, 0640);
        $checksum = @hash_file('sha256', $dest) ?: null;
        $publicId = $this->createUniquePublicId();

        $stmt = $this->db->prepare(
            "INSERT INTO box_files
              (public_id, owner_user_id, original_name, display_name, stored_name, extension, mime_type,
               size_bytes, checksum_sha256, storage_path)
             VALUES
              (:public_id, :owner_user_id, :original_name, :display_name, :stored_name, :extension, :mime_type,
               :size_bytes, :checksum_sha256, :storage_path)"
        );
        $stmt->execute([
            'public_id' => $publicId,
            'owner_user_id' => isset($auth['user']['id']) ? (int)$auth['user']['id'] : null,
            'original_name' => $originalName,
            'display_name' => $displayName,
            'stored_name' => $stored,
            'extension' => $ext,
            'mime_type' => $mime,
            'size_bytes' => filesize($dest) ?: $size,
            'checksum_sha256' => $checksum,
            'storage_path' => $stored,
        ]);

        return $this->getFileById((int)$this->db->lastInsertId());
    }

    public function renameFile(int $id, string $name): array
    {
        $file = $this->getFileById($id);
        $displayName = $this->normalizeDisplayName($name);
        if (is_double_ext_danger($displayName)) {
            throw new InvalidArgumentException('Nom cible interdit.');
        }
        if (!is_extension_allowed(get_ext($displayName))) {
            throw new InvalidArgumentException('Extension non autorisee.');
        }

        $stmt = $this->db->prepare(
            "UPDATE box_files
             SET display_name = :display_name
             WHERE id = :id
               AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $file['id'], 'display_name' => $displayName]);

        return $this->getFileById($file['id']);
    }

    public function deleteFile(int $id): void
    {
        $file = $this->getFileById($id);
        $stmt = $this->db->prepare(
            "UPDATE box_files
             SET deleted_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $file['id']]);
    }

    public function getFileById(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.*, u.username AS owner_username, 0 AS active_share_count
             FROM box_files f
             LEFT JOIN users u ON u.id = f.owner_user_id
             WHERE f.id = :id
               AND f.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            throw new InvalidArgumentException('Fichier introuvable.');
        }

        return $this->mapFile($row);
    }

    public function listShares(int $fileId): array
    {
        $this->getFileById($fileId);

        $stmt = $this->db->prepare(
            "SELECT *
             FROM box_shares
             WHERE file_id = :file_id
             ORDER BY is_active DESC, created_at DESC, id DESC"
        );
        $stmt->execute(['file_id' => $fileId]);

        return array_map(fn(array $row): array => $this->mapShare($row), $stmt->fetchAll() ?: []);
    }

    public function createShare(int $fileId, array $input, array $auth): array
    {
        $this->getFileById($fileId);

        $expiresDays = isset($input['expires_days']) && $input['expires_days'] !== ''
            ? max(1, min(3650, (int)$input['expires_days']))
            : null;
        $maxDownloads = isset($input['max_downloads']) && $input['max_downloads'] !== ''
            ? max(1, min(1000000, (int)$input['max_downloads']))
            : null;
        $expiresAt = $expiresDays !== null
            ? (new DateTimeImmutable('now'))->modify('+' . $expiresDays . ' days')->format('Y-m-d H:i:s')
            : null;
        $token = $this->createUniqueShareToken();

        $stmt = $this->db->prepare(
            "INSERT INTO box_shares
              (file_id, token, expires_at, max_downloads, created_by_user_id)
             VALUES
              (:file_id, :token, :expires_at, :max_downloads, :created_by_user_id)"
        );
        $stmt->execute([
            'file_id' => $fileId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads,
            'created_by_user_id' => isset($auth['user']['id']) ? (int)$auth['user']['id'] : null,
        ]);

        return $this->getShareByToken($token, false);
    }

    public function revokeShare(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('Token requis.');
        }

        $stmt = $this->db->prepare(
            "UPDATE box_shares
             SET is_active = 0
             WHERE token = :token"
        );
        $stmt->execute(['token' => $token]);
    }

    public function getPublicShare(string $token): array
    {
        return $this->getShareByToken($token, true);
    }

    public function getShareByToken(string $token, bool $requireUsable): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, f.display_name, f.size_bytes, f.mime_type, f.extension, f.download_count AS file_download_count
             FROM box_shares s
             JOIN box_files f ON f.id = s.file_id AND f.deleted_at IS NULL
             WHERE s.token = :token
             LIMIT 1"
        );
        $stmt->execute(['token' => trim($token)]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            throw new InvalidArgumentException('Lien de partage introuvable.');
        }

        $share = $this->mapShare($row);
        if ($requireUsable && !$share['is_usable']) {
            throw new InvalidArgumentException('Lien de partage expire ou desactive.');
        }

        $share['file'] = [
            'id' => (int)$row['file_id'],
            'name' => (string)$row['display_name'],
            'size' => (int)$row['size_bytes'],
            'mime_type' => (string)$row['mime_type'],
            'extension' => (string)$row['extension'],
            'download_count' => (int)$row['file_download_count'],
        ];

        return $share;
    }

    public function getDownloadFileById(int $id): array
    {
        return $this->getFileById($id);
    }

    public function getDownloadFileByShare(string $token): array
    {
        $share = $this->getShareByToken($token, true);
        $file = $this->getFileById((int)$share['file_id']);
        $file['share_id'] = (int)$share['id'];
        $file['share_token'] = $share['token'];

        return $file;
    }

    public function recordDownload(array $file, ?array $auth = null): void
    {
        $fileId = (int)$file['id'];
        $shareId = isset($file['share_id']) ? (int)$file['share_id'] : null;
        $userId = isset($auth['user']['id']) ? (int)$auth['user']['id'] : null;
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE box_files SET download_count = download_count + 1 WHERE id = :id")
                ->execute(['id' => $fileId]);

            if ($shareId !== null) {
                $this->db->prepare("UPDATE box_shares SET download_count = download_count + 1 WHERE id = :id")
                    ->execute(['id' => $shareId]);
            }

            $this->db->prepare(
                "INSERT INTO box_download_events (file_id, share_id, user_id, ip_hash, user_agent_hash)
                 VALUES (:file_id, :share_id, :user_id, :ip_hash, :user_agent_hash)"
            )->execute([
                'file_id' => $fileId,
                'share_id' => $shareId,
                'user_id' => $userId,
                'ip_hash' => $ip !== '' ? hash('sha256', $ip) : null,
                'user_agent_hash' => $ua !== '' ? hash('sha256', $ua) : null,
            ]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function physicalPath(array $file): string
    {
        $path = storage_path((string)$file['storage_path']);
        if (!is_file($path)) {
            throw new RuntimeException('Fichier physique introuvable.');
        }

        return $path;
    }

    private function mapFile(array $row): array
    {
        $id = (int)$row['id'];
        $publicId = (string)$row['public_id'];

        return [
            'id' => $id,
            'public_id' => $publicId,
            'name' => (string)$row['display_name'],
            'original_name' => (string)$row['original_name'],
            'stored_name' => (string)$row['stored_name'],
            'extension' => (string)$row['extension'],
            'mime_type' => (string)$row['mime_type'],
            'size' => (int)$row['size_bytes'],
            'checksum_sha256' => $row['checksum_sha256'] !== null ? (string)$row['checksum_sha256'] : null,
            'storage_path' => (string)$row['storage_path'],
            'description' => $row['description'] !== null ? (string)$row['description'] : null,
            'download_count' => (int)($row['download_count'] ?? 0),
            'active_share_count' => (int)($row['active_share_count'] ?? 0),
            'owner_user_id' => $row['owner_user_id'] !== null ? (int)$row['owner_user_id'] : null,
            'owner_username' => $row['owner_username'] !== null ? (string)$row['owner_username'] : null,
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'mtime' => strtotime((string)$row['updated_at']) ?: time(),
            'url' => api_url('download.php', ['id' => $id]),
            'download_url' => api_url('download.php', ['id' => $id]),
        ];
    }

    private function mapShare(array $row): array
    {
        $expiresAt = $row['expires_at'] !== null ? (string)$row['expires_at'] : null;
        $maxDownloads = $row['max_downloads'] !== null ? (int)$row['max_downloads'] : null;
        $downloadCount = (int)($row['download_count'] ?? 0);
        $isActive = (bool)$row['is_active'];
        $isExpired = $expiresAt !== null && strtotime($expiresAt) <= time();
        $isLimited = $maxDownloads !== null && $downloadCount >= $maxDownloads;
        $token = (string)$row['token'];

        return [
            'id' => (int)$row['id'],
            'file_id' => (int)$row['file_id'],
            'token' => $token,
            'is_active' => $isActive,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads,
            'download_count' => $downloadCount,
            'is_expired' => $isExpired,
            'is_limited' => $isLimited,
            'is_usable' => $isActive && !$isExpired && !$isLimited,
            'created_by_user_id' => $row['created_by_user_id'] !== null ? (int)$row['created_by_user_id'] : null,
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'share_url' => share_page_url($token),
            'download_url' => api_url('download.php', ['token' => $token]),
        ];
    }

    private function normalizeDisplayName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?: '';
        $name = trim($name, ". \t\r\n");

        if (!is_safe_display_name($name)) {
            throw new InvalidArgumentException('Nom de fichier invalide.');
        }

        return $name;
    }

    private function createUniquePublicId(): string
    {
        do {
            $publicId = random_public_id();
            $stmt = $this->db->prepare('SELECT 1 FROM box_files WHERE public_id = :public_id LIMIT 1');
            $stmt->execute(['public_id' => $publicId]);
        } while ($stmt->fetchColumn());

        return $publicId;
    }

    private function createUniqueShareToken(): string
    {
        do {
            $token = random_share_token();
            $stmt = $this->db->prepare('SELECT 1 FROM box_shares WHERE token = :token LIMIT 1');
            $stmt->execute(['token' => $token]);
        } while ($stmt->fetchColumn());

        return $token;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
            UPLOAD_ERR_PARTIAL => 'Televersement partiel.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier recu.',
            default => 'Erreur d upload.',
        };
    }
}
