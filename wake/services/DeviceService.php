<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseService.php';

class DeviceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
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

        return array_map(fn(array $row) => $this->mapDevice($row), $rows);
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

        return is_array($row) ? $this->mapDevice($row) : null;
    }

    public function createDevice(array $input, ?int $createdByUserId = null): array
    {
        $payload = $this->normalizePayload($input);

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

        $device = $this->getDeviceById((int)$this->db->lastInsertId());
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
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
