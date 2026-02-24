<?php

require_once __DIR__ . '/../../auth/services/SessionService.php';
require_once __DIR__ . '/../../auth/utils/request.php';
require_once __DIR__ . '/../utils/response.php';

class AuthMiddleware
{
    public static function check(): int
    {
        $sessionId = getSessionId();

        if (!$sessionId) {
            json_error('Non authentifié', 401);
        }

        $sessionService = new SessionService();
        $userId = $sessionService->getUserIdFromSession($sessionId);

        if (!$userId) {
            json_error('Session invalide ou expirée', 401);
        }

        return (int)$userId;
    }
}
