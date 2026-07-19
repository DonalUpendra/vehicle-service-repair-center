<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class SalaryController {

    public static function index() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT sp.*, u.name as technician_name, u.commission_percentage
             FROM salary_payments sp
             JOIN users u ON sp.technician_id = u.id
             ORDER BY sp.payment_date DESC, sp.created_at DESC'
        );
        $stmt->execute();
        $payments = $stmt->fetchAll();

        // Pre-fetch total billed per technician (completed jobs)
        $billedCache = [];
        $billedStmt = $db->prepare(
            "SELECT u.id,
                    COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END), 0) as total_billed
             FROM users u
             LEFT JOIN bills b ON b.technician_id = u.id AND b.status = 'completed'
             WHERE u.role = 'technician'
             GROUP BY u.id"
        );
        $billedStmt->execute();
        foreach ($billedStmt->fetchAll() as $e) {
            $billedCache[(int)$e['id']] = (float)$e['total_billed'];
        }

        // Pre-fetch total paid per technician
        $paidCache = [];
        $paidStmt = $db->prepare('SELECT technician_id, COALESCE(SUM(amount), 0) as paid FROM salary_payments GROUP BY technician_id');
        $paidStmt->execute();
        foreach ($paidStmt->fetchAll() as $p2) {
            $paidCache[(int)$p2['technician_id']] = (float)$p2['paid'];
        }

        // Pre-fetch all payments grouped by technician
        $allPmtsStmt = $db->prepare('SELECT id, technician_id, amount, payment_date, notes FROM salary_payments ORDER BY payment_date DESC, created_at DESC');
        $allPmtsStmt->execute();
        $pmtsByTech = [];
        foreach ($allPmtsStmt->fetchAll() as $pmt) {
            $tid = (int)$pmt['technician_id'];
            if (!isset($pmtsByTech[$tid])) {
                $pmtsByTech[$tid] = [];
            }
            $pmtsByTech[$tid][] = [
                'id' => (int)$pmt['id'],
                'amount' => (float)$pmt['amount'],
                'payment_date' => $pmt['payment_date'],
                'notes' => $pmt['notes'],
            ];
        }

        // Attach earnings context to each payment
        $seenTechIds = [];
        foreach ($payments as &$p) {
            $p['id'] = (int)$p['id'];
            $p['technician_id'] = (int)$p['technician_id'];
            $p['amount'] = (float)$p['amount'];
            $p['commission_percentage'] = (float)$p['commission_percentage'];

            $techId = $p['technician_id'];

            // Only compute full context once per technician to avoid redundant data
            if (!isset($seenTechIds[$techId])) {
                $seenTechIds[$techId] = true;

                $commissionPct = $p['commission_percentage'];
                $totalBilled = $billedCache[$techId] ?? 0;
                $estimatedCommission = round($totalBilled * $commissionPct / 100, 2);
                $totalPaid = $paidCache[$techId] ?? 0;

                $p['total_billed'] = $totalBilled;
                $p['estimated_commission'] = $estimatedCommission;
                $p['total_paid'] = $totalPaid;
                $p['balance'] = round($estimatedCommission - $totalPaid, 2);
                $p['payments'] = $pmtsByTech[$techId] ?? [];
            } else {
                $p['total_billed'] = null;
                $p['estimated_commission'] = null;
                $p['total_paid'] = null;
                $p['balance'] = null;
                $p['payments'] = null;
            }
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

    public static function allTechnicianEarnings() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            "SELECT u.id, u.name, u.email, u.commission_percentage, u.active,
                    COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END), 0) as total_billed
             FROM users u
             LEFT JOIN bills b ON b.technician_id = u.id AND b.status = 'completed'
             WHERE u.role = 'technician'
             GROUP BY u.id
             ORDER BY u.name"
        );
        $stmt->execute();
        $techs = $stmt->fetchAll();

        $result = [];
        foreach ($techs as $t) {
            $commissionPct = (float)$t['commission_percentage'];
            $totalBilled = (float)$t['total_billed'];
            $estimatedCommission = round($totalBilled * $commissionPct / 100, 2);

            $stmt2 = $db->prepare('SELECT COALESCE(SUM(amount), 0) as paid FROM salary_payments WHERE technician_id = ?');
            $stmt2->execute([(int)$t['id']]);
            $paid = (float)$stmt2->fetchColumn();

            $result[] = [
                'technician_id' => (int)$t['id'],
                'technician_name' => $t['name'],
                'email' => $t['email'],
                'active' => (bool)$t['active'],
                'commission_percentage' => $commissionPct,
                'total_billed' => $totalBilled,
                'estimated_commission' => $estimatedCommission,
                'paid' => $paid,
                'balance' => round($estimatedCommission - $paid, 2),
            ];
        }

        jsonResponse($result);
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