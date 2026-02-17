<?php
require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../utils/request.php';
require_once __DIR__ . '/../utils/response.php';

class AuthMiddleware
{
    /**
     * Vérifie que l'utilisateur est connecté (session valide).
     * À appeler au début d'un endpoint protégé.
     */
    public static function check(): int
    {
        // Récupère le session_id (via cookie ou header)
        $sessionId = getSessionId();

        if (!$sessionId) {
            json_error('Non authentifié', 401);
        }

        $sessionService = new SessionService();
        $userId = $sessionService->getUserIdFromSession($sessionId);

        if (!$userId) {
            json_error('Session invalide ou expirée', 401);
        }

        return $userId;
    }
}

?>
