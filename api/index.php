<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/VehicleController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/BillController.php';
require_once __DIR__ . '/controllers/PublicController.php';
require_once __DIR__ . '/controllers/ReportController.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/SalaryController.php';
require_once __DIR__ . '/controllers/MakesController.php';
require_once __DIR__ . '/controllers/ModelsController.php';
require_once __DIR__ . '/controllers/PushController.php';

$method = $_SERVER['REQUEST_METHOD'];

$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
if (empty($path)) {
    $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $base = dirname($_SERVER['SCRIPT_NAME']);
    if ($base !== '/' && strpos($uri, $base) === 0) {
        $path = substr($uri, strlen($base));
    }
}
$path = rtrim($path, '/');
if ($path === '') $path = '/';

$segments = explode('/', trim($path, '/'));
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

try {
    Database::getInstance()->getConnection();
} catch (PDOException $e) {
    jsonError('Database connection failed.', 500);
}

// Public endpoints (no auth required)
if ($resource === 'public') {
    if ($id === 'quotation' && $method === 'GET') {
        PublicController::quotation();
    }
    if ($id === 'approve' && $method === 'POST') {
        PublicController::approve();
    }
    jsonError('Not found', 404);
}

// Auth endpoints
if ($resource === 'login' && $method === 'POST') {
    AuthController::login();
}
if ($resource === 'logout' && $method === 'POST') {
    AuthController::logout();
}
if ($resource === 'me' && $method === 'GET') {
    AuthController::me();
}

// Users (technician management)
if ($resource === 'users') {
    if ($method === 'GET' && !$id) {
        UserController::index();
    }
    if ($method === 'POST' && !$id) {
        UserController::store();
    }
    if ($id && $method === 'PUT') {
        UserController::update($id);
    }
    if ($id && $method === 'DELETE') {
        UserController::destroy($id);
    }
    jsonError('Not found', 404);
}

// Vehicles
if ($resource === 'vehicles') {
    if ($method === 'GET' && !$id) {
        VehicleController::index();
    }
    if ($method === 'GET' && $id) {
        VehicleController::show($id);
    }
    if ($method === 'POST' && $id === 'check-in') {
        VehicleController::checkin();
    }
    if ($method === 'POST' && !$id) {
        VehicleController::store();
    }
    if ($id && $method === 'PUT') {
        VehicleController::update($id);
    }
    if ($id && $method === 'DELETE') {
        VehicleController::destroy($id);
    }
    jsonError('Not found', 404);
}

// Visits
if ($resource === 'visits') {
    if ($id === 'stale' && $method === 'GET') {
        VehicleController::staleVisits();
    }
    if ($id && $method === 'GET') {
        require_once __DIR__ . '/middleware/auth.php';
        requireAuth();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT vi.*, v.registration_number, v.make, v.model, v.owner_name,
                    b.id as bill_id, b.status as bill_status, b.total_amount
             FROM visits vi
             JOIN vehicles v ON vi.vehicle_id = v.id
             LEFT JOIN bills b ON vi.id = b.visit_id
             WHERE vi.id = ?'
        );
        $stmt->execute([(int)$id]);
        $visit = $stmt->fetch();
        if (!$visit) jsonError('Visit not found', 404);
        $visit['id'] = (int)$visit['id'];
        $visit['vehicle_id'] = (int)$visit['vehicle_id'];
        $visit['bill_id'] = $visit['bill_id'] ? (int)$visit['bill_id'] : null;
        $visit['total_amount'] = $visit['total_amount'] ? (float)$visit['total_amount'] : null;
        jsonResponse($visit);
    }
    if ($id && $method === 'PUT') {
        require_once __DIR__ . '/middleware/auth.php';
        VehicleController::cancelVisit($id);
    }
    jsonError('Not found', 404);
}

// Products
if ($resource === 'products') {
    if ($method === 'GET' && !$id) {
        ProductController::index();
    }
    if ($method === 'POST' && !$id) {
        ProductController::store();
    }
    if ($id && $method === 'PUT') {
        ProductController::update($id);
    }
    if ($id && $method === 'DELETE') {
        ProductController::destroy($id);
    }
    jsonError('Not found', 404);
}

// Bills
if ($resource === 'bills') {
    if ($method === 'GET' && !$id) {
        BillController::index();
    }
    if ($method === 'GET' && $id && !$action) {
        BillController::show($id);
    }
    if ($method === 'POST' && !$id) {
        BillController::store();
    }
    if ($id && $action === 'submit' && $method === 'POST') {
        BillController::submit($id);
    }
    if ($id && $action === 'status' && $method === 'PUT') {
        BillController::updateStatus($id);
    }
    if ($id && $action === 'resend' && $method === 'POST') {
        BillController::resend($id);
    }
    if ($id && $action === 'job-done' && $method === 'POST') {
        BillController::jobDone($id);
    }
    jsonError('Not found', 404);
}

