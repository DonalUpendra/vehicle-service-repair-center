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

    public static function full() {
        requireAuth();
        $db = Database::getInstance()->getConnection();
        $today = date('Y-m-d');

        $stmt = $db->prepare('SELECT COUNT(*) FROM visits WHERE DATE(check_in_date) = ?');
        $stmt->execute([$today]);
        $todayCheckins = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM bills');
        $stmt->execute();
        $totalBills = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE status IN ('pending_approval', 'pending_admin_approval')");
        $stmt->execute();
        $pendingApprovals = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE status = ?');
        $stmt->execute(['completed']);
        $completedBills = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE status = ? AND DATE(created_at) = ?');
        $stmt->execute(['completed', $today]);
        $completedToday = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE status IN ('in_progress', 'approved', 'pending_admin_delivery', 'ready_for_delivery')");
        $stmt->execute();
        $activeJobs = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM vehicles');
        $stmt->execute();
        $totalVehicles = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE status = 'rejected'");
        $stmt->execute();
        $rejectedBills = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE status = 'cancelled'");
        $stmt->execute();
        $cancelledBills = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE status = 'draft'");
        $stmt->execute();
        $draftBills = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM visits");
        $stmt->execute();
        $totalVisits = (int)$stmt->fetchColumn();

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
        $totalProfit = max((float)$profitData['revenue'] - (float)$profitData['cost'], 0);

        $summary = [
            'todayCheckins'   => $todayCheckins,
            'totalBills'      => $totalBills,
            'pendingApprovals' => $pendingApprovals,
            'completedBills'  => $completedBills,
            'completedToday'  => $completedToday,
            'activeJobs'      => $activeJobs,
            'totalVehicles'   => $totalVehicles,
            'totalVisits'     => $totalVisits,
            'totalRevenue'    => $totalRevenue,
            'totalProfit'     => $totalProfit,
            'rejectedBills'   => $rejectedBills,
            'cancelledBills'  => $cancelledBills,
            'draftBills'      => $draftBills,
        ];

        $statuses = ['pending_admin_approval', 'pending_approval', 'approved', 'in_progress', 'pending_admin_delivery', 'ready_for_delivery', 'completed', 'rejected', 'cancelled', 'draft'];
        $statusBreakdown = [];
        $stmt = $db->prepare('SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as revenue FROM bills WHERE status = ?');
        foreach ($statuses as $s) {
            $stmt->execute([$s]);
            $row = $stmt->fetch();
            $statusBreakdown[] = [
                'status'  => $s,
                'count'   => (int)$row['cnt'],
                'revenue' => (float)$row['revenue'],
            ];
        }

        $stmt = $db->prepare(
            "SELECT b.*, v.registration_number, v.make, v.model, v.owner_name, v.owner_email, v.owner_phone,
                    vi.check_in_date, vi.status as visit_status, vi.odometer as visit_odometer,
                    u.name as technician_name,
                    (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id) as item_count,
                    (SELECT COALESCE(SUM(line_total), 0) FROM bill_items WHERE bill_id = b.id) as items_total,
                    (SELECT COALESCE(SUM(line_cost), 0) FROM bill_items WHERE bill_id = b.id) as items_cost
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
             ORDER BY b.created_at DESC"
        );
        $stmt->execute();
        $bills = $stmt->fetchAll();

        foreach ($bills as &$b) {
            $b['id'] = (int)$b['id'];
            $b['visit_id'] = (int)$b['visit_id'];
            $b['technician_id'] = (int)$b['technician_id'];
            $b['total_amount'] = (float)$b['total_amount'];
            $b['item_count'] = (int)$b['item_count'];
            $b['items_total'] = (float)$b['items_total'];
            $b['items_cost'] = (float)$b['items_cost'];
            $b['bill_profit'] = max((float)$b['items_total'] - (float)$b['items_cost'], 0);
        }

        $stmt = $db->prepare(
            "SELECT u.id, u.name, u.commission_percentage,
                    COUNT(b.id) as total_bills,
                    COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed_count,
                    COUNT(CASE WHEN b.status IN ('in_progress','approved','pending_admin_delivery','ready_for_delivery') THEN 1 END) as active_jobs,
                    COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END), 0) as completed_revenue,
                    COALESCE(SUM(bi.line_total), 0) as total_line_revenue,
                    COALESCE(SUM(bi.line_cost), 0) as total_line_cost
             FROM users u
             LEFT JOIN bills b ON u.id = b.technician_id
             LEFT JOIN bill_items bi ON b.id = bi.bill_id
             WHERE u.role = 'technician' AND u.active = 1
             GROUP BY u.id, u.name, u.commission_percentage
             ORDER BY completed_revenue DESC"
        );
        $stmt->execute();
        $technicians = $stmt->fetchAll();

        foreach ($technicians as &$t) {
            $t['id'] = (int)$t['id'];
            $t['total_bills'] = (int)$t['total_bills'];
            $t['completed_count'] = (int)$t['completed_count'];
            $t['active_jobs'] = (int)$t['active_jobs'];
            $t['completed_revenue'] = (float)$t['completed_revenue'];
            $t['total_line_revenue'] = (float)$t['total_line_revenue'];
            $t['total_line_cost'] = (float)$t['total_line_cost'];
            $t['total_profit'] = max((float)$t['total_line_revenue'] - (float)$t['total_line_cost'], 0);
            $t['commission_percentage'] = (float)$t['commission_percentage'];
            $t['commission_earned'] = round($t['completed_revenue'] * $t['commission_percentage'] / 100, 2);
        }

        jsonResponse([
            'summary'         => $summary,
            'statusBreakdown' => $statusBreakdown,
            'bills'           => $bills,
            'technicians'     => $technicians,
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
