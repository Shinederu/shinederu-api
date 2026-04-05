<?php
require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../utils/request.php';
require_once __DIR__ . '/../utils/response.php';

class AuthMiddleware
{
    /**
     * VÃ©rifie que l'utilisateur est connectÃ© (session valide).
     * Ã€ appeler au dÃ©but d'un endpoint protÃ©gÃ©.
     */
    public static function check(): int
    {
        // RÃ©cupÃ¨re le session_id (via cookie ou header)
        $sessionId = getSessionId();

        if (!$sessionId) {
            json_error('Non authentifiÃ©', 401);
        }

        $sessionService = new SessionService();
        $userId = $sessionService->getUserIdFromSession($sessionId);

        if (!$userId) {
            json_error('Session invalide ou expirÃ©e', 401);
        }

        return $userId;
    }
}
