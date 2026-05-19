<?php
/**
 * NOXARA - Mining Functions
 */

/**
 * Beli paket mining
 */
function purchaseProduct(int $userId, int $productId, ?string $voucherCode = null, string $pin = ''): array {
    $db = db();

    // Cek PIN
    $stmt = $db->prepare("SELECT pin FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user['pin'] || !verifyPin($pin, $user['pin'])) {
        return ['success' => false, 'message' => 'PIN salah.'];
    }

    // Ambil produk
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.id = ? AND p.is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        return ['success' => false, 'message' => 'Produk tidak ditemukan.'];
    }

    $price    = (int)$product['price'];
    $discount = 0;
    $voucherId = null;

    // Validasi voucher
    if ($voucherCode) {
        $stmt = $db->prepare("SELECT * FROM vouchers WHERE code = ? AND is_active = 1 AND (type = 'product' OR type = 'general') AND valid_from <= NOW() AND valid_until >= NOW() LIMIT 1");
        $stmt->bind_param("s", $voucherCode);
        $stmt->execute();
        $voucher = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$voucher) {
            return ['success' => false, 'message' => 'Kode voucher tidak valid atau sudah expired.'];
        }

        // Cek VIP level
        $stmt = $db->prepare("SELECT vip_level FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userVip = (int)$stmt->get_result()->fetch_assoc()['vip_level'];
        $stmt->close();

        if ($userVip < (int)$voucher['min_vip_level']) {
            return [
                'success'       => false,
                'message'       => "Voucher ini hanya untuk VIP {$voucher['min_vip_level']} ke atas. Level kamu: VIP {$userVip}.",
                'vip_required'  => $voucher['min_vip_level'],
                'show_upgrade'  => true,
            ];
        }

        // Hitung diskon
        if ((int)$voucher['usage_limit'] > 0 && $voucher['usage_count'] >= $voucher['usage_limit']) {
            return ['success' => false, 'message' => 'Voucher sudah habis digunakan.'];
        }

        if ($price < (int)$voucher['min_amount']) {
            return ['success' => false, 'message' => 'Harga paket terlalu kecil untuk voucher ini.'];
        }

        if ($voucher['discount_type'] === 'percent') {
            $discount = (int)floor($price * $voucher['discount_value'] / 100);
            if ($voucher['max_discount'] && $discount > (int)$voucher['max_discount']) {
                $discount = (int)$voucher['max_discount'];
            }
        } else {
            $discount = (int)$voucher['discount_value'];
        }
        $voucherId = (int)$voucher['id'];
    }

    $finalPrice = max(0, $price - $discount);

    // Cek saldo (bonus bisa dipakai, main juga)
    $wallet = getUserWallet($userId);
    $totalAvailable = (int)$wallet['balance_main'] + (int)$wallet['balance_bonus'];

    if ($totalAvailable < $finalPrice) {
        return ['success' => false, 'message' => 'Saldo tidak mencukupi. Silakan deposit terlebih dahulu.'];
    }

    $db->begin_transaction();
    try {
        // Kurangi saldo (bonus dulu, sisanya dari main)
        $bonusBalance = (int)$wallet['balance_bonus'];
        $fromBonus = min($bonusBalance, $finalPrice);
        $fromMain  = $finalPrice - $fromBonus;

        if ($fromBonus > 0) {
            debitWallet($userId, 'bonus', $fromBonus, TRX_PURCHASE, 'Pembelian paket: ' . $product['name'], $productId, 'product');
        }
        if ($fromMain > 0) {
            debitWallet($userId, 'main', $fromMain, TRX_PURCHASE, 'Pembelian paket: ' . $product['name'], $productId, 'product');
        }

        // Insert user_product
        $startDate  = date('Y-m-d');
        $endDate    = date('Y-m-d', strtotime('+' . $product['duration_days'] . ' days'));
        $profitPerDay = (int)$product['profit_per_day'];

        $stmt = $db->prepare("INSERT INTO user_products (user_id, product_id, purchase_price, profit_per_day, voucher_id, discount_amount, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iiiiiiis", $userId, $productId, $finalPrice, $profitPerDay, $voucherId, $discount, $startDate, $endDate);
        $stmt->execute();
        $userProductId = $db->insert_id;
        $stmt->close();

        // Update voucher usage
        if ($voucherId) {
            $stmt = $db->prepare("UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?");
            $stmt->bind_param("i", $voucherId);
            $stmt->execute();
            $stmt->close();

            // Log user_voucher
            $now    = date('Y-m-d H:i:s');
            $usedFor = 'product_' . $productId;
            $stmt = $db->prepare("INSERT INTO user_vouchers (user_id, voucher_id, used_at, used_for, discount_given) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $userId, $voucherId, $now, $usedFor, $discount);
            $stmt->execute();
            $stmt->close();
        }

        // Proses komisi referral transaksi
        processReferralCommission($userId, $price, 'transaction', $userProductId);

        // Notifikasi
        sendNotification($userId, NOTIF_PROFIT,
            'Paket Berhasil Diaktifkan!',
            "Paket {$product['name']} aktif selama {$product['duration_days']} hari. Jangan lupa mining setiap hari!"
        );

        // Update misi
        updateMissionProgress($userId, 'purchase');

        $db->commit();
        return [
            'success'         => true,
            'message'         => 'Pembelian berhasil! Paket mining aktif.',
            'user_product_id' => $userProductId,
            'product_name'    => $product['name'],
            'final_price'     => $finalPrice,
            'discount'        => $discount,
            'end_date'        => $endDate,
        ];

    } catch (Exception $e) {
        $db->rollback();
        error_log('Purchase error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan saat pembelian.'];
    }
}

