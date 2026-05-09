<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/DeviceController.php';

apply_cors();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = get_request_action();
$body = get_request_body();
$controller = new DeviceController();

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'status':
                    $controller->status();
                    break;
                case 'listDevices':
                    $controller->listDevices();
                    break;
                default:
                    json_error('Action GET inconnue.', 404);
            }
            break;

        case 'POST':
            switch ($action) {
                case 'wakeDevice':
                    $controller->wakeDevice($body);
                    break;
                case 'createDevice':
                    $auth = AuthMiddleware::requireWakeManagement();
                    $controller->createDevice($body, $auth);
                    break;
                default:
                    json_error('Action POST inconnue.', 404);
            }
            break;

        case 'PUT':
            switch ($action) {
                case 'updateDevice':
                    AuthMiddleware::requireWakeManagement();
                    $controller->updateDevice($body);
                    break;
                default:
                    json_error('Action PUT inconnue.', 404);
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'deleteDevice':
                    AuthMiddleware::requireWakeManagement();
                    $controller->deleteDevice($body);
                    break;
                default:
                    json_error('Action DELETE inconnue.', 404);
            }
            break;

        default:
            json_error('Methode non autorisee.', 405);
    }
} catch (InvalidArgumentException $exception) {
    json_error($exception->getMessage(), 400);
} catch (PDOException $exception) {
    json_error('Erreur SQL pendant le traitement de la requete.', 500);
} catch (RuntimeException $exception) {
    json_error($exception->getMessage(), 500);
} catch (Throwable $exception) {
    json_error('Erreur applicative inattendue.', 500);
}
