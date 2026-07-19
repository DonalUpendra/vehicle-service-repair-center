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

        $stmt = $db->prepare('SELECT id, status, technician_id FROM bills WHERE visit_id = ?');
        $stmt->execute([$visitId]);
        $existingBill = $stmt->fetch();
        if ($existingBill && !in_array($existingBill['status'], ['draft', 'rejected'])) {
            jsonError('A bill already exists for this visit', 409);
        }
        if ($existingBill && getCurrentUserRole() !== 'admin' && (int)$existingBill['technician_id'] !== getCurrentUserId()) {
            jsonError('This bill belongs to another technician', 403);
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
            if ($existingBill) {
                $billId = (int)$existingBill['id'];
                $stmt = $db->prepare('UPDATE bills SET status = ?, total_amount = ?, admin_note = NULL, technician_id = ? WHERE id = ?');
                $stmt->execute(['pending_admin_approval', $totalAmount, $technicianId, $billId]);
                $stmt = $db->prepare('DELETE FROM bill_items WHERE bill_id = ?');
                $stmt->execute([$billId]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO bills (visit_id, technician_id, status, total_amount, estimated_delivery, created_at) VALUES (?, ?, ?, ?, NULL, NOW())'
                );
                $stmt->execute([$visitId, $technicianId, 'pending_admin_approval', $totalAmount]);
                $billId = (int)$db->lastInsertId();
            }

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

            $oldStatus = $existingBill ? $existingBill['status'] : null;
            self::logStatusChange($billId, $visitId, $oldStatus, 'pending_admin_approval', 'internal');

            $db->commit();

            $vehicleStr = trim(($vehicleInfo['make'] ?? '') . ' ' . ($vehicleInfo['model'] ?? '') . ' (' . ($vehicleInfo['registration_number'] ?? '') . ')');

            $actionVerb = $existingBill ? 'resubmitted' : 'created';
            $notifLink = 'index.html#jobs';
            NotificationController::create(
                $technicianId,
                'info',
                'Quotation ' . ucfirst($actionVerb),
                "Quotation #{$billId} {$actionVerb} for {$vehicleStr} — Total: LKR " . number_format($totalAmount, 2) . ". Awaiting admin review.",
                $notifLink
            );

            $adminStmt = $db->prepare('SELECT id FROM users WHERE role = ? AND active = 1');
            $adminStmt->execute(['admin']);
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                NotificationController::create(
                    (int)$admin['id'],
                    'info',
                    $existingBill ? 'Quotation Resubmitted for Review' : 'New Quotation Needs Review',
                    "Technician {$actionVerb} Bill #{$billId} for {$vehicleStr} — Total: LKR " . number_format($totalAmount, 2) . ". Please review and approve.",
                    $notifLink
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

            self::syncVisitStatus((int)$bill['visit_id'], 'pending_admin_approval');

            self::logStatusChange((int)$id, (int)$bill['visit_id'], 'draft', 'pending_admin_approval', 'internal');

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
                    'index.html#jobs'
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
            'pending_approval'       => [],
            'approved'               => ['in_progress', 'cancelled'],
            'in_progress'            => ['pending_admin_delivery', 'completed', 'cancelled'],
            'pending_admin_delivery' => ['ready_for_delivery', 'in_progress'],
            'ready_for_delivery'     => ['completed', 'in_progress'],
            'draft'                  => ['pending_admin_approval', 'cancelled'],
            'rejected'               => ['draft'],
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

        // Admin override: can cancel stuck pending_approval quotations
        if ($currentStatus === 'pending_approval' && getCurrentUserRole() === 'admin') {
            $allowed = ['cancelled'];
        }

        if (!in_array($newStatus, $allowed)) {
            jsonError("Cannot transition from '{$currentStatus}' to '{$newStatus}'", 400);
        }

        $db->beginTransaction();
        try {
            $adminNote = trim($data['admin_note'] ?? '');

            if ($newStatus === 'rejected' && $currentStatus === 'pending_admin_approval' && $adminNote) {
                $stmt = $db->prepare('UPDATE bills SET status = ?, admin_note = ? WHERE id = ?');
                $stmt->execute([$newStatus, $adminNote, (int)$id]);
            } elseif ($newStatus === 'draft' && $currentStatus === 'rejected') {
                $stmt = $db->prepare('UPDATE bills SET status = ?, admin_note = NULL WHERE id = ?');
                $stmt->execute([$newStatus, (int)$id]);
            } elseif (($newStatus === 'in_progress' && $currentStatus === 'ready_for_delivery')
                   || ($newStatus === 'in_progress' && $currentStatus === 'pending_admin_delivery')) {
                $stmt = $db->prepare('UPDATE bills SET status = ?, estimated_delivery = NULL WHERE id = ?');
                $stmt->execute([$newStatus, (int)$id]);
            } else {
                $stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
                $stmt->execute([$newStatus, (int)$id]);
            }

            self::syncVisitStatus((int)$bill['visit_id'], $newStatus);

            $stmt = $db->prepare('SELECT v.owner_email, v.owner_name, v.make, v.model, v.registration_number, b.total_amount, b.technician_id FROM bills b JOIN visits vi ON b.visit_id = vi.id JOIN vehicles v ON vi.vehicle_id = v.id WHERE b.id = ?');
            $stmt->execute([(int)$id]);
            $notificationInfo = $stmt->fetch();

            if ($newStatus === 'pending_approval' && $currentStatus === 'pending_admin_approval' && $notificationInfo) {
                $token = generateToken();

                $stmt = $db->prepare('DELETE FROM approval_tokens WHERE bill_id = ?');
                $stmt->execute([(int)$id]);

                $stmt = $db->prepare('INSERT INTO approval_tokens (token, bill_id, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 0)');
                $stmt->execute([$token, (int)$id]);

                $pendingApprovalToken = $token;
                $pendingApprovalEmail = $notificationInfo['owner_email'] ?? '';
                $pendingApprovalName = $notificationInfo['owner_name'] ?? 'Customer';
                $pendingApprovalAmount = (float)$notificationInfo['total_amount'];
                $pendingApprovalVehicle = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');

                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'success',
                    'Admin Approved Quotation',
                    "Bill #{$id} approved by admin — {$pendingApprovalVehicle}. Approval email being sent to customer.",
                    'index.html#jobs'
                );
            }

            if ($newStatus === 'approved' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'success',
                    'Job Approved',
                    "Bill #{$id} approved by customer — {$vehicleStr}. Ready to start work!",
                    'index.html#jobs'
                );
            }

            if ($newStatus === 'rejected' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                $rejectedBy = $currentStatus === 'pending_admin_approval' ? 'admin' : 'customer';
                $noteText = $adminNote ? " Reason: {$adminNote}" : '';
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'error',
                    'Quotation Rejected',
                    "Bill #{$id} was rejected by {$rejectedBy} — {$vehicleStr}.{$noteText}",
                    'index.html#jobs'
                );
            }

            if ($newStatus === 'in_progress' && $currentStatus === 'ready_for_delivery' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'warning',
                    'Job Reopened',
                    "Bill #{$id} reopened from ready for delivery — {$vehicleStr}. Additional work needed.",
                    'index.html#jobs'
                );
            }

            if ($newStatus === 'in_progress' && $currentStatus === 'pending_admin_delivery' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'warning',
                    'Delivery Rejected',
                    "Admin returned Bill #{$id} for additional work — {$vehicleStr}.",
                    'index.html#jobs'
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

            if ($newStatus === 'ready_for_delivery' && $currentStatus === 'pending_admin_delivery' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');

                $deliveryApprovedEmail = !empty($notificationInfo['owner_email']) ? $notificationInfo['owner_email'] : null;
                $deliveryApprovedName = $notificationInfo['owner_name'] ?? 'Customer';
                $deliveryApprovedAmount = (float)$notificationInfo['total_amount'];
                $deliveryApprovedVehicle = $vehicleStr;

                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'success',
                    'Delivery Approved',
                    "Admin approved delivery for Bill #{$id} — {$vehicleStr}. Customer will be notified.",
                    'index.html#jobs'
                );
            }

            if ($newStatus === 'pending_admin_approval' && $currentStatus === 'draft' && $notificationInfo) {
                $vehicleStr = trim(($notificationInfo['make'] ?? '') . ' ' . ($notificationInfo['model'] ?? '') . ' (' . ($notificationInfo['registration_number'] ?? '') . ')');
                NotificationController::create(
                    (int)$notificationInfo['technician_id'],
                    'info',
                    'Quotation Submitted',
                    "Bill #{$id} submitted for {$vehicleStr} — Total: LKR " . number_format((float)$notificationInfo['total_amount'], 2) . ". Awaiting admin review.",
                    'index.html#jobs'
                );
                $adminStmt = $db->prepare('SELECT id FROM users WHERE role = ? AND active = 1');
                $adminStmt->execute(['admin']);
                $admins = $adminStmt->fetchAll();
                foreach ($admins as $admin) {
                    NotificationController::create(
                        (int)$admin['id'],
                        'info',
                        'Quotation Submitted for Review',
                        "Technician submitted Bill #{$id} for {$vehicleStr} — Total: LKR " . number_format((float)$notificationInfo['total_amount'], 2) . ". Please review and approve.",
                        'index.html#jobs'
                    );
                }
            }

            self::logStatusChange((int)$id, (int)$bill['visit_id'], $currentStatus, $newStatus, 'internal', $adminNote ?: null);

            $db->commit();

            if (isset($pendingApprovalToken) && !empty($pendingApprovalEmail)) {
                $mail = new MailService();
                $mail->sendApprovalRequest(
                    $pendingApprovalEmail,
                    $pendingApprovalName ?? 'Customer',
                    (int)$id,
                    $pendingApprovalToken,
                    $pendingApprovalAmount,
                    $pendingApprovalVehicle
                );
            }

            if (isset($deliveryApprovedEmail) && $deliveryApprovedEmail) {
                $mail = new MailService();
                $mail->sendReadyForPickup(
                    $deliveryApprovedEmail,
                    $deliveryApprovedName,
                    (int)$id,
                    $deliveryApprovedAmount,
                    $deliveryApprovedVehicle
                );
            }

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

        $oldStatus = $bill['status'];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM approval_tokens WHERE bill_id = ?');
            $stmt->execute([(int)$id]);

            $stmt = $db->prepare('INSERT INTO approval_tokens (token, bill_id, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 0)');
            $stmt->execute([$token, (int)$id]);

            $stmt = $db->prepare('UPDATE bills SET status = ? WHERE id = ?');
            $stmt->execute(['pending_approval', (int)$id]);

            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
            $stmt->execute(['pending_approval', (int)$bill['visit_id']]);

            self::logStatusChange((int)$id, (int)$bill['visit_id'], $oldStatus, 'pending_approval', 'internal');

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to resend approval: ' . $e->getMessage(), 500);
        }

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
            $stmt->execute(['pending_admin_delivery', $estimatedDelivery, (int)$id]);

            $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
            $stmt->execute(['pending_admin_delivery', (int)$bill['visit_id']]);

            $stmt = $db->prepare('SELECT v.owner_email, v.owner_name, v.make, v.model, v.registration_number, b.total_amount, b.technician_id FROM bills b JOIN visits vi ON b.visit_id = vi.id JOIN vehicles v ON vi.vehicle_id = v.id WHERE b.id = ?');
            $stmt->execute([(int)$id]);
            $jobInfo = $stmt->fetch();

            NotificationController::create(
                (int)$jobInfo['technician_id'],
                'info',
                'Job Marked as Done',
                "Job for Bill #{$id} — {$jobInfo['make']} {$jobInfo['model']} ({$jobInfo['registration_number']}) marked as done. Awaiting admin review.",
                'index.html#jobs'
            );

            $adminStmt = $db->prepare('SELECT id FROM users WHERE role = ? AND active = 1');
            $adminStmt->execute(['admin']);
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                NotificationController::create(
                    (int)$admin['id'],
                    'info',
                    'Job Ready for Review',
                    "Technician marked Bill #{$id} — {$jobInfo['make']} {$jobInfo['model']} ({$jobInfo['registration_number']}) as done. Please review and approve delivery.",
                    'index.html#jobs'
                );
            }

            self::logStatusChange((int)$id, (int)$bill['visit_id'], $bill['status'], 'pending_admin_delivery', 'internal');

            $db->commit();

            jsonResponse([
                'message' => 'Job marked as done. Awaiting admin review.',
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to mark job as done: ' . $e->getMessage(), 500);
        }
    }

    public static function currentJob() {
        requireTechnician();
        $db = Database::getInstance()->getConnection();
        $userId = getCurrentUserId();

        $stmt = $db->prepare(
            'SELECT b.id, b.status, b.admin_note, b.total_amount, b.estimated_delivery, b.created_at,
                    b.technician_id, b.visit_id,
                    u.commission_percentage,
                    v.registration_number, v.make, v.model, v.owner_name, v.owner_email, v.owner_phone,
                    vi.check_in_date, vi.status as visit_status, u.name as technician_name
             FROM bills b
             JOIN visits vi ON b.visit_id = vi.id
             JOIN vehicles v ON vi.vehicle_id = v.id
             JOIN users u ON b.technician_id = u.id
             WHERE b.technician_id = ? AND b.status IN (\'approved\', \'in_progress\')
             ORDER BY FIELD(b.status, \'in_progress\', \'approved\'), b.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $job = $stmt->fetch();

        if (!$job) {
            jsonResponse(null);
            return;
        }

        $job['id'] = (int)$job['id'];
        $job['technician_id'] = (int)$job['technician_id'];
        $job['visit_id'] = (int)$job['visit_id'];
        $job['total_amount'] = (float)$job['total_amount'];
        $job['commission_percentage'] = (float)$job['commission_percentage'];
        $job['commission_amount'] = round($job['total_amount'] * $job['commission_percentage'] / 100, 2);

        $stmt = $db->prepare('SELECT * FROM bill_items WHERE bill_id = ?');
        $stmt->execute([$job['id']]);
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

        $job['items'] = $items;

        jsonResponse($job);
    }

    public static function jobsList() {
        requireTechnician();
        $db = Database::getInstance()->getConnection();
        $statusFilter = trim($_GET['status'] ?? '');
        $userId = getCurrentUserId();
        $isAdmin = getCurrentUserRole() === 'admin';

        $sql = 'SELECT b.id, b.status, b.admin_note, b.total_amount, b.estimated_delivery, b.created_at,
                      b.technician_id,
                      u.commission_percentage,
                      v.registration_number, v.make, v.model, v.owner_name, v.owner_email,
                      vi.check_in_date, vi.status as visit_status, u.name as technician_name,
                      at.description as rejection_reason
               FROM bills b
               JOIN visits vi ON b.visit_id = vi.id
               JOIN vehicles v ON vi.vehicle_id = v.id
               JOIN users u ON b.technician_id = u.id
               LEFT JOIN approval_tokens at ON b.id = at.bill_id
               WHERE 1=1';

        $params = [];

        if ($statusFilter) {
            if (strpos($statusFilter, ',') !== false) {
                $statuses = array_map('trim', explode(',', $statusFilter));
                $placeholders = implode(',', array_fill(0, count($statuses), '?'));
                $sql .= " AND b.status IN ($placeholders)";
                $params = $statuses;
            } else {
                $sql .= ' AND b.status = ?';
                $params = [$statusFilter];
            }
        }

        if ($statusFilter === 'draft' && !$isAdmin) {
            $sql .= ' AND b.technician_id = ?';
            $params[] = $userId;
        }

        if (in_array($statusFilter, ['approved', 'in_progress']) && !$isAdmin) {
            $sql .= ' AND b.technician_id = ?';
            $params[] = $userId;
        }

        $sql .= ' ORDER BY
                CASE b.status
                    WHEN \'pending_admin_approval\' THEN 0
                    WHEN \'draft\' THEN 1
                    WHEN \'in_progress\' THEN 2
                    WHEN \'pending_approval\' THEN 3
                    WHEN \'approved\' THEN 4
                    WHEN \'pending_admin_delivery\' THEN 5
                    WHEN \'ready_for_delivery\' THEN 6
                    WHEN \'completed\' THEN 7
                    WHEN \'rejected\' THEN 8
                    WHEN \'cancelled\' THEN 9
                END,
                b.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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

    public static function jobsStats() {
        requireTechnician();
        $db = Database::getInstance()->getConnection();
        $userId = getCurrentUserId();
        $isAdmin = getCurrentUserRole() === 'admin';

        $stats = [];

        $countStmt = $db->prepare('SELECT COUNT(*) FROM bills b WHERE b.status = ?');
        $statuses = ['pending_admin_approval', 'pending_approval', 'approved', 'in_progress', 'pending_admin_delivery', 'ready_for_delivery', 'completed', 'rejected', 'cancelled'];
        foreach ($statuses as $s) {
            $countStmt->execute([$s]);
            $stats[$s] = (int)$countStmt->fetchColumn();
        }

        $myDraftStmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE status = ? AND technician_id = ?');
        $myDraftStmt->execute(['draft', $userId]);
        $stats['my_drafts'] = (int)$myDraftStmt->fetchColumn();

        $myActiveStmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE status IN (?, ?) AND technician_id = ?');
        $myActiveStmt->execute(['approved', 'in_progress', $userId]);
        $stats['my_active'] = (int)$myActiveStmt->fetchColumn();

        if ($isAdmin) {
            $stats['total_pending_action'] = $stats['pending_admin_approval'];
            $stats['total_active'] = $stats['approved'] + $stats['in_progress'];
            $stats['total_awaiting_delivery'] = $stats['ready_for_delivery'];
        } else {
            $stats['total_active'] = $stats['my_active'];
            $stats['total_drafts'] = $stats['my_drafts'];
        }

        jsonResponse($stats);
    }

    private static function syncVisitStatus($visitId, $newStatus) {
        if ($newStatus === 'draft') return;
        $db = Database::getInstance()->getConnection();
        $visitMap = [
            'pending_admin_approval' => 'pending_admin_approval',
            'pending_approval' => 'pending_approval',
            'approved' => 'approved',
            'in_progress' => 'in_progress',
            'pending_admin_delivery' => 'pending_admin_delivery',
            'ready_for_delivery' => 'ready_for_delivery',
            'completed' => 'completed',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
        ];
        $mapped = $visitMap[$newStatus] ?? $newStatus;
        $stmt = $db->prepare('UPDATE visits SET status = ? WHERE id = ?');
        $stmt->execute([$mapped, (int)$visitId]);
    }

    private static function logStatusChange($billId, $visitId, $oldStatus, $newStatus, $source = 'internal', $note = null) {
        $db = Database::getInstance()->getConnection();
        $userId = getCurrentUserId() ?? null;
        $stmt = $db->prepare(
            'INSERT INTO status_history (bill_id, visit_id, old_status, new_status, changed_by, source, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$billId, $visitId, $oldStatus, $newStatus, $userId, $source, $note]);
    }
}
