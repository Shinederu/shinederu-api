<?php

function json_success(?string $message = null, $data = null, int $status = 200): void
{
    http_response_code($status);
    $payload = ['success' => true];
    if ($message !== null) {
        $payload['message'] = $message;
    }
    if ($data !== null) {
        $payload['data'] = $data;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, $errors = null): void
{
    http_response_code($status);
    $payload = ['success' => false, 'error' => $message];
    if ($errors !== null) {
        $payload['errors'] = $errors;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

?>
