<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseService.php';
require_once __DIR__ . '/../../core/services/ProjectAccessService.php';

class AuthService
{
    private PDO $db;
    private ProjectAccessService $accessService;
    private ?bool $hasUsersIsAdminColumn = null;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
        $this->accessService = new ProjectAccessService($this->db);
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

        $userId = (int)$user['id'];
        $isGlobalAdmin = $this->accessService->isGlobalAdmin($userId);
        $canManage = $this->accessService->hasPermission($userId, 'wake', ['devices.manage', 'users.manage'], false);
        $canWake = $canManage || $this->accessService->hasPermission($userId, 'wake', 'devices.wake', false);

        $status['authenticated'] = true;
        $status['is_global_admin'] = $isGlobalAdmin;
        $status['can_wake'] = $isGlobalAdmin || $canWake;
        $status['can_manage'] = $isGlobalAdmin || $canManage;
        $status['user'] = [
            'id' => $userId,
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

        $roleKeys = [];
        if ($canManage) {
            $roleKeys = ['manage'];
        } elseif ($canWake) {
            $roleKeys = ['wake'];
        }

        $this->accessService->setUserProjectRoles($userId, 'wake', $roleKeys, $grantedByUserId);

        $updatedUser = $this->findWakeUserById($userId);
        if ($updatedUser === null) {
            throw new RuntimeException('Utilisateur introuvable apres mise a jour des permissions.');
        }

        return $updatedUser;
    }

    private function findUserBySessionId(string $sessionId): ?array
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'u.is_admin' : 'NULL AS is_admin';
        $sql = 'SELECT u.id, u.username, u.email, u.role, ' . $selectIsAdmin . '
                FROM auth_sessions s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.id = :session_id
                  AND s.expires_at > NOW()
                LIMIT 1';

        $statement = $this->db->prepare($sql);
        $statement->execute(['session_id' => $sessionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function fetchWakeUsers(): array
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'u.is_admin' : 'NULL AS is_admin';
        $sql = 'SELECT u.id,
                       u.username,
                       u.email,
                       u.role,
                       ' . $selectIsAdmin . ',
                       u.created_at
                FROM users u
                ORDER BY u.username ASC, u.email ASC, u.id ASC';

        $statement = $this->db->query($sql);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function findWakeUserById(int $userId): ?array
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'u.is_admin' : 'NULL AS is_admin';
        $sql = 'SELECT u.id,
                       u.username,
                       u.email,
                       u.role,
                       ' . $selectIsAdmin . ',
                       u.created_at
                FROM users u
                WHERE u.id = :user_id
                LIMIT 1';

        $statement = $this->db->prepare($sql);
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapWakeUser($row) : null;
    }

    private function mapWakeUser(array $row): array
    {
        $userId = (int)$row['id'];
        $isGlobalAdmin = $this->accessService->isGlobalAdmin($userId);
        $roleKeys = $this->accessService->getUserProjectRoleKeys($userId, 'wake');
        $hasDedicatedEntry = $roleKeys !== [];
        $dedicatedCanManage = in_array('manage', $roleKeys, true);
        $dedicatedCanWake = $dedicatedCanManage || in_array('wake', $roleKeys, true);
        $effectiveCanWake = $isGlobalAdmin || $dedicatedCanWake;
        $effectiveCanManage = $isGlobalAdmin || $dedicatedCanManage;
        $permissionLevel = $effectiveCanManage ? 'manage' : ($effectiveCanWake ? 'wake' : 'none');
        $permissionSource = $isGlobalAdmin ? 'global_admin' : ($hasDedicatedEntry ? 'dedicated' : 'none');

        return [
            'id' => $userId,
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
            'granted_by_user_id' => null,
            'permission_created_at' => null,
            'permission_updated_at' => null,
            'created_at' => (string)($row['created_at'] ?? ''),
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
}
