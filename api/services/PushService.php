<?php

class PushService {

    private static function log($message, $context = []) {
        $logFile = __DIR__ . '/../../push-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] {$message}";
        if (!empty($context)) {
            $entry .= ' | ' . json_encode($context);
        }
        $entry .= "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    private static function getSetting($key) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    }

    public static function getPublicKey() {
        $key = self::getSetting('vapid_public_key');
        if (!$key) {
            $keys = self::generateVAPIDKeys();
            self::saveVAPIDKeys($keys['publicKey'], $keys['privateKey']);
            return $keys['publicKey'];
        }
        return $key;
    }

    public static function generateVAPIDKeys() {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];
        $key = openssl_pkey_new($config);
        if (!$key) {
            throw new Exception('Failed to generate VAPID keypair: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($key);
        if (!$details || !isset($details['key'])) {
            throw new Exception('Failed to extract VAPID keys');
        }

        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        if (strlen($x) < 32) $x = str_repeat("\0", 32 - strlen($x)) . $x;
        if (strlen($y) < 32) $y = str_repeat("\0", 32 - strlen($y)) . $y;

        $publicKey = self::base64urlEncode(hex2bin('04') . $x . $y);

        $privateKeyOut = '';
        openssl_pkey_export($key, $privateKeyOut);
        $privateKey = self::pemToBase64Url($privateKeyOut);

        return [
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ];
    }

    public static function ensureVAPIDKeysExist() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
        $stmt->execute(['vapid_public_key']);
        if ((int)$stmt->fetchColumn() === 0) {
            $keys = self::generateVAPIDKeys();
            self::saveVAPIDKeys($keys['publicKey'], $keys['privateKey']);
        }
    }

    private static function saveVAPIDKeys($publicKey, $privateKey) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute(['vapid_public_key', $publicKey]);
        $stmt->execute(['vapid_private_key', $privateKey]);
    }

    private static function pemToBase64Url($pem) {
        $lines = explode("\n", trim($pem));
        $key = '';
        foreach ($lines as $line) {
            if (strpos($line, '-----') === false) {
                $key .= $line;
            }
        }
        return self::base64urlEncode(base64_decode($key));
    }

    public static function sendToAllTechnicians($title, $body, $link = '', $excludeUserId = null) {
        $db = Database::getInstance()->getConnection();
        $sql = 'SELECT ps.* FROM push_subscriptions ps
             JOIN users u ON ps.user_id = u.id
             WHERE u.role = ? AND u.active = 1';
        $params = ['technician'];

        if ($excludeUserId) {
            $sql .= ' AND ps.user_id != ?';
            $params[] = (int)$excludeUserId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            self::log('No active technician push subscriptions found');
            return [];
        }

        self::log('Sending push to ' . count($subscriptions) . ' technician(s)', [
            'title' => $title,
            'exclude_user_id' => $excludeUserId,
        ]);

        $results = [];
        foreach ($subscriptions as $sub) {
            $result = self::sendNotification(
                $sub['endpoint'],
                $sub['p256dh'],
                $sub['auth'],
                $title,
                $body,
                $link
            );
            $results[] = [
                'user_id' => (int)$sub['user_id'],
                'endpoint' => substr($sub['endpoint'], 0, 80) . '...',
                'success' => $result,
            ];

            if (!$result) {
                self::removeSubscription($sub['id']);
            }
        }

        self::log('Push results', $results);
        return $results;
    }

    public static function sendNotification($endpoint, $userPublicKey, $userAuthToken, $title, $body, $link = '') {
        $publicKey = self::getPublicKey();
        $privateKey = self::getSetting('vapid_private_key');

        if (!$publicKey || !$privateKey) {
            self::log('Missing VAPID keys', ['has_public' => (bool)$publicKey, 'has_private' => (bool)$privateKey]);
            return false;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/Vehicle%20Service%20%26%20Repair%20Center/icons/icon-192x192.png',
            'badge' => '/Vehicle%20Service%20%26%20Repair%20Center/icons/icon-192x192.png',
            'data' => [
                'link' => $link,
            ],
            'requireInteraction' => true,
        ]);

        $userPublicKeyBin = self::base64urlDecode($userPublicKey);
        $userAuthTokenBin = self::base64urlDecode($userAuthToken);

        $salt = random_bytes(16);