// Reports
if ($resource === 'reports') {
    if ($id === 'today' && $method === 'GET') {
        ReportController::today();
    }
    if ($id === 'pending' && $method === 'GET') {
        ReportController::pending();
    }
    if ($id === 'revenue' && $method === 'GET') {
        ReportController::revenue();
    }
    if ($id === 'dashboard' && $method === 'GET') {
        ReportController::dashboard();
    }
    jsonError('Not found', 404);
}

// Jobs
if ($resource === 'jobs') {
    if ($method === 'GET' && !$id) {
        BillController::jobsList();
    }
    if ($id === 'stats' && $method === 'GET') {
        BillController::jobsStats();
    }
    jsonError('Not found', 404);
}

// Vehicle Makes
if ($resource === 'makes') {
    if ($method === 'GET' && !$id) {
        MakesController::index();
    }
    if ($method === 'POST' && !$id) {
        MakesController::store();
    }
    if ($id && $method === 'PUT') {
        MakesController::update($id);
    }
    if ($id && $method === 'DELETE') {
        MakesController::destroy($id);
    }
    jsonError('Not found', 404);
}

// Vehicle Models
if ($resource === 'models') {
    if ($method === 'GET' && !$id) {
        ModelsController::index();
    }
    if ($method === 'POST' && !$id) {
        ModelsController::store();
    }
    if ($id && $method === 'PUT') {
        ModelsController::update($id);
    }
    if ($id && $method === 'DELETE') {
        ModelsController::destroy($id);
    }
    jsonError('Not found', 404);
}

// Settings
if ($resource === 'settings') {
    if ($method === 'GET' && !$id) {
        SettingsController::index();
    }
    if ($method === 'PUT' && !$id) {
        SettingsController::update();
    }
    if ($id === 'test-email' && $method === 'POST') {
        SettingsController::testEmail();
    }
    jsonError('Not found', 404);
}

// Email
if ($resource === 'email') {
    if ($method === 'POST' && !$id) {
        require_once __DIR__ . '/middleware/auth.php';
        requireAdmin();
        $data = getJsonInput();
        $to = trim($data['to'] ?? '');
        $subject = trim($data['subject'] ?? '');
        $body = trim($data['body'] ?? '');
        
        if (!$to || !$subject || !$body) {
            jsonError('to, subject, and body are required', 400);
        }
        
        $mail = new MailService();
        $sent = $mail->sendCustom($to, $subject, $body);
        
        jsonResponse([
            'message' => $sent ? 'Email sent successfully' : 'Failed to send email',
            'sent' => $sent,
        ]);
    }
    jsonError('Not found', 404);
}

// Customers
if ($resource === 'customers') {
    if ($method === 'GET' && !$id) {
        CustomerController::index();
    }
    if ($id && $action === 'send-email' && $method === 'POST') {
        CustomerController::sendEmail($id);
    }
    jsonError('Not found', 404);
}

// Salary Payments
if ($resource === 'salaries') {
    if ($method === 'GET' && !$id) {
        SalaryController::index();
    }
    if ($method === 'POST' && !$id) {
        SalaryController::store();
    }
    if ($id && $method === 'DELETE') {
        SalaryController::destroy($id);
    }
    if ($id && $action === 'earnings' && $method === 'GET') {
        SalaryController::technicianEarnings($id);
    }
    jsonError('Not found', 404);
}

// Notifications
if ($resource === 'notifications') {
    if ($method === 'GET' && !$id) {
        NotificationController::index();
    }
    if ($id === 'read-all' && $method === 'POST') {
        NotificationController::markAllRead();
    }
    if ($id && is_numeric($id) && $action === 'read' && $method === 'POST') {
        NotificationController::markRead($id);
    }
    jsonError('Not found', 404);
}

// Push Notifications
if ($resource === 'push') {
    if ($id === 'public-key' && $method === 'GET') {
        PushController::publicKey();
    }
    if ($id === 'subscribe' && $method === 'POST') {
        PushController::subscribe();
    }
    if ($id === 'unsubscribe' && $method === 'POST') {
        PushController::unsubscribe();
    }
    if ($id === 'test' && $method === 'POST') {
        PushController::test();
    }
    jsonError('Not found', 404);
}

jsonError('Endpoint not found', 404);
