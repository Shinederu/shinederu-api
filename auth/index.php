<?php



// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once('utils/response.php');
require_once('controllers/AuthController.php');
require_once('controllers/UserController.php');
require_once('middlewares/CorsMiddleware.php');
require_once('middlewares/AuthMiddleware.php');
//CorsMiddleware::apply(); Nginx gère les CORS



// Récupération des données POST/PUT (body JSON ou formulaire)
$body = [];
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $parsedJson = json_decode($rawInput, true);
        if (is_array($parsedJson)) {
            $body = $parsedJson;
        }
    }
    // Fallback formulaire si besoin
    if (empty($body) && !empty($_POST)) {
        $body = $_POST;
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = null;

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? null;
            switch ($action) {
                case 'me':
                    $userId = AuthMiddleware::check();
                    (new AuthController())->me($userId);
                    exit;
                case 'getAvatar':
                    (new UserController())->getAvatar($_GET);
                    exit;
                default:
                    unknownAction('GET');
                    exit;
            }

        case 'POST':
            $action = $body['action'] ?? $_POST['action'] ?? null;
            switch ($action) {
                case 'register':
                    (new AuthController())->register($body);
                    exit;
                case 'verifyEmail':
                    (new AuthController())->verifyEmail($body);
                    exit;
                case 'revokeRegister':
                    (new AuthController())->revokeRegister($body);
                    exit;
                case 'login':
                    (new AuthController())->login($body);
                    exit;
                case 'logout':
                    $userId = AuthMiddleware::check();
                    (new AuthController())->logout($body, $userId);
                    exit;
                case 'logoutAll':
                    $userId = AuthMiddleware::check();
                    (new AuthController())->logoutAll($body, $userId);
                    exit;
                case 'requestPasswordReset':
                    (new AuthController())->requestPasswordReset($body);
                    exit;
                case 'confirmEmailUpdate':
                    (new AuthController())->confirmEmailUpdate($body);
                    exit;
                case 'revokeEmailUpdate':
                    (new AuthController())->revokeEmailUpdate($body);
                    exit;
                case 'updateProfile':
                    $userId = AuthMiddleware::check();
                    (new UserController())->updateProfile($body, $userId);
                    exit;
                case 'updateAvatar':
                    $userId = AuthMiddleware::check();
                    (new UserController())->updateAvatar($body, $userId);
                    exit;
                default:
                    unknownAction('POST');
                    exit;
            }

        case 'PUT':
            $action = $body['action'] ?? $_REQUEST['action'] ?? null;
            switch ($action) {
                case 'resetPassword':
                    (new AuthController())->resetPassword($body);
                    exit;
                case 'requestEmailUpdate':
                    $userId = AuthMiddleware::check();
                    (new AuthController())->requestEmailUpdate($body, $userId);
                    exit;
                default:
                    unknownAction('PUT');
                    exit;
            }

        case 'DELETE':
            $action = $body['action'] ?? $_REQUEST['action'] ?? null;
            switch ($action) {
                case 'deleteAccount':
                    $userId = AuthMiddleware::check();
                    (new UserController())->deleteAccount($body, $userId);
                    exit;
                default:
                    unknownAction('DELETE');
                    exit;
            }

        default:
            notAllowedMethod();
            break;
    }

} catch (PDOException $e) {
    json_error('Database Error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    json_error('Application Error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_error('Unknown Error: ' . $e->getMessage(), 500);
} finally {
    exit;
}

// Fonctions utilitaires

function unknownAction($method)
{
    json_error("Unknown action for $method method", 404);
}

function notAllowedMethod()
{
    json_error('Method not allowed', 405);
}
