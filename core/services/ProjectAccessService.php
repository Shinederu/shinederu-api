<?php
declare(strict_types=1);

class ProjectAccessService
{
    private ?PDO $db;
    private ?bool $hasCoreTables = null;
    private ?bool $hasUsersIsAdminColumn = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    public function isGlobalAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($this->hasCoreTables()) {
            $stmt = $this->db()->prepare(
                "SELECT 1
                 FROM core_user_project_roles upr
                 JOIN core_project_roles r ON r.id = upr.project_role_id AND r.is_active = 1
                 JOIN core_projects p ON p.id = r.project_id AND p.is_active = 1
                 WHERE upr.user_id = :user_id
                   AND p.code = 'core'
                   AND r.role_key = 'super_admin'
                 LIMIT 1"
            );
            $stmt->execute(['user_id' => $userId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return $this->isLegacyGlobalAdmin($userId);
    }

    public function hasPermission(int $userId, string $projectCode, string|array $permissionKeys, bool $includeGlobalAdmin = true): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $permissionKeys = $this->normalizeList($permissionKeys);
        if ($permissionKeys === []) {
            return false;
        }

        if ($includeGlobalAdmin && $this->isGlobalAdmin($userId)) {
            return true;
        }

        if (!$this->hasCoreTables()) {
            return $this->hasLegacyPermission($userId, $projectCode, $permissionKeys);
        }

        $placeholders = $this->placeholders($permissionKeys, 'permission');
        $params = ['user_id' => $userId, 'project_code' => $projectCode] + $this->placeholderParams($permissionKeys, 'permission');

        $stmt = $this->db()->prepare(
            "SELECT 1
             FROM core_user_project_roles upr
             JOIN core_project_roles r ON r.id = upr.project_role_id AND r.is_active = 1
             JOIN core_projects p ON p.id = r.project_id AND p.is_active = 1
             JOIN core_project_role_permissions rp ON rp.role_id = r.id
             JOIN core_project_permissions perm ON perm.id = rp.permission_id AND perm.is_active = 1
             WHERE upr.user_id = :user_id
               AND p.code = :project_code
               AND perm.permission_key IN ({$placeholders})
             LIMIT 1"
        );
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function hasRole(int $userId, string $projectCode, string|array $roleKeys, bool $includeGlobalAdmin = true): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $roleKeys = $this->normalizeList($roleKeys);
        if ($roleKeys === []) {
            return false;
        }

        if ($includeGlobalAdmin && $this->isGlobalAdmin($userId)) {
            return true;
        }

        if (!$this->hasCoreTables()) {
            return false;
        }

        $placeholders = $this->placeholders($roleKeys, 'role');
        $params = ['user_id' => $userId, 'project_code' => $projectCode] + $this->placeholderParams($roleKeys, 'role');

        $stmt = $this->db()->prepare(
            "SELECT 1
             FROM core_user_project_roles upr
             JOIN core_project_roles r ON r.id = upr.project_role_id AND r.is_active = 1
             JOIN core_projects p ON p.id = r.project_id AND p.is_active = 1
             WHERE upr.user_id = :user_id
               AND p.code = :project_code
               AND r.role_key IN ({$placeholders})
             LIMIT 1"
        );
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function getUserProjectRoleKeys(int $userId, string $projectCode): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (!$this->hasCoreTables()) {
            return $this->getLegacyProjectRoleKeys($userId, $projectCode);
        }

        $stmt = $this->db()->prepare(
            "SELECT r.role_key
             FROM core_user_project_roles upr
             JOIN core_project_roles r ON r.id = upr.project_role_id AND r.is_active = 1
             JOIN core_projects p ON p.id = r.project_id AND p.is_active = 1
             WHERE upr.user_id = :user_id
               AND p.code = :project_code
             ORDER BY r.sort_order DESC, r.role_key ASC"
        );
        $stmt->execute(['user_id' => $userId, 'project_code' => $projectCode]);

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    public function setUserProjectRoles(int $userId, string $projectCode, array $roleKeys, ?int $grantedByUserId = null): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user id.');
        }
        if (!$this->hasCoreTables()) {
            throw new RuntimeException('Core project access tables are missing.');
        }

        $roleKeys = $this->normalizeList($roleKeys);
        $projectId = $this->getProjectId($projectCode);
        if ($projectId === null) {
            throw new RuntimeException('Unknown project code: ' . $projectCode);
        }

        $db = $this->db();
        $started = !$db->inTransaction();
        if ($started) {
            $db->beginTransaction();
        }

        try {
            if ($roleKeys === []) {
                $delete = $db->prepare(
                    "DELETE upr
                     FROM core_user_project_roles upr
                     JOIN core_project_roles r ON r.id = upr.project_role_id
                     WHERE upr.user_id = :user_id
                       AND r.project_id = :project_id"
                );
                $delete->execute(['user_id' => $userId, 'project_id' => $projectId]);
            } else {
                $placeholders = $this->placeholders($roleKeys, 'role');
                $params = ['user_id' => $userId, 'project_id' => $projectId] + $this->placeholderParams($roleKeys, 'role');

                $delete = $db->prepare(
                    "DELETE upr
                     FROM core_user_project_roles upr
                     JOIN core_project_roles r ON r.id = upr.project_role_id
                     WHERE upr.user_id = :user_id
                       AND r.project_id = :project_id
                       AND r.role_key NOT IN ({$placeholders})"
                );
                $delete->execute($params);

                $select = $db->prepare(
                    "SELECT id, role_key
                     FROM core_project_roles
                     WHERE project_id = :project_id
                       AND role_key IN ({$placeholders})
                       AND is_active = 1"
                );
                $select->execute(['project_id' => $projectId] + $this->placeholderParams($roleKeys, 'role'));
                $roles = $select->fetchAll();

                if (count($roles) !== count($roleKeys)) {
                    throw new RuntimeException('One or more role keys are unknown for project ' . $projectCode . '.');
                }

                $insert = $db->prepare(
                    "INSERT INTO core_user_project_roles (user_id, project_role_id, granted_by_user_id)
                     VALUES (:user_id, :project_role_id, :granted_by_user_id)
                     ON DUPLICATE KEY UPDATE
                       granted_by_user_id = VALUES(granted_by_user_id),
                       updated_at = CURRENT_TIMESTAMP"
                );
                foreach ($roles as $role) {
                    $insert->execute([
                        'user_id' => $userId,
                        'project_role_id' => (int)$role['id'],
                        'granted_by_user_id' => $grantedByUserId,
                    ]);
                }
            }

            if ($started) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function db(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }

        $dbType = defined('DB_TYPE') ? constant('DB_TYPE') : $this->env('DB_TYPE', 'mysql');
        $dbHost = defined('DB_HOST') ? constant('DB_HOST') : $this->env('DB_HOST', '127.0.0.1');
        $dbPort = defined('DB_PORT') ? constant('DB_PORT') : $this->env('DB_PORT', '3306');
        $dbName = defined('DB_NAME') ? constant('DB_NAME') : $this->env('DB_NAME', 'ShinedeCore');
        $dbUser = defined('DB_USER') ? constant('DB_USER') : $this->env('DB_USER', 'root');
        $dbPass = defined('DB_PASS') ? constant('DB_PASS') : $this->env('DB_PASS', '');

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbType, $dbHost, $dbPort, $dbName);
        $this->db = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->db;
    }

