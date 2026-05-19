<?php
/**
 * NOXARA - Auth Functions
 */

/**
 * Register member baru
 */
function registerUser(array $data): array {
    $db = db();

    // Cek duplikat
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR phone = ? LIMIT 1");
    $stmt->bind_param("sss", $data['email'], $data['username'], $data['phone']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Email, username, atau nomor HP sudah terdaftar.'];
    }
    $stmt->close();

    // Cek referral code
    $referredBy = null;
    if (!empty($data['referral_code'])) {
        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("s", $data['referral_code']);
        $stmt->execute();
        $ref = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ref) {
            return ['success' => false, 'message' => 'Kode referral tidak valid.'];
        }
        $referredBy = $ref['id'];
    }

    // Cek IP limit referral
    if ($referredBy) {
        $maxRef = (int)getSetting('max_ref_per_ip', 5);
        $ip     = getClientIP();
        $stmt   = $db->prepare("SELECT COUNT(*) as cnt FROM user_login_logs WHERE ip_address = ? AND status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        if ($cnt >= $maxRef) {
            return ['success' => false, 'message' => 'Terlalu banyak akun dari IP yang sama.'];
        }
    }

    $db->begin_transaction();
    try {
        $password     = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $referralCode = generateReferralCode();
        $freeBonus    = (int)getSetting('free_bonus_new_member', 5000);

        // Insert user
        $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, full_name, referral_code, referred_by, vip_level, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)");
        $stmt->bind_param("ssssssi", $data['username'], $data['email'], $data['phone'], $password, $data['full_name'], $referralCode, $referredBy);
        $stmt->execute();
        $userId = $db->insert_id;
        $stmt->close();

        // Buat wallet dengan saldo bonus gratis
        $stmt = $db->prepare("INSERT INTO user_wallets (user_id, balance_main, balance_profit, balance_bonus, balance_referral) VALUES (?, 0, 0, ?, 0)");
        $stmt->bind_param("ii", $userId, $freeBonus);
        $stmt->execute();
        $stmt->close();

        // Log transaksi saldo gratis
        if ($freeBonus > 0) {
            $desc = 'Saldo bonus registrasi gratis';
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, wallet_type, balance_before, balance_after, description, status) VALUES (?, 'bonus', ?, 'bonus', 0, ?, ?, 'completed')");
            $stmt->bind_param("iiis", $userId, $freeBonus, $freeBonus, $desc);
            $stmt->execute();
            $stmt->close();
        }

        // Notification settings default
        $stmt = $db->prepare("INSERT INTO notification_settings (user_id) VALUES (?)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        // Buat chat room
        $stmt = $db->prepare("INSERT INTO chat_rooms (user_id) VALUES (?)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        // Proses referral chain (L1, L2, L3)
        if ($referredBy) {
            insertReferralChain($userId, $referredBy, $db);
        }

        // Notifikasi selamat datang
        sendNotification($userId, 'system', 'Selamat Datang di NOXARA!',
            'Akun kamu berhasil dibuat. Dapatkan saldo bonus Rp ' . number_format($freeBonus, 0, ',', '.') . ' untuk mulai mining!', $db);

        $db->commit();
        return ['success' => true, 'message' => 'Registrasi berhasil!', 'user_id' => $userId];

    } catch (Exception $e) {
        $db->rollback();
        error_log('Register error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan. Silakan coba lagi.'];
    }
}

/**
 * Insert referral chain L1-L3
 */
