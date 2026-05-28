<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';

class NotificationController {

    public static function index() {
        requireAuth();
        $userId = getCurrentUserId();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT id, type, title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();

        foreach ($notifications as &$n) {
            $n['id'] = (int)$n['id'];
            $n['is_read'] = (bool)$n['is_read'];
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadCount = (int)$stmt->fetchColumn();

        jsonResponse([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public static function markRead($id) {
        requireAuth();
        $userId = getCurrentUserId();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$id, $userId]);

        jsonResponse(['message' => 'Notification marked as read']);
    }

    public static function markAllRead() {
        requireAuth();
        $userId = getCurrentUserId();
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);

        jsonResponse(['message' => 'All notifications marked as read']);
    }

    public static function create($userId, $type, $title, $message, $link = null) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->execute([$userId, $type, $title, $message, $link]);
        return (int)$db->lastInsertId();
    }
}
