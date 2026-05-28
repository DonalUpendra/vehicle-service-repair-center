<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/MailService.php';

class SettingsController {

    public static function index() {
        requireAdmin();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query('SELECT id, setting_key, setting_value, updated_at FROM settings ORDER BY setting_key');
        $settings = $stmt->fetchAll();

        foreach ($settings as &$s) {
            $s['id'] = (int)$s['id'];
        }

        jsonResponse($settings);
    }

    public static function update() {
        requireAdmin();
        $data = getJsonInput();

        if (empty($data) || !is_array($data)) {
            jsonError('Settings data is required', 400);
        }

        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');

            foreach ($data as $key => $value) {
                $stmt->execute([$key, $value !== null ? (string)$value : '']);
            }

            $db->commit();
            jsonResponse(['message' => 'Settings updated successfully']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }

    public static function testEmail() {
        requireAdmin();
        $data = getJsonInput();
        $testEmail = trim($data['email'] ?? '');

        if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            jsonError('A valid test email address is required', 400);
        }

        try {
            $mail = new MailService();
            $sent = $mail->send(
                $testEmail,
                'Test Email - Lumina AutoWorks',
                '<html><body style="font-family:Arial;padding:20px;"><h2>Email Configuration Test</h2><p>This is a test email from your Lumina AutoWorks system.</p><p>If you received this, your email settings are configured correctly.</p></body></html>'
            );

            jsonResponse([
                'success' => $sent,
                'message' => $sent ? 'Test email sent successfully' : 'Failed to send test email. Check your SMTP settings.',
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ]);
        }
    }
}
