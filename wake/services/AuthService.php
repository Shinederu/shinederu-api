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

    private function findUserBySessionId(string $sessionId): ?array
    {
        if ($this->hasUsersIsAdminColumn()) {
            $sql = 'SELECT u.id, u.username, u.email, u.role, u.is_admin
                    FROM sessions s
                    INNER JOIN users u ON u.id = s.user_id
                    WHERE s.id = :session_id
                      AND s.expires_at > NOW()
                    LIMIT 1';
        } else {
            $sql = 'SELECT u.id, u.username, u.email, u.role, NULL AS is_admin
                    FROM sessions s
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
