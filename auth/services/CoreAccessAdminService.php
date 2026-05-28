<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/services/ProjectAccessService.php';

class CoreAccessAdminService
{
    private ?PDO $pdo = null;
    private ?bool $hasUsersIsAdminColumn = null;

    public function listOverview(): array
    {
        $projects = $this->fetchProjects();
        $roles = $this->fetchRoles();
        $permissions = $this->fetchPermissions();
        $rolePermissions = $this->fetchRolePermissions();

        foreach ($roles as &$role) {
            $role['permission_ids'] = $rolePermissions['ids'][$role['id']] ?? [];
            $role['permission_keys'] = $rolePermissions['keys'][$role['id']] ?? [];
        }
        unset($role);

        $projectsById = [];
        foreach ($projects as $project) {
            $project['roles'] = [];
            $project['permissions'] = [];
            $projectsById[$project['id']] = $project;
        }

        foreach ($permissions as $permission) {
            $projectsById[$permission['project_id']]['permissions'][] = $permission;
        }

        foreach ($roles as $role) {
            $projectsById[$role['project_id']]['roles'][] = $role;
        }

        return [
            'projects' => array_values($projectsById),
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $this->fetchUsers(),
            'assignments' => $this->fetchAssignments(),
        ];
    }

    public function saveProject(array $data): array
    {
        $id = $this->intValue($data['id'] ?? 0);
        $name = $this->textValue($data['name'] ?? '', 120);
        $description = $this->nullableText($data['description'] ?? null, 255);
        $isActive = $this->boolValue($data['is_active'] ?? true) ? 1 : 0;

        if ($name === '') {
            throw new InvalidArgumentException('Nom de projet requis.');
        }

        $db = $this->db();
        if ($id > 0) {
            $existing = $this->getProjectById($id);
            if (!$existing) {
                throw new RuntimeException('Projet introuvable.');
            }
            if ($existing['code'] === 'core' && $isActive !== 1) {
                throw new InvalidArgumentException('Le projet core ne peut pas etre desactive.');
            }

            $stmt = $db->prepare(
                'UPDATE core_projects
                 SET name = :name, description = :description, is_active = :is_active
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive,
            ]);

            return $this->getProjectById($id);
        }

        $code = $this->keyValue($data['code'] ?? '', 64);
        if ($code === '') {
            throw new InvalidArgumentException('Code de projet requis.');
        }

