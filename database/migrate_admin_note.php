<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'vsr_center';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

echo "=== Admin Note Column Migration ===\n\n";

try {
    $dsn = "mysql:unix_socket=$socket;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to database '$dbName'\n";

    try {
        $pdo->exec("ALTER TABLE bills ADD COLUMN admin_note VARCHAR(500) DEFAULT NULL");
        echo "[OK] Added bills.admin_note column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] bills.admin_note already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\n=== Migration Complete ===\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
