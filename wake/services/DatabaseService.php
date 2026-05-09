<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class DatabaseService
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_TYPE, DB_HOST, DB_PORT, DB_NAME);

        self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$instance;
    }
}
