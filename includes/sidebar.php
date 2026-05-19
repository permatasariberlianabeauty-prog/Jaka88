<?php
/**
 * NOXARA - Sidebar Navigation
 */
$currentUser = getCurrentUser();
$vipLevel    = (int)($currentUser['vip_level'] ?? 0);
$vipInfo     = getVipInfo($vipLevel);
$vipColor    = $vipInfo['color'] ?? '#6B7A99';
$vipLabel    = $vipInfo['badge_label'] ?? 'BASIC';
$avatar      = $currentUser['avatar'] ?? 'default.png';
$avatarUrl   = UPLOADS_URL . '/avatars/' . htmlspecialchars($avatar);
if (!file_exists(UPLOADS_PATH . '/avatars/' . $avatar)) {
    $avatarUrl = ASSETS_URL . '/img/default-avatar.png';
}

// Active page detection
$currentUri = strtok($_SERVER['REQUEST_URI'], '?');
function isActive(string $path): string {
    global $currentUri;
    return ($currentUri === $path || str_starts_with($currentUri, $path . '/')) ? ' active' : '';
}

// Unread chat count (admin belum balas)
$chatStmt = db()->prepare("SELECT unread_by_user FROM chat_rooms WHERE user_id = ? LIMIT 1");
$chatStmt->bind_param("i", (int)$currentUser['id']);
$chatStmt->execute();
$chatRow     = $chatStmt->get_result()->fetch_assoc();
$chatStmt->close();
$unreadChat  = (int)($chatRow['unread_by_user'] ?? 0);

$unreadNotif = getUnreadNotifCount((int)$currentUser['id']);
$siteName    = getSetting('site_name', 'NOXARA');
?>

<aside class="nox-sidebar" id="sidebar">
  <!-- Logo -->
  <a href="/dashboard" class="nox-sidebar__logo">
    <svg class="nox-sidebar__logo-icon" viewBox="0 0 40 40">
      <use href="#icon-noxara"/>
    </svg>
    <span class="nox-sidebar__logo-text"><?= htmlspecialchars($siteName) ?></span>
  </a>

  <!-- Navigation -->
  <nav class="nox-sidebar__nav">

    <!-- UTAMA -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Utama</div>

      <a href="/dashboard" class="nox-sidebar__item<?= isActive('/dashboard') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-dashboard"/></svg>
        Dashboard
      </a>
    </div>

    <!-- KEUANGAN -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Keuangan</div>

      <a href="/deposit" class="nox-sidebar__item<?= isActive('/deposit') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-deposit"/></svg>
        Isi Ulang
      </a>

      <a href="/withdraw" class="nox-sidebar__item<?= isActive('/withdraw') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-withdraw"/></svg>
        Penarikan
      </a>

      <a href="/history" class="nox-sidebar__item<?= isActive('/history') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-history"/></svg>
        Riwayat Transaksi
      </a>
    </div>

    <!-- INVESTASI -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Investasi</div>

      <a href="/products" class="nox-sidebar__item<?= isActive('/products') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-package"/></svg>
        Produk Mining
      </a>

      <a href="/my-packages" class="nox-sidebar__item<?= isActive('/my-packages') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-mining"/></svg>
        Paket Aktif
      </a>

      <a href="/ads" class="nox-sidebar__item<?= isActive('/ads') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-ads"/></svg>
        Nonton Iklan
      </a>
    </div>

    <!-- REWARD -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Reward</div>

      <a href="/daily-reward" class="nox-sidebar__item<?= isActive('/daily-reward') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-gift"/></svg>
        Hadiah Harian
        <span class="nox-sidebar__item-new">NEW</span>
      </a>

      <a href="/missions" class="nox-sidebar__item<?= isActive('/missions') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-mission"/></svg>
        Misi
      </a>

      <a href="/leaderboard" class="nox-sidebar__item<?= isActive('/leaderboard') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-leaderboard"/></svg>
        Leaderboard
      </a>

      <a href="/profit-calendar" class="nox-sidebar__item<?= isActive('/profit-calendar') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-calendar"/></svg>
        Kalender Profit
      </a>
    </div>

    <!-- REFERRAL & VIP -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Referral & VIP</div>

      <a href="/referral" class="nox-sidebar__item<?= isActive('/referral') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-referral"/></svg>
        Referral
      </a>

      <a href="/vip" class="nox-sidebar__item<?= isActive('/vip') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-vip"/></svg>
        Level VIP
        <span class="nox-vip-badge nox-vip-<?= $vipLevel ?>" style="margin-left:auto;padding:2px 8px;font-size:10px">VIP <?= $vipLevel ?></span>
      </a>

      <a href="/voucher" class="nox-sidebar__item<?= isActive('/voucher') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-voucher"/></svg>
        Voucher
      </a>
    </div>

    <!-- LAINNYA -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Lainnya</div>

      <a href="/notifications" class="nox-sidebar__item<?= isActive('/notifications') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-bell"/></svg>
        Notifikasi
        <?php if ($unreadNotif > 0): ?>
          <span class="nox-sidebar__item-badge"><?= $unreadNotif ?></span>
        <?php endif; ?>
      </a>

      <a href="/chat" class="nox-sidebar__item<?= isActive('/chat') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-chat"/></svg>
        Live Chat
        <?php if ($unreadChat > 0): ?>
          <span class="nox-sidebar__item-badge"><?= $unreadChat ?></span>
        <?php endif; ?>
      </a>

      <a href="/info" class="nox-sidebar__item<?= isActive('/info') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-info"/></svg>
        Info Platform
      </a>

      <a href="/faq" class="nox-sidebar__item<?= isActive('/faq') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-faq"/></svg>
        FAQ
      </a>

      <a href="/contact" class="nox-sidebar__item<?= isActive('/contact') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-contact"/></svg>
        Hubungi Kami
      </a>
    </div>

    <!-- AKUN -->
    <div class="nox-sidebar__section">
      <div class="nox-sidebar__section-label">Akun</div>

      <a href="/profile" class="nox-sidebar__item<?= isActive('/profile') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-profile"/></svg>
        Profil Saya
      </a>

      <a href="/security" class="nox-sidebar__item<?= isActive('/security') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-security"/></svg>
        Keamanan
      </a>

      <a href="/bank-accounts" class="nox-sidebar__item<?= isActive('/bank-accounts') ?>">
        <svg class="nox-sidebar__item-icon"><use href="#icon-bank"/></svg>
        Rekening Bank
      </a>
    </div>

  </nav>

  <!-- Sidebar Footer -->
  <div class="nox-sidebar__footer">
    <!-- User Info -->
    <div class="nox-sidebar__user">
      <div class="nox-sidebar__user-avatar">
        <img src="<?= $avatarUrl ?>" alt="" onerror="this.src='<?= ASSETS_URL ?>/img/default-avatar.png'">
      </div>
      <div class="nox-sidebar__user-info">
        <div class="nox-sidebar__user-name"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></div>
        <div class="nox-sidebar__user-level" style="color:<?= htmlspecialchars($vipColor) ?>">
          VIP <?= $vipLevel ?> · <?= htmlspecialchars($vipLabel) ?>
        </div>
      </div>
    </div>

    <!-- Logout -->
    <button class="nox-sidebar__logout" onclick="confirmLogout()">
      <svg width="16" height="16"><use href="#icon-logout"/></svg>
      Keluar
    </button>
  </div>
</aside>

<!-- Drawer Overlay (mobile) -->
<div class="nox-drawer-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
