<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/NotificationController.php';

class BillController {

    public static function index() {
        requireAuth();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT b.*, v.registration_number, v.make, v.model, v.owner_name, v.owner_email,
                    vi.check_in_date, vi.status as visit_status, u.name as technician_name
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
             ORDER BY b.created_at DESC'
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

    public static function show($id) {
        requireAuth();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT b.*, v.registration_number, v.make, v.model, v.owner_name, v.owner_email,
                    vi.check_in_date, vi.status as visit_status, u.name as technician_name
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
             WHERE b.id = ?'
        );
        $stmt->execute([(int)$id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            jsonError('Bill not found', 404);
        }

        $bill['id'] = (int)$bill['id'];
        $bill['visit_id'] = (int)$bill['visit_id'];
        $bill['technician_id'] = (int)$bill['technician_id'];
        $bill['total_amount'] = (float)$bill['total_amount'];

        $stmt = $db->prepare('SELECT * FROM bill_items WHERE bill_id = ?');
        $stmt->execute([(int)$id]);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['bill_id'] = (int)$item['bill_id'];
            $item['product_id'] = (int)$item['product_id'];
            $item['quantity'] = (int)$item['quantity'];
            $item['unit_price'] = (float)$item['unit_price'];
            $item['buy_price'] = (float)$item['buy_price'];
            $item['line_total'] = (float)$item['line_total'];
            $item['line_cost'] = (float)$item['line_cost'];
        }

        $bill['items'] = $items;

        $stmt = $db->prepare('SELECT token FROM approval_tokens WHERE bill_id = ?');
        $stmt->execute([(int)$id]);
        $tokenRow = $stmt->fetch();
        $bill['token'] = $tokenRow ? $tokenRow['token'] : null;

        jsonResponse($bill);
    }

    public static function store() {
        requireTechnician();
        $data = getJsonInput();

        $visitId = (int)($data['visitId'] ?? $data['visit_id'] ?? 0);
        $technicianId = (int)($data['technicianId'] ?? $data['technician_id'] ?? getCurrentUserId());
        $items = $data['items'] ?? [];

        if (!$visitId) {
            jsonError('Visit ID is required', 400);
        }
        if (empty($items)) {
            jsonError('At least one item is required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id, status FROM visits WHERE id = ?');
        $stmt->execute([$visitId]);
        $visit = $stmt->fetch();
        if (!$visit) {
            jsonError('Visit not found', 404);
        }

        $stmt = $db->prepare('SELECT id FROM bills WHERE visit_id = ?');
        $stmt->execute([$visitId]);
        if ($stmt->fetch()) {
            jsonError('A bill already exists for this visit', 409);
        }

        $stmt = $db->prepare('SELECT v.owner_email, v.owner_name, v.make, v.model, v.registration_number FROM visits vi JOIN vehicles v ON vi.vehicle_id = v.id WHERE vi.id = ?');
        $stmt->execute([$visitId]);
        $vehicleInfo = $stmt->fetch();

        $totalAmount = 0;
        foreach ($items as $item) {
            $qty = (int)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? $item['unitPrice'] ?? 0);
            $totalAmount += $qty * $price;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO bills (visit_id, technician_id, status, total_amount, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$visitId, $technicianId, 'pending_admin_approval', $totalAmount]);
            $billId = (int)$db->lastInsertId();

            foreach ($items as $item) {
                $productId = (int)($item['productId'] ?? $item['product_id'] ?? 0);
                $productName = trim($item['productName'] ?? $item['product_name'] ?? '');
                $qty = (int)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? $item['unitPrice'] ?? 0);
                $lineTotal = (float)($item['lineTotal'] ?? $item['line_total'] ?? ($qty * $price));
                $buyPrice = (float)($item['buy_price'] ?? $item['buyPrice'] ?? 0);
                $lineCost = $buyPrice * $qty;

                $stmt = $db->prepare(
                    'INSERT INTO bill_items (bill_id, product_id, product_name, quantity, unit_price, buy_price, line_total, line_cost)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$billId, $productId, $productName, $qty, $price, $buyPrice, $lineTotal, $lineCost]);
            }

            $stmt = $db->prepare('UPDATE visits SET bill_id = ?, status = ? WHERE id = ?');
            $stmt->execute([$billId, 'pending_admin_approval', $visitId]);

            $db->commit();

            $vehicleStr = trim(($vehicleInfo['make'] ?? '') . ' ' . ($vehicleInfo['model'] ?? '') . ' (' . ($vehicleInfo['registration_number'] ?? '') . ')');

            NotificationController::create(
                $technicianId,
                'info',
                'Quotation Created',
                "Quotation #{$billId} created for {$vehicleStr} — Total: LKR " . number_format($totalAmount, 2) . ". Awaiting admin review.",
                null
            );

            $adminStmt = $db->prepare('SELECT id FROM users WHERE role = ? AND active = 1');
            $adminStmt->execute(['admin']);
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                NotificationController::create(
                    (int)$admin['id'],
                    'info',
                    'New Quotation Needs Review',
                    "Technician created Bill #{$billId} for {$vehicleStr} — Total: LKR " . number_format($totalAmount, 2) . ". Please review and approve.",
                    null
                );
            }

