<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController {

    public static function login() {
        $data = getJsonInput();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            jsonError('Email and password are required', 400);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, name, email, password_hash, role, active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonError('Invalid email or password', 401);
        }

        if (!$user['active']) {
            jsonError('Account has been disabled. Contact administrator.', 403);
        }

        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];

        jsonResponse([
            'user' => $_SESSION['user'],
            'message' => 'Login successful',
        ]);
    }

    public static function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        jsonResponse(['message' => 'Logged out successfully']);
    }

    public static function me() {
        requireAuth();
        jsonResponse(['user' => $_SESSION['user']]);
    }
}
