<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once(__DIR__ . '/../config/database.php');
use Medoo\Medoo;
class DatabaseService
{
    private static $instance = null;
    private $medoo;
    private function __construct()
    {
        $this->medoo = new Medoo([
            'type' => DB_TYPE,
            'host' => DB_HOST,
            'port' => DB_PORT,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASS,
        ]);
    }
    /*
    private function __clone()
    {
    }
    private function __wakeup()
    {
    }
    */
    public static function getInstance(): Medoo
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->medoo;
    }
}
?>