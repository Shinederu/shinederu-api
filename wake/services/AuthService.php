<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseService.php';

class AuthService
{
    private PDO $db;
    private ?bool $hasUsersIsAdminColumn = null;
    private ?bool $hasPermissionsTable = null;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    public function getStatus(): array
    {
        $status = [
            'authenticated' => false,
            'can_wake' => false,
            'can_manage' => false,
            'is_global_admin' => false,
            'user' => null,
        ];

        $sessionId = get_session_id();
        if (!$sessionId) {
            return $status;
        }

        $user = $this->findUserBySessionId($sessionId);
        if ($user === null) {
            return $status;
        }

        $isGlobalAdmin = to_bool($user['is_admin'] ?? null) || strtolower((string)($user['role'] ?? '')) === 'admin';
        $permissions = $this->findWakePermissions((int)$user['id']);

        $status['authenticated'] = true;
        $status['is_global_admin'] = $isGlobalAdmin;
        $status['can_wake'] = $isGlobalAdmin || (bool)($permissions['can_wake'] ?? false) || (bool)($permissions['can_manage'] ?? false);
        $status['can_manage'] = $isGlobalAdmin || (bool)($permissions['can_manage'] ?? false);
        $status['user'] = [
            'id' => (int)$user['id'],
            'username' => (string)($user['username'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'role' => $isGlobalAdmin ? 'admin' : (string)($user['role'] ?? 'user'),
            'is_admin' => $isGlobalAdmin,
        ];

        return $status;
    }

    public function requireWakeAccess(): array
    {
        $status = $this->getStatus();

        if (!$status['authenticated']) {
            json_error('Non authentifie', 401);
        }

        if (!$status['can_wake']) {
            json_error('Acces refuse', 403);
        }

        return $status;
    }

    public function requireWakeManagement(): array
    {
        $status = $this->getStatus();

        if (!$status['authenticated']) {
            json_error('Non authentifie', 401);
        }

        if (!$status['can_manage']) {
            json_error('Droit de gestion requis', 403);
        }

        return $status;
    }

    public function listWakeUsers(): array
    {
        $rows = $this->fetchWakeUsers();

        return array_map(fn(array $row) => $this->mapWakeUser($row), $rows);
    }

    public function updateWakeUserPermissions(int $userId, bool $canWake, bool $canManage, ?int $grantedByUserId = null): array
    {
        $user = $this->findWakeUserById($userId);
        if ($user === null) {
            throw new InvalidArgumentException('Utilisateur introuvable.');
        }

        if ($user['is_global_admin']) {
            throw new InvalidArgumentException('Les admins globaux disposent deja de l\'acces complet.');
        }

        $this->ensurePermissionsTableAvailable();

        if ($canManage) {
            $canWake = true;
        }

        if (!$canWake && !$canManage) {
            $statement = $this->db->prepare('DELETE FROM wake_user_permissions WHERE user_id = :user_id');
            $statement->execute([
                'user_id' => $userId,
            ]);

            $updatedUser = $this->findWakeUserById($userId);
            if ($updatedUser === null) {
                throw new RuntimeException('Utilisateur introuvable apres suppression des permissions.');
            }

            return $updatedUser;
        }

        $statement = $this->db->prepare(
            'INSERT INTO wake_user_permissions (user_id, can_wake, can_manage, granted_by_user_id)
             VALUES (:user_id, :can_wake, :can_manage, :granted_by_user_id)
             ON DUPLICATE KEY UPDATE
                can_wake = VALUES(can_wake),
                can_manage = VALUES(can_manage),
                granted_by_user_id = VALUES(granted_by_user_id),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'user_id' => $userId,
            'can_wake' => $canWake ? 1 : 0,
            'can_manage' => $canManage ? 1 : 0,
            'granted_by_user_id' => $grantedByUserId,
        ]);

        $updatedUser = $this->findWakeUserById($userId);
        if ($updatedUser === null) {
            throw new RuntimeException('Utilisateur introuvable apres mise a jour des permissions.');
        }

        return $updatedUser;
    }

    private function findUserBySessionId(string $sessionId): ?array
    {
        if ($this->hasUsersIsAdminColumn()) {
            $sql = 'SELECT u.id, u.username, u.email, u.role, u.is_admin
                    FROM auth_sessions s
                    INNER JOIN users u ON u.id = s.user_id
                    WHERE s.id = :session_id
                      AND s.expires_at > NOW()
                    LIMIT 1';
        } else {
            $sql = 'SELECT u.id, u.username, u.email, u.role, NULL AS is_admin
                    FROM auth_sessions s
                    INNER JOIN users u ON u.id = s.user_id
                    WHERE s.id = :session_id
                      AND s.expires_at > NOW()
                    LIMIT 1';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['session_id' => $sessionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function findWakePermissions(int $userId): ?array
    {
        if (!$this->hasPermissionsTable()) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT can_wake, can_manage
             FROM wake_user_permissions
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'can_wake' => to_bool($row['can_wake'] ?? 0),
            'can_manage' => to_bool($row['can_manage'] ?? 0),
        ];
    }

    private function fetchWakeUsers(): array
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'u.is_admin' : 'NULL AS is_admin';

        if ($this->hasPermissionsTable()) {
            $sql = 'SELECT u.id,
                           u.username,
                           u.email,
                           u.role,
                           ' . $selectIsAdmin . ',
                           u.created_at,
                           CASE WHEN p.user_id IS NULL THEN 0 ELSE 1 END AS has_dedicated_entry,
                           COALESCE(p.can_wake, 0) AS can_wake,
                           COALESCE(p.can_manage, 0) AS can_manage,
                           p.granted_by_user_id,
                           p.created_at AS permission_created_at,
                           p.updated_at AS permission_updated_at
                    FROM users u
                    LEFT JOIN wake_user_permissions p ON p.user_id = u.id
                    ORDER BY u.username ASC, u.email ASC, u.id ASC';
        } else {
            $sql = 'SELECT u.id,
                           u.username,
                           u.email,
                           u.role,
                           ' . $selectIsAdmin . ',
                           u.created_at,
                           0 AS has_dedicated_entry,
                           0 AS can_wake,
                           0 AS can_manage,
                           NULL AS granted_by_user_id,
                           NULL AS permission_created_at,
                           NULL AS permission_updated_at
                    FROM users u
                    ORDER BY u.username ASC, u.email ASC, u.id ASC';
        }

        $statement = $this->db->query($sql);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function findWakeUserById(int $userId): ?array
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'u.is_admin' : 'NULL AS is_admin';

        if ($this->hasPermissionsTable()) {
            $sql = 'SELECT u.id,
                           u.username,
                           u.email,
                           u.role,
                           ' . $selectIsAdmin . ',
                           u.created_at,
                           CASE WHEN p.user_id IS NULL THEN 0 ELSE 1 END AS has_dedicated_entry,
                           COALESCE(p.can_wake, 0) AS can_wake,
                           COALESCE(p.can_manage, 0) AS can_manage,
                           p.granted_by_user_id,
                           p.created_at AS permission_created_at,
                           p.updated_at AS permission_updated_at
                    FROM users u
                    LEFT JOIN wake_user_permissions p ON p.user_id = u.id
                    WHERE u.id = :user_id
                    LIMIT 1';
        } else {
            $sql = 'SELECT u.id,
                           u.username,
                           u.email,
                           u.role,
                           ' . $selectIsAdmin . ',
                           u.created_at,
                           0 AS has_dedicated_entry,
                           0 AS can_wake,
                           0 AS can_manage,
                           NULL AS granted_by_user_id,
                           NULL AS permission_created_at,
                           NULL AS permission_updated_at
                    FROM users u
                    WHERE u.id = :user_id
                    LIMIT 1';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapWakeUser($row) : null;
    }

    private function mapWakeUser(array $row): array
    {
        $isGlobalAdmin = to_bool($row['is_admin'] ?? null) || strtolower((string)($row['role'] ?? '')) === 'admin';
        $hasDedicatedEntry = to_bool($row['has_dedicated_entry'] ?? 0);
        $dedicatedCanWake = to_bool($row['can_wake'] ?? 0);
        $dedicatedCanManage = to_bool($row['can_manage'] ?? 0);
        $effectiveCanWake = $isGlobalAdmin || $dedicatedCanWake || $dedicatedCanManage;
        $effectiveCanManage = $isGlobalAdmin || $dedicatedCanManage;
        $permissionLevel = $effectiveCanManage ? 'manage' : ($effectiveCanWake ? 'wake' : 'none');
        $permissionSource = $isGlobalAdmin ? 'global_admin' : ($hasDedicatedEntry ? 'dedicated' : 'none');

        return [
            'id' => (int)$row['id'],
            'username' => (string)($row['username'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'role' => $isGlobalAdmin ? 'admin' : (string)($row['role'] ?? 'user'),
            'is_global_admin' => $isGlobalAdmin,
            'has_dedicated_entry' => $hasDedicatedEntry,
            'can_wake' => $dedicatedCanWake,
            'can_manage' => $dedicatedCanManage,
            'effective_can_wake' => $effectiveCanWake,
            'effective_can_manage' => $effectiveCanManage,
            'permission_level' => $permissionLevel,
            'permission_source' => $permissionSource,
            'granted_by_user_id' => $row['granted_by_user_id'] !== null ? (int)$row['granted_by_user_id'] : null,
            'permission_created_at' => $row['permission_created_at'] !== null ? (string)$row['permission_created_at'] : null,
            'permission_updated_at' => $row['permission_updated_at'] !== null ? (string)$row['permission_updated_at'] : null,
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }

    private function ensurePermissionsTableAvailable(): void
    {
        if ($this->hasPermissionsTable()) {
            return;
        }

        throw new RuntimeException('La table wake_user_permissions est absente.');
    }

    private function hasUsersIsAdminColumn(): bool
    {
        if ($this->hasUsersIsAdminColumn !== null) {
            return $this->hasUsersIsAdminColumn;
        }

        $statement = $this->db->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'is_admin'
             LIMIT 1"
        );

        $this->hasUsersIsAdminColumn = (bool)$statement->fetch();

        return $this->hasUsersIsAdminColumn;
    }

    private function hasPermissionsTable(): bool
    {
        if ($this->hasPermissionsTable !== null) {
            return $this->hasPermissionsTable;
        }

        $statement = $this->db->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'wake_user_permissions'
             LIMIT 1"
        );

        $this->hasPermissionsTable = (bool)$statement->fetch();

        return $this->hasPermissionsTable;
    }
}