        $localConfig = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];
        $localKeyPair = openssl_pkey_new($localConfig);
        $localDetails = openssl_pkey_get_details($localKeyPair);
        $lx = $localDetails['ec']['x'];
        $ly = $localDetails['ec']['y'];
        if (strlen($lx) < 32) $lx = str_repeat("\0", 32 - strlen($lx)) . $lx;
        if (strlen($ly) < 32) $ly = str_repeat("\0", 32 - strlen($ly)) . $ly;
        $localPublicKeyBin = hex2bin('04') . $lx . $ly;

        $localPrivateKeyPem = '';
        openssl_pkey_export($localKeyPair, $localPrivateKeyPem);

        $sharedSecret = self::ecdh($localPrivateKeyPem, $userPublicKeyBin);

        $prkCombined = self::hkdfExtract($salt, $sharedSecret);

        $keyInfo = 'WebPush: info' . "\0" . $userPublicKeyBin . $localPublicKeyBin;
        $prk = self::hkdfExpand($prkCombined, $keyInfo, 32);

        $cekInfo = 'Content-Encoding: aes128gcm' . "\0";
        $contentEncryptionKey = self::hkdfExpand($prk, $cekInfo, 16);

        $nonceInfo = 'Content-Encoding: nonce' . "\0";
        $nonce = self::hkdfExpand($prk, $nonceInfo, 12);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $payload,
            'aes-128-gcm',
            $contentEncryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            self::log('Push encryption failed', ['openssl_error' => openssl_error_string()]);
            return false;
        }

        $rs = pack('N', 4096);
        $keylen = pack('n', 65);
        $encryptedBody = $salt . $rs . $keylen . $localPublicKeyBin . $ciphertext . $tag;

        $jwt = self::createVapidJWT($endpoint, $publicKey, $privateKey);

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: high',
            'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encryptedBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            self::log('Push curl error', [
                'error' => $curlError,
                'endpoint_domain' => parse_url($endpoint, PHP_URL_HOST),
            ]);
            return false;
        }

        if ($httpCode === 410) {
            self::log('Push subscription expired (410)', ['endpoint' => $endpoint]);
            return false;
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        if (!$success) {
            self::log('Push delivery failed', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200),
                'endpoint_domain' => parse_url($endpoint, PHP_URL_HOST),
            ]);
        }

        return $success;
    }

    public static function removeSubscription($id) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('DELETE FROM push_subscriptions WHERE id = ?');
        $stmt->execute([(int)$id]);
    }

    private static function createVapidJWT($audience, $publicKey, $privateKey) {
        $header = self::base64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));

        $origin = parse_url($audience, PHP_URL_SCHEME) . '://' . parse_url($audience, PHP_URL_HOST);
        $payload = self::base64urlEncode(json_encode([
            'aud' => $origin,
            'exp' => time() + 86400,
            'sub' => 'mailto:admin@vsrcenter.local',
        ]));

        $data = $header . '.' . $payload;

        $privateKeyBin = self::base64urlDecode($privateKey);
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" .
            chunk_split(base64_encode($privateKeyBin), 64, "\n") .
            "-----END EC PRIVATE KEY-----";

        $derSig = '';
        openssl_sign($data, $derSig, $pem, 'sha256');

        $rawSig = self::derToRawSignature($derSig);

        return $data . '.' . self::base64urlEncode($rawSig);
    }

    private static function derToRawSignature($der) {
        $pos = 2;
        $pos++;
        $rLen = ord($der[$pos]);
        $pos++;
        $rStart = $pos;
        if ($rLen > 32) {
            $rStart++;
            $rLen = 32;
        }
        $r = substr($der, $rStart, 32);
        if (strlen($r) < 32) {
            $r = str_repeat("\0", 32 - strlen($r)) . $r;
        }
        $pos = $rStart + $rLen;
        $pos++;
        $sLen = ord($der[$pos]);
        $pos++;
        $sStart = $pos;
        if ($sLen > 32) {
            $sStart++;
        }
        $s = substr($der, $sStart, 32);
        if (strlen($s) < 32) {
            $s = str_repeat("\0", 32 - strlen($s)) . $s;
        }
        return $r . $s;
    }

    private static function ecdh($privatePem, $publicKeyBin) {
        $der = self::rawEcPointToDer($publicKeyBin);
        $pubPem = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            "-----END PUBLIC KEY-----";

        $sharedSecret = openssl_pkey_derive($pubPem, $privatePem);
        return $sharedSecret ?: '';
    }

    private static function rawEcPointToDer($point) {
        $algorithmId = hex2bin('301306072a8648ce3d020106082a8648ce3d030107');
        $bitString = hex2bin('03') . self::encodeDerLength(65 + 1) . hex2bin('00') . $point;
        return hex2bin('30') . self::encodeDerLength(strlen($algorithmId) + strlen($bitString)) . $algorithmId . $bitString;
    }

    private static function encodeDerLength($length) {
        if ($length < 128) {
            return chr($length);
        }
        if ($length < 256) {
            return chr(0x81) . chr($length);
        }
        return chr(0x82) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
    }

    private static function hkdfExtract($salt, $ikm) {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    private static function hkdfExpand($prk, $info, $length) {
        $hashLen = 32;
        $blocks = (int) ceil($length / $hashLen);
        $okm = '';
        $prev = '';

        for ($i = 1; $i <= $blocks; $i++) {
            $prev = hash_hmac('sha256', $prev . $info . chr($i), $prk, true);
            $okm .= $prev;
        }

        return substr($okm, 0, $length);
    }

    private static function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