    private function hasCoreTables(): bool
    {
        if ($this->hasCoreTables !== null) {
            return $this->hasCoreTables;
        }

        $stmt = $this->db()->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (
                 'core_projects',
                 'core_project_roles',
                 'core_project_permissions',
                 'core_project_role_permissions',
                 'core_user_project_roles'
               )"
        );

        $this->hasCoreTables = ((int)$stmt->fetchColumn()) === 5;
        return $this->hasCoreTables;
    }

    private function isLegacyGlobalAdmin(int $userId): bool
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'is_admin' : 'NULL AS is_admin';
        $stmt = $this->db()->prepare("SELECT role, {$selectIsAdmin} FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return false;
        }

        return $this->toBool($row['is_admin'] ?? null) || strtolower((string)($row['role'] ?? '')) === 'admin';
    }

    private function hasUsersIsAdminColumn(): bool
    {
        if ($this->hasUsersIsAdminColumn !== null) {
            return $this->hasUsersIsAdminColumn;
        }

        $stmt = $this->db()->query(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'is_admin'
             LIMIT 1"
        );

        $this->hasUsersIsAdminColumn = (bool)$stmt->fetchColumn();
        return $this->hasUsersIsAdminColumn;
    }

    private function hasLegacyPermission(int $userId, string $projectCode, array $permissionKeys): bool
    {
        if ($projectCode !== 'wake') {
            return false;
        }

        if (!$this->tableExists('wake_user_permissions')) {
            return false;
        }

        $stmt = $this->db()->prepare(
            "SELECT can_wake, can_manage
             FROM wake_user_permissions
             WHERE user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return false;
        }

        $canWake = $this->toBool($row['can_wake'] ?? 0);
        $canManage = $this->toBool($row['can_manage'] ?? 0);

        foreach ($permissionKeys as $permissionKey) {
            if ($permissionKey === 'devices.wake' && ($canWake || $canManage)) {
                return true;
            }
            if (in_array($permissionKey, ['devices.manage', 'users.manage'], true) && $canManage) {
                return true;
            }
        }

        return false;
    }

    private function getLegacyProjectRoleKeys(int $userId, string $projectCode): array
    {
        if ($projectCode !== 'wake' || !$this->tableExists('wake_user_permissions')) {
            return [];
        }

        $stmt = $this->db()->prepare(
            "SELECT can_wake, can_manage
             FROM wake_user_permissions
             WHERE user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return [];
        }

        if ($this->toBool($row['can_manage'] ?? 0)) {
            return ['manage'];
        }
        if ($this->toBool($row['can_wake'] ?? 0)) {
            return ['wake'];
        }

        return [];
    }

    private function getProjectId(string $projectCode): ?int
    {
        $stmt = $this->db()->prepare('SELECT id FROM core_projects WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $projectCode]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db()->prepare(
            "SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1"
        );
        $stmt->execute(['table_name' => $table]);

        return (bool)$stmt->fetchColumn();
    }

    private function normalizeList(string|array $values): array
    {
        $values = is_array($values) ? $values : [$values];
        $values = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $values
        ))));

        return $values;
    }

    private function placeholders(array $values, string $prefix): string
    {
        return implode(', ', array_map(
            static fn(int $index): string => ':' . $prefix . '_' . $index,
            array_keys(array_values($values))
        ));
    }

    private function placeholderParams(array $values, string $prefix): array
    {
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $params[$prefix . '_' . $index] = $value;
        }

        return $params;
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }

    private function toBool($value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on', 'admin'], true);
    }
}

