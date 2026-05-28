<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class MakesController {

    public static function index() {
        requireAuth();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query('SELECT * FROM vehicle_makes ORDER BY name ASC');
        $makes = $stmt->fetchAll();
        foreach ($makes as &$m) {
            $m['id'] = (int)$m['id'];
        }
        jsonResponse($makes);
    }

    public static function store() {
        requireAdmin();
        $data = getJsonInput();
        $name = trim($data['name'] ?? '');
        if (!$name) {
            jsonError('Make name is required', 400);
        }
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('INSERT INTO vehicle_makes (name) VALUES (?)');
        try {
            $stmt->execute([$name]);
            jsonResponse(['id' => (int)$db->lastInsertId(), 'name' => $name], 201);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                jsonError('This make already exists', 409);
            }
            jsonError('Failed to create make', 500);
        }
    }

    public static function update($id) {
        requireAdmin();
        $data = getJsonInput();
        $name = trim($data['name'] ?? '');
        if (!$name) {
            jsonError('Make name is required', 400);
        }
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id FROM vehicle_makes WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Make not found', 404);
        }
        try {
            $stmt = $db->prepare('UPDATE vehicle_makes SET name = ? WHERE id = ?');
            $stmt->execute([$name, (int)$id]);
            jsonResponse(['message' => 'Make updated successfully']);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                jsonError('This make already exists', 409);
            }
            jsonError('Failed to update make', 500);
        }
    }

    public static function destroy($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id FROM vehicle_makes WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Make not found', 404);
        }
        $stmt = $db->prepare('DELETE FROM vehicle_makes WHERE id = ?');
        $stmt->execute([(int)$id]);
        jsonResponse(['message' => 'Make deleted successfully']);
    }
}
