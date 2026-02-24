<?php

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../auth/services/AuthService.php';

class AdminMiddleware
{
    public static function check(int $userId): void
    {
        $authService = new AuthService();
        if (!$authService->isUserAdmin($userId)) {
            json_error('Admin requis', 403);
        }
    }
}
