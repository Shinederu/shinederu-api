<?php
declare(strict_types=1);

class WakeService
{
    public function sendMagicPacket(string $macAddress, string $broadcastAddress = '', int $port = WAKE_DEFAULT_PORT): void
    {
        $cleanMac = preg_replace('/[^0-9A-Fa-f]/', '', $macAddress);
        if (!is_string($cleanMac) || strlen($cleanMac) !== 12) {
            throw new InvalidArgumentException('Adresse MAC invalide pour le reveil.');
        }

        $broadcastAddress = trim($broadcastAddress) !== '' ? trim($broadcastAddress) : WAKE_DEFAULT_BROADCAST;
        if (filter_var($broadcastAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Adresse de broadcast invalide.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port WOL invalide.');
        }

        $packet = hex2bin(str_repeat('FF', 6) . str_repeat(strtoupper($cleanMac), 16));
        if (!is_string($packet)) {
            throw new RuntimeException('Impossible de generer le Magic Packet.');
        }

        if (function_exists('socket_create')) {
            $this->sendWithSocketExtension($packet, $broadcastAddress, $port);
            return;
        }

        $this->sendWithStreamSocket($packet, $broadcastAddress, $port);
    }

    private function sendWithSocketExtension(string $packet, string $broadcastAddress, int $port): void
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            throw new RuntimeException('Creation du socket UDP impossible.');
        }

        try {
            if (!socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1)) {
                throw new RuntimeException('Activation du broadcast UDP impossible.');
            }

            $sent = @socket_sendto($socket, $packet, strlen($packet), 0, $broadcastAddress, $port);
            if ($sent === false || $sent <= 0) {
                throw new RuntimeException('Envoi du Magic Packet impossible.');
            }
        } finally {
            socket_close($socket);
        }
    }

    private function sendWithStreamSocket(string $packet, string $broadcastAddress, int $port): void
    {
        $handle = @stream_socket_client(
            sprintf('udp://%s:%d', $broadcastAddress, $port),
            $errno,
            $errorMessage,
            3,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($handle)) {
            throw new RuntimeException('Ouverture du socket UDP impossible: ' . $errorMessage);
        }

        try {
            $written = fwrite($handle, $packet);
            if ($written === false || $written <= 0) {
                throw new RuntimeException('Envoi du Magic Packet impossible.');
            }
        } finally {
            fclose($handle);
        }
    }
}
