<?php

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/CoreAccessAdminService.php';
require_once __DIR__ . '/../../core/services/ProjectAccessService.php';

class CoreAccessController
{
    private function ensureSuperAdmin(int $userId): void
    {
        $accessService = new ProjectAccessService();
        if (!$accessService->isGlobalAdmin($userId)) {
            json_error('Super-admin requis', 403);
        }
    }

    public function listCoreAccess(int $requestUserId): void
    {
        $this->ensureSuperAdmin($requestUserId);

        $service = new CoreAccessAdminService();
        json_success(null, $service->listOverview());
    }

    public function saveProject(array $data, int $requestUserId): void
    {
        $this->ensureSuperAdmin($requestUserId);

        $service = new CoreAccessAdminService();
        $project = $service->saveProject($data);
        json_success('Projet enregistre', ['project' => $project]);
    }

    public function saveRole(array $data, int $requestUserId): void
    {
        $this->ensureSuperAdmin($requestUserId);

        $service = new CoreAccessAdminService();
        $role = $service->saveRole($data);
        json_success('Role enregistre', ['role' => $role]);
    }

    public function savePermission(array $data, int $requestUserId): void
    {
        $this->ensureSuperAdmin($requestUserId);

        $service = new CoreAccessAdminService();
        $permission = $service->savePermission($data);
        json_success('Permission enregistree', ['permission' => $permission]);
    }

    public function setRolePermissions(array $data, int $requestUserId): void
    {
        $this->ensureSuperAdmin($requestUserId);

        $service = new CoreAccessAdminService();
        $service->setRolePermissions($data);
        json_success('Permissions du role enregistrees');
    }

    public function setUserProjectRoles(array $data, int $requestUserId): void
    {
        $this->ensureSuperAdmin($requestUserId);

        $service = new CoreAccessAdminService();
        $service->setUserProjectRoles($requestUserId, $data);
        json_success('Roles utilisateur enregistres');
    }
}