            jsonResponse([
                'bill' => [
                    'id' => $billId,
                    'visit_id' => $visitId,
                    'technician_id' => $technicianId,
                    'status' => 'pending_admin_approval',
                    'total_amount' => $totalAmount,
                ],
            ], 201);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to create bill: ' . $e->getMessage(), 500);
        }
    }

    public static function submit($id) {
        requireTechnician();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id, status, visit_id, technician_id FROM bills WHERE id = ?');
        $stmt->execute([(int)$id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            jsonError('Bill not found', 404);
        }
        if ($bill['status'] !== 'draft') {
            jsonError('Only draft bills can be submitted', 400);
        }
        if (getCurrentUserRole() !== 'admin' && (int)$bill['technician_id'] !== getCurrentUserId()) {
            jsonError('You can only manage your own jobs', 403);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
            $stmt->execute(['pending_admin_approval', (int)$id]);

            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
            $stmt->execute(['pending_admin_approval', (int)$bill['visit_id']]);

            $db->commit();

            $stmt = $db->prepare('SELECT v.owner_name, v.make, v.model, v.registration_number, b.total_amount FROM bills b JOIN visits vi ON b.visit_id = vi.id JOIN vehicles v ON vi.vehicle_id = v.id WHERE b.id = ?');
            $stmt->execute([(int)$id]);
            $billInfo = $stmt->fetch();
            $vehicleStr = trim(($billInfo['make'] ?? '') . ' ' . ($billInfo['model'] ?? '') . ' (' . ($billInfo['registration_number'] ?? '') . ')');

            $adminStmt = $db->prepare('SELECT id FROM users WHERE role = ? AND active = 1');
            $adminStmt->execute(['admin']);
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                NotificationController::create(
                    (int)$admin['id'],
                    'info',
                    'Quotation Submitted for Review',
                    "Bill #{$id} submitted for {$vehicleStr} — Total: LKR " . number_format((float)$billInfo['total_amount'], 2) . ". Please review and approve.",
                    null
                );
            }

            jsonResponse([
                'message' => 'Bill submitted for admin approval',
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to submit bill: ' . $e->getMessage(), 500);
        }
    }

    public static function updateStatus($id) {
        requireTechnician();
        $data = getJsonInput();
        $newStatus = trim($data['status'] ?? '');

        $allowedTransitions = [
            'pending_admin_approval' => ['pending_approval', 'rejected'],
            'pending_approval'       => ['approved', 'rejected'],
            'approved'               => ['in_progress', 'cancelled'],
            'in_progress'            => ['completed', 'cancelled'],
            'draft'                  => ['cancelled'],
        ];

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT b.id, b.status, b.visit_id, b.technician_id FROM bills b WHERE b.id = ?');
        $stmt->execute([(int)$id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            jsonError('Bill not found', 404);
        }

        if (getCurrentUserRole() !== 'admin' && (int)$bill['technician_id'] !== getCurrentUserId()) {
            jsonError('You can only manage your own jobs', 403);
        }

        $currentStatus = $bill['status'];
        $allowed = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            jsonError("Cannot transition from '{$currentStatus}' to '{$newStatus}'", 400);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, (int)$id]);

            $visitStatusMap = [
                'rejected' => 'rejected',
                'cancelled' => 'cancelled',
                'completed' => 'completed',
            ];

            $mapped = $visitStatusMap[$newStatus] ?? $newStatus;
            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
            $stmt->execute([$mapped, (int)$bill['visit_id']]);

            $stmt = $db->prepare('SELECT v.owner_email, v.owner_name, v.make, v.model, v.registration_number, b.total_amount, b.technician_id FROM bills b JOIN visits vi ON b.visit_id = vi.id JOIN vehicles v ON vi.vehicle_id = v.id WHERE b.id = ?');
            $stmt->execute([(int)$id]);
            $notificationInfo = $stmt->fetch();

            if ($newStatus === 'pending_approval' && $currentStatus === 'pending_admin_approval' && $notificationInfo) {
                $token = generateToken();

                $stmt = $db->prepare('DELETE FROM approval_tokens WHERE bill_id = ?');
                $stmt->execute([(int)$id]);

                $stmt = $db->prepare('INSERT INTO approval_tokens (token, bill_id, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 0)');
                $stmt->execute([$token, (int)$id]);

                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');

                if (!empty($notificationInfo['owner_email'])) {
                    $mail = new MailService();
                    $mail->sendApprovalRequest(
                        $notificationInfo['owner_email'],
                        $notificationInfo['owner_name'] ?? 'Customer',
                        (int)$id,
                        $token,
                        (float)$notificationInfo['total_amount'],
                        $vehicleStr
                    );
                }

                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'success',
                    'Admin Approved Quotation',
                    "Bill #{$id} approved by admin — {$vehicleStr}. Approval email sent to customer.",
                    null
                );
            }

            if ($newStatus === 'approved' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'success',
                    'Job Approved',
                    "Bill #{$id} approved by customer — {$vehicleStr}. Ready to start work!",
                    null
                );
            }

            if ($newStatus === 'rejected' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                $rejectedBy = $currentStatus === 'pending_admin_approval' ? 'admin' : 'customer';
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'error',
                    'Job Rejected',
                    "Bill #{$id} was rejected by {$rejectedBy} — {$vehicleStr}.",
                    null
                );
            }

            if ($newStatus === 'completed') {
                if ($notificationInfo && !empty($notificationInfo['owner_email'])) {
                    $mail = new MailService();
                    $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . '');
                    $mail->sendBillCompletion(
                        $notificationInfo['owner_email'],
                        $notificationInfo['owner_name'] ?? 'Customer',
                        (int)$id,
                        (float)$notificationInfo['total_amount'],
                        $vehicleStr
                    );
                }
            }

            $db->commit();

            jsonResponse([
                'message' => "Bill status updated to '{$newStatus}'",
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    public static function resend($id) {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id, status, visit_id FROM bills WHERE id = ?');
        $stmt->execute([(int)$id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            jsonError('Bill not found', 404);
        }

        $token = generateToken();

        $stmt = $db->prepare('DELETE FROM approval_tokens WHERE bill_id = ?');
        $stmt->execute([(int)$id]);

        $stmt = $db->prepare('INSERT INTO approval_tokens (token, bill_id, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 0)');
        $stmt->execute([$token, (int)$id]);

$stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
        $stmt->execute(['pending_approval', (int)$id]);

        $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
        $stmt->execute(['pending_approval', (int)$bill['visit_id']]);

        $mailSent = false;
        $stmt = $db->prepare('SELECT v.owner_email, v.owner_name, v.make, v.model, v.registration_number, b.total_amount FROM bills b JOIN visits vi ON b.visit_id = vi.id JOIN vehicles v ON vi.vehicle_id = v.id WHERE b.id = ?');
        $stmt->execute([(int)$id]);
        $customerInfo = $stmt->fetch();

        if ($customerInfo && !empty($customerInfo['owner_email'])) {
            $mail = new MailService();
            $vehicleStr = trim(($customerInfo['make'] ?? '') . ' ' . ($customerInfo['model'] ?? '') . ' (' . ($customerInfo['registration_number'] ?? '') . '');
            $mailSent = $mail->sendApprovalRequest(
                $customerInfo['owner_email'],
                $customerInfo['owner_name'] ?? 'Customer',
                (int)$id,
                $token,
                (float)$customerInfo['total_amount'],
                $vehicleStr
            );
}

        jsonResponse([
            'message' => 'Approval email resent',
            'token' => $token,
        ]);
    }

    public static function jobDone($id) {
        requireTechnician();
        $data = getJsonInput();
        $estimatedDelivery = trim($data['estimated_delivery'] ?? '');

        if (!$estimatedDelivery) {
            jsonError('Estimated delivery date/time is required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT b.id, b.status, b.visit_id, b.technician_id FROM bills b WHERE b.id = ?');
        $stmt->execute([(int)$id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            jsonError('Bill not found', 404);
        }

        if (getCurrentUserRole() !== 'admin' && (int)$bill['technician_id'] !== getCurrentUserId()) {
            jsonError('You can only manage your own jobs', 403);
        }

        $validStatuses = ['approved', 'in_progress'];
        if (!in_array($bill['status'], $validStatuses)) {
            jsonError('This bill cannot be marked as done from its current status', 400);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE bills SET status = ?, estimated_delivery = ? WHERE id = ?');
            $stmt->execute(['completed', $estimatedDelivery, (int)$id]);

            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
            $stmt->execute(['completed', (int)$bill['visit_id']]);

            $stmt = $db->prepare('SELECT v.owner_email, v.owner_name, v.make, v.model, v.registration_number, b.total_amount, b.technician_id FROM bills b JOIN visits vi ON b.visit_id = vi.id JOIN vehicles v ON vi.vehicle_id = v.id WHERE b.id = ?');
            $stmt->execute([(int)$id]);
            $jobInfo = $stmt->fetch();

            NotificationController::create(
                (int)$jobInfo['technician_id'],
                'success',
                'Job Completed',
                "Job for Bill #{$id} — {$jobInfo['make']} {$jobInfo['model']} ({$jobInfo['registration_number']}) marked as completed.",
                null
            );

            $db->commit();

            $mailSent = false;
            if (MailService::isEmailEnabled()) {
                if ($jobInfo && !empty($jobInfo['owner_email'])) {
                    $mail = new MailService();
                    $vehicleStr = trim(($jobInfo['make'] ?? '') . ' ' . ($jobInfo['model'] ?? '') . ' (' . ($jobInfo['registration_number'] ?? '') . '');
                    $mailSent = $mail->sendBillCompletion(
                        $jobInfo['owner_email'],
                        $jobInfo['owner_name'] ?? 'Customer',
                        (int)$id,
                        (float)$jobInfo['total_amount'],
                        $vehicleStr,
                        $estimatedDelivery
                    );
                }
            }

            jsonResponse([
                'message' => 'Job marked as completed. Customer notified.',
                'mail_sent' => $mailSent,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to mark job as done: ' . $e->getMessage(), 500);
        }
    }

    public static function jobsList() {
        requireTechnician();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT b.id, b.status, b.total_amount, b.estimated_delivery, b.created_at,
                    b.technician_id,
                    u.commission_percentage,
                    v.registration_number, v.make, v.model, v.owner_name, v.owner_email,
                    vi.check_in_date, vi.status as visit_status, u.name as technician_name
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
              WHERE b.status IN (\'pending_admin_approval\', \'approved\', \'in_progress\', \'completed\', \'rejected\')
              ORDER BY
                CASE b.status
                    WHEN \'pending_admin_approval\' THEN 0
                    WHEN \'in_progress\' THEN 1
                    WHEN \'approved\' THEN 2
                    WHEN \'completed\' THEN 3
                    WHEN \'rejected\' THEN 4
                END,
                b.created_at DESC'
        );
        $stmt->execute();
        $jobs = $stmt->fetchAll();

        foreach ($jobs as &$j) {
            $j['id'] = (int)$j['id'];
            $j['technician_id'] = (int)$j['technician_id'];
            $j['total_amount'] = (float)$j['total_amount'];
            $j['commission_percentage'] = (float)$j['commission_percentage'];
            $j['commission_amount'] = round($j['total_amount'] * $j['commission_percentage'] / 100, 2);
        }

        jsonResponse($jobs);
    }
}
