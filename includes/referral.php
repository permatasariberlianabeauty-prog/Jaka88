<?php
/**
 * NOXARA - Referral & Commission Functions
 */

/**
 * Proses komisi referral
 */
function processReferralCommission(int $fromUserId, int $amount, string $type, int $refId): void {
    $db = db();

    // Ambil semua upline (L1, L2, L3)
    $stmt = $db->prepare("SELECT r.referrer_id, r.level FROM referrals r WHERE r.referred_id = ? ORDER BY r.level ASC");
    $stmt->bind_param("i", $fromUserId);
    $stmt->execute();
    $uplines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($uplines)) return;

    foreach ($uplines as $upline) {
        $level      = (int)$upline['level'];
        $referrerId = (int)$upline['referrer_id'];

        // Ambil % komisi dari settings
        $stmt = $db->prepare("SELECT percent FROM commission_settings WHERE type = ? AND level = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("si", $type, $level);
        $stmt->execute();
        $setting = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$setting || $setting['percent'] <= 0) continue;

        $percent    = (float)$setting['percent'];
        $commission = (int)floor($amount * $percent / 100);
        if ($commission <= 0) continue;

        // Cek apakah referrer aktif (punya deposit)
        $stmt = $db->prepare("SELECT total_deposit_cumulative FROM users WHERE id = ? AND is_active = 1 AND is_blocked = 0 LIMIT 1");
        $stmt->bind_param("i", $referrerId);
        $stmt->execute();
        $referrer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$referrer) continue;

        // Kredit ke saldo referral
        creditWallet($referrerId, 'referral', $commission, TRX_COMMISSION,
            "Rabat {$type} L{$level} dari member", $refId, $type);

        // Log komisi
        $refType = $type;
        $status  = 'completed';
        $stmt    = $db->prepare("INSERT INTO commissions (user_id, from_user_id, level, type, source_amount, commission_percent, commission_amount, reference_id, reference_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissdiiss", $referrerId, $fromUserId, $level, $type, $amount, $percent, $commission, $refId, $refType, $status);
        $stmt->execute();
        $stmt->close();

        // Notifikasi ke upline
        sendNotification($referrerId, NOTIF_REFERRAL,
            "Rabat Referral Masuk!",
            "Rabat " . ucfirst($type) . " Level {$level}: +" . formatRupiah($commission) . " masuk ke saldo referral kamu!"
        );
    }
}

/**
 * Ambil data downline user
 */
function getUserDownlines(int $userId, ?int $level = null): array {
    $db = db();

    $sql = "SELECT r.level, u.id, u.username, u.full_name, u.avatar, u.vip_level, u.created_at,
                   w.balance_main, u.total_deposit_cumulative,
                   CASE WHEN u.total_deposit_cumulative > 0 THEN 'active' ELSE 'inactive' END as status
            FROM referrals r
            JOIN users u ON u.id = r.referred_id
            LEFT JOIN user_wallets w ON w.user_id = u.id
            WHERE r.referrer_id = ?";

    if ($level !== null) {
        $sql .= " AND r.level = ?";
        $stmt = $db->prepare($sql . " ORDER BY r.level ASC, u.created_at DESC");
        $stmt->bind_param("ii", $userId, $level);
    } else {
        $stmt = $db->prepare($sql . " ORDER BY r.level ASC, u.created_at DESC");
        $stmt->bind_param("i", $userId);
    }

    $stmt->execute();
    $downlines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $downlines;
}

/**
 * Statistik referral user
 */
function getReferralStats(int $userId): array {
    $db = db();

    // Total per level
    $stmt = $db->prepare("SELECT r.level, COUNT(*) as total,
                SUM(CASE WHEN u.total_deposit_cumulative > 0 THEN 1 ELSE 0 END) as active
                FROM referrals r
                JOIN users u ON u.id = r.referred_id
                WHERE r.referrer_id = ?
                GROUP BY r.level");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $levelStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Total komisi hari ini
    $stmt = $db->prepare("SELECT SUM(commission_amount) as today FROM commissions WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $todayCommission = (int)($stmt->get_result()->fetch_assoc()['today'] ?? 0);
    $stmt->close();

    // Total komisi all time
    $stmt = $db->prepare("SELECT SUM(commission_amount) as total FROM commissions WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $totalCommission = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Susun per level
    $byLevel = [1 => ['total' => 0, 'active' => 0], 2 => ['total' => 0, 'active' => 0], 3 => ['total' => 0, 'active' => 0]];
    foreach ($levelStats as $stat) {
        $byLevel[(int)$stat['level']] = ['total' => (int)$stat['total'], 'active' => (int)$stat['active']];
    }

    return [
        'by_level'         => $byLevel,
        'total_downlines'  => array_sum(array_column($levelStats, 'total')),
        'active_downlines' => array_sum(array_column($levelStats, 'active')),
        'today_commission' => $todayCommission,
        'total_commission' => $totalCommission,
    ];
}

/**
 * Riwayat komisi dengan filter
 */
function getCommissionHistory(int $userId, string $type = 'all', int $page = 1): array {
    $db      = db();
    $perPage = ITEMS_PER_PAGE;
    $offset  = ($page - 1) * $perPage;

    $where = "WHERE c.user_id = ?";
    $params = [$userId];
    $types  = "i";

    if ($type !== 'all') {
        $where .= " AND c.type = ?";
        $params[] = $type;
        $types   .= "s";
    }

    // Count
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM commissions c {$where}");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    // Data
    $stmt = $db->prepare("SELECT c.*, u.username, u.full_name, u.avatar
                          FROM commissions c
                          JOIN users u ON u.id = c.from_user_id
                          {$where}
                          ORDER BY c.created_at DESC
                          LIMIT ? OFFSET ?");
    $params[] = $perPage;
    $params[] = $offset;
    $types   .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['data' => $rows, 'total' => $total];
}

/**
 * Build referral tree untuk tampilan pohon
 */
function buildReferralTree(int $userId, int $depth = 3): array {
    if ($depth === 0) return [];
    $db   = db();
    $stmt = $db->prepare("SELECT r.referred_id, r.level, u.username, u.full_name, u.avatar, u.vip_level, u.total_deposit_cumulative FROM referrals r JOIN users u ON u.id = r.referred_id WHERE r.referrer_id = ? AND r.level = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($children as &$child) {
        $child['status']   = $child['total_deposit_cumulative'] > 0 ? 'active' : 'inactive';
        $child['children'] = $depth > 1 ? buildReferralTree($child['referred_id'], $depth - 1) : [];
    }

    return $children;
}
