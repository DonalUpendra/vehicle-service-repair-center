<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/MailService.php';

class CustomerController {

    public static function index() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();

        $search = trim($_GET['search'] ?? '');

        if ($search) {
            $like = '%' . $search . '%';
            $stmt = $db->prepare(
                "SELECT v.id, v.registration_number, v.make, v.model, v.owner_name,
                        v.owner_email, v.owner_phone, MAX(vi.check_in_date) as last_visit_date,
                        vi.status as last_visit_status, b.total_amount as last_bill_amount
                 FROM vehicles v
                 LEFT JOIN visits vi ON v.id = vi.vehicle_id
                 LEFT JOIN bills b ON vi.bill_id = b.id
                 WHERE (v.owner_name LIKE ? OR v.owner_email LIKE ? OR v.registration_number LIKE ?)
                 GROUP BY v.id
                 ORDER BY last_visit_date DESC"
            );
            $stmt->execute([$like, $like, $like]);
        } else {
            $stmt = $db->prepare(
                "SELECT v.id, v.registration_number, v.make, v.model, v.owner_name,
                        v.owner_email, v.owner_phone, MAX(vi.check_in_date) as last_visit_date,
                        vi.status as last_visit_status, b.total_amount as last_bill_amount
                 FROM vehicles v
                 LEFT JOIN visits vi ON v.id = vi.vehicle_id
                 LEFT JOIN bills b ON vi.bill_id = b.id
                 GROUP BY v.id
                 ORDER BY last_visit_date DESC"
            );
            $stmt->execute();
        }

        $customers = $stmt->fetchAll();

        foreach ($customers as &$c) {
            $c['id'] = (int)$c['id'];
            $c['last_bill_amount'] = $c['last_bill_amount'] ? (float)$c['last_bill_amount'] : null;
        }

        jsonResponse($customers);
    }

    public static function sendEmail($id) {
        requireAdmin();
        $data = getJsonInput();

        $subject = trim($data['subject'] ?? '');
        $body = trim($data['body'] ?? '');

        if (!$subject || !$body) {
            jsonError('subject and body are required', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id, owner_name, owner_email FROM vehicles WHERE id = ?');
        $stmt->execute([(int)$id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            jsonError('Customer not found', 404);
        }

        if (empty($customer['owner_email'])) {
            jsonError('Customer has no email address', 400);
        }

        $mail = new MailService();
        $sent = $mail->sendCustom($customer['owner_email'], $subject, $body);

        jsonResponse([
            'message' => $sent ? 'Email sent successfully' : 'Failed to send email',
            'sent' => $sent,
            'to' => $customer['owner_email'],
            'customer_name' => $customer['owner_name'],
        ]);
    }
}