/**
 * Klik mining harian
 */
function doMining(int $userId, int $userProductId): array {
    $db = db();

    // Ambil paket
    $stmt = $db->prepare("SELECT up.*, p.name as product_name FROM user_products up JOIN products p ON p.id = up.product_id WHERE up.id = ? AND up.user_id = ? AND up.status = 'active' LIMIT 1");
    $stmt->bind_param("ii", $userProductId, $userId);
    $stmt->execute();
    $package = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$package) {
        return ['success' => false, 'message' => 'Paket tidak ditemukan atau tidak aktif.'];
    }

    // Cek tanggal berakhir
    if (date('Y-m-d') > $package['end_date']) {
        return ['success' => false, 'message' => 'Paket sudah berakhir.'];
    }

    // Cek sudah mining hari ini
    $today = date('Y-m-d');
    $stmt  = $db->prepare("SELECT id, profit_status FROM mining_logs WHERE user_product_id = ? AND mining_date = ? LIMIT 1");
    $stmt->bind_param("is", $userProductId, $today);
    $stmt->execute();
    $existingLog = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingLog) {
        if ($existingLog['profit_status'] === 'pending') {
            // Hitung sisa countdown
            $stmt = $db->prepare("SELECT mined_at FROM mining_logs WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $existingLog['id']);
            $stmt->execute();
            $log = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $minedAt  = strtotime($log['mined_at']);
            $creditAt = $minedAt + (MINING_COUNTDOWN_HOURS * 3600);
            $remaining = max(0, $creditAt - time());

            return [
                'success'   => false,
                'message'   => 'Sudah mining hari ini. Profit masuk dalam ' . gmdate('H:i:s', $remaining),
                'countdown' => $remaining,
                'status'    => 'pending',
            ];
        }
        return ['success' => false, 'message' => 'Sudah mining hari ini. Profit sudah masuk!', 'status' => 'completed'];
    }

    // Insert log mining
    $profitAmount = (int)$package['profit_per_day'];
    $now          = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO mining_logs (user_product_id, user_id, product_id, mined_at, profit_amount, profit_status, mining_date) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param("iiiiss", $userProductId, $userId, $package['product_id'], $now, $profitAmount, $today);
    $stmt->execute();
    $stmt->close();

    // Update last_mining_at & pending
    $stmt = $db->prepare("UPDATE user_products SET last_mining_at = NOW(), mining_profit_pending = ? WHERE id = ?");
    $stmt->bind_param("ii", $profitAmount, $userProductId);
    $stmt->execute();
    $stmt->close();

    // Update misi
    updateMissionProgress($userId, 'mining');

    $countdown = MINING_COUNTDOWN_HOURS * 3600;
    return [
        'success'      => true,
        'message'      => "Mining dimulai! Profit " . formatRupiah($profitAmount) . " masuk dalam " . MINING_COUNTDOWN_HOURS . " jam.",
        'countdown'    => $countdown,
        'profit_amount' => $profitAmount,
    ];
}

/**
 * Credit profit mining (dipanggil cron atau setelah 3 jam)
 */
