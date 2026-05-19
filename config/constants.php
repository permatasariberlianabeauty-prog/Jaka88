<?php
/**
 * NOXARA - Constants
 */

// ─── VIP Levels ──────────────────────────────────────────────
define('VIP_LEVELS', [
    0 => ['name' => 'VIP 0', 'label' => 'BASIC',    'color' => '#6B7A99'],
    1 => ['name' => 'VIP 1', 'label' => 'BRONZE',   'color' => '#00D4FF'],
    2 => ['name' => 'VIP 2', 'label' => 'SILVER',   'color' => '#00E676'],
    3 => ['name' => 'VIP 3', 'label' => 'GOLD',     'color' => '#7B2FFF'],
    4 => ['name' => 'VIP 4', 'label' => 'PLATINUM', 'color' => '#FFB300'],
    5 => ['name' => 'VIP 5', 'label' => 'ELITE',    'color' => '#FF6B6B'],
]);

// ─── Wallet Types ─────────────────────────────────────────────
define('WALLET_MAIN',     'main');
define('WALLET_PROFIT',   'profit');
define('WALLET_BONUS',    'bonus');
define('WALLET_REFERRAL', 'referral');

// ─── Transaction Types ────────────────────────────────────────
define('TRX_DEPOSIT',      'deposit');
define('TRX_WITHDRAW',     'withdraw');
define('TRX_PURCHASE',     'purchase');
define('TRX_PROFIT',       'profit');
define('TRX_COMMISSION',   'referral_commission');
define('TRX_BONUS',        'bonus');
define('TRX_VOUCHER',      'voucher');
define('TRX_DAILY_REWARD', 'daily_reward');
define('TRX_AD_REWARD',    'ad_reward');
define('TRX_MISSION',      'mission_reward');
define('TRX_REFUND',       'refund');

// ─── Status ───────────────────────────────────────────────────
define('STATUS_PENDING',    'pending');
define('STATUS_CONFIRMED',  'confirmed');
define('STATUS_REJECTED',   'rejected');
define('STATUS_EXPIRED',    'expired');
define('STATUS_PROCESSING', 'processing');
define('STATUS_COMPLETED',  'completed');
define('STATUS_FAILED',     'failed');
define('STATUS_ACTIVE',     'active');
define('STATUS_INACTIVE',   'inactive');

// ─── Admin Roles ──────────────────────────────────────────────
define('ROLE_SUPERADMIN', 'superadmin');
define('ROLE_CS',         'cs');
define('ROLE_FINANCE',    'finance');

// ─── Notification Types ───────────────────────────────────────
define('NOTIF_DEPOSIT',       'deposit');
define('NOTIF_WITHDRAW',      'withdraw');
define('NOTIF_PROFIT',        'profit');
define('NOTIF_REFERRAL',      'referral');
define('NOTIF_VIP',           'vip');
define('NOTIF_MISSION',       'mission');
define('NOTIF_ANNOUNCEMENT',  'announcement');
define('NOTIF_DAILY_REWARD',  'daily_reward');
define('NOTIF_SYSTEM',        'system');

// ─── Colors NOXARA ────────────────────────────────────────────
define('COLOR_CYAN',    '#00D4FF');
define('COLOR_PURPLE',  '#7B2FFF');
define('COLOR_GREEN',   '#00E676');
define('COLOR_AMBER',   '#FFB300');
define('COLOR_RED',     '#FF4444');
define('COLOR_MUTED',   '#6B7A99');
define('COLOR_BG',      '#0A0E1A');
define('COLOR_CARD',    '#0F1629');

// ─── Banks List ──────────────────────────────────────────────
define('BANK_LIST', [
    'BCA'     => 'BCA',
    'BRI'     => 'BRI',
    'BNI'     => 'BNI',
    'Mandiri' => 'Mandiri',
    'BSI'     => 'BSI',
    'CIMB'    => 'CIMB Niaga',
    'Permata' => 'Permata Bank',
    'Danamon' => 'Danamon',
    'BTN'     => 'BTN',
    'DANA'    => 'DANA',
    'OVO'     => 'OVO',
    'GoPay'   => 'GoPay',
    'ShopeePay' => 'ShopeePay',
    'LinkAja' => 'LinkAja',
]);
