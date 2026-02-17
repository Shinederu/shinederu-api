<?php
require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/request.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ProfileService.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../services/SessionService.php';

class AuthController
{
    /**
     * Inscription
     */
    public function register(array $data)
    {
        $input = sanitizeRegisterInput($data);
        $username = $input['username'];
        $email = $input['email'];
        $password = $input['password'];
        $passwordConfirm = $input['password_confirm'];

        // Validation classique
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Adresse e-mail invalide', 400);
        }

        if (strlen($username) < 4) {
            json_error("Nom d’utilisateur trop court (minimum 4 caractères)", 400);
        }

        if (strlen($username) > 64) {
            json_error("Nom d’utilisateur trop long (maximum 64 caractères)", 400);
        }

        if (strlen($password) < 8) {
            json_error('Le mot de passe doit faire au moins 8 caractères', 400);
        }

        if ($password !== $passwordConfirm) {
            json_error('Les mots de passe ne correspondent pas', 400);
        }

        $auth = new AuthService();

        if ($auth->userOrEmailExists($username, $email)) {
            json_error('Utilisateur ou e-mail déjà utilisé', 409);
        }

        if (!$auth->createUser($username, $email, $password)) {
            json_error('Erreur serveur lors de la création du compte', 500);
        }

        // Récupère l’ID nouvellement créé
        $db = DatabaseService::getInstance();
        $userId = $db->id();

        $url = "https://ui-avatars.com/api/?name={$username}&bold=true&size=" . 256;
        $profile = new ProfileService();
        $profile->setDefaultAvatarUrl($userId, $url);

        // Génère le token de vérif + envoie le mail
        $token = $auth->createEmailVerificationToken($userId);
        $link = "https://shinederu.lol/newEmail?action=verifyEmail&token=$token";
        $link2 = "https://shinederu.lol/newEmail?action=revokeRegister&token=$token";

        MailService::send(
            $email,
            'verify_email_register',
            [
                'verify_link' => $link,
                'revoke_link' => $link2,
            ]
        );

