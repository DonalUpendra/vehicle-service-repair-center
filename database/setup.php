<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'vsr_center';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

echo "=== Vehicle Service & Repair Center - Database Setup ===\n\n";

try {
    $dsn = "mysql:unix_socket=$socket;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to MySQL\n";

    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "[OK] Dropped existing database (if any)\n";

    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Created database '$dbName'\n";

    $pdo->exec("USE `$dbName`");

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    echo "[OK] Tables created\n";

    $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
    $techHash = password_hash('tech123', PASSWORD_BCRYPT);

    $pdo->exec("INSERT INTO users (name, email, password_hash, role, active) VALUES
        ('Admin User', 'admin@garage.lk', '$adminHash', 'admin', 1),
        ('Technician One', 'tech@garage.lk', '$techHash', 'technician', 1),
        ('Technician Two', 'tech2@garage.lk', '$techHash', 'technician', 1)
    ");

    $pdo->exec("INSERT INTO products (name, unit_price, description, active) VALUES
        ('Engine Oil Change (5W-30)', 4500.00, 'Full synthetic engine oil change with filter', 1),
        ('Brake Pad Replacement (Front)', 8500.00, 'Front brake pad set replacement including labour', 1),
        ('Brake Pad Replacement (Rear)', 7500.00, 'Rear brake pad set replacement including labour', 1),
        ('Air Filter Replacement', 2500.00, 'Engine air filter replacement', 1),
        ('AC Gas Refill', 5500.00, 'Air conditioning refrigerant refill R134a', 1),
        ('Wheel Alignment', 3500.00, '4-wheel computerised alignment', 1),
        ('Full Service (Major)', 25000.00, 'Comprehensive major service: oil, filters, spark plugs, inspection', 1),
        ('Full Service (Minor)', 12000.00, 'Standard minor service: oil and filter change, inspection', 1),
        ('Battery Replacement', 18000.00, 'Maintenance-free battery replacement', 1),
        ('Wiper Blade Replacement', 2000.00, 'Pair of front wiper blades', 1),
        ('Tyre Replacement (each)', 22000.00, 'Single tyre replacement including fitting and balancing', 1),
        ('Diagnostic Scan', 3000.00, 'OBD-II computer diagnostic scan', 1),
        ('Shock Absorber Replacement (Pair)', 28000.00, 'Front shock absorber pair replacement', 1),
        ('Timing Belt Replacement', 35000.00, 'Timing belt kit replacement including tensioners', 1),
        ('Clutch Replacement', 45000.00, 'Full clutch kit replacement', 1)
    ");

    $pdo->exec("INSERT INTO vehicles (registration_number, make, model, owner_name, owner_email, owner_phone, odometer) VALUES
        ('WP-ABC-1234', 'Toyota', 'Corolla', 'John Silva', 'john@email.lk', '0712345678', 55000),
        ('WP-CDE-5678', 'Honda', 'Civic', 'Sarah Perera', 'sarah@email.lk', '0771234567', 32000),
        ('WP-FGH-9012', 'Suzuki', 'Alto', 'Kumar Fernando', 'kumar@email.lk', '0759876543', 68000)
    ");

    echo "[OK] Seed data inserted\n";
    echo "\n=== Setup Complete ===\n";
    echo "Default credentials:\n";
    echo "  Admin:      admin@garage.lk / admin123\n";
    echo "  Technician: tech@garage.lk / tech123\n";
    echo "  Technician: tech2@garage.lk / tech123\n";

} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "\nMake sure MySQL is running in XAMPP Control Panel.\n";
    echo "Start Apache and MySQL from XAMPP Control Panel first.\n";
}