        $stmt = $db->prepare(
            'INSERT INTO core_projects (code, name, description, is_active)
             VALUES (:code, :name, :description, :is_active)'
        );
        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
        ]);

        return $this->getProjectById((int)$db->lastInsertId());
    }

    public function saveRole(array $data): array
    {
        $id = $this->intValue($data['id'] ?? 0);
        $label = $this->textValue($data['label'] ?? '', 120);
        $description = $this->nullableText($data['description'] ?? null, 255);
        $sortOrder = $this->intValue($data['sort_order'] ?? 0);
        $isActive = $this->boolValue($data['is_active'] ?? true) ? 1 : 0;

        if ($label === '') {
            throw new InvalidArgumentException('Libelle de role requis.');
        }

        $db = $this->db();
        if ($id > 0) {
            $existing = $this->getRoleById($id);
            if (!$existing) {
                throw new RuntimeException('Role introuvable.');
            }
            if ($existing['project_code'] === 'core' && $existing['role_key'] === 'super_admin' && $isActive !== 1) {
                throw new InvalidArgumentException('Le role core.super_admin ne peut pas etre desactive.');
            }

            $stmt = $db->prepare(
                'UPDATE core_project_roles
                 SET label = :label, description = :description, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);

            return $this->getRoleById($id);
        }

        $projectId = $this->intValue($data['project_id'] ?? 0);
        $roleKey = $this->keyValue($data['role_key'] ?? '', 64);
        if ($projectId <= 0 || !$this->getProjectById($projectId)) {
            throw new InvalidArgumentException('Projet invalide.');
        }
        if ($roleKey === '') {
            throw new InvalidArgumentException('Cle de role requise.');
        }

        $stmt = $db->prepare(
            'INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order, is_active)
             VALUES (:project_id, :role_key, :label, :description, :sort_order, :is_active)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'role_key' => $roleKey,
            'label' => $label,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        return $this->getRoleById((int)$db->lastInsertId());
    }

    public function savePermission(array $data): array
    {
        $id = $this->intValue($data['id'] ?? 0);
        $label = $this->textValue($data['label'] ?? '', 120);
        $description = $this->nullableText($data['description'] ?? null, 255);
        $isActive = $this->boolValue($data['is_active'] ?? true) ? 1 : 0;

        if ($label === '') {
            throw new InvalidArgumentException('Libelle de permission requis.');
        }

        $db = $this->db();
        if ($id > 0) {
            if (!$this->getPermissionById($id)) {
                throw new RuntimeException('Permission introuvable.');
            }

            $stmt = $db->prepare(
                'UPDATE core_project_permissions
                 SET label = :label, description = :description, is_active = :is_active
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'is_active' => $isActive,
            ]);

            return $this->getPermissionById($id);
        }

        $projectId = $this->intValue($data['project_id'] ?? 0);
        $permissionKey = $this->permissionKeyValue($data['permission_key'] ?? '', 96);
        if ($projectId <= 0 || !$this->getProjectById($projectId)) {
            throw new InvalidArgumentException('Projet invalide.');
        }
        if ($permissionKey === '') {
            throw new InvalidArgumentException('Cle de permission requise.');
        }

        $stmt = $db->prepare(
            'INSERT INTO core_project_permissions (project_id, permission_key, label, description, is_active)
             VALUES (:project_id, :permission_key, :label, :description, :is_active)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'permission_key' => $permissionKey,
            'label' => $label,
            'description' => $description,
            'is_active' => $isActive,
        ]);

        return $this->getPermissionById((int)$db->lastInsertId());
    }

    public function setRolePermissions(array $data): void
    {
        $roleId = $this->intValue($data['role_id'] ?? 0);
        $permissionIds = $this->intList($data['permission_ids'] ?? []);

        $role = $this->getRoleById($roleId);
        if (!$role) {
            throw new RuntimeException('Role introuvable.');
        }

        if ($permissionIds !== []) {
            $placeholders = $this->placeholders($permissionIds, 'permission');
            $stmt = $this->db()->prepare(
                "SELECT COUNT(*)
                 FROM core_project_permissions
                 WHERE project_id = :project_id
                   AND id IN ({$placeholders})"
            );
            $stmt->execute(['project_id' => (int)$role['project_id']] + $this->params($permissionIds, 'permission'));
            if ((int)$stmt->fetchColumn() !== count($permissionIds)) {
                throw new InvalidArgumentException('Une permission ne correspond pas au projet du role.');
            }
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM core_project_role_permissions WHERE role_id = :role_id');
            $delete->execute(['role_id' => $roleId]);

            if ($permissionIds !== []) {
                $insert = $db->prepare(
                    'INSERT INTO core_project_role_permissions (role_id, permission_id)
                     VALUES (:role_id, :permission_id)'
                );
                foreach ($permissionIds as $permissionId) {
                    $insert->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function setUserProjectRoles(int $requestUserId, array $data): void
    {
        $userId = $this->intValue($data['user_id'] ?? 0);
        $projectCode = $this->keyValue($data['project_code'] ?? '', 64);
        $roleKeys = $this->stringList($data['role_keys'] ?? []);

        if ($userId <= 0 || !$this->userExists($userId)) {
            throw new InvalidArgumentException('Utilisateur invalide.');
        }
        if (!$this->getProjectByCode($projectCode)) {
            throw new InvalidArgumentException('Projet invalide.');
        }
        if ($requestUserId === $userId && $projectCode === 'core' && !in_array('super_admin', $roleKeys, true)) {
            throw new InvalidArgumentException('Vous ne pouvez pas retirer votre propre role super-admin.');
        }

        $access = new ProjectAccessService($this->db());
        $access->setUserProjectRoles($userId, $projectCode, $roleKeys, $requestUserId);

        if ($projectCode === 'core') {
            $this->syncLegacyGlobalAdmin($userId, in_array('super_admin', $roleKeys, true));
        }
    }

    private function fetchProjects(): array
    {
        $rows = $this->db()->query(
            'SELECT id, code, name, description, is_active, created_at, updated_at
             FROM core_projects
             ORDER BY code ASC'
        )->fetchAll();

        return array_map(fn(array $row): array => [
            'id' => (int)$row['id'],
            'code' => (string)$row['code'],
            'name' => (string)$row['name'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'is_active' => (bool)$row['is_active'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ], $rows);
    }

    private function fetchRoles(): array
    {
        $rows = $this->db()->query(
            'SELECT r.id, r.project_id, p.code AS project_code, r.role_key, r.label, r.description,
                    r.sort_order, r.is_active, r.created_at, r.updated_at
             FROM core_project_roles r
             JOIN core_projects p ON p.id = r.project_id
             ORDER BY p.code ASC, r.sort_order DESC, r.role_key ASC'
        )->fetchAll();

        return array_map(fn(array $row): array => [
            'id' => (int)$row['id'],
            'project_id' => (int)$row['project_id'],
            'project_code' => (string)$row['project_code'],
            'role_key' => (string)$row['role_key'],
            'label' => (string)$row['label'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'sort_order' => (int)$row['sort_order'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'permission_ids' => [],
            'permission_keys' => [],
        ], $rows);
    }

    private function fetchPermissions(): array
    {
        $rows = $this->db()->query(
            'SELECT perm.id, perm.project_id, p.code AS project_code, perm.permission_key,
                    perm.label, perm.description, perm.is_active, perm.created_at, perm.updated_at
             FROM core_project_permissions perm
             JOIN core_projects p ON p.id = perm.project_id
             ORDER BY p.code ASC, perm.permission_key ASC'
        )->fetchAll();

        return array_map(fn(array $row): array => [
            'id' => (int)$row['id'],
            'project_id' => (int)$row['project_id'],
            'project_code' => (string)$row['project_code'],
            'permission_key' => (string)$row['permission_key'],
            'label' => (string)$row['label'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'is_active' => (bool)$row['is_active'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ], $rows);
    }

    private function fetchRolePermissions(): array
    {
        $rows = $this->db()->query(
            'SELECT rp.role_id, rp.permission_id, perm.permission_key
             FROM core_project_role_permissions rp
             JOIN core_project_permissions perm ON perm.id = rp.permission_id
             ORDER BY perm.permission_key ASC'
        )->fetchAll();

        $ids = [];
        $keys = [];
        foreach ($rows as $row) {
            $roleId = (int)$row['role_id'];
            $ids[$roleId] ??= [];
            $keys[$roleId] ??= [];
            $ids[$roleId][] = (int)$row['permission_id'];
            $keys[$roleId][] = (string)$row['permission_key'];
        }

        return ['ids' => $ids, 'keys' => $keys];
    }

    private function fetchAssignments(): array
    {
        $rows = $this->db()->query(
            'SELECT upr.user_id, p.code AS project_code, r.role_key, upr.granted_by_user_id,
                    upr.created_at, upr.updated_at
             FROM core_user_project_roles upr
             JOIN core_project_roles r ON r.id = upr.project_role_id
             JOIN core_projects p ON p.id = r.project_id
             ORDER BY upr.user_id ASC, p.code ASC, r.sort_order DESC, r.role_key ASC'
        )->fetchAll();

        return array_map(fn(array $row): array => [
            'user_id' => (int)$row['user_id'],
            'project_code' => (string)$row['project_code'],
            'role_key' => (string)$row['role_key'],
            'granted_by_user_id' => $row['granted_by_user_id'] !== null ? (int)$row['granted_by_user_id'] : null,
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ], $rows);
    }

    private function fetchUsers(): array
    {
        $selectIsAdmin = $this->hasUsersIsAdminColumn() ? 'is_admin' : 'NULL AS is_admin';
        $rows = $this->db()->query(
            "SELECT id, username, email, role, {$selectIsAdmin}, created_at
             FROM users
             ORDER BY username ASC, email ASC, id ASC"
        )->fetchAll();

        $assignments = $this->fetchAssignments();
        $rolesByUser = [];
        foreach ($assignments as $assignment) {
            $userId = $assignment['user_id'];
            $projectCode = $assignment['project_code'];
            $rolesByUser[$userId] ??= [];
            $rolesByUser[$userId][$projectCode] ??= [];
            $rolesByUser[$userId][$projectCode][] = $assignment['role_key'];
        }

        return array_map(function (array $row) use ($rolesByUser): array {
            $userId = (int)$row['id'];
            $projectRoles = $rolesByUser[$userId] ?? [];
            $isGlobalAdmin = $this->boolValue($row['is_admin'] ?? null)
                || strtolower((string)($row['role'] ?? '')) === 'admin'
                || in_array('super_admin', $projectRoles['core'] ?? [], true);

            return [
                'id' => $userId,
                'username' => (string)$row['username'],
                'email' => (string)$row['email'],
                'role' => $isGlobalAdmin ? 'admin' : (string)($row['role'] ?? 'user'),
                'is_global_admin' => $isGlobalAdmin,
                'created_at' => (string)$row['created_at'],
                'project_roles' => $projectRoles,
            ];
        }, $rows);
    }

    private function syncLegacyGlobalAdmin(int $userId, bool $isAdmin): void
    {
        $fields = ['role = :role'];
        $params = ['id' => $userId, 'role' => $isAdmin ? 'admin' : 'user'];
        if ($this->hasUsersIsAdminColumn()) {
            $fields[] = 'is_admin = :is_admin';
            $params['is_admin'] = $isAdmin ? 1 : 0;
        }

        $stmt = $this->db()->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    private function getProjectById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, code, name, description, is_active, created_at, updated_at FROM core_projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? [
            'id' => (int)$row['id'],
            'code' => (string)$row['code'],
            'name' => (string)$row['name'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'is_active' => (bool)$row['is_active'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ] : null;
    }

    private function getProjectByCode(string $code): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, code, name, description, is_active, created_at, updated_at FROM core_projects WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return is_array($row) ? ['id' => (int)$row['id'], 'code' => (string)$row['code']] : null;
    }

    private function getRoleById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT r.id, r.project_id, p.code AS project_code, r.role_key, r.label, r.description,
                    r.sort_order, r.is_active, r.created_at, r.updated_at
             FROM core_project_roles r
             JOIN core_projects p ON p.id = r.project_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? [
            'id' => (int)$row['id'],
            'project_id' => (int)$row['project_id'],
            'project_code' => (string)$row['project_code'],
            'role_key' => (string)$row['role_key'],
            'label' => (string)$row['label'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'sort_order' => (int)$row['sort_order'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ] : null;
    }

    private function getPermissionById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT perm.id, perm.project_id, p.code AS project_code, perm.permission_key,
                    perm.label, perm.description, perm.is_active, perm.created_at, perm.updated_at
             FROM core_project_permissions perm
             JOIN core_projects p ON p.id = perm.project_id
             WHERE perm.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? [
            'id' => (int)$row['id'],
            'project_id' => (int)$row['project_id'],
            'project_code' => (string)$row['project_code'],
            'permission_key' => (string)$row['permission_key'],
            'label' => (string)$row['label'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'is_active' => (bool)$row['is_active'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ] : null;
    }

    private function userExists(int $userId): bool
    {
        $stmt = $this->db()->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return (bool)$stmt->fetchColumn();
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

    private function db(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dbType = $this->env('DB_TYPE', 'mysql');
        $dbHost = $this->env('DB_HOST', '127.0.0.1');
        $dbPort = $this->env('DB_PORT', '3306');
        $dbName = $this->env('DB_NAME', 'ShinedeCore');
        $dbUser = $this->env('DB_USER', 'root');
        $dbPass = $this->env('DB_PASS', '');

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbType, $dbHost, $dbPort, $dbName);
        $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo;
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string)$value;
    }

    private function textValue($value, int $max): string
    {
        return substr(trim((string)$value), 0, $max);
    }

    private function nullableText($value, int $max): ?string
    {
        $text = $this->textValue($value ?? '', $max);
        return $text === '' ? null : $text;
    }

    private function keyValue($value, int $max): string
    {
        $key = strtolower($this->textValue($value, $max));
        return preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $key) ? $key : '';
    }

    private function permissionKeyValue($value, int $max): string
    {
        $key = strtolower($this->textValue($value, $max));
        return preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $key) ? $key : '';
    }

    private function intValue($value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function boolValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on', 'admin'], true);
    }

    private function intList($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn($value): int => $this->intValue($value),
            $values
        ), fn(int $value): bool => $value > 0)));
    }

    private function stringList($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn($value): string => $this->keyValue($value, 64),
            $values
        ), fn(string $value): bool => $value !== '')));
    }

    private function placeholders(array $values, string $prefix): string
    {
        return implode(', ', array_map(
            static fn(int $index): string => ':' . $prefix . '_' . $index,
            array_keys(array_values($values))
        ));
    }

    private function params(array $values, string $prefix): array
    {
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $params[$prefix . '_' . $index] = $value;
        }
        return $params;
    }
}
