<?php
/**
 * NOXARA - Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    // Session security settings (PHP 8.2)
    // Shared hosting: session save path fallback
    $sessionPath = ROOT_PATH . '/logs/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    ini_set('session.cookie_httponly', '1');
    // Ubah ke '1' jika domain sudah aktif SSL/HTTPS
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

    session_name(SESSION_NAME);
    session_start();

    // Regenerate session ID setiap 30 menit untuk keamanan
    if (!isset($_SESSION['_last_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    } elseif (time() - $_SESSION['_last_regenerated'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }
}

/**
 * Set session member
 */
function setUserSession(array $user): void {
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['vip_level']     = $user['vip_level'];
    $_SESSION['avatar']        = $user['avatar'];
    $_SESSION['is_verified']   = $user['is_verified'];
    $_SESSION['login_time']    = time();
    $_SESSION['user_ip']       = getClientIP();
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login — redirect ke login jika belum
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/dashboard');
        header('Location: /login?redirect=' . $redirect);
        exit;
    }

    // Cek apakah user diblokir
    $userId = (int)$_SESSION['user_id'];
    $stmt = db()->prepare("SELECT is_blocked, is_active FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result || !$result['is_active']) {
        destroySession();
        header('Location: /errors/blocked.php?reason=inactive');
        exit;
    }

    if ($result['is_blocked']) {
        destroySession();
        header('Location: /errors/blocked.php?reason=blocked');
        exit;
    }

    // Cek maintenance mode
    checkMaintenance();
}

/**
 * Destroy session (logout)
 */
function destroySession(): void {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

/**
 * Cek maintenance mode
 */
function checkMaintenance(): void {
    $stmt = db()->prepare("SELECT value FROM settings WHERE `key` = 'maintenance_mode'");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['value'] === '1') {
        // Admin bypass maintenance
        if (isset($_SESSION['admin_id'])) return;
        include ROOT_PATH . '/errors/maintenance.php';
        exit;
    }
}

/**
 * Get client real IP
 */
function getClientIP(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
