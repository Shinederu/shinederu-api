<?php
declare(strict_types=1);

class WakeService
{
    public function sendMagicPacket(
        string $macAddress,
        string $broadcastAddress = '',
        int $port = WAKE_DEFAULT_PORT,
        string $targetIp = ''
    ): array
    {
        $cleanMac = $this->normalizeMacAddress($macAddress);

        $broadcastAddress = trim($broadcastAddress) !== '' ? trim($broadcastAddress) : WAKE_DEFAULT_BROADCAST;
        if (filter_var($broadcastAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Adresse de broadcast invalide.');
        }

        $targetIp = trim($targetIp);
        if ($targetIp !== '' && filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Adresse IP cible invalide.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port WOL invalide.');
        }

        $packet = hex2bin(str_repeat('FF', 6) . str_repeat(strtoupper($cleanMac), 16));
        if (!is_string($packet)) {
            throw new RuntimeException('Impossible de generer le Magic Packet.');
        }

        $destinations = $this->buildDestinations($targetIp, $broadcastAddress);
        $transport = function_exists('socket_create') ? 'socket' : 'stream';
        $successfulDestinations = [];
        $failedDestinations = [];

        foreach ($destinations as $destination) {
            try {
                $bytesSent = $transport === 'socket'
                    ? $this->sendWithSocketExtension($packet, $destination['address'], $port)
                    : $this->sendWithStreamSocket($packet, $destination['address'], $port);

                $successfulDestinations[] = [
                    'label' => $destination['label'],
                    'address' => $destination['address'],
                    'bytes_sent' => $bytesSent,
                ];
            } catch (Throwable $exception) {
                $failedDestinations[] = [
                    'label' => $destination['label'],
                    'address' => $destination['address'],
                    'error' => $exception->getMessage(),
                ];
            }
        }

        if ($successfulDestinations === []) {
            $errors = array_map(
                static fn(array $entry): string => $entry['label'] . ' ' . $entry['address'] . ' -> ' . $entry['error'],
                $failedDestinations
            );

            throw new RuntimeException('Aucun envoi WOL n\'a abouti. ' . implode(' | ', $errors));
        }

        return $this->buildResult($cleanMac, $port, $transport, $packet, $successfulDestinations, $failedDestinations);
    }

    private function sendWithSocketExtension(string $packet, string $broadcastAddress, int $port): int
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            throw new RuntimeException('Creation du socket UDP impossible: ' . socket_strerror(socket_last_error()));
        }

        try {
            if (!socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1)) {
                throw new RuntimeException('Activation du broadcast UDP impossible: ' . socket_strerror(socket_last_error($socket)));
            }

            $sent = @socket_sendto($socket, $packet, strlen($packet), 0, $broadcastAddress, $port);
            if ($sent === false) {
                throw new RuntimeException('Envoi du Magic Packet impossible: ' . socket_strerror(socket_last_error($socket)));
            }

            if ($sent !== strlen($packet)) {
                throw new RuntimeException(sprintf('Magic Packet tronque: %d/%d octets envoyes.', $sent, strlen($packet)));
            }

            return $sent;
        } finally {
            socket_close($socket);
        }
    }

    private function sendWithStreamSocket(string $packet, string $broadcastAddress, int $port): int
    {
        $context = stream_context_create([
            'socket' => [
                'so_broadcast' => true,
            ],
        ]);
        $handle = @stream_socket_client(
            sprintf('udp://%s:%d', $broadcastAddress, $port),
            $errno,
            $errorMessage,
            3,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($handle)) {
            throw new RuntimeException(sprintf('Ouverture du socket UDP impossible (%d): %s', $errno, $errorMessage));
        }

        try {
            $written = fwrite($handle, $packet);
            if ($written === false) {
                throw new RuntimeException('Envoi du Magic Packet impossible via stream socket.');
            }

            if ($written !== strlen($packet)) {
                throw new RuntimeException(sprintf('Magic Packet tronque via stream socket: %d/%d octets envoyes.', $written, strlen($packet)));
            }

            return $written;
        } finally {
            fclose($handle);
        }
    }

    private function normalizeMacAddress(string $macAddress): string
    {
        $cleanMac = preg_replace('/[^0-9A-Fa-f]/', '', $macAddress);
        if (!is_string($cleanMac) || strlen($cleanMac) !== 12) {
            throw new InvalidArgumentException('Adresse MAC invalide pour le reveil.');
        }

        return strtoupper($cleanMac);
    }

    private function buildDestinations(string $targetIp, string $broadcastAddress): array
    {
        $destinations = [];
        $seen = [];

        $append = static function (array &$items, array &$known, string $label, string $address): void {
            if ($address === '' || isset($known[$address])) {
                return;
            }

            $known[$address] = true;
            $items[] = [
                'label' => $label,
                'address' => $address,
            ];
        };

        $append($destinations, $seen, 'target_ip', $targetIp);
        $append($destinations, $seen, 'broadcast', $broadcastAddress);
        $append($destinations, $seen, 'global_broadcast', '255.255.255.255');

        return $destinations;
    }

    private function buildResult(
        string $cleanMac,
        int $port,
        string $transport,
        string $packet,
        array $successfulDestinations,
        array $failedDestinations
    ): array
    {
        return [
            'normalized_mac' => implode(':', str_split($cleanMac, 2)),
            'port' => $port,
            'transport' => $transport,
            'packet_size' => strlen($packet),
            'destinations' => $successfulDestinations,
            'failed_destinations' => $failedDestinations,
        ];
    }
}
