<?php
require_once __DIR__ . '/DatabaseService.php';
require_once __DIR__ . '/TokenService.php';
require_once __DIR__ . '/../config/config.php';

class SessionService
{
    private $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    /**
     * Crée une nouvelle session en DB et retourne l'ID de session généré.
     */
    public function createSession(int $userId, int $durationHours = SESSION_DURATION_HOURS): string
    {
        $sessionId = TokenService::generateToken(64); // ID sécurisé
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$durationHours hours"));

        $this->db->insert('sessions', [
            'id'         => $sessionId,
            'user_id'    => $userId,
            'expires_at' => $expiresAt
        ]);
        return $sessionId;
    }

    /**
     * Vérifie la validité d'une session (true = OK, false = KO).
     */
    public function isSessionValid(string $sessionId, bool $refresh = true): bool
    {
        $session = $this->db->get('sessions', ['user_id', 'expires_at'], [
            'id' => $sessionId
        ]);

        if (!$session || strtotime($session['expires_at']) <= time()) {
            return false;
        }

        if ($refresh) {
            $newExpires = date('Y-m-d H:i:s', strtotime("+" . SESSION_DURATION_HOURS . " hours"));
            $this->db->update('sessions', ['expires_at' => $newExpires], ['id' => $sessionId]);
        }
        return true;
    }

    /**
     * Récupère l'utilisateur lié à la session, ou false si invalide.
     */
    public function getUserIdFromSession(string $sessionId)
    {
        $session = $this->db->get('sessions', ['user_id', 'expires_at'], [
            'id' => $sessionId
        ]);
        if (!$session || strtotime($session['expires_at']) <= time()) {
            return false;
        }

        // Sliding expiration: refresh on access
        $newExpires = date('Y-m-d H:i:s', strtotime("+" . SESSION_DURATION_HOURS . " hours"));
        $this->db->update('sessions', ['expires_at' => $newExpires], ['id' => $sessionId]);

        return $session['user_id'];
    }

    /**
     * Supprime une session (déconnexion).
     */
    public function deleteSession(string $sessionId): void
    {
        $this->db->delete('sessions', ['id' => $sessionId]);
    }

    /**
     * Optionnel : Supprime toutes les sessions d'un utilisateur (logout partout).
     */
    public function deleteAllSessionsForUser(int $userId): void
    {
        $this->db->delete('sessions', ['user_id' => $userId]);
    }
}

?>
