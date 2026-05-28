<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class ModelsController {

    public static function index() {
        requireAuth();
        $db = Database::getInstance()->getConnection();
        $makeId = isset($_GET['make_id']) ? (int)$_GET['make_id'] : null;

        if ($makeId) {
            $stmt = $db->prepare(
                'SELECT m.*, mk.name as make_name FROM vehicle_models m
                 JOIN vehicle_makes mk ON m.make_id = mk.id
                 WHERE m.make_id = ?
                 ORDER BY m.name ASC'
            );
            $stmt->execute([$makeId]);
        } else {
            $stmt = $db->query(
                'SELECT m.*, mk.name as make_name FROM vehicle_models m
                 JOIN vehicle_makes mk ON m.make_id = mk.id
                 ORDER BY mk.name ASC, m.name ASC'
            );
        }

        $models = $stmt->fetchAll();
        foreach ($models as &$m) {
            $m['id'] = (int)$m['id'];
            $m['make_id'] = (int)$m['make_id'];
        }
        jsonResponse($models);
    }

    public static function store() {
        requireAdmin();
        $data = getJsonInput();
        $makeId = (int)($data['make_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if (!$makeId || !$name) {
            jsonError('make_id and name are required', 400);
        }
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id FROM vehicle_makes WHERE id = ?');
        $stmt->execute([$makeId]);
        if (!$stmt->fetch()) {
            jsonError('Make not found', 404);
        }
        try {
            $stmt = $db->prepare('INSERT INTO vehicle_models (make_id, name) VALUES (?, ?)');
            $stmt->execute([$makeId, $name]);
            jsonResponse(['id' => (int)$db->lastInsertId(), 'make_id' => $makeId, 'name' => $name], 201);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                jsonError('This model already exists for this make', 409);
            }
            jsonError('Failed to create model', 500);
        }
    }

    public static function update($id) {
        requireAdmin();
        $data = getJsonInput();
        $name = trim($data['name'] ?? '');
        if (!$name) {
            jsonError('Model name is required', 400);
        }
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id FROM vehicle_models WHERE id = ?');
        $stmt->execute([(int)$id]);
        $model = $stmt->fetch();
        if (!$model) {
            jsonError('Model not found', 404);
        }
        try {
            $stmt = $db->prepare('UPDATE vehicle_models SET name = ? WHERE id = ?');
            $stmt->execute([$name, (int)$id]);
            jsonResponse(['message' => 'Model updated successfully']);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                jsonError('This model already exists for this make', 409);
            }
            jsonError('Failed to update model', 500);
        }
    }

    public static function destroy($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id FROM vehicle_models WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Model not found', 404);
        }
        $stmt = $db->prepare('DELETE FROM vehicle_models WHERE id = ?');
        $stmt->execute([(int)$id]);
        jsonResponse(['message' => 'Model deleted successfully']);
    }
}
