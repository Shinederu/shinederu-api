<?php
declare(strict_types=1);

require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../services/DeviceService.php';
require_once __DIR__ . '/../services/WakeService.php';

class DeviceController
{
    public function status(): void
    {
        json_success(null, [
            'status' => AuthMiddleware::getStatus(),
        ]);
    }

    public function listDevices(): void
    {
        $auth = AuthMiddleware::requireWakeAccess();
        $deviceService = new DeviceService();

        json_success(null, [
            'devices' => $deviceService->listDevices($auth['can_manage']),
        ]);
    }

    public function wakeDevice(array $data): void
    {
        AuthMiddleware::requireWakeAccess();

        $deviceId = isset($data['deviceId']) ? (int)$data['deviceId'] : 0;
        if ($deviceId <= 0) {
            json_error('Machine cible invalide.', 400);
        }

        $deviceService = new DeviceService();
        $device = $deviceService->getDeviceById($deviceId);

        if ($device === null) {
            json_error('Machine introuvable.', 404);
        }

        if (!$device['is_enabled']) {
            json_error('Cette machine est desactivee.', 400);
        }

        $wakeService = new WakeService();
        $wakeService->sendMagicPacket(
            $device['mac_address'],
            $device['broadcast_address'] !== '' ? $device['broadcast_address'] : WAKE_DEFAULT_BROADCAST,
            $device['port']
        );

        $deviceService->touchLastWakeAt($deviceId);

        json_success('Magic packet envoye.', [
            'device' => $deviceService->getDeviceById($deviceId),
        ]);
    }

    public function createDevice(array $data, array $auth): void
    {
        $deviceService = new DeviceService();
        $device = $deviceService->createDevice($data, isset($auth['user']['id']) ? (int)$auth['user']['id'] : null);

        json_success('Machine ajoutee.', [
            'device' => $device,
        ], 201);
    }

    public function updateDevice(array $data): void
    {
        $deviceId = isset($data['id']) ? (int)$data['id'] : 0;
        if ($deviceId <= 0) {
            json_error('Machine cible invalide.', 400);
        }

        $deviceService = new DeviceService();
        $device = $deviceService->updateDevice($deviceId, $data);

        json_success('Machine mise a jour.', [
            'device' => $device,
        ]);
    }

    public function deleteDevice(array $data): void
    {
        $deviceId = isset($data['id']) ? (int)$data['id'] : 0;
        if ($deviceId <= 0) {
            json_error('Machine cible invalide.', 400);
        }

        $deviceService = new DeviceService();
        if (!$deviceService->deleteDevice($deviceId)) {
            json_error('Machine introuvable.', 404);
        }

        json_success('Machine supprimee.');
    }
}
