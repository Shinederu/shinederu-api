<?php
declare(strict_types=1);

require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../services/AuthService.php';
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
        $devices = $deviceService->listDevices($auth['can_manage']);

        $unknownDevices = array_values(array_filter(
            $devices,
            static fn(array $device): bool => ($device['power_state'] ?? '') === 'unknown'
        ));

        if ($unknownDevices !== []) {
            wake_log('wake_power_state_unknown', [
                'count' => count($unknownDevices),
                'devices' => array_map(
                    static fn(array $device): array => [
                        'id' => $device['id'] ?? null,
                        'name' => $device['name'] ?? null,
                        'target_ip' => $device['target_ip'] ?? null,
                        'reason' => $device['power_state_reason'] ?? null,
                    ],
                    $unknownDevices
                ),
            ]);
        }

        json_success(null, [
            'devices' => $devices,
        ]);
    }

    public function listUsers(): void
    {
        AuthMiddleware::requireWakeManagement();

        $authService = new AuthService();

        json_success(null, [
            'users' => $authService->listWakeUsers(),
        ]);
    }

    public function wakeDevice(array $data): void
    {
        $auth = AuthMiddleware::requireWakeAccess();

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
        $logContext = [
            'device_id' => $deviceId,
            'device_name' => $device['name'],
            'target_ip' => $device['target_ip'],
            'broadcast_address' => $device['broadcast_address'] !== '' ? $device['broadcast_address'] : WAKE_DEFAULT_BROADCAST,
            'port' => $device['port'],
            'requested_by_user_id' => isset($auth['user']['id']) ? (int)$auth['user']['id'] : null,
            'requested_by_username' => $auth['user']['username'] ?? null,
        ];

        wake_log('wake_attempt_started', $logContext + [
            'mac_address_input' => $device['mac_address'],
        ]);

        try {
            $sendResult = $wakeService->sendMagicPacket(
                $device['mac_address'],
                $device['broadcast_address'] !== '' ? $device['broadcast_address'] : WAKE_DEFAULT_BROADCAST,
                $device['port'],
                $device['target_ip']
            );
        } catch (Throwable $exception) {
            wake_log_exception('wake_attempt_failed', $exception, $logContext + [
                'mac_address_input' => $device['mac_address'],
            ]);

            json_error('Le Magic Packet n\'a pas pu etre envoye.', 500, [
                'trace_id' => wake_request_id(),
            ]);
        }

        wake_log('wake_packet_sent', $logContext + $sendResult);
        $deviceService->touchLastWakeAt($deviceId);
        wake_log('wake_attempt_succeeded', $logContext + $sendResult);

        json_success('Magic packet envoye.', [
            'device' => $deviceService->getDeviceById($deviceId),
            'trace_id' => wake_request_id(),
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

    public function updateUserPermissions(array $data, array $auth): void
    {
        $userId = isset($data['userId']) ? (int)$data['userId'] : 0;
        if ($userId <= 0) {
            json_error('Utilisateur cible invalide.', 400);
        }

        $canWake = to_bool($data['can_wake'] ?? false);
        $canManage = to_bool($data['can_manage'] ?? false);

        $authService = new AuthService();
        $user = $authService->updateWakeUserPermissions(
            $userId,
            $canWake,
            $canManage,
            isset($auth['user']['id']) ? (int)$auth['user']['id'] : null
        );

        wake_log('wake_user_permission_updated', [
            'target_user_id' => $userId,
            'target_username' => $user['username'],
            'can_wake' => $user['can_wake'],
            'can_manage' => $user['can_manage'],
            'effective_can_wake' => $user['effective_can_wake'],
            'effective_can_manage' => $user['effective_can_manage'],
            'permission_source' => $user['permission_source'],
            'updated_by_user_id' => isset($auth['user']['id']) ? (int)$auth['user']['id'] : null,
            'updated_by_username' => $auth['user']['username'] ?? null,
        ]);

        json_success('Permissions utilisateur mises a jour.', [
            'user' => $user,
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
