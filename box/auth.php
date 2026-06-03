<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

try {
    rate_limit('auth', 60, 60);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';

    switch ($action) {
        case 'status':
            $auth = get_current_auth_state();
            json_success([
                'authenticated' => (bool)$auth['authenticated'],
                'is_admin' => (bool)$auth['is_admin'],
                'user' => $auth['user'],
                'login_url' => $auth['login_url'],
                'logout_url' => $auth['logout_url'],
            ]);
            break;

        case 'logout':
            setcookie('sid', '', time() - 3600, '/', '.shinederu.ch', true, true);
            setcookie('session_id', '', time() - 3600, '/', '.shinederu.ch', true, true);

            json_success([
                'logout_url' => rtrim((string)env('AUTH_API_BASE', 'https://api.shinederu.ch/auth/'), '/') . '/?action=logout',
            ]);
            break;

        default:
            json_error('Action inconnue.', 400);
    }
} catch (Throwable $exception) {
    handle_api_exception($exception);
}
