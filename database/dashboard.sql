-- ============================================================
-- NOXARA Investment Platform - Database Schema
-- ============================================================
-- CARA IMPORT DI VPS:
--   mysql -u USERNAME -p DB_NAME < dashboard.sql
-- CARA IMPORT DI phpMyAdmin:
--   Pilih database → Tab Import → pilih file ini → Go
-- ============================================================
-- PERHATIAN: Tidak ada CREATE DATABASE / USE di file ini
-- Buat database manual dulu sebelum import!
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- ============================================================
-- TABLE: admin_users
-- ============================================================
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('superadmin','cs','finance') NOT NULL DEFAULT 'cs',
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `referral_code` varchar(20) NOT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `vip_level` tinyint(1) NOT NULL DEFAULT 0,
  `total_deposit_cumulative` bigint(20) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `block_reason` text DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `theme_mode` enum('dark','light') NOT NULL DEFAULT 'dark',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `referred_by` (`referred_by`),
  KEY `vip_level` (`vip_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: user_wallets
-- ============================================================
CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `balance_main` bigint(20) NOT NULL DEFAULT 0,
  `balance_profit` bigint(20) NOT NULL DEFAULT 0,
  `balance_bonus` bigint(20) NOT NULL DEFAULT 0,
  `balance_referral` bigint(20) NOT NULL DEFAULT 0,
  `balance_frozen` bigint(20) NOT NULL DEFAULT 0,
  `is_frozen` tinyint(1) NOT NULL DEFAULT 0,
  `freeze_reason` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_sessions
-- ============================================================
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_login_logs
-- ============================================================
CREATE TABLE `user_login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email_or_username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed','blocked') NOT NULL DEFAULT 'success',
  `fail_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: vip_levels
-- ============================================================
CREATE TABLE `vip_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` tinyint(1) NOT NULL,
  `name` varchar(50) NOT NULL,
  `min_deposit_cumulative` bigint(20) NOT NULL DEFAULT 0,
  `min_withdraw` bigint(20) NOT NULL DEFAULT 0,
  `withdraw_fee_percent` decimal(5,2) NOT NULL DEFAULT 15.00,
  `color` varchar(20) NOT NULL DEFAULT '#6B7A99',
  `badge_label` varchar(20) NOT NULL DEFAULT 'BASIC',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: vip_codes
-- ============================================================
CREATE TABLE `vip_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vip_level` tinyint(1) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `benefit` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vip_level` (`vip_level`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: bank_accounts (rekening bank member)
-- ============================================================
CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bank_name` varchar(50) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: deposits
-- ============================================================
CREATE TABLE `deposits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `deposit_code` varchar(20) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `unique_code` int(4) NOT NULL DEFAULT 0,
  `total_amount` bigint(20) NOT NULL,
  `bank_target_id` int(11) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `voucher_bonus` bigint(20) NOT NULL DEFAULT 0,
  `status` enum('pending','confirmed','rejected','expired') NOT NULL DEFAULT 'pending',
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `rejected_reason` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deposit_code` (`deposit_code`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: admin_bank_accounts (rekening tujuan deposit)
-- ============================================================
CREATE TABLE `admin_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(50) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: withdrawals
-- ============================================================
CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `withdrawal_code` varchar(20) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `fee_percent` decimal(5,2) NOT NULL DEFAULT 0,
  `fee_amount` bigint(20) NOT NULL DEFAULT 0,
  `amount_received` bigint(20) NOT NULL,
  `from_wallet` enum('main','profit','bonus','referral') NOT NULL DEFAULT 'main',
  `status` enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `rejected_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_code` (`withdrawal_code`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: transactions (log semua transaksi)
-- ============================================================
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdraw','purchase','profit','referral_commission','bonus','voucher','daily_reward','ad_reward','mission_reward','refund') NOT NULL,
  `amount` bigint(20) NOT NULL,
  `wallet_type` enum('main','profit','bonus','referral') NOT NULL DEFAULT 'main',
  `balance_before` bigint(20) NOT NULL DEFAULT 0,
  `balance_after` bigint(20) NOT NULL DEFAULT 0,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: product_categories
-- ============================================================
CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#00D4FF',
  `gradient_from` varchar(20) NOT NULL DEFAULT '#00D4FF',
  `gradient_to` varchar(20) NOT NULL DEFAULT '#7B2FFF',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: products
-- ============================================================
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` bigint(20) NOT NULL,
  `profit_per_day` bigint(20) NOT NULL,
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `total_profit` bigint(20) GENERATED ALWAYS AS (`profit_per_day` * `duration_days`) STORED,
  `roi_percent` decimal(8,2) GENERATED ALWAYS AS ((`profit_per_day` * `duration_days` / `price`) * 100) STORED,
  `total_return` bigint(20) GENERATED ALWAYS AS (`price` + (`profit_per_day` * `duration_days`)) STORED,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: user_products (paket aktif member)
-- ============================================================
CREATE TABLE `user_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `purchase_price` bigint(20) NOT NULL,
  `profit_per_day` bigint(20) NOT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `discount_amount` bigint(20) NOT NULL DEFAULT 0,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `last_mining_at` datetime DEFAULT NULL,
  `mining_profit_pending` bigint(20) NOT NULL DEFAULT 0,
  `total_profit_earned` bigint(20) NOT NULL DEFAULT 0,
  `total_days_mined` int(11) NOT NULL DEFAULT 0,
  `modal_returned` tinyint(1) NOT NULL DEFAULT 0,
  `modal_returned_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: mining_logs
-- ============================================================
CREATE TABLE `mining_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `mined_at` datetime NOT NULL,
  `profit_amount` bigint(20) NOT NULL,
  `profit_status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `profit_credited_at` datetime DEFAULT NULL,
  `mining_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_product_id` (`user_product_id`),
  KEY `user_id` (`user_id`),
  KEY `mining_date` (`mining_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: referrals
-- ============================================================
CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `level` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referred_id` (`referred_id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: commissions (riwayat rabat referral)
-- ============================================================
CREATE TABLE `commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `level` tinyint(1) NOT NULL,
  `type` enum('deposit','transaction') NOT NULL,
  `source_amount` bigint(20) NOT NULL,
  `commission_percent` decimal(5,2) NOT NULL,
  `commission_amount` bigint(20) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: commission_settings
-- ============================================================
CREATE TABLE `commission_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('deposit','transaction') NOT NULL,
  `level` tinyint(1) NOT NULL,
  `percent` decimal(5,2) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_level` (`type`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: vouchers
-- ============================================================
CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('deposit','product','general') NOT NULL DEFAULT 'general',
  `discount_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0,
  `min_vip_level` tinyint(1) NOT NULL DEFAULT 0,
  `max_vip_level` tinyint(1) NOT NULL DEFAULT 5,
  `min_amount` bigint(20) NOT NULL DEFAULT 0,
  `max_discount` bigint(20) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `valid_from` datetime NOT NULL,
  `valid_until` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_vouchers
-- ============================================================
CREATE TABLE `user_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `used_for` varchar(100) DEFAULT NULL,
  `discount_given` bigint(20) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `voucher_id` (`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: ads (iklan manual)
-- ============================================================
CREATE TABLE `ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `reward_amount` bigint(20) NOT NULL DEFAULT 0,
  `timer_seconds` int(11) NOT NULL DEFAULT 30,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: ad_watches
-- ============================================================
CREATE TABLE `ad_watches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ad_id` int(11) NOT NULL,
  `reward_amount` bigint(20) NOT NULL DEFAULT 0,
  `watched_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `watch_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `watch_date` (`watch_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: ad_settings
-- ============================================================
CREATE TABLE `ad_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `max_per_day` int(11) NOT NULL DEFAULT 10,
  `cooldown_minutes` int(11) NOT NULL DEFAULT 5,
  `reward_wallet` enum('main','profit','bonus') NOT NULL DEFAULT 'profit',
  `require_active_package` tinyint(1) NOT NULL DEFAULT 0,
  `min_vip_level` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: daily_reward_settings
-- ============================================================
CREATE TABLE `daily_reward_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reset_hour` int(11) NOT NULL DEFAULT 0,
  `max_claims_per_day` int(11) NOT NULL DEFAULT 1,
  `reward_wallet` enum('main','profit','bonus') NOT NULL DEFAULT 'bonus',
  `require_login` tinyint(1) NOT NULL DEFAULT 1,
  `require_active_package` tinyint(1) NOT NULL DEFAULT 0,
  `min_vip_level` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: daily_reward_items
-- ============================================================
CREATE TABLE `daily_reward_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('balance','voucher','extra_ads','boost_profit') NOT NULL DEFAULT 'balance',
  `value` bigint(20) NOT NULL DEFAULT 0,
  `probability` decimal(5,2) NOT NULL DEFAULT 0,
  `color` varchar(20) NOT NULL DEFAULT '#00D4FF',
  `icon` varchar(100) DEFAULT NULL,
  `is_jackpot` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_daily_claims
-- ============================================================
CREATE TABLE `user_daily_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reward_item_id` int(11) NOT NULL,
  `reward_value` bigint(20) NOT NULL DEFAULT 0,
  `reward_type` varchar(50) DEFAULT NULL,
  `claim_date` date NOT NULL,
  `claimed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `claim_date` (`claim_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: missions
-- ============================================================
CREATE TABLE `missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('daily','weekly','total') NOT NULL DEFAULT 'daily',
  `action` varchar(100) NOT NULL,
  `target_count` int(11) NOT NULL DEFAULT 1,
  `reward_type` enum('balance','voucher','bonus') NOT NULL DEFAULT 'bonus',
  `reward_amount` bigint(20) NOT NULL DEFAULT 0,
  `reward_wallet` enum('main','profit','bonus','referral') NOT NULL DEFAULT 'bonus',
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_missions
-- ============================================================
CREATE TABLE `user_missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `mission_id` int(11) NOT NULL,
  `current_count` int(11) NOT NULL DEFAULT 0,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `is_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `reset_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `mission_id` (`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: chat_rooms
-- ============================================================
CREATE TABLE `chat_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `last_message_preview` varchar(255) DEFAULT NULL,
  `unread_by_admin` int(11) NOT NULL DEFAULT 0,
  `unread_by_user` int(11) NOT NULL DEFAULT 0,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: chat_messages
-- ============================================================
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `sender_type` enum('user','admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_type` enum('image','file') DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: chat_templates (quick reply admin)
-- ============================================================
CREATE TABLE `chat_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#00D4FF',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notification_settings
-- ============================================================
CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notif_profit` tinyint(1) NOT NULL DEFAULT 1,
  `notif_deposit` tinyint(1) NOT NULL DEFAULT 1,
  `notif_withdraw` tinyint(1) NOT NULL DEFAULT 1,
  `notif_referral` tinyint(1) NOT NULL DEFAULT 1,
  `notif_mission` tinyint(1) NOT NULL DEFAULT 1,
  `notif_vip` tinyint(1) NOT NULL DEFAULT 1,
  `notif_announcement` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: banners
-- ============================================================
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `image_mobile` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: announcements
-- ============================================================
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('marquee','popup','banner') NOT NULL DEFAULT 'marquee',
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `show_on` enum('all','member','guest') NOT NULL DEFAULT 'all',
  `popup_frequency` enum('every_login','once','daily') DEFAULT 'every_login',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: platform_info
-- ============================================================
CREATE TABLE `platform_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: contact_settings
-- ============================================================
CREATE TABLE `contact_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `whatsapp` varchar(20) DEFAULT NULL,
  `telegram` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `youtube` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cs_status` enum('online','busy','offline') NOT NULL DEFAULT 'online',
  `cs_hours` varchar(100) DEFAULT '08:00 - 22:00 WIB',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: popup_settings
-- ============================================================
CREATE TABLE `popup_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `style` enum('success','info','warning','error') NOT NULL DEFAULT 'success',
  `duration_seconds` int(11) NOT NULL DEFAULT 3,
  `position` enum('center','top-right','top-left','bottom-right','bottom-left') NOT NULL DEFAULT 'center',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: settings (konfigurasi sistem global)
-- ============================================================
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('text','number','boolean','json','textarea') NOT NULL DEFAULT 'text',
  `group` varchar(50) DEFAULT 'general',
  `label` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: leaderboard_cache
-- ============================================================
CREATE TABLE `leaderboard_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('deposit','referral','profit') NOT NULL,
  `period` enum('monthly','alltime') NOT NULL DEFAULT 'monthly',
  `user_id` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  `value` bigint(20) NOT NULL DEFAULT 0,
  `period_key` varchar(20) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type_period` (`type`,`period`,`period_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: admin_logs
-- ============================================================
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: cron_logs
-- ============================================================
CREATE TABLE `cron_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) NOT NULL,
  `status` enum('running','success','failed') NOT NULL DEFAULT 'running',
  `records_processed` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `duration_seconds` decimal(10,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_name` (`job_name`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: password_resets
-- ============================================================
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: marquee_settings
-- ============================================================
CREATE TABLE `marquee_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `show_deposits` tinyint(1) NOT NULL DEFAULT 1,
  `show_purchases` tinyint(1) NOT NULL DEFAULT 1,
  `show_vip_upgrades` tinyint(1) NOT NULL DEFAULT 1,
  `show_manual_messages` tinyint(1) NOT NULL DEFAULT 1,
  `speed` enum('slow','normal','fast') NOT NULL DEFAULT 'normal',
  `bg_color` varchar(20) NOT NULL DEFAULT '#0A0E1A',
  `text_color` varchar(20) NOT NULL DEFAULT '#00D4FF',
  `mask_names` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;



-- ============================================================
-- DATA AWAL — INSERT
-- ============================================================

-- Admin users (password: Admin@Noxara88)
INSERT INTO `admin_users` (`username`, `email`, `password`, `full_name`, `role`, `is_active`) VALUES
('superadmin', 'superadmin@noxara.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'superadmin', 1),
('admin_cs', 'cs@noxara.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Customer Service', 'cs', 1),
('admin_finance', 'finance@noxara.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Finance Admin', 'finance', 1);

-- VIP Levels
INSERT INTO `vip_levels` (`level`, `name`, `min_deposit_cumulative`, `min_withdraw`, `withdraw_fee_percent`, `color`, `badge_label`) VALUES
(0, 'VIP 0', 0, 100000, 15.00, '#6B7A99', 'BASIC'),
(1, 'VIP 1', 20000, 50000, 10.00, '#00D4FF', 'BRONZE'),
(2, 'VIP 2', 100000, 30000, 7.00, '#00E676', 'SILVER'),
(3, 'VIP 3', 500000, 20000, 5.00, '#7B2FFF', 'GOLD'),
(4, 'VIP 4', 2000000, 15000, 3.00, '#FFB300', 'PLATINUM'),
(5, 'VIP 5', 5000000, 10000, 1.00, '#FF6B6B', 'ELITE');

-- VIP Codes
INSERT INTO `vip_codes` (`vip_level`, `code`, `description`, `benefit`) VALUES
(0, 'NOXARA-VIP0-FREE0', 'Kode akses member baru', 'Akses grup komunitas NOXARA'),
(1, 'NOXARA-VIP1-BRZ1', 'Kode akses VIP 1 Bronze', 'Diskon biaya WD tambahan 2%'),
(2, 'NOXARA-VIP2-SLV2', 'Kode akses VIP 2 Silver', 'Akses paket mining eksklusif'),
(3, 'NOXARA-VIP3-GLD3', 'Kode akses VIP 3 Gold', 'Bonus deposit 5% sekali pakai'),
(4, 'NOXARA-VIP4-PLT4', 'Kode akses VIP 4 Platinum', 'Gratis biaya WD 1x per bulan'),
(5, 'NOXARA-VIP5-ELT5', 'Kode akses VIP 5 Elite', 'Priority CS + benefit eksklusif');


-- Commission Settings (rabat referral)
INSERT INTO `commission_settings` (`type`, `level`, `percent`, `is_active`) VALUES
('deposit', 1, 2.00, 1),
('deposit', 2, 1.00, 1),
('deposit', 3, 0.50, 1),
('transaction', 1, 3.00, 1),
('transaction', 2, 1.50, 1),
('transaction', 3, 0.75, 1);

-- Product Categories
INSERT INTO `product_categories` (`name`, `slug`, `description`, `color`, `gradient_from`, `gradient_to`, `sort_order`) VALUES
('Mining Pemula', 'mining-pemula', 'Paket mining untuk pemula dengan modal terjangkau dan profit stabil', '#00D4FF', '#00D4FF', '#0099CC', 1),
('Mining Menengah', 'mining-menengah', 'Paket mining menengah dengan ROI lebih tinggi untuk investor berpengalaman', '#7B2FFF', '#7B2FFF', '#5500CC', 2),
('Mining Premium', 'mining-premium', 'Paket mining premium eksklusif dengan ROI tertinggi untuk investor serius', '#FFB300', '#FFB300', '#FF8800', 3);

-- Products (12 paket - profit_per_day & price saja, kolom generated otomatis hitung)
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `price`, `profit_per_day`, `duration_days`, `sort_order`) VALUES
-- Mining Pemula
(1, 'STONE I', 'stone-i', 'Paket mining pemula entry level. Cocok untuk yang baru memulai investasi di NOXARA. Modal terjangkau dengan profit harian yang konsisten.', 10000, 500, 30, 1),
(1, 'STONE II', 'stone-ii', 'Paket mining pemula level 2. Profit lebih besar dengan modal yang masih terjangkau. Ideal untuk pemula yang ingin hasil lebih optimal.', 20000, 1000, 30, 2),
(1, 'STONE III', 'stone-iii', 'Paket mining pemula level 3. Keseimbangan sempurna antara modal dan profit. Pilihan terpopuler di kategori pemula.', 50000, 2500, 30, 3),
(1, 'STONE IV', 'stone-iv', 'Paket mining pemula level tertinggi. Profit maksimal di kategori pemula dengan modal yang masih ramah di kantong.', 100000, 5500, 30, 4),
-- Mining Menengah
(2, 'IRON I', 'iron-i', 'Paket mining menengah entry level. ROI signifikan untuk investor yang siap naik level. Profit harian lebih menggiurkan.', 200000, 14000, 30, 1),
(2, 'IRON II', 'iron-ii', 'Paket mining menengah level 2. Kombinasi modal dan profit yang sangat menguntungkan. Favorit investor aktif NOXARA.', 500000, 37500, 30, 2),
(2, 'IRON III', 'iron-iii', 'Paket mining menengah level 3. Profit harian signifikan untuk pertumbuhan aset yang cepat dan konsisten.', 1000000, 80000, 30, 3),
(2, 'IRON IV', 'iron-iv', 'Paket mining menengah premium. ROI tertinggi di kategori menengah. Untuk investor serius yang ingin hasil maksimal.', 2000000, 170000, 30, 4),
-- Mining Premium
(3, 'GOLD I', 'gold-i', 'Paket mining premium entry level. Eksklusif untuk investor besar. Profit harian yang luar biasa untuk pertumbuhan aset eksponensial.', 3000000, 270000, 30, 1),
(3, 'GOLD II', 'gold-ii', 'Paket mining premium level 2. Salah satu paket terbaik di NOXARA. Profit harian 3x lipat dari modal dalam 30 hari.', 5000000, 500000, 30, 2),
(3, 'GOLD III', 'gold-iii', 'Paket mining premium level 3. Untuk investor elite yang menginginkan pertumbuhan aset luar biasa cepat.', 8000000, 880000, 30, 3),
(3, 'GOLD IV', 'gold-iv', 'Paket mining premium tertinggi. ROI terbesar di seluruh platform NOXARA. Eksklusif untuk investor kelas atas.', 10000000, 1200000, 30, 4);


-- Admin Bank Accounts
INSERT INTO `admin_bank_accounts` (`bank_name`, `account_number`, `account_name`, `is_active`, `sort_order`) VALUES
('BCA', '1234567890', 'PT NOXARA INVESTAMA', 1, 1),
('BRI', '0987654321', 'PT NOXARA INVESTAMA', 1, 2),
('BNI', '1122334455', 'PT NOXARA INVESTAMA', 1, 3),
('Mandiri', '5544332211', 'PT NOXARA INVESTAMA', 1, 4),
('DANA', '08123456789', 'NOXARA Official', 1, 5),
('OVO', '08123456789', 'NOXARA Official', 1, 6);

-- Daily Reward Items
INSERT INTO `daily_reward_items` (`name`, `type`, `value`, `probability`, `color`, `is_jackpot`, `sort_order`) VALUES
('Saldo Bonus Rp 500', 'balance', 500, 28.00, '#00D4FF', 0, 1),
('Saldo Bonus Rp 1.000', 'balance', 1000, 25.00, '#00D4FF', 0, 2),
('Saldo Bonus Rp 2.000', 'balance', 2000, 18.00, '#7B2FFF', 0, 3),
('Saldo Bonus Rp 5.000', 'balance', 5000, 12.00, '#7B2FFF', 0, 4),
('Saldo Bonus Rp 10.000', 'balance', 10000, 7.00, '#FFB300', 0, 5),
('Extra Iklan +5', 'extra_ads', 5, 5.00, '#00E676', 0, 6),
('Boost Profit +0.1%', 'boost_profit', 1, 3.00, '#FF6B6B', 0, 7),
('JACKPOT Rp 100.000', 'balance', 100000, 2.00, '#FFB300', 1, 8);

-- Daily Reward Settings
INSERT INTO `daily_reward_settings` (`reset_hour`, `max_claims_per_day`, `reward_wallet`, `require_login`, `is_active`) VALUES
(0, 1, 'bonus', 1, 1);

-- Ad Settings
INSERT INTO `ad_settings` (`max_per_day`, `cooldown_minutes`, `reward_wallet`, `require_active_package`, `is_active`) VALUES
(10, 5, 'profit', 0, 1);


-- Popup Settings
INSERT INTO `popup_settings` (`event`, `title`, `message`, `style`, `duration_seconds`, `position`, `is_active`) VALUES
('login', 'Selamat Datang!', 'Selamat datang kembali di NOXARA, {nama}! 🎉', 'success', 3, 'top-right', 1),
('register', 'Pendaftaran Berhasil!', 'Akun kamu berhasil dibuat! Selamat bergabung di NOXARA 🚀', 'success', 5, 'center', 1),
('deposit_success', 'Deposit Dikonfirmasi!', 'Deposit Rp {nominal} berhasil masuk ke saldo utama kamu! 💰', 'success', 4, 'center', 1),
('deposit_pending', 'Deposit Menunggu Konfirmasi', 'Deposit Rp {nominal} sedang diverifikasi admin. Mohon tunggu. ⏳', 'info', 4, 'center', 1),
('withdraw_success', 'Penarikan Diproses!', 'Penarikan Rp {nominal} sedang diproses. Estimasi 1x24 jam. 💸', 'info', 4, 'center', 1),
('withdraw_rejected', 'Penarikan Ditolak', 'Maaf, penarikan kamu ditolak. Cek alasan di riwayat transaksi. ❌', 'error', 5, 'center', 1),
('purchase_success', 'Pembelian Berhasil!', 'Paket {nama_produk} berhasil diaktifkan! Mulai mining sekarang! ⛏️', 'success', 5, 'center', 1),
('mining_success', 'Mining Dimulai!', 'Mining berjalan! Profit Rp {nominal} akan masuk dalam 3 jam. ⛏️', 'success', 3, 'top-right', 1),
('profit_credited', 'Profit Masuk!', 'Profit mining Rp {nominal} telah masuk ke saldo profit kamu! 📈', 'success', 4, 'top-right', 1),
('vip_upgrade', 'Naik Level VIP!', 'Selamat! Kamu naik ke {vip_level}! Nikmati keuntungan barumu 🎊', 'success', 5, 'center', 1),
('logout', 'Sampai Jumpa!', 'Terima kasih telah menggunakan NOXARA, {nama}. Sampai jumpa! 👋', 'info', 2, 'top-right', 1),
('daily_reward', 'Hadiah Harian!', 'Kamu mendapat {hadiah}! Jangan lupa klaim lagi besok! 🎁', 'success', 4, 'center', 1);

-- Marquee Settings
INSERT INTO `marquee_settings` (`show_deposits`, `show_purchases`, `show_vip_upgrades`, `show_manual_messages`, `speed`, `is_active`) VALUES
(1, 1, 1, 1, 'normal', 1);

-- Chat Templates
INSERT INTO `chat_templates` (`title`, `message`, `sort_order`) VALUES
('Sambutan', 'Halo! Selamat datang di NOXARA CS. Ada yang bisa kami bantu? 😊', 1),
('Minta Bukti Transfer', 'Baik kak, mohon kirimkan bukti transfer deposit ya. Terima kasih 🙏', 2),
('Deposit Proses', 'Deposit kakak sedang dalam proses konfirmasi. Mohon tunggu maksimal 1 jam ya kak 😊', 3),
('Withdraw Proses', 'Penarikan kakak sedang diproses. Estimasi selesai dalam 1x24 jam ya kak 🙏', 4),
('Terima Kasih', 'Terima kasih sudah menghubungi CS NOXARA! Ada yang ingin ditanyakan lagi? 😊', 5),
('Masalah Teknis', 'Mohon maaf atas ketidaknyamanannya. Tim teknis kami sedang menangani masalah ini. Harap bersabar ya kak 🙏', 6);


-- Missions
INSERT INTO `missions` (`title`, `description`, `type`, `action`, `target_count`, `reward_type`, `reward_amount`, `reward_wallet`, `sort_order`) VALUES
('Login Harian', 'Login ke akun NOXARA hari ini', 'daily', 'login', 1, 'balance', 500, 'bonus', 1),
('Mining Harian', 'Lakukan mining pada paket aktif kamu', 'daily', 'mining', 1, 'balance', 1000, 'bonus', 2),
('Nonton Iklan 3x', 'Tonton 3 iklan hari ini', 'daily', 'watch_ads', 3, 'balance', 1500, 'bonus', 3),
('Nonton Iklan 5x', 'Tonton 5 iklan hari ini', 'daily', 'watch_ads', 5, 'balance', 3000, 'bonus', 4),
('Klaim Hadiah Harian', 'Klaim hadiah harian kamu', 'daily', 'daily_reward', 1, 'balance', 500, 'bonus', 5),
('Mining 5x Minggu Ini', 'Lakukan mining 5 kali minggu ini', 'weekly', 'mining', 5, 'balance', 10000, 'bonus', 6),
('Ajak 1 Teman Minggu Ini', 'Ajak 1 teman daftar pakai link referralmu', 'weekly', 'referral', 1, 'balance', 5000, 'bonus', 7),
('Deposit Pertama', 'Lakukan deposit pertama kamu', 'total', 'deposit', 1, 'balance', 5000, 'bonus', 8),
('Beli Paket Pertama', 'Beli paket mining pertama kamu', 'total', 'purchase', 1, 'balance', 10000, 'bonus', 9),
('Ajak 5 Teman', 'Ajak 5 teman daftar pakai link referralmu', 'total', 'referral', 5, 'balance', 25000, 'bonus', 10),
('Ajak 10 Teman', 'Ajak 10 teman daftar pakai link referralmu', 'total', 'referral', 10, 'balance', 75000, 'bonus', 11),
('Total Deposit Rp 500rb', 'Total deposit kumulatif mencapai Rp 500.000', 'total', 'deposit_cumulative', 500000, 'balance', 50000, 'bonus', 12);

-- Platform Info
INSERT INTO `platform_info` (`key`, `value`) VALUES
('about_us', '<h3>Tentang NOXARA</h3><p>NOXARA adalah platform investasi digital terpercaya yang hadir untuk memberikan peluang investasi yang menguntungkan bagi seluruh masyarakat Indonesia.</p><p>Didirikan pada tahun 2024, NOXARA telah berkembang pesat dan menjadi salah satu platform investasi mining digital terbesar di Indonesia dengan ratusan ribu member aktif.</p><p>Kami berkomitmen untuk memberikan transparansi, keamanan, dan profit yang konsisten bagi setiap member kami.</p>'),
('terms_conditions', '<h3>Syarat dan Ketentuan NOXARA</h3><p>1. Member wajib berusia minimal 17 tahun untuk mendaftar.</p><p>2. Setiap akun hanya boleh dimiliki oleh satu orang.</p><p>3. Dilarang menggunakan bot atau script otomatis.</p><p>4. Modal akan dikembalikan setelah masa paket berakhir.</p><p>5. NOXARA berhak menangguhkan akun yang melanggar ketentuan.</p>'),
('privacy_policy', '<h3>Kebijakan Privasi NOXARA</h3><p>Data pribadi Anda aman bersama kami. Kami tidak menjual atau memberikan data Anda kepada pihak ketiga tanpa izin.</p><p>Data yang kami kumpulkan hanya digunakan untuk keperluan operasional platform.</p>'),
('withdraw_policy', '<h3>Kebijakan Penarikan Dana</h3><p>1. Penarikan diproses setiap hari kerja pukul 08.00-17.00 WIB.</p><p>2. Minimal penarikan sesuai level VIP masing-masing member.</p><p>3. Biaya admin sesuai level VIP.</p><p>4. Proses maksimal 1x24 jam pada hari kerja.</p>'),
('registered_since', '2024'),
('total_members_display', '284750'),
('total_payout_display', '15800000000'),
('rating', '4.9'),
('free_bonus_amount', '5000'),
('free_bonus_description', 'Saldo bonus gratis hanya dapat digunakan untuk pembelian paket mining. Tidak dapat ditarik.');


-- Contact Settings
INSERT INTO `contact_settings` (`whatsapp`, `telegram`, `email`, `instagram`, `facebook`, `youtube`, `cs_status`, `cs_hours`) VALUES
('6281234567890', '@noxara_official', 'cs@noxara.com', '@noxara.official', 'NOXARA Official', 'https://youtube.com/@noxara', 'online', '08:00 - 22:00 WIB');

-- Settings (konfigurasi sistem)
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `label`) VALUES
('site_name', 'NOXARA', 'text', 'general', 'Nama Website'),
('site_tagline', 'Invest Smarter, Grow Faster', 'text', 'general', 'Tagline'),
('site_url', 'https://noxara.com', 'text', 'general', 'URL Website'),
('site_logo', '', 'text', 'general', 'Logo'),
('site_favicon', '', 'text', 'general', 'Favicon'),
('maintenance_mode', '0', 'boolean', 'general', 'Mode Maintenance'),
('maintenance_message', 'Sistem sedang dalam pemeliharaan. Mohon tunggu sebentar.', 'textarea', 'general', 'Pesan Maintenance'),
('maintenance_estimate', '', 'text', 'general', 'Estimasi Selesai Maintenance'),
('registration_open', '1', 'boolean', 'general', 'Buka Pendaftaran'),
('deposit_open', '1', 'boolean', 'general', 'Fitur Deposit'),
('withdraw_open', '1', 'boolean', 'general', 'Fitur Withdraw'),
('ads_open', '1', 'boolean', 'general', 'Fitur Iklan'),
('referral_open', '1', 'boolean', 'general', 'Fitur Referral'),
('daily_reward_open', '1', 'boolean', 'general', 'Hadiah Harian'),
('chat_open', '1', 'boolean', 'general', 'Live Chat'),
('min_deposit', '10000', 'number', 'deposit', 'Minimal Deposit'),
('max_deposit', '50000000', 'number', 'deposit', 'Maksimal Deposit'),
('deposit_expiry_hours', '3', 'number', 'deposit', 'Expired Deposit (jam)'),
('withdraw_days', '1,2,3,4,5', 'text', 'withdraw', 'Hari Operasional WD'),
('withdraw_hour_start', '08:00', 'text', 'withdraw', 'Jam Mulai WD'),
('withdraw_hour_end', '17:00', 'text', 'withdraw', 'Jam Selesai WD'),
('withdraw_max_per_day', '3', 'number', 'withdraw', 'Maks WD per Hari'),
('withdraw_cooldown_after_deposit', '0', 'number', 'withdraw', 'Cooldown WD setelah Deposit (jam)'),
('max_login_attempts', '5', 'number', 'security', 'Maks Gagal Login'),
('login_lock_minutes', '30', 'number', 'security', 'Durasi Lock Akun (menit)'),
('session_timeout_minutes', '120', 'number', 'security', 'Timeout Session (menit)'),
('max_ref_per_ip', '5', 'number', 'security', 'Maks Referral 1 IP'),
('free_bonus_new_member', '5000', 'number', 'bonus', 'Saldo Gratis Member Baru'),
('otp_withdraw_min_amount', '5000000', 'number', 'security', 'Min WD yang Butuh OTP'),
('mining_countdown_hours', '3', 'number', 'mining', 'Countdown Mining (jam)'),
('whatsapp_api_key', '', 'text', 'notification', 'WhatsApp API Key'),
('whatsapp_provider', 'fonnte', 'text', 'notification', 'WhatsApp Provider'),
('smtp_host', '', 'text', 'email', 'SMTP Host'),
('smtp_port', '587', 'number', 'email', 'SMTP Port'),
('smtp_user', '', 'text', 'email', 'SMTP Username'),
('smtp_pass', '', 'text', 'email', 'SMTP Password'),
('smtp_from', 'noreply@noxara.com', 'text', 'email', 'Email Pengirim');


-- Banners (placeholder - admin bisa ganti via panel)
INSERT INTO `banners` (`title`, `image`, `is_active`, `sort_order`) VALUES
('Promo Mining Terbaru', 'banner1.jpg', 1, 1),
('Ajak Teman Dapat Rabat', 'banner2.jpg', 1, 2),
('Upgrade VIP Lebih Untung', 'banner3.jpg', 1, 3);

-- Announcements
INSERT INTO `announcements` (`type`, `content`, `is_active`, `sort_order`) VALUES
('marquee', '🎉 Selamat datang di NOXARA! Platform investasi mining terpercaya di Indonesia', 1, 1),
('marquee', '💰 Total payout NOXARA sudah mencapai Rp 15,8 Miliar! Bergabunglah sekarang!', 1, 2),
('marquee', '🏆 NOXARA kini hadir dengan paket mining baru yang lebih menguntungkan!', 1, 3),
('marquee', '⚡ Promo spesial: Deposit sekarang dan nikmati profit harian mulai dari 5%!', 1, 4);

-- Vouchers (contoh voucher aktif)
INSERT INTO `vouchers` (`code`, `type`, `discount_type`, `discount_value`, `min_vip_level`, `max_vip_level`, `min_amount`, `usage_limit`, `valid_from`, `valid_until`, `is_active`) VALUES
('WELCOME2024', 'deposit', 'percent', 5.00, 0, 5, 10000, 1000, '2024-01-01 00:00:00', '2030-12-31 23:59:59', 1),
('MINING10', 'product', 'percent', 10.00, 1, 5, 50000, 500, '2024-01-01 00:00:00', '2030-12-31 23:59:59', 1),
('NOXVIP3', 'general', 'percent', 15.00, 3, 5, 100000, 200, '2024-01-01 00:00:00', '2030-12-31 23:59:59', 1);

-- ============================================================
-- DATA REALISTIS — Member, Transaksi, dll
-- ============================================================
-- Catatan: Data member di bawah adalah contoh realistis
-- Total member ditampilkan di landing page dari settings
-- Data ini hanya beberapa sample, bukan 284.750 record
-- (karena akan membuat file SQL terlalu besar)
-- ============================================================

-- Sample users (password semua: Password123!)
INSERT INTO `users` (`username`, `email`, `phone`, `password`, `full_name`, `referral_code`, `referred_by`, `vip_level`, `total_deposit_cumulative`, `is_active`, `is_verified`) VALUES
('budi_santoso', 'budi@example.com', '081234567890', '$2y$12$hP8YwEL2X5LNXV8YmGJfXOkYAZfIuQELc3cQjYXf4K5xMz2TfMUe', 'Budi Santoso', 'NOX001', NULL, 3, 750000, 1, 1),
('siti_rahayu', 'siti@example.com', '081234567891', '$2y$12$hP8YwEL2X5LNXV8YmGJfXOkYAZfIuQELc3cQjYXf4K5xMz2TfMUe', 'Siti Rahayu', 'NOX002', 1, 2, 150000, 1, 1),
('ahmad_wijaya', 'ahmad@example.com', '081234567892', '$2y$12$hP8YwEL2X5LNXV8YmGJfXOkYAZfIuQELc3cQjYXf4K5xMz2TfMUe', 'Ahmad Wijaya', 'NOX003', 1, 1, 50000, 1, 1),
('dewi_lestari', 'dewi@example.com', '081234567893', '$2y$12$hP8YwEL2X5LNXV8YmGJfXOkYAZfIuQELc3cQjYXf4K5xMz2TfMUe', 'Dewi Lestari', 'NOX004', 1, 0, 0, 1, 1),
('rizki_pratama', 'rizki@example.com', '081234567894', '$2y$12$hP8YwEL2X5LNXV8YmGJfXOkYAZfIuQELc3cQjYXf4K5xMz2TfMUe', 'Rizki Pratama', 'NOX005', 2, 5, 5500000, 1, 1);

-- Wallets untuk sample users
INSERT INTO `user_wallets` (`user_id`, `balance_main`, `balance_profit`, `balance_bonus`, `balance_referral`) VALUES
(1, 500000, 1250000, 15000, 87500),
(2, 150000, 450000, 10000, 15000),
(3, 50000, 85000, 5000, 0),
(4, 5000, 0, 5000, 0),
(5, 2500000, 8750000, 25000, 450000);

-- Notification settings untuk sample users
INSERT INTO `notification_settings` (`user_id`) VALUES (1),(2),(3),(4),(5);

-- ============================================================
-- END OF NOXARA DATABASE
-- ============================================================
-- Import selesai! Akun admin:
-- Super Admin : superadmin / Admin@Noxara88
-- CS Admin    : admin_cs   / Admin@Noxara88
-- Finance     : admin_finance / Admin@Noxara88
-- ============================================================

