<?php
declare(strict_types=1);

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_success(?string $message = null, array $data = [], int $status = 200): void
{
    $payload = ['success' => true];

    if ($message !== null && $message !== '') {
        $payload['message'] = $message;
    }

    if ($data !== []) {
        $payload['data'] = $data;
    }

    json_response($status, $payload);
}

function json_error(string $message, int $status = 400, array $data = []): void
{
    $payload = [
        'success' => false,
        'error' => $message,
    ];

    if ($data !== []) {
        $payload['data'] = $data;
    }

    json_response($status, $payload);
}
