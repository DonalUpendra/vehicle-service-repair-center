<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class UserController {

    public static function index() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, name, email, role, active, commission_percentage, created_at FROM users WHERE role = ? ORDER BY id DESC');
        $stmt->execute(['technician']);
        $techs = $stmt->fetchAll();
        foreach ($techs as &$t) {
            $t['commission_percentage'] = (float)$t['commission_percentage'];
        }
        jsonResponse($techs);
    }

    public static function store() {
        requireAdmin();
        $data = getJsonInput();
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $commissionPercentage = (float)($data['commission_percentage'] ?? $data['commissionPercentage'] ?? 0);

        if (!$name || !$email || !$password) {
            jsonError('Name, email, and password are required', 400);
        }

        if (strlen($password) < 4) {
            jsonError('Password must be at least 4 characters', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonError('Email already exists', 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, active, commission_percentage) VALUES (?, ?, ?, ?, 1, ?)');
        $stmt->execute([$name, $email, $hash, 'technician', $commissionPercentage]);

        $id = (int)$db->lastInsertId();
        jsonResponse([
            'id'    => $id,
            'name'  => $name,
            'email' => $email,
            'role'  => 'technician',
            'active' => true,
            'commission_percentage' => $commissionPercentage,
        ], 201);
    }

    public static function update($id) {
        requireAdmin();
        $data = getJsonInput();
        $db = Database::getInstance()->getConnection();

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $params[] = trim($data['email']);
        }
        if (isset($data['active'])) {
            $fields[] = 'active = ?';
            $params[] = $data['active'] ? 1 : 0;
        }
        if (isset($data['commission_percentage']) || isset($data['commissionPercentage'])) {
            $fields[] = 'commission_percentage = ?';
            $params[] = (float)($data['commission_percentage'] ?? $data['commissionPercentage'] ?? 0);
        }

        if (empty($fields)) {
            jsonError('No fields to update', 400);
        }

        $params[] = (int)$id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['message' => 'User updated successfully']);
    }

    public static function destroy($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([(int)$id]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonError('User not found', 404);
        }
        if ($user['role'] === 'admin') {
            jsonError('Cannot delete admin users', 403);
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([(int)$id]);

        jsonResponse(['message' => 'User deleted successfully']);
    }
}