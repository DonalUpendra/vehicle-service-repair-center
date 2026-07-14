<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'vsr_center';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

echo "=== Status History Audit Log Migration ===\n\n";

try {
    $dsn = "mysql:unix_socket=$socket;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to database '$dbName'\n";

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS status_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            bill_id INT NOT NULL,
            visit_id INT NOT NULL,
            old_status VARCHAR(50) DEFAULT NULL,
            new_status VARCHAR(50) NOT NULL,
            changed_by INT DEFAULT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'internal',
            note VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        echo "[OK] Created status_history table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] status_history table already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\n=== Migration Complete ===\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
