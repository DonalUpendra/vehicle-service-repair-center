<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/PushService.php';

class PushController {

    public static function publicKey() {
        $publicKey = PushService::getPublicKey();
        jsonResponse(['publicKey' => $publicKey]);
    }

    public static function subscribe() {
        requireAuth();
        $userId = getCurrentUserId();
        $data = getJsonInput();

        $endpoint = trim($data['endpoint'] ?? '');
        $p256dh = trim($data['keys']['p256dh'] ?? '');
        $auth = trim($data['keys']['auth'] ?? '');
        $userAgent = trim($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (!$endpoint || !$p256dh || !$auth) {
            jsonError('Missing subscription data', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
        $stmt->execute([$userId, $endpoint]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare('UPDATE push_subscriptions SET p256dh = ?, auth = ?, user_agent = ? WHERE id = ?');
            $stmt->execute([$p256dh, $auth, $userAgent, (int)$existing['id']]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $endpoint, $p256dh, $auth, $userAgent]);
        }

        jsonResponse(['message' => 'Subscribed successfully']);
    }

    public static function unsubscribe() {
        requireAuth();
        $userId = getCurrentUserId();
        $data = getJsonInput();

        $endpoint = trim($data['endpoint'] ?? '');

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
        $stmt->execute([$userId, $endpoint]);

        jsonResponse(['message' => 'Unsubscribed successfully']);
    }
}
