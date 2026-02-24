<?php

$authVendorAutoload = __DIR__ . '/../auth/vendor/autoload.php';
if (file_exists($authVendorAutoload)) {
    require_once $authVendorAutoload;

    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../auth');
        $dotenv->safeLoad();
    }
}

require_once __DIR__ . '/utils/response.php';
require_once __DIR__ . '/utils/request.php';
require_once __DIR__ . '/middlewares/CorsMiddleware.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/middlewares/AdminMiddleware.php';
require_once __DIR__ . '/controllers/AnnouncementController.php';
//CorsMiddleware::apply(); Nginx gère les CORS

$body = get_body();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = get_action($method, $body);

try {
    $controller = new AnnouncementController();

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'listPublicAnnouncements':
                    $controller->listPublic($_GET);
                    break;
                case 'listAnnouncements':
                    $userId = AuthMiddleware::check();
                    AdminMiddleware::check($userId);
                    $controller->listAdmin($_GET);
                    break;
                default:
                    json_error('Unknown action for GET method', 404);
            }
            break;

        case 'POST':
            switch ($action) {
                case 'createAnnouncement':
                    $userId = AuthMiddleware::check();
                    AdminMiddleware::check($userId);
                    $controller->create($body, $userId);
                    break;
                case 'updateAnnouncement':
                    $userId = AuthMiddleware::check();
                    AdminMiddleware::check($userId);
                    $controller->update($body, $userId);
                    break;
                case 'deleteAnnouncement':
                    $userId = AuthMiddleware::check();
                    AdminMiddleware::check($userId);
                    $controller->delete(array_merge($_GET, $body));
                    break;
                default:
                    json_error('Unknown action for POST method', 404);
            }
            break;

        case 'PUT':
            switch ($action) {
                case 'updateAnnouncement':
                    $userId = AuthMiddleware::check();
                    AdminMiddleware::check($userId);
                    $controller->update($body, $userId);
                    break;
                default:
                    json_error('Unknown action for PUT method', 404);
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'deleteAnnouncement':
                    $userId = AuthMiddleware::check();
                    AdminMiddleware::check($userId);
                    $controller->delete(array_merge($_GET, $body));
                    break;
                default:
                    json_error('Unknown action for DELETE method', 404);
            }
            break;

        default:
            json_error('Method not allowed', 405);
    }
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 400);
} catch (PDOException $e) {
    json_error('Database Error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_error('Unknown Error: ' . $e->getMessage(), 500);
}
