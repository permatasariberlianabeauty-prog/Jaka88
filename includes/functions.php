<?php
/**
 * NOXARA - General Functions
 */

/**
 * Upload file gambar
 */
function uploadImage(array $file, string $folder, string $prefix = ''): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_UPLOAD_SIZE) return false;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) return false;

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTS)) return false;

    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir  = UPLOADS_PATH . '/' . $folder;
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        return $filename;
    }
    return false;
}

/**
 * Delete uploaded file
 */
function deleteUpload(string $folder, string $filename): void {
    $path = UPLOADS_PATH . '/' . $folder . '/' . $filename;
    if (file_exists($path) && $filename !== 'default.png') {
        unlink($path);
    }
}

/**
 * Pagination helper
 */
function paginate(int $total, int $perPage, int $currentPage, string $baseUrl): array {
    $totalPages = (int)ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
        'prev_page'    => $currentPage - 1,
        'next_page'    => $currentPage + 1,
        'base_url'     => $baseUrl,
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination(array $p): string {
    if ($p['total_pages'] <= 1) return '';
    $html = '<div class="nox-pagination">';
    if ($p['has_prev']) {
        $html .= '<a href="' . $p['base_url'] . '?page=' . $p['prev_page'] . '" class="nox-page-btn">&#8249;</a>';
    }
    $start = max(1, $p['current_page'] - 2);
    $end   = min($p['total_pages'], $p['current_page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $p['current_page'] ? ' active' : '';
        $html .= '<a href="' . $p['base_url'] . '?page=' . $i . '" class="nox-page-btn' . $active . '">' . $i . '</a>';
    }
    if ($p['has_next']) {
        $html .= '<a href="' . $p['base_url'] . '?page=' . $p['next_page'] . '" class="nox-page-btn">&#8250;</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Generate deposit unique code (3-4 digit unik)
 */
function generateDepositUniqueCode(): int {
    return random_int(100, 999);
}

/**
 * Generate deposit code (misal: DEP20240101XXXX)
 */
function generateDepositCode(): string {
    return 'DEP' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Generate withdrawal code
 */
function generateWithdrawalCode(): string {
    return 'WD' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Validate PIN (6 digit numerik)
 */
function validatePin(string $pin): bool {
    return preg_match('/^\d{6}$/', $pin) === 1;
}

/**
 * Hash PIN
 */
function hashPin(string $pin): string {
    return password_hash($pin, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

/**
 * Verify PIN
 */
function verifyPin(string $pin, string $hash): bool {
    return password_verify($pin, $hash);
}

/**
 * Get popup settings untuk event tertentu
 */
function getPopupSetting(string $event): ?array {
    $stmt = db()->prepare("SELECT * FROM popup_settings WHERE event = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $event);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Replace placeholder di pesan popup
 */
function parsPopupMessage(string $message, array $data = []): string {
    foreach ($data as $key => $value) {
        $message = str_replace('{' . $key . '}', $value, $message);
    }
    return $message;
}

/**
 * Set flash message (tampil sekali)
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get & clear flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message HTML
 */
function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
    $icon = $icons[$flash['type']] ?? 'info';
    return '<div class="nox-flash nox-flash--' . $flash['type'] . '" id="flashMessage">
        <svg class="nox-flash__icon"><use href="#icon-' . $icon . '"/></svg>
        <span>' . htmlspecialchars($flash['message']) . '</span>
        <button onclick="this.parentElement.remove()" class="nox-flash__close">&times;</button>
    </div>';
}

/**
 * Cek apakah withdraw sedang buka (hari & jam operasional)
 */
function isWithdrawOpen(): bool {
    $days  = explode(',', getSetting('withdraw_days', '1,2,3,4,5'));
    $start = getSetting('withdraw_hour_start', '08:00');
    $end   = getSetting('withdraw_hour_end', '17:00');

    $today    = (int)date('N'); // 1=Senin, 7=Minggu
    $nowTime  = date('H:i');

    return in_array((string)$today, $days)
        && $nowTime >= $start
        && $nowTime <= $end;
}

/**
 * Rate limit check (anti spam)
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $key = 'rate_' . $action . '_' . getClientIP();
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_at' => time() + $windowSeconds];
    }
    if (time() > $_SESSION[$key]['reset_at']) {
        $_SESSION[$key] = ['count' => 0, 'reset_at' => time() + $windowSeconds];
    }
    $_SESSION[$key]['count']++;
    return $_SESSION[$key]['count'] <= $maxAttempts;
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Get VIP info dari DB
 */
function getVipInfo(int $level): ?array {
    $stmt = db()->prepare("SELECT * FROM vip_levels WHERE level = ? LIMIT 1");
    $stmt->bind_param("i", $level);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
