<?php
$dbConfig = [];
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    $dbConfig = require $configFile;
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        global $dbConfig;
        $host = $dbConfig['db_host'] ?? (getenv('DB_HOST') ?: 'localhost');
        $name = $dbConfig['db_name'] ?? (getenv('DB_NAME') ?: 'vsr_center');
        $user = $dbConfig['db_user'] ?? (getenv('DB_USER') ?: 'root');
        $pass = $dbConfig['db_pass'] ?? (getenv('DB_PASS') ?: '');
        $charset = $dbConfig['db_charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}
