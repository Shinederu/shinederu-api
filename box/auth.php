<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

rate_limit('auth', 20, 60);

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

switch ($action) {
    case 'status':
        $auth = get_current_auth_state();
        json_response(200, [
            'success' => true,
            'authenticated' => (bool)$auth['authenticated'],
            'is_admin' => (bool)$auth['is_admin'],
            'user' => $auth['user'],
            'login_url' => $auth['login_url'],
            'logout_url' => $auth['logout_url'],
        ]);
        break;

    case 'logout':
        // Local cookie cleanup is best-effort.
        setcookie('sid', '', time() - 3600, '/', '.shinederu.lol', true, true);
        setcookie('session_id', '', time() - 3600, '/', '.shinederu.lol', true, true);

        json_response(200, [
            'success' => true,
            'logout_url' => rtrim((string)env('AUTH_API_BASE', 'https://api.shinederu.lol/auth/'), '/') . '/?action=logout',
        ]);
        break;

    default:
        json_response(400, ['success' => false, 'error' => 'Action inconnue']);
}

