<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class SalaryController {

    public static function index() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT sp.*, u.name as technician_name
             FROM salary_payments sp
             JOIN users u ON sp.technician_id = u.id
             ORDER BY sp.payment_date DESC, sp.created_at DESC'
        );
        $stmt->execute();
        $payments = $stmt->fetchAll();

        foreach ($payments as &$p) {
            $p['id'] = (int)$p['id'];
            $p['technician_id'] = (int)$p['technician_id'];
            $p['amount'] = (float)$p['amount'];
        }

        jsonResponse($payments);
    }

    public static function store() {
        requireAdmin();
        $data = getJsonInput();

        $technicianId = (int)($data['technician_id'] ?? $data['technicianId'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        $paymentDate = trim($data['payment_date'] ?? $data['paymentDate'] ?? date('Y-m-d'));
        $notes = trim($data['notes'] ?? '');

        if (!$technicianId || $amount <= 0) {
            jsonError('Technician and valid amount are required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id, name FROM users WHERE id = ? AND role = ?');
        $stmt->execute([$technicianId, 'technician']);
        if (!$stmt->fetch()) {
            jsonError('Technician not found', 404);
        }

        $stmt = $db->prepare(
            'INSERT INTO salary_payments (technician_id, amount, payment_date, notes) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$technicianId, $amount, $paymentDate, $notes]);
        $id = (int)$db->lastInsertId();

        jsonResponse([
            'id' => $id,
            'technician_id' => $technicianId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'notes' => $notes,
        ], 201);
    }

    public static function destroy($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id FROM salary_payments WHERE id = ?');
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            jsonError('Payment not found', 404);
        }

        $stmt = $db->prepare('DELETE FROM salary_payments WHERE id = ?');
        $stmt->execute([(int)$id]);

        jsonResponse(['message' => 'Payment deleted successfully']);
    }

    public static function technicianEarnings($technicianId) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_billed FROM bills WHERE technician_id = ? AND status = 'completed'");
        $stmt->execute([(int)$technicianId]);
        $totalBilled = (float)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT commission_percentage FROM users WHERE id = ?');
        $stmt->execute([(int)$technicianId]);
        $user = $stmt->fetch();
        $commissionPct = $user ? (float)$user['commission_percentage'] : 0;
        $estimatedCommission = round($totalBilled * $commissionPct / 100, 2);

        $stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) as paid FROM salary_payments WHERE technician_id = ?');
        $stmt->execute([(int)$technicianId]);
        $paid = (float)$stmt->fetchColumn();

        jsonResponse([
            'technician_id' => (int)$technicianId,
            'total_billed' => $totalBilled,
            'commission_percentage' => $commissionPct,
            'estimated_commission' => $estimatedCommission,
            'paid' => $paid,
            'balance' => round($estimatedCommission - $paid, 2),
        ]);
    }
}