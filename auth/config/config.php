<?php
// Values come from environment when available, with sensible defaults
define('BASE_API', $_ENV['BASE_API'] ?? 'https://api.shinederu.lol/auth/');

// Comma-separated list in .env, fallback to common image types
$allowedMimeEnv = $_ENV['ALLOWED_MIME'] ?? 'image/png,image/jpeg,image/webp';
define('ALLOWED_MIME', array_map('trim', explode(',', $allowedMimeEnv)));

// Session duration (hours). Default: 7 days (168 hours)
define('SESSION_DURATION_HOURS', (int)($_ENV['SESSION_DURATION_HOURS'] ?? 168));
?>
