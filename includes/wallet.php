<?php
/**
 * NOXARA - Wallet & Transaction Functions
 */

/**
 * Tambah saldo ke wallet
 */
function creditWallet(int $userId, string $walletType, int $amount, string $txType, string $description = '', int $refId = 0, string $refType = ''): bool {
    if ($amount <= 0) return false;
    $db = db();

    $col = 'balance_' . $walletType;
    $allowedCols = ['balance_main', 'balance_profit', 'balance_bonus', 'balance_referral'];
    if (!in_array($col, $allowedCols)) return false;

    // Cek saldo saat ini
    $stmt = $db->prepare("SELECT {$col} FROM user_wallets WHERE user_id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$wallet) return false;

    $balanceBefore = (int)$wallet[$col];
    $balanceAfter  = $balanceBefore + $amount;

    // Update saldo
    $stmt = $db->prepare("UPDATE user_wallets SET {$col} = {$col} + ? WHERE user_id = ?");
    $stmt->bind_param("ii", $amount, $userId);
    $stmt->execute();
    $stmt->close();

    // Log transaksi
    $status = 'completed';
    $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, wallet_type, balance_before, balance_after, reference_id, reference_type, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isissiiiss", $userId, $txType, $amount, $walletType, $balanceBefore, $balanceAfter, $refId, $refType, $description, $status);
    $stmt->execute();
    $stmt->close();

    return true;
}

/**
 * Kurangi saldo dari wallet
 */
function debitWallet(int $userId, string $walletType, int $amount, string $txType, string $description = '', int $refId = 0, string $refType = ''): bool {
    if ($amount <= 0) return false;
    $db = db();

    $col = 'balance_' . $walletType;
    $allowedCols = ['balance_main', 'balance_profit', 'balance_bonus', 'balance_referral'];
    if (!in_array($col, $allowedCols)) return false;

    // Cek saldo cukup
    $stmt = $db->prepare("SELECT {$col} FROM user_wallets WHERE user_id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$wallet || (int)$wallet[$col] < $amount) return false;

    $balanceBefore = (int)$wallet[$col];
    $balanceAfter  = $balanceBefore - $amount;

    // Update saldo
    $stmt = $db->prepare("UPDATE user_wallets SET {$col} = {$col} - ? WHERE user_id = ? AND {$col} >= ?");
    $stmt->bind_param("iii", $amount, $userId, $amount);
    $stmt->execute();
    $affected = $db->affected_rows;
    $stmt->close();

    if ($affected === 0) return false;

    // Log transaksi
    $negAmount = -$amount;
    $status    = 'completed';
    $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, wallet_type, balance_before, balance_after, reference_id, reference_type, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isissiiiss", $userId, $txType, $negAmount, $walletType, $balanceBefore, $balanceAfter, $refId, $refType, $description, $status);
    $stmt->execute();
    $stmt->close();

    return true;
}

/**
 * Ambil semua saldo user
 */
function getUserWallet(int $userId): array {
    $stmt = db()->prepare("SELECT * FROM user_wallets WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: [
        'balance_main'     => 0,
        'balance_profit'   => 0,
        'balance_bonus'    => 0,
        'balance_referral' => 0,
        'is_frozen'        => 0,
    ];
}

/**
 * Cek saldo cukup
 */
function hasSufficientBalance(int $userId, string $walletType, int $amount): bool {
    $wallet = getUserWallet($userId);
    $col    = 'balance_' . $walletType;
    return isset($wallet[$col]) && (int)$wallet[$col] >= $amount;
}

/**
 * Proses deposit (submit manual)
 */
function submitDeposit(int $userId, int $amount, int $bankId, ?string $proofFile = null, ?int $voucherId = null): array {
    $db = db();

    $minDeposit = (int)getSetting('min_deposit', 10000);
    $maxDeposit = (int)getSetting('max_deposit', 50000000);

    if ($amount < $minDeposit) {
        return ['success' => false, 'message' => 'Minimal deposit ' . formatRupiah($minDeposit)];
    }
    if ($amount > $maxDeposit) {
        return ['success' => false, 'message' => 'Maksimal deposit ' . formatRupiah($maxDeposit)];
    }

    // Generate kode unik
    $uniqueCode  = generateDepositUniqueCode();
    $totalAmount = $amount + $uniqueCode;
    $depositCode = generateDepositCode();
    $expiryHours = (int)getSetting('deposit_expiry_hours', 3);
    $expiresAt   = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));

    // Cek & hitung voucher
    $voucherBonus = 0;
    if ($voucherId) {
        $stmt = $db->prepare("SELECT * FROM vouchers WHERE id = ? AND is_active = 1 AND valid_from <= NOW() AND valid_until >= NOW() LIMIT 1");
        $stmt->bind_param("i", $voucherId);
        $stmt->execute();
        $voucher = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($voucher) {
            if ($voucher['discount_type'] === 'percent') {
                $voucherBonus = (int)floor($amount * $voucher['discount_value'] / 100);
                if ($voucher['max_discount'] && $voucherBonus > $voucher['max_discount']) {
                    $voucherBonus = (int)$voucher['max_discount'];
                }
            } else {
                $voucherBonus = (int)$voucher['discount_value'];
            }
        }
    }

    $stmt = $db->prepare("INSERT INTO deposits (user_id, deposit_code, amount, unique_code, total_amount, bank_target_id, payment_proof, voucher_id, voucher_bonus, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param("isiiiisis", $userId, $depositCode, $amount, $uniqueCode, $totalAmount, $bankId, $proofFile, $voucherId, $voucherBonus, $expiresAt);
    $stmt->execute();
    $depositId = $db->insert_id;
    $stmt->close();

    return [
        'success'      => true,
        'message'      => 'Deposit berhasil disubmit. Menunggu konfirmasi admin.',
        'deposit_id'   => $depositId,
        'deposit_code' => $depositCode,
        'unique_code'  => $uniqueCode,
        'total_amount' => $totalAmount,
        'expires_at'   => $expiresAt,
    ];
}

/**
 * Konfirmasi deposit (admin)
 */
function confirmDeposit(int $depositId, int $adminId): array {
    $db = db();

    $stmt = $db->prepare("SELECT * FROM deposits WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->bind_param("i", $depositId);
    $stmt->execute();
    $deposit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$deposit) {
        return ['success' => false, 'message' => 'Deposit tidak ditemukan atau sudah diproses.'];
    }

    $db->begin_transaction();
    try {
        $userId = (int)$deposit['user_id'];
        $amount = (int)$deposit['amount'];
        $bonus  = (int)$deposit['voucher_bonus'];

        // Update status deposit
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE deposits SET status = 'confirmed', confirmed_by = ?, confirmed_at = ? WHERE id = ?");
        $stmt->bind_param("isi", $adminId, $now, $depositId);
        $stmt->execute();
        $stmt->close();

        // Kredit saldo utama
        creditWallet($userId, 'main', $amount, TRX_DEPOSIT, 'Deposit dikonfirmasi - ' . $deposit['deposit_code'], $depositId, 'deposit');

        // Kredit bonus voucher ke saldo bonus
        if ($bonus > 0) {
            creditWallet($userId, 'bonus', $bonus, TRX_VOUCHER, 'Bonus voucher deposit', $depositId, 'deposit');
        }

        // Update total deposit kumulatif
        $stmt = $db->prepare("UPDATE users SET total_deposit_cumulative = total_deposit_cumulative + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $userId);
        $stmt->execute();
        $stmt->close();

        // Update voucher usage
        if ($deposit['voucher_id']) {
            $stmt = $db->prepare("UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?");
            $stmt->bind_param("i", $deposit['voucher_id']);
            $stmt->execute();
            $stmt->close();
        }

        // Proses komisi referral deposit
        processReferralCommission($userId, $amount, 'deposit', $depositId);

        // Update VIP
        updateVipLevel($userId);

        // Notifikasi
        sendNotification($userId, NOTIF_DEPOSIT,
            'Deposit Dikonfirmasi!',
            'Deposit ' . formatRupiah($amount) . ' berhasil masuk ke saldo utama kamu!'
        );

        $db->commit();
        return ['success' => true, 'message' => 'Deposit berhasil dikonfirmasi.'];

    } catch (Exception $e) {
        $db->rollback();
        error_log('Confirm deposit error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan saat konfirmasi.'];
    }
}

/**
 * Submit penarikan
 */
function submitWithdrawal(int $userId, int $amount, int $bankAccountId, string $fromWallet, string $pin): array {
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

    // Cek VIP rules
    $vipRules  = getWithdrawVipRules($userId);
    $minWD     = (int)$vipRules['min_withdraw'];
    $feePercent = (float)$vipRules['withdraw_fee_percent'];

    if ($amount < $minWD) {
        return ['success' => false, 'message' => 'Minimal penarikan ' . formatRupiah($minWD) . ' untuk VIP kamu.'];
    }

    // Cek jam operasional
    if (!isWithdrawOpen()) {
        return ['success' => false, 'message' => 'Penarikan hanya bisa dilakukan ' . getSetting('withdraw_hour_start') . '-' . getSetting('withdraw_hour_end') . ' WIB.'];
    }

    // Cek max per hari
    $maxPerDay = (int)getSetting('withdraw_max_per_day', 3);
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM withdrawals WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $todayCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($todayCount >= $maxPerDay) {
        return ['success' => false, 'message' => "Maksimal {$maxPerDay}x penarikan per hari."];
    }

    // Cek saldo
    if (!hasSufficientBalance($userId, $fromWallet, $amount)) {
        return ['success' => false, 'message' => 'Saldo tidak mencukupi.'];
    }

    // Hitung fee
    $feeAmount     = (int)floor($amount * $feePercent / 100);
    $amountReceived = $amount - $feeAmount;
    $withdrawCode  = generateWithdrawalCode();

    $db->begin_transaction();
    try {
        // Debit saldo
        debitWallet($userId, $fromWallet, $amount, TRX_WITHDRAW, 'Penarikan - ' . $withdrawCode, 0, 'withdrawal');

        // Insert withdrawal
        $status = 'pending';
        $stmt   = $db->prepare("INSERT INTO withdrawals (user_id, withdrawal_code, bank_account_id, amount, fee_percent, fee_amount, amount_received, from_wallet, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiiddiss", $userId, $withdrawCode, $bankAccountId, $amount, $feePercent, $feeAmount, $amountReceived, $fromWallet, $status);
        $stmt->execute();
        $stmt->close();

        // Notifikasi
        sendNotification($userId, NOTIF_WITHDRAW,
            'Penarikan Disubmit!',
            'Penarikan ' . formatRupiah($amount) . ' sedang diproses. Estimasi 1x24 jam.'
        );

        $db->commit();
        return [
            'success'        => true,
            'message'        => 'Penarikan berhasil disubmit.',
            'withdrawal_code' => $withdrawCode,
            'fee'            => $feeAmount,
            'received'       => $amountReceived,
        ];
    } catch (Exception $e) {
        $db->rollback();
        error_log('Withdrawal error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan.'];
    }
}
