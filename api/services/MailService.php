<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/database.php';

class MailService {
    private $host;
    private $username;
    private $password;
    private $port;
    private $encryption;
    private $fromEmail;
    private $fromName;
    private $timeout = 30;

    public function __construct() {
        $settings = self::loadSettings();
        $this->host = $settings['smtp_host'] ?? 'mail.spacemail.com';
        $this->username = $settings['smtp_username'] ?? '';
        $this->password = $settings['smtp_password'] ?? '';
        $this->port = (int)($settings['smtp_port'] ?? 465);
        $this->encryption = $settings['smtp_encryption'] ?? 'ssl';
        $this->fromEmail = $settings['smtp_from_email'] ?? '';
        $this->fromName = $settings['smtp_from_name'] ?? 'Lumina AutoWorks';
    }

    private static function loadSettings() {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query('SELECT setting_key, setting_value FROM settings');
            $rows = $stmt->fetchAll();
            $cache = [];
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $cache = [];
        }
        return $cache;
    }

    public static function getSetting($key, $default = '') {
        $settings = self::loadSettings();
        return $settings[$key] ?? $default;
    }

    public static function isEmailEnabled() {
        return self::getSetting('email_enabled', '1') === '1';
    }

    public function send($to, $subject, $body, $isHtml = true) {
        if (!self::isEmailEnabled()) {
            error_log("MailService: Email sending is disabled in settings");
            return false;
        }

        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        $headers[] = 'Date: ' . date('r');

        if ($isHtml) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $headersStr = implode("\r\n", $headers);
        return $this->smtpSend($to, $subject, $body, $headersStr);
    }

    public function sendApprovalRequest($to, $customerName, $billId, $token, $totalAmount, $vehicleInfo) {
        $baseUrl = $this->getBaseUrl();
        $appBaseUrl = rtrim(dirname($baseUrl), '/');
        $approvalUrl = $appBaseUrl . "/?token={$token}";
        $fromName = $this->fromName;

        $subject = "Service Quotation Approval Request - Bill #{$billId}";

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; background: #3498db; color: white; padding: 12px 24px;
                          text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .details { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Service Quotation</h1>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>Your vehicle service quotation has been prepared. Please review and approve the quotation below.</p>

                    <div class='details'>
                        <h3>Bill Details</h3>
                        <p><strong>Bill ID:</strong> #{$billId}</p>
                        <p><strong>Vehicle:</strong> {$vehicleInfo}</p>
                        <p><strong>Total Amount:</strong> Rs. " . number_format($totalAmount, 2) . "</p>
                    </div>

                    <p>Please click the button below to review and approve your quotation:</p>
                    <a href='{$approvalUrl}' class='button'>Review & Approve Quotation</a>

                    <p><strong>Important:</strong> This approval link will expire in 7 days.</p>

                    <p>If you have any questions, please contact us.</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}<br>Lumina AutoWorks</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }

    public function sendVehicleRegistration($to, $customerName, $vehicleInfo, $issues, $odometer) {
        $fromName = $this->fromName;
        $subject = "Vehicle Registered - {$vehicleInfo}";

        $issuesStr = $issues ? "<p><strong>Reported Issues:</strong> {$issues}</p>" : '<p><strong>Reported Issues:</strong> None specified</p>';
        $odometerStr = $odometer ? number_format($odometer) . ' km' : 'N/A';

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Vehicle Checked In</h1>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>Your vehicle has been successfully registered at our service center. Our team will inspect the vehicle and prepare a detailed quotation for the required repairs or services.</p>

                    <div class='details'>
                        <h3>Vehicle Details</h3>
                        <p><strong>Vehicle:</strong> {$vehicleInfo}</p>
                        <p><strong>Odometer Reading:</strong> {$odometerStr}</p>
                        {$issuesStr}
                    </div>

                    <p>You will receive another email with the service quotation once our technicians complete their assessment. You can then approve or reject the quotation directly from your email.</p>

                    <p>If you have any questions, please contact us.</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}<br>Lumina AutoWorks</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }

    public function sendBillCompletion($to, $customerName, $billId, $totalAmount, $vehicleInfo, $estimatedDelivery = null) {
        $fromName = $this->fromName;
        $subject = "Vehicle Collected — Bill #{$billId}";

        $deliveryInfo = '';
        if ($estimatedDelivery) {
            $deliveryDate = date('F j, Y', strtotime($estimatedDelivery));
            $deliveryInfo = "<p><strong>Vehicle Ready for Collection:</strong> {$deliveryDate}</p>";
        }

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #27ae60; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .delivery-info { background: #eafaf1; border: 1px solid #27ae60; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Vehicle Collected</h1>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>Your vehicle has been collected from our service center. Thank you for choosing our service!</p>

                    <div class='details'>
                        <h3>Bill Summary</h3>
                        <p><strong>Bill ID:</strong> #{$billId}</p>
                        <p><strong>Vehicle:</strong> {$vehicleInfo}</p>
                        <p><strong>Total Amount:</strong> Rs. " . number_format($totalAmount, 2) . "</p>
                    </div>

                    <p>We hope you are satisfied with our work. If you have any questions, please don't hesitate to contact us.</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}<br>Lumina AutoWorks</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }

    public function sendReadyForPickup($to, $customerName, $billId, $totalAmount, $vehicleInfo, $estimatedDelivery = null) {
        $fromName = $this->fromName;
        $subject = "Vehicle Ready for Pickup — Bill #{$billId}";

        $deliveryInfo = '';
        if ($estimatedDelivery) {
            $deliveryDate = date('F j, Y', strtotime($estimatedDelivery));
            $deliveryInfo = "
                <div class='delivery-info'>
                    <h3><i class='fa-solid fa-calendar'></i> Estimated Pickup Date</h3>
                    <p><strong>{$deliveryDate}</strong></p>
                </div>";
        }

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #27ae60; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .delivery-info { background: #eafaf1; border: 1px solid #27ae60; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ready for Collection</h1>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>Great news! Your vehicle has been serviced and is <strong>ready for collection</strong>.</p>

                    <div class='details'>
                        <h3>Bill Summary</h3>
                        <p><strong>Bill ID:</strong> #{$billId}</p>
                        <p><strong>Vehicle:</strong> {$vehicleInfo}</p>
                        <p><strong>Total Amount:</strong> Rs. " . number_format($totalAmount, 2) . "</p>
                    </div>

                    {$deliveryInfo}

                    <p>Please visit our service center to collect your vehicle. Thank you for choosing Lumina AutoWorks!</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}<br>Lumina AutoWorks</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }

    public function sendRejectionNotice($to, $customerName, $billId, $reason, $vehicleInfo) {
        $fromName = $this->fromName;
        $subject = "Quotation Returned for Revision — Bill #{$billId}";

        $reasonInfo = '';
        if ($reason) {
            $reasonInfo = "
                <div class='delivery-info'>
                    <h3>Reason for Return</h3>
                    <p>{$reason}</p>
                </div>";
        }

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #e67e22; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .delivery-info { background: #fef5e7; border: 1px solid #e67e22; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Quotation Under Review</h1>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>The quotation for your vehicle has been reviewed and returned to our technician for revision.</p>

                    <div class='details'>
                        <h3>Bill Details</h3>
                        <p><strong>Bill ID:</strong> #{$billId}</p>
                        <p><strong>Vehicle:</strong> {$vehicleInfo}</p>
                    </div>

                    {$reasonInfo}

                    <p>You will receive an updated quotation once the technician makes the necessary adjustments.</p>

                    <p>If you have any questions, please contact us.</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}<br>Lumina AutoWorks</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }

    public function sendCustom($to, $subject, $body) {
        return $this->send($to, $subject, $body, true);
    }

    private function smtpSend($to, $subject, $body, $headersStr) {
        $errno = 0;
        $errstr = '';

        $protocol = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $address = $protocol . '://' . $this->host . ':' . $this->port;

        $socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);

        if (!$socket) {
            error_log("MailService: Failed to connect to {$address} - {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        if (!$this->smtpRead($socket)) return false;

        if ($this->encryption === 'tls') {
            $this->smtpCommand($socket, "EHLO localhost");
            $this->smtpCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("MailService: TLS handshake failed");
                fclose($socket);
                return false;
            }
        }

        $this->smtpCommand($socket, "EHLO localhost");
        $this->smtpCommand($socket, "AUTH LOGIN");
        $this->smtpCommand($socket, base64_encode($this->username));
        $this->smtpCommand($socket, base64_encode($this->password));
        $this->smtpCommand($socket, "MAIL FROM:<{$this->fromEmail}>");
        $this->smtpCommand($socket, "RCPT TO:<{$to}>");
        $this->smtpCommand($socket, "DATA");

        $emailContent = "Subject: {$subject}\r\n{$headersStr}\r\n\r\n{$body}";

        fwrite($socket, $emailContent . "\r\n.\r\n");
        if (!$this->smtpRead($socket)) return false;

        $this->smtpCommand($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function smtpCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
        return $this->smtpRead($socket);
    }

    private function smtpRead($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = isset($response[0]) ? (int)substr($response, 0, 3) : 0;

        if ($code >= 200 && $code < 400) {
            return true;
        }

        error_log("MailService: SMTP error - {$response}");
        return false;
    }

    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace(basename($script), '', $script), '/');
        return $protocol . '://' . $host . $basePath;
    }
}