function insertReferralChain(int $newUserId, int $directReferrerId, mysqli $db): void {
    // L1
    $stmt = $db->prepare("INSERT IGNORE INTO referrals (referrer_id, referred_id, level) VALUES (?, ?, 1)");
    $stmt->bind_param("ii", $directReferrerId, $newUserId);
    $stmt->execute();
    $stmt->close();

    // L2 — cari siapa yang refer L1
    $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $directReferrerId);
    $stmt->execute();
    $l2 = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($l2 && $l2['referred_by']) {
        $l2Id = (int)$l2['referred_by'];
        $stmt = $db->prepare("INSERT IGNORE INTO referrals (referrer_id, referred_id, level) VALUES (?, ?, 2)");
        $stmt->bind_param("ii", $l2Id, $newUserId);
        $stmt->execute();
        $stmt->close();

        // L3
        $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $l2Id);
        $stmt->execute();
        $l3 = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($l3 && $l3['referred_by']) {
            $l3Id = (int)$l3['referred_by'];
            $stmt = $db->prepare("INSERT IGNORE INTO referrals (referrer_id, referred_id, level) VALUES (?, ?, 3)");
            $stmt->bind_param("ii", $l3Id, $newUserId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Login user
 */
function loginUser(string $identifier, string $password): array {
    $db  = db();
    $ip  = getClientIP();
    $key = 'login_attempts_' . $ip;

    // Cek lock
    if (isset($_SESSION[$key . '_locked_until']) && time() < $_SESSION[$key . '_locked_until']) {
        $remaining = ceil(($_SESSION[$key . '_locked_until'] - time()) / 60);
        return ['success' => false, 'message' => "Akun dikunci. Coba lagi dalam {$remaining} menit."];
    }

    // Cari user by email atau username
    $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Gagal login
    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        $maxAttempts = (int)getSetting('max_login_attempts', 5);

        // Log gagal
        $userId = $user['id'] ?? null;
        $status = 'failed';
        $reason = 'Wrong password or user not found';
        $stmt = $db->prepare("INSERT INTO user_login_logs (user_id, email_or_username, ip_address, user_agent, status, fail_reason) VALUES (?, ?, ?, ?, ?, ?)");
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt->bind_param("isssss", $userId, $identifier, $ip, $agent, $status, $reason);
        $stmt->execute();
        $stmt->close();

        if ($_SESSION[$key] >= $maxAttempts) {
            $lockMinutes = (int)getSetting('login_lock_minutes', 30);
            $_SESSION[$key . '_locked_until'] = time() + ($lockMinutes * 60);
            unset($_SESSION[$key]);
            return ['success' => false, 'message' => "Terlalu banyak percobaan. Akun dikunci {$lockMinutes} menit."];
        }

        $remaining = $maxAttempts - $_SESSION[$key];
        return ['success' => false, 'message' => "Password salah. Sisa percobaan: {$remaining}x."];
    }

    // Cek status
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Akun tidak aktif. Hubungi admin.'];
    }
    if ($user['is_blocked']) {
        return ['success' => false, 'message' => 'Akun kamu telah diblokir. Hubungi admin.'];
    }

    // Cek maintenance
    if (getSetting('maintenance_mode') === '1') {
        return ['success' => false, 'message' => 'Sistem sedang maintenance. Coba beberapa saat lagi.'];
    }

    // Reset attempts
    unset($_SESSION[$key], $_SESSION[$key . '_locked_until']);

    // Set session
    setUserSession($user);

    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
    $stmt->bind_param("si", $ip, $user['id']);
    $stmt->execute();
    $stmt->close();

    // Log sukses
    $status = 'success';
    $reason = null;
    $stmt = $db->prepare("INSERT INTO user_login_logs (user_id, email_or_username, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?)");
    $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt->bind_param("issss", $user['id'], $identifier, $ip, $agent, $status);
    $stmt->execute();
    $stmt->close();

    // Update VIP otomatis
    updateVipLevel($user['id']);

    return ['success' => true, 'message' => 'Login berhasil!', 'user' => $user];
}

/**
 * Logout
 */
function logoutUser(): void {
    destroySession();
}

/**
 * Cek apakah email sudah ada
 */
function emailExists(string $email): bool {
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Cek apakah username sudah ada
 */
function usernameExists(string $username): bool {
    $stmt = db()->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Simpan token reset password
 */
function createPasswordResetToken(string $email): string|false {
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) return false;

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 jam

    $stmt = db()->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), used_at = NULL");
    $stmt->bind_param("sss", $email, $token, $expires);
    $stmt->execute();
    $stmt->close();

    return $token;
}

/**
 * Reset password dengan token
 */
function resetPasswordWithToken(string $token, string $newPassword): array {
    $stmt = db()->prepare("SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset) {
        return ['success' => false, 'message' => 'Token tidak valid atau sudah expired.'];
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    $now    = date('Y-m-d H:i:s');

    $stmt = db()->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed, $reset['email']);
    $stmt->execute();
    $stmt->close();

    $stmt = db()->prepare("UPDATE password_resets SET used_at = ? WHERE token = ?");
    $stmt->bind_param("ss", $now, $token);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Password berhasil direset.'];
}
