<?php
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTP_AUTH', filter_var($_ENV['SMTP_AUTH'], FILTER_VALIDATE_BOOLEAN));
define('SMTP_USER', $_ENV['SMTP_USER']);
define('SMTP_PASS', $_ENV['SMTP_PASS']);
define('SMTP_PORT', $_ENV['SMTP_PORT']);
define('SMTP_FROM', $_ENV['SMTP_FROM']);
define('SMTP_NAME', $_ENV['SMTP_NAME']);
?>