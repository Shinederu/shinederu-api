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

        $command = $this->buildPingCommand($targetIp);
        $result = $this->runCommand($command);

        if ($result['ok']) {
            return $this->buildState('online', 'Allume', null);
        }

        if ($this->isPingCommandMissing($result['output'])) {
            return $this->buildState('unknown', 'Indetermine', 'Commande ping introuvable dans le conteneur.');
        }

        if ($this->isPingEnvironmentError($result['output'])) {
            return $this->buildState('unknown', 'Indetermine', 'Le conteneur PHP ne peut pas executer ping correctement.');
        }

        if ($result['backend'] === 'none') {
            return $this->buildState('unknown', 'Indetermine', $result['reason']);
        }

        if ($result['exit_code'] === null) {
            return $this->buildState('unknown', 'Indetermine', $result['reason']);
        }

        return $this->buildState('offline', 'Eteint', null);
    }

    private function buildPingCommand(string $targetIp): string
    {
        $escapedIp = escapeshellarg($targetIp);
        $binary = trim(WAKE_PING_COMMAND) !== '' ? trim(WAKE_PING_COMMAND) : 'ping';

        if (PHP_OS_FAMILY === 'Windows') {
            return sprintf('%s -n 1 -w %d %s', $binary, WAKE_PING_TIMEOUT_SECONDS * 1000, $escapedIp);
        }

        return sprintf('%s -n -c 1 -W %d %s', $binary, WAKE_PING_TIMEOUT_SECONDS, $escapedIp);
    }

    private function runCommand(string $command): array
    {
        $procOpenResult = $this->runWithProcOpen($command);
        if ($procOpenResult !== null) {
            return $procOpenResult;
        }

        $execResult = $this->runWithExec($command);
        if ($execResult !== null) {
            return $execResult;
        }

        $shellExecResult = $this->runWithShellExec($command);
        if ($shellExecResult !== null) {
            return $shellExecResult;
        }

        return [
            'ok' => false,
            'backend' => 'none',
            'exit_code' => null,
            'output' => '',
            'reason' => 'Aucune fonction systeme PHP disponible pour lancer ping.',
        ];
    }

    private function runWithProcOpen(string $command): ?array
    {
        if (!$this->isFunctionUsable('proc_open') || !$this->isFunctionUsable('proc_close')) {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return [
                'ok' => false,
                'backend' => 'proc_open',
                'exit_code' => null,
                'output' => '',
                'reason' => 'Impossible de lancer la commande ping via proc_open.',
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'ok' => $exitCode === 0,
            'backend' => 'proc_open',
            'exit_code' => $exitCode,
            'output' => trim((string)$stdout . "\n" . (string)$stderr),
            'reason' => $exitCode === 0 ? null : 'La commande ping a echoue via proc_open.',
        ];
    }

    private function runWithExec(string $command): ?array
    {
        if (!$this->isFunctionUsable('exec')) {
            return null;
        }

        $outputLines = [];
        $exitCode = null;

        @exec($command . ' 2>&1', $outputLines, $exitCode);

        if ($exitCode === null) {
            return [
                'ok' => false,
                'backend' => 'exec',
                'exit_code' => null,
                'output' => implode("\n", $outputLines),
                'reason' => 'La commande ping n a pas retourne de code via exec.',
            ];
        }

        return [
            'ok' => $exitCode === 0,
            'backend' => 'exec',
            'exit_code' => $exitCode,
            'output' => trim(implode("\n", $outputLines)),
            'reason' => $exitCode === 0 ? null : 'La commande ping a echoue via exec.',
        ];
    }

    private function runWithShellExec(string $command): ?array
    {
        if (!$this->isFunctionUsable('shell_exec')) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $wrappedCommand = sprintf('%s 2>&1 & echo __EXIT__%%errorlevel%%', $command);
        } else {
            $wrappedCommand = sprintf('%s 2>&1; echo __EXIT__$?', $command);
        }

        $output = @shell_exec($wrappedCommand);
        if (!is_string($output) || trim($output) === '') {
            return [
                'ok' => false,
                'backend' => 'shell_exec',
                'exit_code' => null,
                'output' => '',
                'reason' => 'La commande ping n a renvoye aucune sortie via shell_exec.',
            ];
        }

        $exitCode = $this->extractExitCodeFromShellOutput($output);
        if ($exitCode === null) {
            return [
                'ok' => false,
                'backend' => 'shell_exec',
                'exit_code' => null,
                'output' => trim($output),
                'reason' => 'Impossible de lire le code retour ping via shell_exec.',
            ];
        }

        return [
            'ok' => $exitCode === 0,
            'backend' => 'shell_exec',
            'exit_code' => $exitCode,
            'output' => trim(preg_replace('/__EXIT__\d+\s*$/', '', $output) ?? $output),
            'reason' => $exitCode === 0 ? null : 'La commande ping a echoue via shell_exec.',
        ];
    }

    private function extractExitCodeFromShellOutput(string $output): ?int
    {
        if (!preg_match('/__EXIT__(\d+)\s*$/', trim($output), $matches)) {
            return null;
        }

        return isset($matches[1]) ? (int)$matches[1] : null;
    }

    private function isPingCommandMissing(string $output): bool
    {
        $output = mb_strtolower($output);

        return str_contains($output, 'not found')
            || str_contains($output, 'introuvable')
            || str_contains($output, 'command not found')
            || str_contains($output, 'is not recognized');
    }

    private function isPingEnvironmentError(string $output): bool
    {
        $output = mb_strtolower($output);

        return str_contains($output, 'operation not permitted')
            || str_contains($output, 'permission denied')
            || str_contains($output, 'socket: operation not permitted')
            || str_contains($output, 'socket: permission denied');
    }

    private function isFunctionUsable(string $functionName): bool
    {
        if (!function_exists($functionName)) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

        return !in_array($functionName, $disabled, true);
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
