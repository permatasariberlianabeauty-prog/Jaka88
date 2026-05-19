<?php
/**
 * NOXARA - Global Configuration
 */

// ─── Environment ─────────────────────────────────────────────
define('APP_ENV', 'production'); // 'development' | 'production'
define('APP_DEBUG', false);

// ─── Paths ───────────────────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// ─── URLs ────────────────────────────────────────────────────
define('BASE_URL', 'https://noxara.com'); // Ganti dengan domain kamu
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ─── Session ─────────────────────────────────────────────────
define('SESSION_NAME', 'noxara_sess');
define('SESSION_LIFETIME', 7200); // 2 jam
define('ADMIN_SESSION_NAME', 'noxara_admin_sess');

// ─── Security ────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_noxara_csrf');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 30);
define('HASH_COST', 12);

// ─── Upload ──────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXTS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ─── Mining ──────────────────────────────────────────────────
define('MINING_COUNTDOWN_HOURS', 3);

// ─── Pagination ──────────────────────────────────────────────
define('ITEMS_PER_PAGE', 20);

// ─── Timezone ────────────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ─── Error Handling ──────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// ─── Shared Hosting Compatibility ────────────────────────────
// Pastikan mbstring & string functions tersedia
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
// Upload tmp dir fallback
if (!ini_get('upload_tmp_dir')) {
    ini_set('upload_tmp_dir', sys_get_temp_dir());
}

// ─── Load Database ───────────────────────────────────────────
require_once CONFIG_PATH . '/database.php';
