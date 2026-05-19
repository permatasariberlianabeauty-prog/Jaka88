<?php
/**
 * NOXARA - Topbar Header
 * Include setelah requireLogin() di setiap halaman member
 */
$currentUser    = getCurrentUser();
$unreadNotif    = $currentUser ? getUnreadNotifCount((int)$currentUser['id']) : 0;
$vipLevel       = (int)($currentUser['vip_level'] ?? 0);
$vipInfo        = getVipInfo($vipLevel);
$vipLabel       = $vipInfo['badge_label'] ?? 'BASIC';
$vipColor       = $vipInfo['color'] ?? '#6B7A99';
$currentPage    = basename($_SERVER['PHP_SELF'], '.php');
$siteName       = getSetting('site_name', 'NOXARA');
$avatar         = $currentUser['avatar'] ?? 'default.png';
$avatarUrl      = UPLOADS_URL . '/avatars/' . htmlspecialchars($avatar);
if (!file_exists(UPLOADS_PATH . '/avatars/' . $avatar)) {
    $avatarUrl = ASSETS_URL . '/img/default-avatar.png';
}

// Marquee data
$marqueeActive = getSetting('maintenance_mode') !== '1';
?>
<!-- SVG Icons Sprite -->
<?php include ASSETS_PATH . '/img/icons/icons.svg'; ?>

<!-- Marquee Running Text -->
<?php if ($marqueeActive): ?>
<?php
$marqueeStmt = db()->prepare("SELECT show_deposits, show_purchases, show_vip_upgrades, is_active, text_color, bg_color FROM marquee_settings LIMIT 1");
$marqueeStmt->execute();
$marqueeSetting = $marqueeStmt->get_result()->fetch_assoc();
$marqueeStmt->close();
if ($marqueeSetting && $marqueeSetting['is_active']): ?>
<div class="nox-marquee-wrap" style="background:<?= htmlspecialchars($marqueeSetting['bg_color'] ?? '#080C18') ?>">
  <div class="nox-marquee">
    <?php
    // Ambil data untuk marquee
    $items = [];
    // Deposit terbaru
    if ($marqueeSetting['show_deposits']) {
        $mStmt = db()->prepare("SELECT u.full_name, d.amount FROM deposits d JOIN users u ON u.id = d.user_id WHERE d.status = 'confirmed' ORDER BY d.confirmed_at DESC LIMIT 8");
        $mStmt->execute();
        $deposits = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mStmt->close();
        foreach ($deposits as $dep) {
            $items[] = '<span class="nox-marquee__item"><svg width="12" height="12"><use href="#icon-deposit"/></svg> ' . maskName($dep['full_name']) . ' melakukan deposit <span>' . formatRupiah((int)$dep['amount']) . '</span></span>';
        }
    }
    // Pembelian terbaru
    if ($marqueeSetting['show_purchases']) {
        $mStmt = db()->prepare("SELECT u.full_name, p.name FROM user_products up JOIN users u ON u.id = up.user_id JOIN products p ON p.id = up.product_id ORDER BY up.created_at DESC LIMIT 8");
        $mStmt->execute();
        $purchases = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mStmt->close();
        foreach ($purchases as $pur) {
            $items[] = '<span class="nox-marquee__item"><svg width="12" height="12"><use href="#icon-mining"/></svg> ' . maskName($pur['full_name']) . ' membeli paket <span>' . htmlspecialchars($pur['name']) . '</span></span>';
        }
    }
    // Announcements marquee
    $mStmt = db()->prepare("SELECT content FROM announcements WHERE type = 'marquee' AND is_active = 1 ORDER BY sort_order ASC LIMIT 5");
    $mStmt->execute();
    $announcements = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mStmt->close();
    foreach ($announcements as $ann) {
        $items[] = '<span class="nox-marquee__item">' . htmlspecialchars($ann['content']) . '</span>';
    }
    if (empty($items)) {
        $items[] = '<span class="nox-marquee__item">Selamat datang di <span>' . htmlspecialchars($siteName) . '</span> — Invest Smarter, Grow Faster</span>';
    }
    // Duplikat untuk loop seamless
    $allItems = implode(' &nbsp;•&nbsp; ', $items);
    echo $allItems . ' &nbsp;•&nbsp; ' . $allItems;
    ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Topbar -->
