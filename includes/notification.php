<?php
/**
 * NOXARA - Notification Functions
 */

/**
 * Kirim notifikasi ke user
 */
function sendNotification(int $userId, string $type, string $title, string $message, ?mysqli $dbConn = null): bool {
    $db = $dbConn ?? db();

    $colorMap = [
        NOTIF_DEPOSIT      => COLOR_CYAN,
        NOTIF_WITHDRAW     => COLOR_AMBER,
        NOTIF_PROFIT       => COLOR_GREEN,
        NOTIF_REFERRAL     => COLOR_PURPLE,
        NOTIF_VIP          => COLOR_AMBER,
        NOTIF_MISSION      => COLOR_CYAN,
        NOTIF_DAILY_REWARD => COLOR_GREEN,
        NOTIF_ANNOUNCEMENT => COLOR_MUTED,
        NOTIF_SYSTEM       => COLOR_MUTED,
    ];

    $color = $colorMap[$type] ?? COLOR_CYAN;

    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message, color) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $type, $title, $message, $color);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Kirim notifikasi massal ke semua member
 */
function sendBroadcastNotification(string $type, string $title, string $message, string $target = 'all'): int {
    $db    = db();
    $count = 0;

    if ($target === 'all') {
        $stmt = $db->prepare("SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0 AND vip_level >= ?");
        $stmt->bind_param("i", $target);
        $stmt->execute();
    }

    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($users as $user) {
        if (sendNotification((int)$user['id'], $type, $title, $message)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Ambil notifikasi user dengan pagination
 */
function getUserNotifications(int $userId, int $page = 1, string $filter = 'all'): array {
    $db      = db();
    $perPage = ITEMS_PER_PAGE;
    $offset  = ($page - 1) * $perPage;

    $where = "WHERE user_id = ?";
    $params = [$userId];
    $types  = "i";

    if ($filter === 'unread') {
        $where .= " AND is_read = 0";
    } elseif ($filter !== 'all') {
        $where .= " AND type = ?";
        $params[] = $filter;
        $types   .= "s";
    }

    // Count
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM notifications {$where}");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    // Data
    $params[] = $perPage;
    $params[] = $offset;
    $types   .= "ii";

    $stmt = $db->prepare("SELECT * FROM notifications {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['data' => $rows, 'total' => $total];
}

/**
 * Tandai semua notifikasi sudah dibaca
 */
function markAllNotificationsRead(int $userId): void {
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Tandai satu notifikasi sudah dibaca
 */
function markNotificationRead(int $notifId, int $userId): void {
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Hapus notifikasi lama (lebih dari 30 hari)
 */
function cleanOldNotifications(int $userId): void {
    $stmt = db()->prepare("DELETE FROM notifications WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Kirim via WhatsApp API (Fonnte/Wablas/dll)
 */
function sendWhatsApp(string $phone, string $message): bool {
    $apiKey   = getSetting('whatsapp_api_key');
    $provider = getSetting('whatsapp_provider', 'fonnte');
    if (!$apiKey) return false;

    // Normalize nomor
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    }

    if ($provider === 'fonnte') {
        $ch = curl_init('https://api.fonnte.com/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['target' => $phone, 'message' => $message],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $apiKey],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        return isset($result['status']) && $result['status'] === true;
    }

    return false;
}

/**
 * Kirim email (PHPMailer via SMTP)
 */
function sendEmail(string $to, string $subject, string $body): bool {
    $host = getSetting('smtp_host');
    $port = (int)getSetting('smtp_port', 587);
    $user = getSetting('smtp_user');
    $pass = getSetting('smtp_pass');
    $from = getSetting('smtp_from', 'noreply@noxara.com');

    if (!$host || !$user) return false;

    // Gunakan PHPMailer jika tersedia, fallback ke mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = $host;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = $user;
            $mailer->Password   = $pass;
            $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port       = $port;
            $mailer->CharSet    = 'UTF-8';
            $mailer->setFrom($from, getSetting('site_name', 'NOXARA'));
            $mailer->addAddress($to);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body    = $body;
            return $mailer->send();
        } catch (Exception $e) {
            error_log('Email error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from}\r\n";
    return mail($to, $subject, $body, $headers);
}

/**
 * Template email
 */
function getEmailTemplate(string $content, string $title): string {
    $siteName = getSetting('site_name', 'NOXARA');
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="background:#0A0E1A;font-family:Arial,sans-serif;color:#E8EAF0;margin:0;padding:20px;">
  <div style="max-width:600px;margin:0 auto;background:#0F1629;border-radius:16px;border:1px solid #1E2A45;padding:32px;">
    <h2 style="color:#00D4FF;font-size:24px;margin-bottom:8px;">{$siteName}</h2>
    <hr style="border:none;height:1px;background:linear-gradient(90deg,#00D4FF,#7B2FFF);margin-bottom:24px;">
    {$content}
    <hr style="border:none;height:1px;background:#1E2A45;margin-top:24px;">
    <p style="color:#6B7A99;font-size:12px;text-align:center;">© 2024 {$siteName}. All rights reserved.</p>
  </div>
</body>
</html>
HTML;
}
