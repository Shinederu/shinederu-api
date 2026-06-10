<?php
require_once __DIR__ . '/DatabaseService.php';
require_once __DIR__ . '/TokenService.php';
require_once __DIR__ . '/../../core/services/ProjectAccessService.php';
require_once __DIR__ . '/../config/config.php';

class AuthService
{
    private $db;
    private ?bool $hasIsAdminColumn = null;
    private ?ProjectAccessService $projectAccess = null;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    /**
     * CrÃ©e un nouvel utilisateur.
     */
    public function createUser(string $username, string $email, string $password): bool
    {
        // Hash du mot de passe
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insertion utilisateur
        $payload = [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'user',
            'email_verified' => 0
        ];

        if ($this->hasIsAdminColumn()) {
            $payload['is_admin'] = 0;
        }

        $this->db->insert('users', $payload);

        return $this->db->id() !== null;
    }

    /**
     * VÃ©rifie quâ€™un utilisateur ou un email nâ€™existe pas dÃ©jÃ .
     */
    public function userOrEmailExists(string $username, string $email): bool
    {
        return $this->db->has('users', [
            'OR' => [
                'username' => $username,
                'email' => $email
            ]
        ]);
    }

    /**
     * VÃ©rifie les identifiants utilisateur (login).
     * Retourne le user si OK, sinon false.
     */
    public function verifyCredentials(string $usernameOrEmail, string $password)
    {
        $user = $this->db->get('users', '*', [
            'OR' => [
                'username' => $usernameOrEmail,
                'email' => $usernameOrEmail
            ]
        ]);

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }

