<?php
declare(strict_types=1);

function wake_request_id(): string
{
    static $requestId = null;

    if (is_string($requestId) && $requestId !== '') {
        return $requestId;
    }

    try {
        $requestId = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        $requestId = uniqid('', true);
    }

    return $requestId;
}

function wake_log(string $event, array $context = []): void
{
    if (defined('WAKE_LOG_ENABLED') && WAKE_LOG_ENABLED === false) {
        return;
    }

    $payload = [
        'timestamp' => gmdate('c'),
        'request_id' => wake_request_id(),
        'event' => $event,
        'hostname' => gethostname() ?: null,
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'context' => sanitize_log_context($context),
    ];

    $line = '[ShinedeWake] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    error_log($line);

    $logFile = defined('WAKE_LOG_FILE') ? trim((string)WAKE_LOG_FILE) : '';
    if ($logFile === '') {
        return;
    }

    $directory = dirname($logFile);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }

    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function wake_log_exception(string $event, Throwable $exception, array $context = []): void
{
    wake_log($event, $context + [
        'exception_class' => get_class($exception),
        'exception_message' => $exception->getMessage(),
        'exception_code' => $exception->getCode(),
        'exception_file' => $exception->getFile(),
        'exception_line' => $exception->getLine(),
    ]);
}

function sanitize_log_context(array $context): array
{
    $sanitized = [];

    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $sanitized[$key] = $value;
            continue;
        }

        if (is_array($value)) {
            $sanitized[$key] = sanitize_log_context($value);
            continue;
        }

        $sanitized[$key] = get_debug_type($value);
    }

    return $sanitized;
}
