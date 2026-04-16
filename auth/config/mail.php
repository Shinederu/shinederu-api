<?php
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
define('SMTP_AUTH', filter_var($_ENV['SMTP_AUTH'] ?? true, FILTER_VALIDATE_BOOLEAN));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 465));
define(
    'SMTP_SECURE',
    strtolower(trim((string)($_ENV['SMTP_SECURE'] ?? (SMTP_PORT === 587 ? 'starttls' : (SMTP_PORT === 465 ? 'smtps' : '')))))
);
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? '');
define('SMTP_NAME', $_ENV['SMTP_NAME'] ?? '');
