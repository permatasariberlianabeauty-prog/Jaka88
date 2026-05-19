<?php
/**
 * NOXARA - Bootstrap
 * File ini di-include di semua halaman sebagai entry point
 */

// Load config utama
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

// Ambil settings dari DB (cache di session per 5 menit)
function getSetting(string $key, mixed $default = null): mixed {
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = db()->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$key] = $row ? $row['value'] : $default;
    return $cache[$key];
}

/**
 * Format rupiah
 */
function formatRupiah(int|float $amount, bool $withPrefix = true): string {
    $formatted = number_format((float)$amount, 0, ',', '.');
    return $withPrefix ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format tanggal Indonesia
 */
function formatDate(string $date, string $format = 'd M Y'): string {
    $months = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
        '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
        '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des',
    ];
    $timestamp = strtotime($date);
    $result = date($format, $timestamp);
    foreach ($months as $num => $name) {
        $result = str_replace(date('M', mktime(0,0,0,(int)$num,1)), $name, $result);
    }
    return $result;
}

/**
 * Format tanggal + waktu
 */
function formatDateTime(string $datetime): string {
    return formatDate($datetime, 'd M Y') . ' ' . date('H:i', strtotime($datetime));
}

/**
 * Time ago Indonesia
 */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Baru saja';
    if ($diff < 3600)   return (int)($diff/60) . ' menit lalu';
    if ($diff < 86400)  return (int)($diff/3600) . ' jam lalu';
    if ($diff < 604800) return (int)($diff/86400) . ' hari lalu';
    return formatDate($datetime);
}

/**
 * Sanitize input
 */
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect
 */
function redirect(string $url, int $code = 302): never {
    header("Location: $url", true, $code);
    exit;
}

/**
 * JSON response
 */
function jsonResponse(bool $success, string $message, array $data = [], int $httpCode = 200): never {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

/**
 * Generate unique code
 */
function generateCode(string $prefix = '', int $length = 8): string {
    return strtoupper($prefix . bin2hex(random_bytes($length / 2)));
}

/**
 * Generate referral code
 */
function generateReferralCode(): string {
    do {
        $code = 'NOX' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $stmt = db()->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $code;
}

/**
 * Mask nama (untuk running text)
 */
function maskName(string $name): string {
    $parts = explode(' ', $name);
    $masked = [];
    foreach ($parts as $part) {
        if (strlen($part) <= 2) {
            $masked[] = $part;
        } else {
            $masked[] = substr($part, 0, 2) . str_repeat('*', strlen($part) - 2);
        }
    }
    return implode(' ', $masked);
}

/**
 * Mask nomor rekening
 */
function maskAccountNumber(string $number): string {
    $len = strlen($number);
    if ($len <= 4) return $number;
    return str_repeat('*', $len - 4) . substr($number, -4);
}

/**
 * Ambil data user yang sedang login
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user !== null) return $user;

    $id = (int)$_SESSION['user_id'];
    $stmt = db()->prepare("
        SELECT u.*, w.balance_main, w.balance_profit, w.balance_bonus, 
               w.balance_referral, w.is_frozen
        FROM users u
        LEFT JOIN user_wallets w ON w.user_id = u.id
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Hitung total saldo
 */
function getTotalBalance(array $user): int {
    return (int)($user['balance_main'] ?? 0)
         + (int)($user['balance_profit'] ?? 0)
         + (int)($user['balance_bonus'] ?? 0)
         + (int)($user['balance_referral'] ?? 0);
}

/**
 * Get unread notification count
 */
function getUnreadNotifCount(int $userId): int {
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

/**
 * Log aktivitas
 */
function logActivity(int $userId, string $action, string $description = ''): void {
    $ip = getClientIP();
    $stmt = db()->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}
