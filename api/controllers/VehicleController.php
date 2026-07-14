<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../services/PushService.php';
require_once __DIR__ . '/NotificationController.php';

class VehicleController {

    public static function index() {
        requireAuth();
        $db = Database::getInstance()->getConnection();
        $search = trim($_GET['search'] ?? '');

        if ($search) {
            $like = '%' . $search . '%';
            $stmt = $db->prepare(
                'SELECT v.*, MAX(vi.check_in_date) as last_visit_date, vi.status as last_visit_status
                 FROM vehicles v
                 LEFT JOIN visits vi ON v.id = vi.vehicle_id
                 WHERE v.registration_number LIKE ? OR v.owner_name LIKE ? OR v.make LIKE ? OR v.model LIKE ?
                 GROUP BY v.id
                 ORDER BY v.id DESC'
            );
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $db->prepare(
                'SELECT v.*, MAX(vi.check_in_date) as last_visit_date, vi.status as last_visit_status
                 FROM vehicles v
                 LEFT JOIN visits vi ON v.id = vi.vehicle_id
                 GROUP BY v.id
                 ORDER BY v.id DESC'
            );
            $stmt->execute();
        }

        $vehicles = $stmt->fetchAll();

        foreach ($vehicles as &$v) {
            $v['id'] = (int)$v['id'];
            $v['odometer'] = $v['odometer'] ? (int)$v['odometer'] : 0;
        }

        jsonResponse($vehicles);
    }

    public static function show($id) {
        requireAuth();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM vehicles WHERE id = ?');
        $stmt->execute([(int)$id]);
        $vehicle = $stmt->fetch();

        if (!$vehicle) {
            jsonError('Vehicle not found', 404);
        }

        $vehicle['id'] = (int)$vehicle['id'];
        $vehicle['odometer'] = $vehicle['odometer'] ? (int)$vehicle['odometer'] : 0;

        $stmt = $db->prepare(
            'SELECT vi.*, b.id as bill_id, b.status as bill_status, b.total_amount
             FROM visits vi
             LEFT JOIN bills b ON vi.id = b.visit_id
             WHERE vi.vehicle_id = ?
             ORDER BY vi.check_in_date DESC'
        );
        $stmt->execute([(int)$id]);
        $visits = $stmt->fetchAll();

        foreach ($visits as &$v) {
            $v['id'] = (int)$v['id'];
            $v['vehicle_id'] = (int)$v['vehicle_id'];
            $v['bill_id'] = $v['bill_id'] ? (int)$v['bill_id'] : null;
        }

        $vehicle['visits'] = $visits;
        jsonResponse($vehicle);
    }

    public static function checkin() {
        requireTechnician();
        $data = getJsonInput();

        $regNumber = trim($data['registration_number'] ?? $data['registrationNumber'] ?? '');
        $make = trim($data['make'] ?? '');
        $model = trim($data['model'] ?? '');
        $ownerName = trim($data['owner_name'] ?? $data['ownerName'] ?? '');
        $ownerEmail = trim($data['owner_email'] ?? $data['ownerEmail'] ?? '');
        $ownerPhone = trim($data['owner_phone'] ?? $data['ownerPhone'] ?? '');
        $odometer = (int)($data['odometer'] ?? 0);
        $issues = trim($data['issues'] ?? '');

        if (!$regNumber || !$ownerName) {
            jsonError('Registration number and owner name are required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM vehicles WHERE registration_number = ?');
        $stmt->execute([$regNumber]);
        $existing = $stmt->fetch();

        $db->beginTransaction();
        try {
            if ($existing) {
                $vehicleId = (int)$existing['id'];
                $stmt = $db->prepare(
                    'UPDATE vehicles SET make=?, model=?, owner_name=?, owner_email=?, owner_phone=?, odometer=? WHERE id=?'
                );
                $stmt->execute([$make, $model, $ownerName, $ownerEmail, $ownerPhone, $odometer, $vehicleId]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO vehicles (registration_number, make, model, owner_name, owner_email, owner_phone, odometer, issues)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$regNumber, $make, $model, $ownerName, $ownerEmail, $ownerPhone, $odometer, $issues]);
                $vehicleId = (int)$db->lastInsertId();
            }

            $stmt = $db->prepare(
                'INSERT INTO visits (vehicle_id, check_in_date, status, odometer, issues)
                 VALUES (?, NOW(), ?, ?, ?)'
            );
            $stmt->execute([$vehicleId, 'checked-in', $odometer, $issues]);
            $visitId = (int)$db->lastInsertId();

            $db->commit();

            if (!empty($ownerEmail) && MailService::isEmailEnabled()) {
                $mail = new MailService();
                $vehicleStr = trim(($make ? $make . ' ' : '') . ($model ?? '') . ' (' . $regNumber . ')');
                $mail->sendVehicleRegistration(
                    $ownerEmail,
                    $ownerName,
                    $vehicleStr,
                    $issues,
                    $odometer
                );
            }

            $vehicleDesc = trim(($make ? $make . ' ' : '') . ($model ?? '') . ' (' . $regNumber . ')');
            $notificationTitle = 'New Vehicle Check-In';
            $notificationBody = $vehicleDesc . ' checked in by ' . ($ownerName ?: 'customer') . '. Reported: ' . ($issues ?: 'No issues reported');
            $notificationLink = 'index.html#jobs';

            $currentUserName = $_SESSION['user']['name'] ?? 'A staff member';
            $notifLink = 'index.html#jobs';
            foreach ($db->query("SELECT id FROM users WHERE role = 'technician' AND active = 1 AND id != " . (int)getCurrentUserId()) as $tech) {
                NotificationController::create(
                    (int)$tech['id'],
                    'info',
                    $notificationTitle,
                    $notificationBody,
                    $notifLink
                );
            }

            PushService::sendToAllTechnicians(
                $notificationTitle,
                $notificationBody,
                $notificationLink,
                getCurrentUserId()
            );

            jsonResponse([
                'vehicle' => [
                    'id' => $vehicleId,
                    'registration_number' => $regNumber,
                    'make' => $make,
                    'model' => $model,
                    'owner_name' => $ownerName,
                    'owner_email' => $ownerEmail,
                    'owner_phone' => $ownerPhone,
                    'odometer' => $odometer,
                    'issues' => $issues,
                    'existing' => (bool)$existing,
                ],
                'visit' => [
                    'id' => $visitId,
                    'vehicle_id' => $vehicleId,
                    'status' => 'checked-in',
                ],
            ], 201);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to check in vehicle: ' . $e->getMessage(), 500);
        }
    }

