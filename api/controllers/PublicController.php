<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';

class PublicController {

    public static function quotation() {
        $token = trim($_GET['token'] ?? '');
        if (!$token) {
            jsonError('Token is required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM approval_tokens WHERE token = ?');
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            jsonError('Invalid token', 404);
        }
        if ($tokenData['used']) {
            jsonError('This link has already been used', 410);
        }
        if (strtotime($tokenData['expires_at']) < time()) {
            jsonError('This link has expired', 410);
        }

        $stmt = $db->prepare(
            'SELECT b.*, v.registration_number, v.make, v.model, v.owner_name, v.owner_email,
                    vi.check_in_date, u.name as technician_name
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
             WHERE b.id = ?'
        );
        $stmt->execute([(int)$tokenData['bill_id']]);
        $bill = $stmt->fetch();

        if (!$bill) {
            jsonError('Bill not found', 404);
        }

        $bill['id'] = (int)$bill['id'];
        $bill['visit_id'] = (int)$bill['visit_id'];
        $bill['technician_id'] = (int)$bill['technician_id'];
        $bill['total_amount'] = (float)$bill['total_amount'];

        $stmt = $db->prepare('SELECT * FROM bill_items WHERE bill_id = ?');
        $stmt->execute([(int)$bill['id']]);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['bill_id'] = (int)$item['bill_id'];
            $item['product_id'] = (int)$item['product_id'];
            $item['quantity'] = (int)$item['quantity'];
            $item['unit_price'] = (float)$item['unit_price'];
            $item['line_total'] = (float)$item['line_total'];
        }

        $bill['items'] = $items;

        jsonResponse([
            'token' => $token,
            'bill' => $bill,
        ]);
    }

    public static function approve() {
        $data = getJsonInput();
        $token = trim($data['token'] ?? '');
        $action = trim($data['action'] ?? '');
        $description = trim($data['description'] ?? '');

        if (!$token || !in_array($action, ['approve', 'reject'])) {
            jsonError('Valid token and action (approve/reject) are required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM approval_tokens WHERE token = ?');
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            jsonError('Invalid token', 404);
        }
        if ($tokenData['used']) {
            jsonError('This link has already been used', 410);
        }
        if (strtotime($tokenData['expires_at']) < time()) {
            jsonError('This link has expired', 410);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE approval_tokens SET used = 1, description = ? WHERE token = ?');
            $stmt->execute([$description ?: null, $token]);

            $newStatus = $action === 'approve' ? 'approved' : 'rejected';

            $stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, (int)$tokenData['bill_id']]);

            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = (SELECT visit_id FROM bills WHERE id = ?)');
            $stmt->execute([$newStatus, (int)$tokenData['bill_id']]);

            $db->commit();

            jsonResponse([
                'message' => $action === 'approve' ? 'Quotation approved successfully' : 'Quotation rejected',
                'action' => $action,
                'status' => $newStatus,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to process request: ' . $e->getMessage(), 500);
        }
    }
}