    /**
     * GÃ©nÃ¨re et stocke un token de vÃ©rification d'email pour un utilisateur.
     * Retourne le token gÃ©nÃ©rÃ©.
     */
    public function createEmailVerificationToken(int $userId, ?string $newEmail = null): string
    {
        $token = TokenService::generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Supprime les anciens tokens de lâ€™utilisateur (Ã©vite les doublons)
        $this->db->delete('auth_email_verification_tokens', ['user_id' => $userId]);

        $this->db->insert('auth_email_verification_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'new_email' => $newEmail
        ]);
        return $token;
    }


    /**
     * VÃ©rifie un token de vÃ©rification d'email.
     * Retourne true si validÃ©, false sinon.
     */
    public function verifyEmailToken(string $token): bool
    {
        $record = $this->db->get('auth_email_verification_tokens', ['user_id', 'expires_at'], [
            'token' => $token
        ]);

        if (!$record)
            return false;
        if (strtotime($record['expires_at']) < time())
            return false;

        $this->db->update('users', ['email_verified' => 1], ['id' => $record['user_id']]);
        $this->db->delete('auth_email_verification_tokens', ['user_id' => $record['user_id']]);
        return true;
    }

    public function revokeRegister(string $token): bool
    {
        $record = $this->db->get('auth_email_verification_tokens', ['user_id', 'expires_at'], [
            'token' => $token
        ]);

        if (!$record)
            return false;
        if (strtotime($record['expires_at']) < time())
            return false;

        $this->db->delete('auth_email_verification_tokens', ['user_id' => $record['user_id']]);
        $this->db->delete('users', ['id' => $record['user_id']]);
        return true;
    }


    /**
     * Supprime un utilisateur et toutes ses donnÃ©es associÃ©es.
     * Attention, irrÃ©versible !
     */
    public function deleteUser($userId)
    {
        $this->db->delete('users', ['id' => $userId]);
    }

    /**
     * RÃ©cupÃ¨re les donnÃ©es utilisateur par son ID.
     * Retourne l'utilisateur ou false si non trouvÃ©.
     */
    public function getUserById($userId)
    {
        $columns = [
            'id',
            'username',
            'email',
            'avatar_url',
            'role',
            'created_at'
        ];

        if ($this->hasIsAdminColumn()) {
            $columns[] = 'is_admin';
        }

        $user = $this->db->get('users', $columns, [
            'id' => $userId
        ]);

        return $this->mapUserAdminFields($user);
    }

    public function getUserRoleById(int $userId)
    {
        $user = $this->getUserById($userId);
        return $user['role'] ?? null;
    }

    public function isUserAdmin(int $userId): bool
    {
        return $this->projectAccess()->hasPermission($userId, 'auth', 'users.manage');
    }

    public function getProjectAccessForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'is_global_admin' => false,
                'roles' => [],
                'permissions' => [],
            ];
        }

        $access = $this->projectAccess();
        $projects = ['core', 'auth', 'main', 'melodyquest', 'box', 'wake'];
        $roles = [];
        foreach ($projects as $projectCode) {
            $roles[$projectCode] = $access->getUserProjectRoleKeys($userId, $projectCode);
        }

        return [
            'is_global_admin' => $access->isGlobalAdmin($userId),
            'roles' => $roles,
            'permissions' => [
                'auth' => [
                    'users_manage' => $access->hasPermission($userId, 'auth', 'users.manage'),
                ],
                'main' => [
                    'announcements_manage' => $access->hasPermission($userId, 'main', 'announcements.manage'),
                ],
                'melodyquest' => [
                    'catalog_manage' => $access->hasPermission($userId, 'melodyquest', 'catalog.manage'),
                ],
                'box' => [
                    'files_manage' => $access->hasPermission($userId, 'box', 'files.manage'),
                ],
                'wake' => [
                    'devices_wake' => $access->hasPermission($userId, 'wake', 'devices.wake'),
                    'devices_manage' => $access->hasPermission($userId, 'wake', 'devices.manage'),
                    'users_manage' => $access->hasPermission($userId, 'wake', 'users.manage'),
                ],
            ],
        ];
    }

    public function listUsersForAdmin(): array
    {
        $columns = [
            'id',
            'username',
            'email',
            'avatar_url',
            'role',
            'email_verified',
            'created_at'
        ];

        if ($this->hasIsAdminColumn()) {
            $columns[] = 'is_admin';
        }

        $users = $this->db->select('users', $columns, [
            'ORDER' => [
                'created_at' => 'DESC'
            ]
        ]);

        if (!is_array($users)) {
            return [];
        }

        return array_map(function ($user) {
            $mapped = $this->mapUserAdminFields($user);
            if (!is_array($mapped) || empty($mapped['id'])) {
                return $mapped;
            }

            $mapped['project_access'] = $this->getProjectAccessForUser((int)$mapped['id']);
            return $mapped;
        }, $users);
    }

    public function updateUserRole(int $userId, string $role): bool
    {
        $payload = ['role' => $role];
        if ($this->hasIsAdminColumn()) {
            $payload['is_admin'] = $role === 'admin' ? 1 : 0;
        }

        $this->db->update('users', $payload, ['id' => $userId]);

        if ($this->db->error !== null) {
            return false;
        }

        $this->syncGlobalAdminRole($userId, $role === 'admin');

        return true;
    }


    /**
     * CrÃ©e un token de rÃ©initialisation de mot de passe pour un utilisateur.
     * Retourne le token gÃ©nÃ©rÃ©.
     */
    public function createPasswordResetToken(int $userId): string
    {
        $token = TokenService::generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Supprime les anciens tokens Ã©ventuels pour ce user
        $this->db->delete('auth_password_reset_tokens', ['user_id' => $userId]);

        $this->db->insert('auth_password_reset_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);
        return $token;
    }

    /**
     * VÃ©rifie un token de rÃ©initialisation de mot de passe.
     * Retourne l'ID utilisateur si valide, false sinon.
     */
    public function verifyPasswordResetToken(string $token)
    {
        $record = $this->db->get('auth_password_reset_tokens', ['user_id', 'expires_at'], [
            'token' => $token
        ]);
        if (!$record)
            return false;
        if (strtotime($record['expires_at']) < time())
            return false;
        return $record['user_id'];
    }

    /**
     * Consomme (supprime) un token de rÃ©initialisation de mot de passe.
     */
    public function consumePasswordResetToken(string $token)
    {
        $this->db->delete('auth_password_reset_tokens', ['token' => $token]);
    }

    /**
     * Met Ã  jour le mot de passe d'un utilisateur.
     * Retourne true si succÃ¨s, false sinon.
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->update('users', ['password_hash' => $passwordHash], ['id' => $userId]);
        return $this->db->error === null;
    }

    /**
     * RÃ©cupÃ¨re un utilisateur par son email.
     * Retourne l'utilisateur ou false si non trouvÃ©.
     */
    public function getUserByEmail(string $email)
    {
        return $this->db->get('users', '*', ['email' => $email]);
    }

    public function getEmailVerificationToken(string $token)
    {
        return $this->db->get('auth_email_verification_tokens', '*', ['token' => $token]);
    }

    public function getEmailVerificationTokenByID(string $id)
    {
        return $this->db->get('auth_email_verification_tokens', '*', ['user_id' => $id], );
    }

    public function getEmailByUserId(string $userId)
    {
        return $this->db->get('users', 'email', ['id' => $userId]);
    }

    public function updateUserEmail(int $userId, string $newEmail): bool
    {
        $this->db->update('users', [
            'email' => $newEmail,
            'email_verified' => 1 // On valide direct
        ], [
            'id' => $userId
        ]);
        return $this->db->error === null;
    }

    public function consumeEmailVerificationToken(string $token)
    {
        $this->db->delete('auth_email_verification_tokens', ['token' => $token]);
    }

    private function hasIsAdminColumn(): bool
    {
        if ($this->hasIsAdminColumn !== null) {
            return $this->hasIsAdminColumn;
        }

        $result = $this->db->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'is_admin'
             LIMIT 1"
        )->fetch();

        $this->hasIsAdminColumn = (bool)$result;
        return $this->hasIsAdminColumn;
    }

    private function toBool($value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        $v = strtolower(trim((string)$value));
        return in_array($v, ['1', 'true', 'yes', 'on', 'admin'], true);
    }

    private function mapUserAdminFields($user)
    {
        if (!is_array($user)) {
            return $user;
        }

        $isAdmin = $this->toBool($user['is_admin'] ?? null) || strtolower((string)($user['role'] ?? '')) === 'admin';
        if (!$isAdmin && isset($user['id'])) {
            $isAdmin = $this->projectAccess()->isGlobalAdmin((int)$user['id']);
        }
        $user['is_admin'] = $isAdmin;
        $user['role'] = $isAdmin ? 'admin' : 'user';
        if (array_key_exists('email_verified', $user)) {
            $user['email_verified'] = $this->toBool($user['email_verified']);
        }
        $user = $this->normalizeAvatarUrl($user);

        return $user;
    }

    private function normalizeAvatarUrl(array $user): array
    {
        $avatarUrl = $user['avatar_url'] ?? null;
        if (!is_string($avatarUrl) || $avatarUrl === '' || !str_contains($avatarUrl, 'action=getAvatar')) {
            return $user;
        }

        $userId = isset($user['id']) ? (int)$user['id'] : 0;
        if ($userId <= 0) {
            return $user;
        }

        $query = parse_url($avatarUrl, PHP_URL_QUERY);
        $params = [];
        if (is_string($query)) {
            parse_str($query, $params);
        }

        $user['avatar_url'] = $this->buildAvatarUrl($userId, isset($params['v']) ? (string)$params['v'] : null);
        return $user;
    }

    private function buildAvatarUrl(int $userId, ?string $version = null): string
    {
        $base = rtrim(BASE_API, '/');
        $separator = str_ends_with($base, 'index.php') ? '?' : '/?';
        $params = [
            'action' => 'getAvatar',
            'user_id' => $userId
        ];

        if ($version !== null && $version !== '') {
            $params['v'] = $version;
        }

        return $base . $separator . http_build_query($params);
    }

    private function projectAccess(): ProjectAccessService
    {
        if ($this->projectAccess === null) {
            $this->projectAccess = new ProjectAccessService();
        }

        return $this->projectAccess;
    }

    private function syncGlobalAdminRole(int $userId, bool $isAdmin): void
    {
        try {
            $this->projectAccess()->setUserProjectRoles(
                $userId,
                'core',
                $isAdmin ? ['super_admin'] : [],
                null
            );
        } catch (Throwable $e) {
            // Core access tables may not exist during a rolling migration. The legacy users.role value remains authoritative.
        }
    }




}