function creditMiningProfit(): int {
    $db      = db();
    $credited = 0;
    $deadline = date('Y-m-d H:i:s', time() - (MINING_COUNTDOWN_HOURS * 3600));

    $stmt = $db->prepare("SELECT ml.*, up.user_id FROM mining_logs ml JOIN user_products up ON up.id = ml.user_product_id WHERE ml.profit_status = 'pending' AND ml.mined_at <= ?");
    $stmt->bind_param("s", $deadline);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($logs as $log) {
        $db->begin_transaction();
        try {
            $now = date('Y-m-d H:i:s');

            // Update log
            $stmt = $db->prepare("UPDATE mining_logs SET profit_status = 'completed', profit_credited_at = ? WHERE id = ?");
            $stmt->bind_param("si", $now, $log['id']);
            $stmt->execute();
            $stmt->close();

            // Credit profit ke saldo profit
            creditWallet($log['user_id'], 'profit', (int)$log['profit_amount'], TRX_PROFIT,
                'Profit mining harian', $log['user_product_id'], 'user_product');

            // Update user_product
            $stmt = $db->prepare("UPDATE user_products SET total_profit_earned = total_profit_earned + ?, mining_profit_pending = 0, total_days_mined = total_days_mined + 1 WHERE id = ?");
            $stmt->bind_param("ii", $log['profit_amount'], $log['user_product_id']);
            $stmt->execute();
            $stmt->close();

            // Notifikasi
            sendNotification($log['user_id'], NOTIF_PROFIT,
                'Profit Mining Masuk!',
                'Profit mining ' . formatRupiah($log['profit_amount']) . ' telah masuk ke saldo profit kamu!'
            );

            $db->commit();
            $credited++;
        } catch (Exception $e) {
            $db->rollback();
            error_log('Credit mining profit error: ' . $e->getMessage());
        }
    }

    return $credited;
}

/**
 * Ambil paket aktif user
 */
function getUserActivePackages(int $userId): array {
    $today = date('Y-m-d');
    $stmt  = db()->prepare("SELECT up.*, p.name, p.image, c.name as category_name, c.color as category_color,
                ml_today.profit_status as today_mining_status,
                ml_today.mined_at as today_mined_at,
                DATEDIFF(up.end_date, CURDATE()) as days_remaining,
                DATEDIFF(CURDATE(), up.start_date) + 1 as days_passed
                FROM user_products up
                JOIN products p ON p.id = up.product_id
                JOIN product_categories c ON c.id = p.category_id
                LEFT JOIN mining_logs ml_today ON ml_today.user_product_id = up.id AND ml_today.mining_date = ?
                WHERE up.user_id = ? AND up.status = 'active'
                ORDER BY up.created_at DESC");
    $stmt->bind_param("si", $today, $userId);
    $stmt->execute();
    $packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($packages as &$pkg) {
        $totalDays   = 30; // semua 30 hari
        $daysPassed  = min($totalDays, max(0, (int)$pkg['days_passed']));
        $pkg['progress_percent'] = round(($daysPassed / $totalDays) * 100, 1);

        // Hitung countdown kalau mining pending
        if ($pkg['today_mining_status'] === 'pending' && $pkg['today_mined_at']) {
            $minedAt  = strtotime($pkg['today_mined_at']);
            $creditAt = $minedAt + (MINING_COUNTDOWN_HOURS * 3600);
            $pkg['mining_countdown'] = max(0, $creditAt - time());
        } else {
            $pkg['mining_countdown'] = 0;
        }
    }

    return $packages;
}

/**
 * Update progress misi
 */
function updateMissionProgress(int $userId, string $action, int $amount = 1): void {
    $db = db();

    $stmt = $db->prepare("SELECT * FROM missions WHERE action = ? AND is_active = 1");
    $stmt->bind_param("s", $action);
    $stmt->execute();
    $missions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($missions as $mission) {
        $today = date('Y-m-d');

        // Cek apakah sudah ada progress
        $stmt = $db->prepare("SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ? LIMIT 1");
        $stmt->bind_param("ii", $userId, $mission['id']);
        $stmt->execute();
        $progress = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$progress) {
            $stmt = $db->prepare("INSERT INTO user_missions (user_id, mission_id, current_count, reset_date) VALUES (?, ?, 0, ?)");
            $stmt->bind_param("iis", $userId, $mission['id'], $today);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ? LIMIT 1");
            $stmt->bind_param("ii", $userId, $mission['id']);
            $stmt->execute();
            $progress = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($progress['is_claimed']) continue;

        // Reset misi harian/mingguan jika perlu
        if ($mission['type'] === 'daily' && $progress['reset_date'] < $today) {
            $stmt = $db->prepare("UPDATE user_missions SET current_count = 0, is_completed = 0, is_claimed = 0, reset_date = ? WHERE id = ?");
            $stmt->bind_param("si", $today, $progress['id']);
            $stmt->execute();
            $stmt->close();
            $progress['current_count'] = 0;
            $progress['is_completed']  = 0;
        }

        if ($progress['is_completed']) continue;

        // Update count
        $newCount = (int)$progress['current_count'] + $amount;
        $isCompleted = $newCount >= (int)$mission['target_count'] ? 1 : 0;
        $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("UPDATE user_missions SET current_count = ?, is_completed = ?, completed_at = ? WHERE id = ?");
        $stmt->bind_param("iisi", $newCount, $isCompleted, $completedAt, $progress['id']);
        $stmt->execute();
        $stmt->close();

        if ($isCompleted) {
            sendNotification($userId, NOTIF_MISSION,
                'Misi Selesai!',
                "Misi '{$mission['title']}' berhasil diselesaikan! Klaim rewardmu sekarang."
            );
        }
    }
}
