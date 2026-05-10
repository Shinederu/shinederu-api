<?php
declare(strict_types=1);

class PingService
{
    public function getPowerState(?string $targetIp): array
    {
        $targetIp = trim((string)$targetIp);

        if (!WAKE_PING_ENABLED) {
            return $this->buildState('unknown', 'Indetermine', 'Verification desactivee.');
        }

        if ($targetIp === '') {
            return $this->buildState('unknown', 'Indetermine', 'Aucune IP cible renseignee.');
        }

        if (filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return $this->buildState('unknown', 'Indetermine', 'IP cible invalide.');
        }

        if (!function_exists('proc_open') || !function_exists('proc_close')) {
            return $this->buildState('unknown', 'Indetermine', 'Les commandes systeme sont indisponibles.');
        }

        $command = $this->buildPingCommand($targetIp);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return $this->buildState('unknown', 'Indetermine', 'Impossible de lancer la commande ping.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode === 0) {
            return $this->buildState('online', 'Allume', null);
        }

        $combinedOutput = trim($stdout . "\n" . $stderr);
        if (stripos($combinedOutput, 'not found') !== false || stripos($combinedOutput, 'introuvable') !== false) {
            return $this->buildState('unknown', 'Indetermine', 'Commande ping introuvable dans le conteneur.');
        }

        return $this->buildState('offline', 'Eteint', null);
    }

    private function buildPingCommand(string $targetIp): string
    {
        $escapedIp = escapeshellarg($targetIp);

        if (PHP_OS_FAMILY === 'Windows') {
            return sprintf('ping -n 1 -w %d %s', WAKE_PING_TIMEOUT_SECONDS * 1000, $escapedIp);
        }

        return sprintf('ping -n -c 1 -W %d %s', WAKE_PING_TIMEOUT_SECONDS, $escapedIp);
    }

    private function buildState(string $state, string $label, ?string $reason): array
    {
        return [
            'power_state' => $state,
            'power_state_label' => $label,
            'power_state_reason' => $reason,
        ];
    }
}
