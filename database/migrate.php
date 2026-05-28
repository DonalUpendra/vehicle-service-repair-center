<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'vsr_center';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

echo "=== Vehicle Service & Repair Center - Database Migration ===\n\n";

try {
    $dsn = "mysql:unix_socket=$socket;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to database '$dbName'\n";

    try {
        $pdo->exec("ALTER TABLE approval_tokens ADD COLUMN description VARCHAR(500) DEFAULT NULL");
        echo "[OK] Added approval_tokens.description column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] approval_tokens.description already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE bills ADD COLUMN estimated_delivery DATETIME DEFAULT NULL");
        echo "[OK] Added bills.estimated_delivery column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] bills.estimated_delivery already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            message TEXT,
            link VARCHAR(500) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "[OK] Created notifications table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] notifications table already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        echo "[OK] Created settings table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] settings table already exists\n";
        } else {
            throw $e;
        }
    }

    $defaults = [
        ['smtp_host', 'mail.spacemail.com'],
        ['smtp_port', '465'],
        ['smtp_encryption', 'ssl'],
        ['smtp_username', 'info@luminaautoparts.com'],
        ['smtp_password', 'LWr##pBrZpNKP49'],
        ['smtp_from_email', 'info@luminaautoparts.com'],
        ['smtp_from_name', 'Lumina Auto Parts'],
        ['email_enabled', '1'],
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($defaults as $d) {
        $stmt->execute($d);
    }
    echo "[OK] Default settings inserted\n";
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN buy_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unit_price");
        echo "[OK] Added products.buy_price column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] products.buy_price already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE bill_items ADD COLUMN buy_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unit_price");
        echo "[OK] Added bill_items.buy_price column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] bill_items.buy_price already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE bill_items ADD COLUMN line_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER line_total");
        echo "[OK] Added bill_items.line_cost column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] bill_items.line_cost already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN commission_percentage DECIMAL(5,2) NOT NULL DEFAULT 0");
        echo "[OK] Added users.commission_percentage column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[OK] users.commission_percentage already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS salary_payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            technician_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_date DATE NOT NULL,
            notes VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "[OK] Created salary_payments table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] salary_payments table already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\n=== Migration Complete ===\n";

} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "\nMake sure MySQL is running in XAMPP Control Panel.\n";
}
