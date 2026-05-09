<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';

class AuthMiddleware
{
    public static function getStatus(): array
    {
        return (new AuthService())->getStatus();
    }

    public static function requireWakeAccess(): array
    {
        return (new AuthService())->requireWakeAccess();
    }

    public static function requireWakeManagement(): array
    {
        return (new AuthService())->requireWakeManagement();
    }
}