    public static function update($id) {
        requireAdmin();
        $data = getJsonInput();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id FROM vehicles WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Vehicle not found', 404);
        }

        $fields = [];
        $params = [];
        $allowed = ['registration_number', 'make', 'model', 'owner_name', 'owner_email', 'owner_phone', 'odometer', 'issues'];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $field === 'odometer' ? (int)$data[$field] : trim($data[$field]);
            }
        }

        if (empty($fields)) {
            jsonError('No fields to update', 400);
        }

        $params[] = (int)$id;
        $sql = 'UPDATE vehicles SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['message' => 'Vehicle updated successfully']);
    }

    public static function destroy($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id FROM vehicles WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Vehicle not found', 404);
        }

        $stmt = $db->prepare('DELETE FROM vehicles WHERE id = ?');
        $stmt->execute([(int)$id]);

        jsonResponse(['message' => 'Vehicle deleted successfully']);
    }

    public static function cancelVisit($id) {
        requireTechnician();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT vi.id, vi.status, vi.vehicle_id, b.id as bill_id, b.technician_id
             FROM visits vi
             LEFT JOIN bills b ON vi.id = b.visit_id
             WHERE vi.id = ?'
        );
        $stmt->execute([(int)$id]);
        $visit = $stmt->fetch();

        if (!$visit) {
            jsonError('Visit not found', 404);
        }

        if ($visit['bill_id'] && getCurrentUserRole() !== 'admin'
            && (int)$visit['technician_id'] !== getCurrentUserId()) {
            jsonError('You can only cancel your own visits', 403);
        }

        $cancellableStatuses = ['checked-in', 'pending_admin_approval', 'pending_approval'];
        if (!in_array($visit['status'], $cancellableStatuses)) {
            jsonError("Cannot cancel a visit with status '{$visit['status']}'", 400);
        }

        if ($visit['bill_id']) {
            $billStmt = $db->prepare('SELECT status, technician_id FROM bills WHERE id = ?');
            $billStmt->execute([(int)$visit['bill_id']]);
            $bill = $billStmt->fetch();
            if ($bill && !in_array($bill['status'], ['draft', 'rejected'])) {
                jsonError('Cannot cancel a visit with an active bill that is not draft or rejected', 400);
            }
            if ($bill && getCurrentUserRole() !== 'admin'
                && (int)$bill['technician_id'] !== getCurrentUserId()) {
                jsonError('You can only cancel your own bills', 403);
            }
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
            $stmt->execute(['cancelled', (int)$id]);

            if ($visit['bill_id']) {
                $stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
                $stmt->execute(['cancelled', (int)$visit['bill_id']]);
            }

            $db->commit();

            jsonResponse(['message' => 'Visit cancelled successfully']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to cancel visit: ' . $e->getMessage(), 500);
        }
    }

    public static function staleVisits() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            "SELECT vi.id, vi.status, vi.check_in_date, vi.vehicle_id,
                    v.registration_number, v.make, v.model, v.owner_name,
                    b.id as bill_id, b.status as bill_status
             FROM visits vi
             JOIN vehicles v ON vi.vehicle_id = v.id
             LEFT JOIN bills b ON vi.id = b.visit_id
             WHERE vi.status IN ('checked-in', 'pending_admin_approval')
               AND vi.check_in_date < DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY vi.check_in_date ASC"
        );
        $stmt->execute();
        $visits = $stmt->fetchAll();

        foreach ($visits as &$v) {
            $v['id'] = (int)$v['id'];
            $v['vehicle_id'] = (int)$v['vehicle_id'];
            $v['bill_id'] = $v['bill_id'] ? (int)$v['bill_id'] : null;
        }

        jsonResponse([
            'count' => count($visits),
            'visits' => $visits,
        ]);
    }
}
