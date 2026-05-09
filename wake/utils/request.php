<?php
declare(strict_types=1);

function get_request_body(): array
{
    static $body = null;

    if (is_array($body)) {
        return $body;
    }

    $body = [];
    $rawInput = file_get_contents('php://input');

    if (is_string($rawInput) && trim($rawInput) !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $body = $decoded;
            return $body;
        }
    }

    if (!empty($_POST)) {
        $body = $_POST;
    }

    return $body;
}

function get_request_action(): ?string
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        return isset($_GET['action']) ? trim((string)$_GET['action']) : null;
    }

    $body = get_request_body();

    if (!empty($body['action'])) {
        return trim((string)$body['action']);
    }

    if (!empty($_POST['action'])) {
        return trim((string)$_POST['action']);
    }

    if (!empty($_REQUEST['action'])) {
        return trim((string)$_REQUEST['action']);
    }

    return null;
}

function get_session_id(): ?string
{
    if (!empty($_COOKIE['sid'])) {
        return trim((string)$_COOKIE['sid']);
    }

    if (!empty($_COOKIE['session_id'])) {
        return trim((string)$_COOKIE['session_id']);
    }

    if (!empty($_SERVER['HTTP_X_SESSION_ID'])) {
        return trim((string)$_SERVER['HTTP_X_SESSION_ID']);
    }

    if (!empty($_SERVER['HTTP_SESSION_ID'])) {
        return trim((string)$_SERVER['HTTP_SESSION_ID']);
    }

    return null;
}

function to_bool($value): bool
{
    if ($value === null) {
        return false;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (int)$value === 1;
    }

    $normalized = strtolower(trim((string)$value));

    return in_array($normalized, ['1', 'true', 'yes', 'on', 'admin'], true);
}
