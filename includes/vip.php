<?php
/**
 * NOXARA - VIP Functions
 */

/**
 * Update VIP level berdasarkan total deposit kumulatif
 */
function updateVipLevel(int $userId): bool {
    $db = db();

    // Ambil total deposit kumulatif
    $stmt = $db->prepare("SELECT total_deposit_cumulative, vip_level FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) return false;

    // Cari level VIP yang sesuai (ambil level tertinggi yang terpenuhi)
    $stmt = $db->prepare("SELECT level FROM vip_levels WHERE min_deposit_cumulative <= ? AND is_active = 1 ORDER BY level DESC LIMIT 1");
    $stmt->bind_param("i", $user['total_deposit_cumulative']);
    $stmt->execute();
    $vip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $newLevel = $vip ? (int)$vip['level'] : 0;
    $oldLevel = (int)$user['vip_level'];

    // Hanya update kalau naik (VIP tidak turun)
    if ($newLevel > $oldLevel) {
        $stmt = $db->prepare("UPDATE users SET vip_level = ? WHERE id = ?");
        $stmt->bind_param("ii", $newLevel, $userId);
        $stmt->execute();
        $stmt->close();

        // Update session
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            $_SESSION['vip_level'] = $newLevel;
        }

        // Kirim notifikasi naik VIP
        $vipInfo = getVipInfo($newLevel);
        $label   = $vipInfo ? $vipInfo['badge_label'] : 'VIP ' . $newLevel;
        sendNotification($userId, 'vip',
            "Selamat! Kamu Naik ke VIP {$newLevel}!",
            "Kamu telah mencapai level {$label}! Nikmati keuntungan baru: min WD lebih rendah dan biaya admin lebih murah."
        );

        return true;
    }

    return false;
}

/**
 * Ambil semua info VIP user
 */
function getUserVipInfo(int $userId): array {
    $db = db();

    $stmt = $db->prepare("SELECT u.vip_level, u.total_deposit_cumulative, vl.* FROM users u LEFT JOIN vip_levels vl ON vl.level = u.vip_level WHERE u.id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Ambil semua level VIP
    $stmt    = $db->prepare("SELECT * FROM vip_levels WHERE is_active = 1 ORDER BY level ASC");
    $stmt->execute();
    $allLevels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Cari next level
    $nextLevel = null;
    foreach ($allLevels as $level) {
        if ($level['level'] > ($current['vip_level'] ?? 0)) {
            $nextLevel = $level;
            break;
        }
    }

    // Hitung progress ke next level
    $progress = 0;
    $needed   = 0;
    if ($nextLevel) {
        $currentMin = $current['min_deposit_cumulative'] ?? 0;
        $nextMin    = $nextLevel['min_deposit_cumulative'];
        $cumulative = $current['total_deposit_cumulative'] ?? 0;
        $range      = $nextMin - $currentMin;
        $done       = $cumulative - $currentMin;
        $progress   = $range > 0 ? min(100, max(0, ($done / $range) * 100)) : 0;
        $needed     = max(0, $nextMin - $cumulative);
    } else {
        $progress = 100; // Level max
    }

    return [
        'current'     => $current,
        'all_levels'  => $allLevels,
        'next_level'  => $nextLevel,
        'progress'    => round($progress, 1),
        'needed'      => $needed,
    ];
}

/**
 * Ambil kode VIP yang sudah di-unlock user
 */
function getUserVipCodes(int $userId): array {
    $db = db();

    $stmt = $db->prepare("SELECT u.vip_level FROM users u WHERE u.id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentLevel = (int)($user['vip_level'] ?? 0);

    $stmt = $db->prepare("SELECT vc.*, vl.min_deposit_cumulative, vl.min_withdraw, vl.withdraw_fee_percent, vl.color, vl.badge_label FROM vip_codes vc JOIN vip_levels vl ON vl.level = vc.vip_level ORDER BY vc.vip_level ASC");
    $stmt->execute();
    $codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($codes as &$code) {
        $code['is_unlocked'] = (int)$code['vip_level'] <= $currentLevel;
    }

    return $codes;
}

/**
 * Validasi kode VIP (untuk form)
 */
function validateVipCode(string $code, int $userId): array {
    $stmt = db()->prepare("SELECT vc.*, vl.badge_label FROM vip_codes vc JOIN vip_levels vl ON vl.level = vc.vip_level WHERE vc.code = ? AND vc.is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $vipCode = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vipCode) {
        return ['valid' => false, 'message' => 'Kode VIP tidak valid.'];
    }

    $stmt = db()->prepare("SELECT vip_level FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $userLevel = (int)($user['vip_level'] ?? 0);
    $codeLevel = (int)$vipCode['vip_level'];

    if ($userLevel < $codeLevel) {
        return [
            'valid'   => false,
            'message' => "Kode ini untuk VIP {$codeLevel} ({$vipCode['badge_label']}). Level kamu: VIP {$userLevel}.",
            'upgrade' => true,
            'needed_level' => $codeLevel,
        ];
    }

    return [
        'valid'       => true,
        'message'     => 'Kode VIP valid!',
        'code'        => $vipCode,
        'description' => $vipCode['description'],
        'benefit'     => $vipCode['benefit'],
    ];
}

/**
 * Ambil info VIP untuk penarikan (min WD & fee)
 */
function getWithdrawVipRules(int $userId): array {
    $stmt = db()->prepare("SELECT u.vip_level, vl.min_withdraw, vl.withdraw_fee_percent, vl.name, vl.badge_label, vl.color FROM users u JOIN vip_levels vl ON vl.level = u.vip_level WHERE u.id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: [
        'vip_level'            => 0,
        'min_withdraw'         => 100000,
        'withdraw_fee_percent' => 15,
        'name'                 => 'VIP 0',
        'badge_label'          => 'BASIC',
        'color'                => '#6B7A99',
    ];
}
