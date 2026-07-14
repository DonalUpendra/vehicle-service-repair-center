<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'vsr_center';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

echo "=== Push Notifications Migration ===\n\n";

try {
    $dsn = "mysql:unix_socket=$socket;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to database '$dbName'\n";

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(100) NOT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "[OK] Created push_subscriptions table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] push_subscriptions table already exists\n";
        } else {
            throw $e;
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
    $stmt->execute(['vapid_public_key']);
    if ((int)$stmt->fetchColumn() === 0) {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];
        $key = openssl_pkey_new($config);
        if (!$key) {
            throw new Exception('Failed to generate VAPID keys: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($key);

        $publicKeyRaw = hex2bin('04') .
            hex2bin(bin2hex($details['ec']['x'])) .
            hex2bin(bin2hex($details['ec']['y']));
        $publicKey = rtrim(strtr(base64_encode($publicKeyRaw), '+/', '-_'), '=');

        openssl_pkey_export($key, $privatePem);
        $lines = explode("\n", trim($privatePem));
        $keyStr = '';
        foreach ($lines as $line) {
            if (strpos($line, '-----') === false) {
                $keyStr .= $line;
            }
        }
        $privateKey = rtrim(strtr(base64_encode(base64_decode($keyStr)), '+/', '-_'), '=');

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)');
        $stmt->execute(['vapid_public_key', $publicKey]);
        $stmt->execute(['vapid_private_key', $privateKey]);
        echo "[OK] Generated and saved VAPID keys\n";
    } else {
        echo "[OK] VAPID keys already exist\n";
    }

    echo "\n=== Migration Complete ===\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
