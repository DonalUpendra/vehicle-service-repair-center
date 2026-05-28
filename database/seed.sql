USE vsr_center;

INSERT INTO users (name, email, password_hash, role, active) VALUES
('Admin User', 'admin@garage.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
('Technician One', 'tech@garage.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', 1),
('Technician Two', 'tech2@garage.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', 1);

INSERT INTO products (name, unit_price, description, active) VALUES
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
('Clutch Replacement', 45000.00, 'Full clutch kit replacement', 1);

INSERT INTO vehicles (registration_number, make, model, owner_name, owner_email, owner_phone, odometer) VALUES
('WP-ABC-1234', 'Toyota', 'Corolla', 'John Silva', 'john@email.lk', '0712345678', 55000),
('WP-CDE-5678', 'Honda', 'Civic', 'Sarah Perera', 'sarah@email.lk', '0771234567', 32000),
('WP-FGH-9012', 'Suzuki', 'Alto', 'Kumar Fernando', 'kumar@email.lk', '0759876543', 68000);
