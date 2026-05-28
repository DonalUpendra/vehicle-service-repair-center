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

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_makes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        echo "[OK] Created vehicle_makes table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] vehicle_makes table already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_models (
            id INT PRIMARY KEY AUTO_INCREMENT,
            make_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (make_id) REFERENCES vehicle_makes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_model_per_make (make_id, name)
        ) ENGINE=InnoDB");
        echo "[OK] Created vehicle_models table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[OK] vehicle_models table already exists\n";
        } else {
            throw $e;
        }
    }

    // Seed common makes and models
    $commonMakes = [
        'Toyota' => ['Camry', 'Corolla', 'Hilux', 'Land Cruiser', 'Prado', 'RAV4', 'Yaris', 'Vitz', 'Premio', 'Allion', 'Axio', 'Rush', 'Hiace', 'Fortuner'],
        'Honda' => ['Civic', 'Accord', 'CR-V', 'Fit', 'Grace', 'Vezel', 'City', 'HR-V', 'Odyssey', 'Stepwgn'],
        'Suzuki' => ['Alto', 'Swift', 'Wagon R', 'Vitara', 'Jimny', 'Celerio', 'Ertiga', 'Baleno', 'Ciaz', 'S-Presso'],
        'Nissan' => ['Sunny', 'March', 'X-Trail', 'Patrol', 'Navara', 'Teana', 'Juke', 'Leaf', 'Note', 'Dualis'],
        'Mitsubishi' => ['Lancer', 'Montero', 'Outlander', 'Pajero', 'Mirage', 'Colt', 'ASX', 'Delica', 'L200'],
        'BMW' => ['3 Series', '5 Series', '7 Series', 'X1', 'X3', 'X5', 'X7', 'M3', 'M5', 'Z4', 'i4'],
        'Mercedes-Benz' => ['A-Class', 'C-Class', 'E-Class', 'S-Class', 'GLC', 'GLE', 'GLS', 'G-Wagon', 'CLA', 'EQC'],
        'Audi' => ['A3', 'A4', 'A6', 'A8', 'Q3', 'Q5', 'Q7', 'Q8', 'e-tron', 'TT'],
        'Volkswagen' => ['Golf', 'Passat', 'Tiguan', 'Polo', 'Jetta', 'Beetle', 'T-Cross', 'Touareg', 'Arteon'],
        'Ford' => ['Focus', 'Fiesta', 'Mustang', 'Ranger', 'Everest', 'Escape', 'Explorer', 'Endeavour', 'Figo'],
        'Hyundai' => ['Elantra', 'Tucson', 'Santa Fe', 'i10', 'i20', 'Creta', 'Grand i10', 'Kona', 'Sonata'],
        'Kia' => ['Picanto', 'Rio', 'Cerato', 'Seltos', 'Sportage', 'Sorento', 'Carnival', 'Stinger', 'EV6'],
        'Mazda' => ['3', '6', 'CX-3', 'CX-5', 'CX-9', 'MX-5', 'BT-50', 'Demio', 'Atenza'],
        'Subaru' => ['Impreza', 'Outback', 'Forester', 'Legacy', 'XV', 'WRX', 'BRZ', 'Levorg'],
        'Tata' => ['Nano', 'Indica', 'Indigo', 'Sumo', 'Safari', 'Nexon', 'Tiago', 'Harrier', 'Punch'],
        'Mahindra' => ['Scorpio', 'XUV500', 'Bolero', 'Thar', 'XUV300', 'KUV100', 'Marazzo', 'Alturas G4'],
        'Land Rover' => ['Range Rover', 'Range Rover Sport', 'Range Rover Evoque', 'Discovery', 'Discovery Sport', 'Defender'],
        'Jaguar' => ['XE', 'XF', 'XJ', 'F-PACE', 'E-PACE', 'I-PACE', 'F-TYPE'],
        'Volvo' => ['XC40', 'XC60', 'XC90', 'S60', 'S90', 'V60', 'V90', 'C40'],
        'Lexus' => ['ES', 'IS', 'LS', 'RX', 'NX', 'UX', 'LX', 'GX'],
    ];

    $insertMake = $pdo->prepare('INSERT IGNORE INTO vehicle_makes (name) VALUES (?)');
    $insertModel = $pdo->prepare('INSERT IGNORE INTO vehicle_models (make_id, name) VALUES (?, ?)');
    $getMake = $pdo->prepare('SELECT id FROM vehicle_makes WHERE name = ?');

    $makeCount = 0;
    $modelCount = 0;
    foreach ($commonMakes as $makeName => $models) {
        $insertMake->execute([$makeName]);
        if ($insertMake->rowCount() > 0) $makeCount++;
        $getMake->execute([$makeName]);
        $makeId = (int)$getMake->fetchColumn();
        foreach ($models as $modelName) {
            $insertModel->execute([$makeId, $modelName]);
            if ($insertModel->rowCount() > 0) $modelCount++;
        }
    }
    echo "[OK] Seeded $makeCount makes and $modelCount models\n";

    echo "\n=== Migration Complete ===\n";

} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "\nMake sure MySQL is running in XAMPP Control Panel.\n";
}
