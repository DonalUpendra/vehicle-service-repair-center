<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class ReportController {

    public static function today() {
        requireAuth();
        $db = Database::getInstance()->getConnection();

        $today = date('Y-m-d');

        $stmt = $db->prepare('SELECT COUNT(*) FROM visits WHERE DATE(check_in_date) = ?');
        $stmt->execute([$today]);
        $todayCheckins = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE status IN ('pending_approval', 'pending_admin_approval')");
        $stmt->execute();
        $pendingApprovals = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE status = ? AND DATE(created_at) = ?');
        $stmt->execute(['completed', $today]);
        $completedToday = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE status = ?');
        $stmt->execute(['in_progress']);
        $inProgress = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM vehicles');
        $stmt->execute();
        $totalVehicles = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE status = ?');
        $stmt->execute(['completed']);
        $totalRevenue = (float)$stmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(bi.line_total), 0) as revenue, COALESCE(SUM(bi.line_cost), 0) as cost
             FROM bill_items bi
             JOIN bills b ON bi.bill_id = b.id
             WHERE b.status = ?'
        );
        $stmt->execute(['completed']);
        $profitData = $stmt->fetch();
        $totalProfit = (float)$profitData['revenue'] - (float)$profitData['cost'];

        jsonResponse([
            'todayCheckins'   => $todayCheckins,
            'pendingApprovals' => $pendingApprovals,
            'completedToday'  => $completedToday,
            'inProgress'      => $inProgress,
            'totalVehicles'   => $totalVehicles,
            'totalRevenue'    => $totalRevenue,
            'totalProfit'     => max($totalProfit, 0),
        ]);
    }

    public static function pending() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            "SELECT b.*, v.registration_number, v.make, v.model, v.owner_name, v.owner_email,
                    vi.check_in_date, u.name as technician_name
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
              WHERE b.status IN ('pending_approval', 'pending_admin_approval')
             ORDER BY b.created_at DESC"
        );
        $stmt->execute();
        $bills = $stmt->fetchAll();

        foreach ($bills as &$b) {
            $b['id'] = (int)$b['id'];
            $b['visit_id'] = (int)$b['visit_id'];
            $b['technician_id'] = (int)$b['technician_id'];
            $b['total_amount'] = (float)$b['total_amount'];
        }

        jsonResponse($bills);
    }

    public static function revenue() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');

        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) as total_revenue, COUNT(*) as completed_count
             FROM bills
             WHERE status = ? AND DATE(created_at) BETWEEN ? AND ?'
        );
        $stmt->execute(['completed', $from, $to]);
        $result = $stmt->fetch();

        jsonResponse([
            'from' => $from,
            'to' => $to,
            'total_revenue' => (float)$result['total_revenue'],
            'completed_count' => (int)$result['completed_count'],
        ]);
    }

    public static function dashboard() {
        requireAuth();
        $db = Database::getInstance()->getConnection();

        $limit = (int)($_GET['limit'] ?? 8);

        $stmt = $db->prepare(
            'SELECT vi.*, v.registration_number, v.make, v.model, v.owner_name,
                    b.id as bill_id, b.status as bill_status, b.total_amount
             FROM visits vi
             JOIN vehicles v ON vi.vehicle_id = v.id
             LEFT JOIN bills b ON vi.id = b.visit_id
             ORDER BY vi.check_in_date DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $visits = $stmt->fetchAll();

        foreach ($visits as &$v) {
            $v['id'] = (int)$v['id'];
            $v['vehicle_id'] = (int)$v['vehicle_id'];
            $v['bill_id'] = $v['bill_id'] ? (int)$v['bill_id'] : null;
            $v['total_amount'] = $v['total_amount'] ? (float)$v['total_amount'] : null;
        }

        jsonResponse($visits);
    }
}
