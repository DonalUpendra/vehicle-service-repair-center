<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class ProductController {

    public static function index() {
        requireAuth();
        $db = Database::getInstance()->getConnection();

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $sql = 'SELECT * FROM products WHERE active = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE ? OR description LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= ' ORDER BY name';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        foreach ($products as &$p) {
            $p['id'] = (int)$p['id'];
            $p['unit_price'] = (float)$p['unit_price'];
            $p['buy_price'] = (float)$p['buy_price'];
        }

        jsonResponse($products);
    }

    public static function store() {
        requireAdmin();
        $data = getJsonInput();
        $name = trim($data['name'] ?? '');
        $unitPrice = (float)($data['unit_price'] ?? $data['unitPrice'] ?? 0);
        $buyPrice = (float)($data['buy_price'] ?? $data['buyPrice'] ?? 0);
        $description = trim($data['description'] ?? '');

        if (!$name) {
            jsonError('Product name is required', 400);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('INSERT INTO products (name, unit_price, buy_price, description, active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$name, $unitPrice, $buyPrice, $description]);
        $id = (int)$db->lastInsertId();

        jsonResponse([
            'id'          => $id,
            'name'        => $name,
            'unit_price'  => $unitPrice,
            'buy_price'   => $buyPrice,
            'description' => $description,
            'active'      => true,
        ], 201);
    }

    public static function update($id) {
        requireAdmin();
        $data = getJsonInput();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id FROM products WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Product not found', 404);
        }

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['unit_price']) || isset($data['unitPrice'])) {
            $fields[] = 'unit_price = ?';
            $params[] = (float)($data['unit_price'] ?? $data['unitPrice'] ?? 0);
        }
        if (isset($data['buy_price']) || isset($data['buyPrice'])) {
            $fields[] = 'buy_price = ?';
            $params[] = (float)($data['buy_price'] ?? $data['buyPrice'] ?? 0);
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = trim($data['description']);
        }

        if (empty($fields)) {
            jsonError('No fields to update', 400);
        }

        $params[] = (int)$id;
        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['message' => 'Product updated successfully']);
    }

    public static function destroy($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('UPDATE products SET active = 0 WHERE id = ?');
        $stmt->execute([(int)$id]);

        jsonResponse(['message' => 'Product deleted successfully']);
    }
}