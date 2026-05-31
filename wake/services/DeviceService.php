<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseService.php';
require_once __DIR__ . '/PingService.php';

class DeviceService
{
    private const COMPONENT_TYPES = [
        'processor',
        'motherboard',
        'memory',
        'graphics_card',
        'storage',
        'network_card',
        'sound_card',
        'capture_card',
        'extension_card',
        'power_supply',
        'cooling',
        'case',
        'other',
    ];

    private PDO $db;
    private PingService $pingService;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
        $this->pingService = new PingService();
    }

    public function listDevices(bool $includeDisabled = true): array
    {
        $sql = 'SELECT id, name, mac_address, target_ip, broadcast_address, port, description, is_enabled, sort_order, last_wake_at, created_at, updated_at
                FROM wake_devices';

        if (!$includeDisabled) {
            $sql .= ' WHERE is_enabled = 1';
        }

        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $statement = $this->db->query($sql);
        $rows = $statement->fetchAll();

        if (!is_array($rows)) {
            return [];
        }

        $devices = array_map(fn(array $row) => $this->mapDevice($row), $rows);
        $componentMap = $this->fetchComponentsForDeviceIds(array_column($devices, 'id'));

        return array_map(
            fn(array $device): array => $this->hydrateDeviceState(array_replace($device, [
                'components' => $componentMap[$device['id']] ?? [],
            ])),
            $devices
        );
    }

    public function getDeviceById(int $deviceId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, name, mac_address, target_ip, broadcast_address, port, description, is_enabled, sort_order, last_wake_at, created_at, updated_at
             FROM wake_devices
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $deviceId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        $device = $this->mapDevice($row);
        $componentMap = $this->fetchComponentsForDeviceIds([$device['id']]);

        return $this->hydrateDeviceState(array_replace($device, [
            'components' => $componentMap[$device['id']] ?? [],
        ]));
    }

    public function createDevice(array $input, ?int $createdByUserId = null): array
    {
        $payload = $this->normalizePayload($input);
        $components = $this->normalizeComponents($input['components'] ?? []);

        try {
            $this->db->beginTransaction();

            $statement = $this->db->prepare(
                'INSERT INTO wake_devices
                    (name, mac_address, target_ip, broadcast_address, port, description, is_enabled, sort_order, created_by_user_id)
                 VALUES
                    (:name, :mac_address, :target_ip, :broadcast_address, :port, :description, :is_enabled, :sort_order, :created_by_user_id)'
            );
            $statement->execute([
                'name' => $payload['name'],
                'mac_address' => $payload['mac_address'],
                'target_ip' => $payload['target_ip'] !== '' ? $payload['target_ip'] : null,
                'broadcast_address' => $payload['broadcast_address'] !== '' ? $payload['broadcast_address'] : null,
                'port' => $payload['port'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'is_enabled' => $payload['is_enabled'] ? 1 : 0,
                'sort_order' => $payload['sort_order'],
                'created_by_user_id' => $createdByUserId,
            ]);

            $deviceId = (int)$this->db->lastInsertId();
            $this->replaceDeviceComponents($deviceId, $components);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $device = $this->getDeviceById($deviceId);
        if ($device === null) {
            throw new RuntimeException('La machine a ete creee, mais sa relecture a echoue.');
        }

        return $device;
    }

    public function updateDevice(int $deviceId, array $input): array
    {
        if ($this->getDeviceById($deviceId) === null) {
            throw new InvalidArgumentException('Machine introuvable.');
        }

        $payload = $this->normalizePayload($input);
        $components = $this->normalizeComponents($input['components'] ?? []);

        try {
            $this->db->beginTransaction();

            $statement = $this->db->prepare(
                'UPDATE wake_devices
                 SET name = :name,
                     mac_address = :mac_address,
                     target_ip = :target_ip,
                     broadcast_address = :broadcast_address,
                     port = :port,
                     description = :description,
                     is_enabled = :is_enabled,
                     sort_order = :sort_order
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $deviceId,
                'name' => $payload['name'],
                'mac_address' => $payload['mac_address'],
                'target_ip' => $payload['target_ip'] !== '' ? $payload['target_ip'] : null,
                'broadcast_address' => $payload['broadcast_address'] !== '' ? $payload['broadcast_address'] : null,
                'port' => $payload['port'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'is_enabled' => $payload['is_enabled'] ? 1 : 0,
                'sort_order' => $payload['sort_order'],
            ]);

            $this->replaceDeviceComponents($deviceId, $components);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $device = $this->getDeviceById($deviceId);
        if ($device === null) {
            throw new RuntimeException('La machine mise a jour est introuvable.');
        }

        return $device;
    }

    public function deleteDevice(int $deviceId): bool
    {
        $statement = $this->db->prepare('DELETE FROM wake_devices WHERE id = :id');
        $statement->execute(['id' => $deviceId]);

        return $statement->rowCount() > 0;
    }

    public function touchLastWakeAt(int $deviceId): void
    {
        $statement = $this->db->prepare('UPDATE wake_devices SET last_wake_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $deviceId]);
    }

    private function normalizePayload(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Le nom de la machine est trop court.');
        }

        $macAddress = $this->normalizeMacAddress((string)($input['mac_address'] ?? ''));
        $targetIp = $this->normalizeIp((string)($input['target_ip'] ?? ''), true);
        $broadcastAddress = $this->normalizeIp((string)($input['broadcast_address'] ?? ''), true);

        if ($broadcastAddress === '' && $targetIp !== '') {
            $broadcastAddress = $this->deriveBroadcastFromIp($targetIp);
        }

        $port = (int)($input['port'] ?? WAKE_DEFAULT_PORT);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Le port WOL doit etre compris entre 1 et 65535.');
        }

        $description = trim((string)($input['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            throw new InvalidArgumentException('La description est trop longue (255 caracteres max).');
        }

        return [
            'name' => $name,
            'mac_address' => $macAddress,
            'target_ip' => $targetIp,
            'broadcast_address' => $broadcastAddress,
            'port' => $port,
            'description' => $description,
            'is_enabled' => to_bool($input['is_enabled'] ?? true),
            'sort_order' => (int)($input['sort_order'] ?? 0),
        ];
    }

    private function normalizeMacAddress(string $value): string
    {
        $normalized = preg_replace('/[^0-9a-fA-F]/', '', $value);
        if (!is_string($normalized) || strlen($normalized) !== 12) {
            throw new InvalidArgumentException('Adresse MAC invalide.');
        }

        return strtoupper(implode('-', str_split($normalized, 2)));
    }

    private function normalizeIp(string $value, bool $allowEmpty): string
    {
        $value = trim($value);

        if ($allowEmpty && $value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Adresse IP invalide.');
        }

        return $value;
    }

    private function deriveBroadcastFromIp(string $value): string
    {
        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return WAKE_DEFAULT_BROADCAST;
        }

        $parts = explode('.', $value);
        if (count($parts) !== 4) {
            return WAKE_DEFAULT_BROADCAST;
        }

        $parts[3] = '255';

        return implode('.', $parts);
    }

    private function mapDevice(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'mac_address' => (string)($row['mac_address'] ?? ''),
            'target_ip' => (string)($row['target_ip'] ?? ''),
            'broadcast_address' => (string)($row['broadcast_address'] ?? ''),
            'port' => (int)($row['port'] ?? WAKE_DEFAULT_PORT),
            'description' => (string)($row['description'] ?? ''),
            'is_enabled' => to_bool($row['is_enabled'] ?? 0),
            'sort_order' => (int)($row['sort_order'] ?? 0),
            'last_wake_at' => $row['last_wake_at'] !== null ? (string)$row['last_wake_at'] : null,
            'components' => [],
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    private function hydrateDeviceState(array $device): array
    {
        return $device + $this->pingService->getPowerState($device['target_ip'] ?? '');
    }

    private function normalizeComponents($input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (!is_array($input)) {
            throw new InvalidArgumentException('La liste des composants est invalide.');
        }

        $components = [];

        foreach (array_values($input) as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $type = trim((string)($entry['component_type'] ?? $entry['type'] ?? 'other'));
            $label = trim((string)($entry['label'] ?? $entry['name'] ?? ''));
            $details = trim((string)($entry['details'] ?? ''));

            if ($label === '' && $details === '') {
                continue;
            }

            if (!in_array($type, self::COMPONENT_TYPES, true)) {
                throw new InvalidArgumentException('Type de composant invalide.');
            }

            if ($label === '') {
                throw new InvalidArgumentException('Le nom du composant est obligatoire.');
            }

            if (mb_strlen($label) > 120) {
                throw new InvalidArgumentException('Le nom du composant est trop long (120 caracteres max).');
            }

            if (mb_strlen($details) > 255) {
                throw new InvalidArgumentException('Le detail du composant est trop long (255 caracteres max).');
            }

            $components[] = [
                'component_type' => $type,
                'label' => $label,
                'details' => $details,
                'sort_order' => isset($entry['sort_order']) ? (int)$entry['sort_order'] : $index,
            ];
        }

        usort(
            $components,
            static fn(array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']
        );

        return array_values($components);
    }

    private function replaceDeviceComponents(int $deviceId, array $components): void
    {
        $delete = $this->db->prepare('DELETE FROM wake_device_components WHERE device_id = :device_id');
        $delete->execute(['device_id' => $deviceId]);

        if ($components === []) {
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO wake_device_components
                (device_id, component_type, label, details, sort_order)
             VALUES
                (:device_id, :component_type, :label, :details, :sort_order)'
        );

        foreach ($components as $index => $component) {
            $insert->execute([
                'device_id' => $deviceId,
                'component_type' => $component['component_type'],
                'label' => $component['label'],
                'details' => $component['details'] !== '' ? $component['details'] : null,
                'sort_order' => $index,
            ]);
        }
    }

    private function fetchComponentsForDeviceIds(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_map('intval', $deviceIds)));
        $deviceIds = array_filter($deviceIds, static fn(int $id): bool => $id > 0);

        if ($deviceIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $statement = $this->db->prepare(
            "SELECT id, device_id, component_type, label, details, sort_order, created_at, updated_at
             FROM wake_device_components
             WHERE device_id IN ($placeholders)
             ORDER BY device_id ASC, sort_order ASC, id ASC"
        );
        $statement->execute($deviceIds);
        $rows = $statement->fetchAll();

        if (!is_array($rows)) {
            return [];
        }

        $componentsByDevice = [];

        foreach ($rows as $row) {
            $deviceId = (int)$row['device_id'];
            $componentsByDevice[$deviceId][] = [
                'id' => (int)$row['id'],
                'device_id' => $deviceId,
                'component_type' => (string)($row['component_type'] ?? 'other'),
                'label' => (string)($row['label'] ?? ''),
                'details' => (string)($row['details'] ?? ''),
                'sort_order' => (int)($row['sort_order'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }

        return $componentsByDevice;
    }
}
