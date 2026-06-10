<?php

require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/request.php';
require_once __DIR__ . '/../utils/response.php';

class AuthMiddleware
{
    public static function check(): int
    {
        $sessionId = getSessionId();

        if (!$sessionId) {
            json_error('Non authentifie', 401);
        }

        $sessionService = new SessionService();
        $userId = $sessionService->getUserIdFromSession($sessionId);

        if (!$userId) {
            json_error('Session invalide ou expiree', 401);
        }

        $authService = new AuthService();
        $user = $authService->getUserById((int)$userId);
        if ($authService->isUserBannedRecord($user)) {
            $sessionService->deleteSession($sessionId);
            json_error('Compte bloque', 403);
        }

        return (int)$userId;
    }
}
