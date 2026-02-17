<?php

function sanitizeRegisterInput(array $data): array
{
    return [
        'username' => trim(htmlspecialchars($data['username'] ?? '', ENT_QUOTES, 'UTF-8')),
        'email' => trim(htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8')),
        'password' => $data['password'] ?? '',
        'password_confirm' => $data['password_confirm'] ?? '',
    ];
}

function sanitizeLoginInput(array $data): array
{
    // login = username ou email + password
    return [
        'username' => trim(htmlspecialchars($data['username'] ?? '', ENT_QUOTES, 'UTF-8')),
        'password' => $data['password'] ?? '',
    ];
}

function sanitizeVerifyEmailInput(array $data): array
{
    return [
        'token' => trim($data['token'] ?? ''),
    ];
}

function sanitizeArray(array $data): array
{
    $clean = [];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            // Si c’est un sous-tableau, on le nettoie récursivement
            $clean[$key] = sanitizeArray($value);
        } else {
            // Si c’est une valeur simple, on la nettoie
            $clean[$key] = trim(htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'));
        }
    }

    return $clean;
}

function sanitizeEmailInput(array $data): array
{
    return [
        'email' => trim(htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8')),
    ];
}

?>