<header class="nox-topbar">
  <button class="nox-topbar__toggle" id="sidebarToggle" aria-label="Toggle Menu">
    <svg width="20" height="20"><use href="#icon-menu"/></svg>
  </button>

  <!-- Search -->
  <div class="nox-topbar__search">
    <svg width="16" height="16" style="color:var(--text-disabled)"><use href="#icon-search"/></svg>
    <input type="text" placeholder="Cari transaksi, paket..." id="globalSearch" autocomplete="off">
  </div>

  <div class="nox-topbar__right">
    <!-- Notification Bell -->
    <button class="nox-topbar__btn" id="notifBtn" aria-label="Notifikasi">
      <svg width="20" height="20"><use href="#icon-bell"/></svg>
      <?php if ($unreadNotif > 0): ?>
        <span class="nox-topbar__badge" id="notifBadge"><?= $unreadNotif > 99 ? '99+' : $unreadNotif ?></span>
      <?php endif; ?>
    </button>

    <!-- User Avatar -->
    <div style="display:flex;align-items:center;gap:10px;cursor:pointer" id="userMenuBtn">
      <div class="nox-topbar__avatar">
        <img src="<?= $avatarUrl ?>" alt="Avatar" onerror="this.src='<?= ASSETS_URL ?>/img/default-avatar.png'">
      </div>
      <div class="nox-topbar__user" style="display:none" class="nox-topbar__user-info">
        <span class="nox-topbar__user-name"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></span>
        <span class="nox-topbar__user-vip" style="color:<?= htmlspecialchars($vipColor) ?>">VIP <?= $vipLevel ?> · <?= htmlspecialchars($vipLabel) ?></span>
      </div>
    </div>
  </div>
</header>

<!-- Notification Dropdown -->
<div id="notifDropdown" class="nox-notif-dropdown" style="display:none">
  <div class="nox-notif-dropdown__header">
    <span class="nox-notif-dropdown__title">Notifikasi</span>
    <button class="nox-notif-dropdown__markall" onclick="markAllRead()">Tandai semua dibaca</button>
  </div>
  <div class="nox-notif-dropdown__list" id="notifList">
    <div style="text-align:center;padding:24px;color:var(--text-secondary);font-size:13px">Memuat...</div>
  </div>
  <a href="/notifications" class="nox-notif-dropdown__footer">Lihat Semua Notifikasi</a>
</div>

<!-- User Dropdown -->
<div id="userDropdown" class="nox-user-dropdown" style="display:none">
  <div class="nox-user-dropdown__info">
    <img src="<?= $avatarUrl ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
    <div>
      <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></div>
      <div style="font-size:12px;color:<?= htmlspecialchars($vipColor) ?>">VIP <?= $vipLevel ?> · <?= htmlspecialchars($vipLabel) ?></div>
    </div>
  </div>
  <hr style="border:none;border-top:1px solid var(--border);margin:8px 0">
  <a href="/profile" class="nox-user-dropdown__item"><svg width="16" height="16"><use href="#icon-profile"/></svg> Profil Saya</a>
  <a href="/security" class="nox-user-dropdown__item"><svg width="16" height="16"><use href="#icon-security"/></svg> Keamanan</a>
  <a href="/vip" class="nox-user-dropdown__item"><svg width="16" height="16"><use href="#icon-vip"/></svg> Level VIP</a>
  <hr style="border:none;border-top:1px solid var(--border);margin:8px 0">
  <a href="/logout" class="nox-user-dropdown__item nox-user-dropdown__item--danger"><svg width="16" height="16"><use href="#icon-logout"/></svg> Keluar</a>
</div>

<style>
.nox-notif-dropdown,.nox-user-dropdown{
  position:fixed;top:72px;right:16px;z-index:500;
  background:var(--bg-card);border:1px solid var(--border-light);
  border-radius:var(--radius-lg);box-shadow:0 16px 48px rgba(0,0,0,0.5);
  width:320px;overflow:hidden;animation:nox-fadeIn 0.2s ease;
}
.nox-notif-dropdown__header{display:flex;align-items:center;justify-content:space-between;padding:16px 16px 12px;border-bottom:1px solid var(--border)}
.nox-notif-dropdown__title{font-weight:700;font-size:15px}
.nox-notif-dropdown__markall{font-size:11px;color:var(--cyan);background:none;border:none;cursor:pointer;font-weight:600}
.nox-notif-dropdown__list{max-height:360px;overflow-y:auto}
.nox-notif-item{display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid rgba(30,42,69,0.4);cursor:pointer;transition:var(--transition)}
.nox-notif-item:hover{background:rgba(0,212,255,0.04)}
.nox-notif-item--unread{background:rgba(0,212,255,0.03)}
.nox-notif-item__dot{width:8px;height:8px;border-radius:50%;background:var(--cyan);margin-top:6px;flex-shrink:0}
.nox-notif-item__title{font-size:13px;font-weight:600;margin-bottom:2px}
.nox-notif-item__msg{font-size:12px;color:var(--text-secondary);line-height:1.5}
.nox-notif-item__time{font-size:11px;color:var(--text-disabled);margin-top:4px}
.nox-notif-dropdown__footer{display:block;text-align:center;padding:12px;font-size:13px;color:var(--cyan);font-weight:600;border-top:1px solid var(--border)}
.nox-user-dropdown{width:220px;padding:12px}
.nox-user-dropdown__info{display:flex;align-items:center;gap:10px;padding:4px 0 8px}
.nox-user-dropdown__item{display:flex;align-items:center;gap:10px;padding:10px 8px;border-radius:var(--radius-sm);font-size:13px;color:var(--text-secondary);cursor:pointer;transition:var(--transition);text-decoration:none}
.nox-user-dropdown__item:hover{background:rgba(0,212,255,0.06);color:var(--text-primary)}
.nox-user-dropdown__item--danger:hover{background:rgba(255,68,68,0.08);color:var(--red)}
</style>