        json_success('Inscription réussie, vérifiez votre e‑mail !');
    }

    public function revokeRegister(array $params)
    {
        $input = sanitizeVerifyEmailInput($params);
        $token = $input['token'];

        $auth = new AuthService();

        $ok = $auth->revokeRegister($token);
        if (!$ok) {
            json_error('Lien invalide ou expiré', 400);
        }

        json_success("L’inscription a bien été annulée");
    }

    /**
     * Vérification email (GET /verify-email?token=...)
     */
    public function verifyEmail(array $params)
    {
        $input = sanitizeVerifyEmailInput($params);
        $token = $input['token'];

        $auth = new AuthService();
        $ok = $auth->verifyEmailToken($token);

        if ($ok) {
            json_success('E‑mail vérifié, vous pouvez vous connecter !');
        } else {
            json_error('Lien invalide ou expiré', 400);
        }
    }

    /**
     * Connexion
     */
    public function login(array $data)
    {
        // Empêche la reconnexion si une session valide existe déjà
        $existingSid = getSessionId();
        if ($existingSid) {
            $sessionService = new SessionService();
            if ($sessionService->isSessionValid($existingSid)) {
                json_success('Déjà connecté', ['session_id' => $existingSid]);
            }
        }

        $input = sanitizeLoginInput($data);
        $usernameOrEmail = $input['username'];
        $password = $input['password'];

        $auth = new AuthService();
        $user = $auth->verifyCredentials($usernameOrEmail, $password);

        if (!$user) {
            json_error('Identifiants invalides', 401);
        }

        if (!$user['email_verified']) {
            $rec = $auth->getEmailVerificationTokenByID($user['id']);
            if (!$rec || (isset($rec['expires_at']) && strtotime($rec['expires_at']) < time())) {
                $token = $auth->createEmailVerificationToken($user['id']);
            } else {
                $token = $rec['token'];
            }
            $email = $user['email'];
            $link = "https://shinederu.lol/newEmail?action=verifyEmail&token=$token";
            $link2 = "https://shinederu.lol/newEmail?action=revokeRegister&token=$token";

            MailService::send(
                $email,
                'verify_email_reminder',
                [
                    'verify_link' => $link,
                    'revoke_link' => $link2,
                ]
            );
            json_error('E‑mail non vérifié, un nouvel e‑mail vous a été envoyé.', 403);
        }

        // Crée la session en DB
        $sessionService = new SessionService();
        $sessionId = $sessionService->createSession($user['id']);

        // Envoie le cookie de session
        setcookie('sid', $sessionId, [
            'expires' => time() + (int)(SESSION_DURATION_HOURS * 3600),
            'path' => '/',
            'domain' => '.shinederu.lol', // partage sous-domaines
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        json_success(null, ['session_id' => $sessionId]);
    }

    /**
     * Déconnexion
     */
    public function logout(array $data = [], int $userId)
    {
        // Récupère l'id de session courant (cookie ou header)
        $sessionId = getSessionId();
        if (!$sessionId) {
            json_error('Session introuvable', 401);
        }

        $sessionService = new SessionService();
        $sessionService->deleteSession($sessionId);

        // Supprime les cookies de session (nouveau et legacy)
        setcookie('sid', '', time() - 3600, '/', '.shinederu.lol', true, true);
        setcookie('session_id', '', time() - 3600, '/', '.shinederu.lol', true, true);

        json_success('Déconnexion réussie');
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(array $data = [], int $userId)
    {
        $sessionService = new SessionService();

        $sessionService->deleteAllSessionsForUser($userId);

        // Supprime les cookies de session (nouveau et legacy)
        setcookie('sid', '', time() - 3600, '/', '.shinederu.lol', true, true);
        setcookie('session_id', '', time() - 3600, '/', '.shinederu.lol', true, true);

        json_success('Déconnexion de tous les appareils réussie');
    }

    /**
     * Demande de reset mot de passe (envoi du mail)
     */
    public function requestPasswordReset(array $data = [])
    {
        $input = sanitizeEmailInput($data);
        $email = $input['email'];
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('E‑mail invalide', 400);
        }

        $auth = new AuthService();
        $user = $auth->getUserByEmail($email);

        // Toujours répondre OK même si l'utilisateur n'existe pas (évite de leak qui est inscrit !)
        if (!$user) {
            json_success('Si un compte existe, un e‑mail a été envoyé.');
        }

        $token = $auth->createPasswordResetToken($user['id']);
        $resetLink = "https://shinederu.lol/newPassword?token=$token";

        // Envoie le mail
        MailService::send(
            $email,
            'password_reset_request',
            [
                'reset_link' => $resetLink,
            ]
        );

        json_success('Si un compte existe, un e‑mail a été envoyé.');
    }

    /**
     * Validation du reset de mot de passe via token
     */
    public function resetPassword(array $data = [])
    {
        // Récupère le token et le nouveau mot de passe
        $token = sanitizeVerifyEmailInput($data)['token'];

        $password = $data['password'];
        $passwordConfirm = $data['passwordConfirm'];

        if (!$token) {
            json_error('Token invalide', 400);
        }

        $auth = new AuthService();
        $userId = $auth->verifyPasswordResetToken($token);
        if (!$userId) {
            json_error('Lien invalide ou expiré', 400);
        }

        if (strlen($password) < 8) {
            json_error('Le mot de passe doit faire au moins 8 caractères', 400);
        }

        if ($password !== $passwordConfirm) {
            json_error('Les mots de passe ne correspondent pas', 400);
        }

        $auth->updatePassword($userId, $password);
        $auth->consumePasswordResetToken($token);

        $sessionService = new SessionService();
        $sessionService->deleteAllSessionsForUser($userId);

        json_success('Mot de passe réinitialisé avec succès !');
    }

    /**
     * Demande de mise à jour de l'e-mail
     * Envoie un mail de confirmation à la nouvelle adresse
     */
    public function requestEmailUpdate(array $data, $userId)
    {
        $newEmail = sanitizeEmailInput($data)['email'];
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            json_error('E‑mail invalide', 400);
        }

        $authService = new AuthService();
        if ($authService->userOrEmailExists('', $newEmail)) {
            json_error('E‑mail déjà utilisé', 409);
        }

        $oldEmail = $authService->getEmailByUserId($userId);

        // Génère un token de vérification email lié à l’update
        $token = $authService->createEmailVerificationToken($userId, $newEmail);
        $link = "https://shinederu.lol/newEmail?token=$token&action=confirmEmailUpdate";
        $link2 = "https://shinederu.lol/newEmail?token=$token&action=revokeEmailUpdate";

        MailService::send(
            $oldEmail,
            'email_update_notice_old',
            [
                'new_email' => $newEmail,
                'revoke_link' => $link2,
            ]
        );

        MailService::send(
            $newEmail,
            'email_update_confirm_new',
            [
                'confirm_link' => $link,
            ]
        );

        json_success('Un e‑mail de confirmation a été envoyé à la nouvelle adresse.');
    }

    public function confirmEmailUpdate(array $data)
    {
        $token = sanitizeVerifyEmailInput($data)['token'];

        $authService = new AuthService();
        $record = $authService->getEmailVerificationToken($token);

        if (!$record || strtotime($record['expires_at']) < time() || empty($record['new_email'])) {
            json_error('Lien invalide ou expiré', 400);
        }

        // Update l’e‑mail en base
        $authService->updateUserEmail($record['user_id'], $record['new_email']);

        // Supprime le token
        $authService->consumeEmailVerificationToken($token);

        json_success('Adresse e‑mail modifiée et confirmée !');
    }

    public function revokeEmailUpdate($data)
    {
        $token = sanitizeVerifyEmailInput($data)['token'];
        $authService = new AuthService();
        $record = $authService->getEmailVerificationToken($token);

        if (!$record || strtotime($record['expires_at']) < time() || empty($record['new_email'])) {
            json_error('Lien invalide ou expiré', 400);
        }

        $authService->consumeEmailVerificationToken($token);

        json_success("Changement d’adresse e‑mail annulé.");
    }

    public function me($userId)
    {
        $authService = new AuthService();
        $user = $authService->getUserById($userId);

        if (!$user) {
            json_error('Utilisateur non trouvé', 404);
        }

        // On filtre les infos sensibles avant de renvoyer
        unset($user['password_hash']);

        json_success(null, ['user' => $user]);
    }
}
?>
