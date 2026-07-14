<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'vsr_center';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

echo "=== Pending Admin Delivery Status Migration ===\n\n";

try {
    $dsn = "mysql:unix_socket=$socket;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to database '$dbName'\n";

    try {
        $pdo->exec("ALTER TABLE visits MODIFY COLUMN status ENUM(
            'checked-in',
            'pending_admin_approval',
            'pending_approval',
            'approved',
            'in_progress',
            'pending_admin_delivery',
            'ready_for_delivery',
            'completed',
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'checked-in'");
        echo "[OK] Added pending_admin_delivery to visits.status ENUM\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already') !== false) {
            echo "[OK] visits.status ENUM already updated\n";
        } else {
            throw $e;
        }
    }

    echo "\n=== Migration Complete ===\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
