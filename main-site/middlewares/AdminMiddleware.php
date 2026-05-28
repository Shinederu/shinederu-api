<?php

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../core/services/ProjectAccessService.php';

class AdminMiddleware
{
    public static function check(int $userId): void
    {
        $accessService = new ProjectAccessService();
        if (!$accessService->hasPermission($userId, 'main', 'announcements.manage')) {
            json_error('Admin requis', 403);
        }
    }
}
