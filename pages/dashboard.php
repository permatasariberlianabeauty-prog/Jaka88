<?php
/**
 * NOXARA - Dashboard Member
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/vip.php';
require_once __DIR__ . '/../includes/mining.php';
require_once __DIR__ . '/../includes/referral.php';
require_once __DIR__ . '/../includes/notification.php';

requireLogin();

$user           = getCurrentUser();
$wallet         = getUserWallet((int)$user['id']);
$vipData        = getUserVipInfo((int)$user['id']);
$activePackages = getUserActivePackages((int)$user['id']);
$refStats       = getReferralStats((int)$user['id']);

// Profit hari ini
$stmtPD = db()->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? AND type='profit' AND DATE(created_at)=CURDATE()");
$stmtPD->bind_param("i", (int)$user['id']);
$stmtPD->execute();
$profitToday = (int)$stmtPD->get_result()->fetch_assoc()['total'];
$stmtPD->close();

// Profit minggu ini
$stmtPW = db()->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? AND type='profit' AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)");
$stmtPW->bind_param("i", (int)$user['id']);
$stmtPW->execute();
$profitWeek = (int)$stmtPW->get_result()->fetch_assoc()['total'];
$stmtPW->close();

// Mining hari ini: selesai / total paket
$miningDone  = 0;
$miningTotal = count($activePackages);
foreach ($activePackages as $pkg) {
    if (!empty($pkg['today_mining_status'])) $miningDone++;
}


// Misi hari ini
$stmtM = db()->prepare("SELECT COUNT(*) as total, SUM(is_completed) as done FROM user_missions um JOIN missions m ON m.id=um.mission_id WHERE um.user_id=? AND m.type='daily'");
$stmtM->bind_param("i", (int)$user['id']);
$stmtM->execute();
$mRow         = $stmtM->get_result()->fetch_assoc();
$stmtM->close();
$missionsTotal = (int)($mRow['total'] ?? 0);
$missionsDone  = (int)($mRow['done'] ?? 0);

// Misi harian untuk ditampilkan (maks 3)
$stmtML = db()->prepare("SELECT m.*, um.current_count, um.is_completed, um.is_claimed FROM missions m LEFT JOIN user_missions um ON um.mission_id=m.id AND um.user_id=? WHERE m.is_active=1 AND m.type='daily' ORDER BY um.is_completed ASC, m.sort_order ASC LIMIT 3");
$stmtML->bind_param("i", (int)$user['id']);
$stmtML->execute();
$missions = $stmtML->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtML->close();

// Transaksi terbaru 5
$stmtTX = db()->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$stmtTX->bind_param("i", (int)$user['id']);
$stmtTX->execute();
$recentTrx = $stmtTX->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTX->close();

// Grafik profit 7 hari (Chart.js)
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('d M', strtotime($date));
    $stmtC = db()->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? AND type='profit' AND DATE(created_at)=?");
    $stmtC->bind_param("is", (int)$user['id'], $date);
    $stmtC->execute();
    $chartLabels[] = $label;
    $chartValues[] = (int)$stmtC->get_result()->fetch_assoc()['total'];
    $stmtC->close();
}

$referralLink = rtrim(getSetting('site_url', ''), '/') . '/register?ref=' . htmlspecialchars($user['referral_code'] ?? '');
$vipCurrent   = $vipData['current'];
$vipNext      = $vipData['next_level'];
$vipProgress  = $vipData['progress'];
$vipNeeded    = $vipData['needed'];
$vipColor     = $vipCurrent['color'] ?? '#6B7A99';
$vipLabel     = $vipCurrent['badge_label'] ?? 'BASIC';
$vipLevel     = (int)($vipCurrent['vip_level'] ?? 0);

$pageTitle = 'Dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= getSetting('site_name','NOXARA') ?></title>
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── Dashboard Specific ── */
.nox-page-header { margin-bottom: 24px; }
.nox-page-header h1 { font-family: 'Space Grotesk', sans-serif; font-size: 26px; font-weight: 700; margin: 0 0 4px; }
.nox-page-header p  { color: var(--text-secondary); font-size: 14px; margin: 0; }

/* VIP Progress */
.nox-vip-progress { padding: 20px 24px; border-radius: var(--radius-lg); background: var(--bg-card); border: 1px solid var(--border-light); }
.nox-vip-progress__top { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.nox-vip-icon { width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.nox-vip-progress__bar-wrap { height: 8px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; margin: 10px 0 8px; }
.nox-vip-progress__bar-fill { height: 100%; border-radius: 99px; transition: width 1.2s cubic-bezier(.4,0,.2,1); }
.nox-vip-progress__info { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-secondary); }

/* Saldo Cards */
.nox-balance-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media(max-width: 768px){ .nox-balance-grid { grid-template-columns: repeat(2,1fr); gap: 12px; } }
@media(max-width: 480px){ .nox-balance-grid { display: flex; overflow-x: auto; gap: 12px; padding-bottom: 8px; scroll-snap-type: x mandatory; } .nox-balance-card { min-width: 200px; scroll-snap-align: start; } }
.nox-balance-card { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 20px; position: relative; overflow: hidden; transition: var(--transition); }
.nox-balance-card:hover { transform: translateY(-2px); border-color: var(--cyan); box-shadow: 0 8px 32px rgba(0,212,255,0.12); }
.nox-balance-card__glow { position: absolute; top: -30px; right: -20px; width: 90px; height: 90px; border-radius: 50%; opacity: 0.12; blur: 24px; }
.nox-balance-card__label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.nox-balance-card__amount { font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; margin-bottom: 14px; line-height: 1.2; }
.nox-balance-card__actions { display: flex; gap: 8px; flex-wrap: wrap; }
.nox-btn-xs { padding: 5px 12px; font-size: 11px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: var(--transition); }
.nox-btn-xs--cyan { background: rgba(0,212,255,0.15); color: var(--cyan); }
.nox-btn-xs--cyan:hover { background: var(--cyan); color: #000; }
.nox-btn-xs--purple { background: rgba(123,47,255,0.15); color: var(--purple); }
.nox-btn-xs--purple:hover { background: var(--purple); color: #fff; }

/* Quick Actions */
.nox-quick-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
@media(max-width: 640px){ .nox-quick-grid { grid-template-columns: repeat(4,1fr); gap: 8px; } }
.nox-quick-card { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 16px 12px; text-align: center; text-decoration: none; color: var(--text-primary); transition: var(--transition); display: flex; flex-direction: column; align-items: center; gap: 8px; }
.nox-quick-card:hover { border-color: var(--cyan); transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,212,255,0.15); color: var(--cyan); }
.nox-quick-card__icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(0,212,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 20px; }
.nox-quick-card__label { font-size: 12px; font-weight: 600; }

/* Stat cards */
.nox-stat-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 24px; }
@media(max-width: 640px){ .nox-stat-grid { grid-template-columns: 1fr 1fr 1fr; gap: 10px; } }
.nox-stat-card { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 16px; text-align: center; }
.nox-stat-card__val { font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 700; margin: 6px 0 4px; }
.nox-stat-card__lbl { font-size: 11px; color: var(--text-secondary); }

/* Package cards */
.nox-pkg-card { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 16px; display: flex; gap: 14px; align-items: flex-start; margin-bottom: 12px; }
.nox-pkg-card__img { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; flex-shrink: 0; background: rgba(0,212,255,0.08); }
.nox-pkg-card__body { flex: 1; min-width: 0; }
.nox-pkg-card__name { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
.nox-pkg-card__meta { font-size: 12px; color: var(--text-secondary); margin-bottom: 8px; display: flex; gap: 12px; flex-wrap: wrap; }
.nox-pkg-card__bar-wrap { height: 5px; background: rgba(255,255,255,0.07); border-radius: 99px; overflow: hidden; margin-bottom: 8px; }
.nox-pkg-card__bar-fill { height: 100%; background: linear-gradient(90deg, var(--cyan), var(--purple)); border-radius: 99px; }

/* Missions */
.nox-mission-item { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 14px 16px; display: flex; align-items: center; gap: 14px; margin-bottom: 10px; }
.nox-mission-item__icon { font-size: 22px; width: 40px; flex-shrink: 0; text-align: center; }
.nox-mission-item__body { flex: 1; min-width: 0; }
.nox-mission-item__title { font-size: 13px; font-weight: 600; margin-bottom: 3px; }
.nox-mission-item__prog { font-size: 11px; color: var(--text-secondary); }
.nox-mission-item__bar { height: 4px; background: rgba(255,255,255,0.07); border-radius: 99px; overflow: hidden; margin-top: 5px; }
.nox-mission-item__bar-fill { height: 100%; background: var(--cyan); border-radius: 99px; }

/* Referral mini */
.nox-ref-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 14px; }
.nox-ref-box { background: rgba(0,212,255,0.05); border: 1px solid rgba(0,212,255,0.15); border-radius: var(--radius-md); padding: 14px; text-align: center; }
.nox-ref-box__num { font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; color: var(--cyan); }
.nox-ref-box__lbl { font-size: 11px; color: var(--text-secondary); margin-top: 3px; }

/* Recent Trx */
.nox-trx-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(30,42,69,0.5); }
.nox-trx-item:last-child { border-bottom: none; }
.nox-trx-item__icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.nox-trx-item__body { flex: 1; min-width: 0; }
.nox-trx-item__name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nox-trx-item__time { font-size: 11px; color: var(--text-secondary); }
.nox-trx-item__amount { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 14px; text-align: right; }

/* Chart */
.nox-chart-wrap { position: relative; height: 220px; }

/* Section header */
.nox-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.nox-section-header h2 { font-size: 15px; font-weight: 700; margin: 0; }
.nox-section-header a { font-size: 12px; color: var(--cyan); text-decoration: none; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <main class="nox-content nox-page-enter">
    <?= renderFlash() ?>

    <!-- ══ PAGE HEADER ══════════════════════════════════ -->
    <div class="nox-page-header">
      <h1>Halo, <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>! 👋</h1>
      <p>Selamat datang di NOXARA — <?= date('l, d F Y') ?></p>
    </div>


    <!-- ══ VIP PROGRESS ═════════════════════════════════ -->
    <div class="nox-vip-progress nox-card--glow" style="margin-bottom:24px;border-color:<?= htmlspecialchars($vipColor) ?>40">
      <div class="nox-vip-progress__top">
        <div class="nox-vip-icon" style="background:<?= htmlspecialchars($vipColor) ?>22;border:2px solid <?= htmlspecialchars($vipColor) ?>40">
          <?php
          $vipIcons = [0=>'🔵',1=>'🥉',2=>'🥈',3=>'🥇',4=>'💎',5=>'👑'];
          echo $vipIcons[$vipLevel] ?? '⭐';
          ?>
        </div>
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <span style="font-weight:700;font-size:16px">VIP <?= $vipLevel ?></span>
            <span class="nox-badge" style="background:<?= htmlspecialchars($vipColor) ?>22;color:<?= htmlspecialchars($vipColor) ?>;font-size:10px;padding:3px 10px;border-radius:99px;font-weight:700"><?= htmlspecialchars($vipLabel) ?></span>
          </div>
          <div style="font-size:12px;color:var(--text-secondary)">
            <?php if ($vipNext): ?>
              Deposit kumulatif: <strong><?= formatRupiah((int)($vipCurrent['total_deposit_cumulative'] ?? 0)) ?></strong>
            <?php else: ?>
              <span style="color:var(--cyan)">🎉 Anda telah mencapai level VIP tertinggi!</span>
            <?php endif; ?>
          </div>
        </div>
        <a href="/vip" class="nox-btn nox-btn--sm nox-btn--outline" style="white-space:nowrap;flex-shrink:0">Lihat VIP</a>
      </div>
      <?php if ($vipNext): ?>
      <div class="nox-vip-progress__bar-wrap">
        <div class="nox-vip-progress__bar-fill" style="width:<?= $vipProgress ?>%;background:linear-gradient(90deg,<?= htmlspecialchars($vipColor) ?>,var(--purple))"></div>
      </div>
      <div class="nox-vip-progress__info">
        <span><?= $vipProgress ?>% menuju VIP <?= (int)$vipNext['level'] ?> (<?= htmlspecialchars($vipNext['badge_label'] ?? '') ?>)</span>
        <span>Butuh <?= formatRupiah((int)$vipNeeded) ?> lagi</span>
      </div>
      <?php else: ?>
      <div class="nox-vip-progress__bar-wrap"><div class="nox-vip-progress__bar-fill" style="width:100%;background:linear-gradient(90deg,#FFB300,#FF6B6B)"></div></div>
      <div class="nox-vip-progress__info"><span>Level Maksimal Tercapai</span><span style="color:#FFB300">👑 ELITE</span></div>
      <?php endif; ?>
    </div>

    <!-- ══ SALDO CARDS ═══════════════════════════════════ -->
    <div class="nox-balance-grid" style="margin-bottom:24px">
      <!-- Saldo Utama -->
      <div class="nox-balance-card">
        <div class="nox-balance-card__glow" style="background:var(--cyan)"></div>
        <div class="nox-balance-card__label">
          <svg width="14" height="14"><use href="#icon-wallet"/></svg> Saldo Utama
        </div>
        <div class="nox-balance-card__amount" style="color:var(--cyan)" data-countup="<?= (int)$wallet['balance_main'] ?>">
          <?= formatRupiah((int)$wallet['balance_main']) ?>
        </div>
        <div class="nox-balance-card__actions">
          <a href="/deposit" class="nox-btn-xs nox-btn-xs--cyan">+ Deposit</a>
          <a href="/withdraw" class="nox-btn-xs nox-btn-xs--purple">Tarik</a>
        </div>
      </div>
      <!-- Saldo Profit -->
      <div class="nox-balance-card">
        <div class="nox-balance-card__glow" style="background:var(--green)"></div>
        <div class="nox-balance-card__label">
          <svg width="14" height="14"><use href="#icon-profit"/></svg> Saldo Profit
        </div>
        <div class="nox-balance-card__amount" style="color:var(--green)" data-countup="<?= (int)$wallet['balance_profit'] ?>">
          <?= formatRupiah((int)$wallet['balance_profit']) ?>
        </div>
        <div class="nox-balance-card__actions">
          <a href="/withdraw?from=profit" class="nox-btn-xs nox-btn-xs--cyan">Tarik</a>
        </div>
      </div>
      <!-- Saldo Bonus -->
      <div class="nox-balance-card">
        <div class="nox-balance-card__glow" style="background:var(--amber)"></div>
        <div class="nox-balance-card__label">
          <svg width="14" height="14"><use href="#icon-gift"/></svg> Saldo Bonus
        </div>
        <div class="nox-balance-card__amount" style="color:var(--amber)" data-countup="<?= (int)$wallet['balance_bonus'] ?>">
          <?= formatRupiah((int)$wallet['balance_bonus']) ?>
        </div>
        <div style="font-size:10px;color:var(--text-disabled);margin-top:2px">Hanya untuk beli paket</div>
        <div class="nox-balance-card__actions">
          <a href="/products" class="nox-btn-xs nox-btn-xs--cyan">Beli Paket</a>
        </div>
      </div>
      <!-- Saldo Referral -->
      <div class="nox-balance-card">
        <div class="nox-balance-card__glow" style="background:var(--purple)"></div>
        <div class="nox-balance-card__label">
          <svg width="14" height="14"><use href="#icon-referral"/></svg> Saldo Referral
        </div>
        <div class="nox-balance-card__amount" style="color:var(--purple)" data-countup="<?= (int)$wallet['balance_referral'] ?>">
          <?= formatRupiah((int)$wallet['balance_referral']) ?>
        </div>
        <div class="nox-balance-card__actions">
          <a href="/withdraw?from=referral" class="nox-btn-xs nox-btn-xs--purple">Tarik</a>
        </div>
      </div>
    </div>


    <!-- ══ QUICK ACTIONS ═════════════════════════════════ -->
    <div class="nox-quick-grid" style="margin-bottom:24px">
      <a href="/deposit" class="nox-quick-card nox-hover-lift">
        <div class="nox-quick-card__icon">💳</div>
        <div class="nox-quick-card__label">Isi Ulang</div>
      </a>
      <a href="/withdraw" class="nox-quick-card nox-hover-lift">
        <div class="nox-quick-card__icon">💸</div>
        <div class="nox-quick-card__label">Tarik Dana</div>
      </a>
      <a href="/products" class="nox-quick-card nox-hover-lift">
        <div class="nox-quick-card__icon">📦</div>
        <div class="nox-quick-card__label">Beli Paket</div>
      </a>
      <a href="/ads" class="nox-quick-card nox-hover-lift">
        <div class="nox-quick-card__icon">▶️</div>
        <div class="nox-quick-card__label">Tonton Iklan</div>
      </a>
    </div>

    <!-- ══ STATISTIK HARI INI ════════════════════════════ -->
    <div class="nox-stat-grid" style="margin-bottom:24px">
      <div class="nox-stat-card">
        <div style="font-size:22px">💰</div>
        <div class="nox-stat-card__val" style="color:var(--cyan)"><?= formatRupiah($profitToday, false) ?></div>
        <div class="nox-stat-card__lbl">Profit Hari Ini</div>
      </div>
      <div class="nox-stat-card">
        <div style="font-size:22px">⛏️</div>
        <div class="nox-stat-card__val" style="color:var(--green)"><?= $miningDone ?>/<?= $miningTotal ?></div>
        <div class="nox-stat-card__lbl">Mining Selesai</div>
      </div>
      <div class="nox-stat-card">
        <div style="font-size:22px">🎯</div>
        <div class="nox-stat-card__val" style="color:var(--purple)"><?= $missionsDone ?>/<?= $missionsTotal ?></div>
        <div class="nox-stat-card__lbl">Misi Selesai</div>
      </div>
    </div>

    <!-- ══ PAKET AKTIF ═══════════════════════════════════ -->
    <div style="margin-bottom:24px">
      <div class="nox-section-header">
        <h2>⛏️ Paket Aktif</h2>
        <a href="/my-packages">Lihat Semua →</a>
      </div>
      <?php if (empty($activePackages)): ?>
        <div class="nox-card" style="text-align:center;padding:32px;color:var(--text-secondary)">
          <div style="font-size:36px;margin-bottom:10px">📦</div>
          <div style="font-weight:600;margin-bottom:6px">Belum ada paket aktif</div>
          <div style="font-size:13px;margin-bottom:16px">Beli paket mining untuk mulai menghasilkan profit harian</div>
          <a href="/products" class="nox-btn nox-btn--primary nox-btn--sm">Lihat Produk</a>
        </div>
      <?php else: ?>
        <?php foreach (array_slice($activePackages, 0, 3) as $pkg): ?>
          <div class="nox-pkg-card">
            <?php $imgSrc = !empty($pkg['image']) ? UPLOADS_URL . '/products/' . $pkg['image'] : ASSETS_URL . '/img/mining/default.png'; ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" class="nox-pkg-card__img" onerror="this.src='<?= ASSETS_URL ?>/img/mining/default.png'">
            <div class="nox-pkg-card__body">
              <div class="nox-pkg-card__name"><?= htmlspecialchars($pkg['name']) ?></div>
              <div class="nox-pkg-card__meta">
                <span style="color:var(--cyan)">+<?= formatRupiah((int)$pkg['profit_per_day']) ?>/hari</span>
                <span>Sisa <?= max(0,(int)$pkg['days_remaining']) ?> hari</span>
              </div>
              <div class="nox-pkg-card__bar-wrap">
                <div class="nox-pkg-card__bar-fill" style="width:<?= min(100,$pkg['progress_percent']) ?>%"></div>
              </div>
              <div style="font-size:11px;color:var(--text-secondary)"><?= round($pkg['progress_percent'],0) ?>% selesai</div>
            </div>
            <div style="flex-shrink:0;text-align:right">
              <?php if (empty($pkg['today_mining_status'])): ?>
                <button class="nox-btn nox-btn--primary nox-btn--sm" onclick="doMining(<?= (int)$pkg['id'] ?>,this)">⛏️ Mining</button>
              <?php elseif ($pkg['today_mining_status'] === 'pending'): ?>
                <div style="font-size:11px;color:var(--amber);text-align:center">
                  <div>⏳ Menunggu</div>
                  <div class="mining-countdown" data-seconds="<?= (int)$pkg['mining_countdown'] ?>">--:--:--</div>
                </div>
              <?php else: ?>
                <span class="nox-badge nox-badge--success" style="font-size:11px">✅ Selesai</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ══ GRAFIK PROFIT 7 HARI ══════════════════════════ -->
    <div class="nox-card" style="margin-bottom:24px;padding:20px">
      <div class="nox-section-header">
        <h2>📈 Profit 7 Hari Terakhir</h2>
        <span style="font-size:12px;color:var(--text-secondary)"><?= formatRupiah($profitWeek) ?> minggu ini</span>
      </div>
      <div class="nox-chart-wrap">
        <canvas id="profitChart"></canvas>
      </div>
    </div>


    <!-- ══ MISI HARIAN ═══════════════════════════════════ -->
    <div style="margin-bottom:24px">
      <div class="nox-section-header">
        <h2>🎯 Misi Harian</h2>
        <a href="/missions">Semua Misi →</a>
      </div>
      <?php if (empty($missions)): ?>
        <div class="nox-card" style="text-align:center;padding:24px;color:var(--text-secondary);font-size:13px">Tidak ada misi aktif hari ini</div>
      <?php else: ?>
        <?php foreach ($missions as $m):
          $cur  = (int)($m['current_count'] ?? 0);
          $tgt  = (int)($m['target_count'] ?? 1);
          $pct  = $tgt > 0 ? min(100, round($cur/$tgt*100)) : 0;
          $done = (bool)($m['is_completed'] ?? false);
          $claimed = (bool)($m['is_claimed'] ?? false);
        ?>
          <div class="nox-mission-item">
            <div class="nox-mission-item__icon"><?= htmlspecialchars($m['icon'] ?? '🎯') ?></div>
            <div class="nox-mission-item__body">
              <div class="nox-mission-item__title"><?= htmlspecialchars($m['title']) ?></div>
              <div class="nox-mission-item__prog"><?= $cur ?>/<?= $tgt ?> · Reward: <?= formatRupiah((int)$m['reward_amount']) ?></div>
              <div class="nox-mission-item__bar">
                <div class="nox-mission-item__bar-fill" style="width:<?= $pct ?>%;<?= $done ? 'background:var(--green)' : '' ?>"></div>
              </div>
            </div>
            <div style="flex-shrink:0">
              <?php if ($claimed): ?>
                <span class="nox-badge" style="font-size:11px;background:rgba(0,230,118,0.1);color:var(--green)">✅ Diklaim</span>
              <?php elseif ($done): ?>
                <a href="/missions?claim=<?= (int)$m['id'] ?>" class="nox-btn nox-btn--primary nox-btn--sm">Klaim</a>
              <?php else: ?>
                <span style="font-size:12px;color:var(--text-disabled)"><?= $pct ?>%</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ══ REFERRAL RINGKASAN ═════════════════════════════ -->
    <div style="margin-bottom:24px">
      <div class="nox-section-header">
        <h2>👥 Referral Saya</h2>
        <a href="/referral">Detail →</a>
      </div>
      <div class="nox-card" style="padding:20px">
        <div class="nox-ref-grid" style="margin-bottom:16px">
          <div class="nox-ref-box">
            <div class="nox-ref-box__num"><?= (int)($refStats['by_level'][1]['total'] ?? 0) ?></div>
            <div class="nox-ref-box__lbl">Downline L1</div>
          </div>
          <div class="nox-ref-box">
            <div class="nox-ref-box__num"><?= (int)($refStats['by_level'][2]['total'] ?? 0) ?></div>
            <div class="nox-ref-box__lbl">Downline L2</div>
          </div>
          <div class="nox-ref-box">
            <div class="nox-ref-box__num"><?= (int)($refStats['by_level'][3]['total'] ?? 0) ?></div>
            <div class="nox-ref-box__lbl">Downline L3</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
          <div>
            <div style="font-size:12px;color:var(--text-secondary)">Rabat Masuk Hari Ini</div>
            <div style="font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--cyan)"><?= formatRupiah($refStats['today_commission']) ?></div>
          </div>
          <div style="text-align:right">
            <div style="font-size:12px;color:var(--text-secondary)">Total Komisi</div>
            <div style="font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--purple)"><?= formatRupiah($refStats['total_commission']) ?></div>
          </div>
        </div>
        <div style="background:rgba(0,212,255,0.05);border:1px solid rgba(0,212,255,0.2);border-radius:10px;padding:12px;display:flex;align-items:center;gap:10px">
          <div style="flex:1;font-size:12px;color:var(--text-secondary);word-break:break-all" id="refLinkText"><?= htmlspecialchars($referralLink) ?></div>
          <button class="nox-btn nox-btn--sm nox-btn--outline" onclick="copyRefLink()">📋 Salin</button>
        </div>
      </div>
    </div>

    <!-- ══ TRANSAKSI TERBARU ══════════════════════════════ -->
    <div style="margin-bottom:32px">
      <div class="nox-section-header">
        <h2>🕐 Transaksi Terbaru</h2>
        <a href="/history">Lihat Semua →</a>
      </div>
      <div class="nox-card" style="padding:0 20px">
        <?php if (empty($recentTrx)): ?>
          <div style="text-align:center;padding:24px;color:var(--text-secondary);font-size:13px">Belum ada transaksi</div>
        <?php else: ?>
          <?php
          $trxIcons = [
            'deposit'=>['🟢','rgba(0,230,118,0.1)'],
            'withdraw'=>['🔴','rgba(255,68,68,0.1)'],
            'profit'=>['💎','rgba(0,212,255,0.1)'],
            'referral_commission'=>['👥','rgba(123,47,255,0.1)'],
            'purchase'=>['📦','rgba(255,179,0,0.1)'],
            'bonus'=>['🎁','rgba(255,179,0,0.1)'],
            'mission_reward'=>['🎯','rgba(0,230,118,0.1)'],
            'ad_reward'=>['▶️','rgba(0,212,255,0.1)'],
          ];
          foreach ($recentTrx as $trx):
            $ti    = $trxIcons[$trx['type']] ?? ['📄','rgba(107,122,153,0.1)'];
            $isPos = (int)$trx['amount'] >= 0;
          ?>
            <div class="nox-trx-item">
              <div class="nox-trx-item__icon" style="background:<?= $ti[1] ?>"><?= $ti[0] ?></div>
              <div class="nox-trx-item__body">
                <div class="nox-trx-item__name"><?= htmlspecialchars(ucwords(str_replace('_',' ',$trx['type']))) ?></div>
                <div class="nox-trx-item__time"><?= timeAgo($trx['created_at']) ?></div>
              </div>
              <div class="nox-trx-item__amount" style="color:<?= $isPos ? 'var(--green)' : 'var(--red)' ?>">
                <?= $isPos ? '+' : '' ?><?= formatRupiah(abs((int)$trx['amount'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>


<script>
/* ── Chart.js Profit 7 Hari ─────────────────── */
(function(){
  const labels = <?= json_encode($chartLabels) ?>;
  const values = <?= json_encode($chartValues) ?>;
  const ctx = document.getElementById('profitChart').getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 200);
  grad.addColorStop(0, 'rgba(0,212,255,0.35)');
  grad.addColorStop(1, 'rgba(0,212,255,0.02)');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Profit (Rp)',
        data: values,
        borderColor: '#00D4FF',
        backgroundColor: grad,
        borderWidth: 2.5,
        pointBackgroundColor: '#00D4FF',
        pointBorderColor: '#0A0E1A',
        pointBorderWidth: 2,
        pointRadius: 5,
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'Rp ' + ctx.parsed.y.toLocaleString('id-ID')
          }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B7A99', font: { size: 11 } } },
        y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B7A99', font: { size: 11 }, callback: v => 'Rp ' + Number(v).toLocaleString('id-ID') } }
      }
    }
  });
})();

/* ── Count-up Animation ─────────────────────── */
document.querySelectorAll('[data-countup]').forEach(el => {
  const target = parseInt(el.dataset.countup, 10);
  if (isNaN(target) || target <= 0) return;
  let start = 0;
  const duration = 1200;
  const step = timestamp => {
    if (!start) start = timestamp;
    const progress = Math.min((timestamp - start) / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    const val = Math.floor(ease * target);
    el.textContent = 'Rp ' + val.toLocaleString('id-ID');
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = 'Rp ' + target.toLocaleString('id-ID');
  };
  requestAnimationFrame(step);
});

/* ── Mining Countdown ───────────────────────── */
document.querySelectorAll('.mining-countdown').forEach(el => {
  let secs = parseInt(el.dataset.seconds, 10);
  if (isNaN(secs) || secs <= 0) { el.textContent = 'Selesai'; return; }
  const tick = () => {
    if (secs <= 0) { el.textContent = 'Selesai ✅'; return; }
    const h = String(Math.floor(secs/3600)).padStart(2,'0');
    const m = String(Math.floor((secs%3600)/60)).padStart(2,'0');
    const s = String(secs%60).padStart(2,'0');
    el.textContent = h + ':' + m + ':' + s;
    secs--; setTimeout(tick, 1000);
  };
  tick();
});

/* ── Copy Referral Link ─────────────────────── */
function copyRefLink() {
  const text = document.getElementById('refLinkText').textContent.trim();
  navigator.clipboard.writeText(text).then(() => {
    const btn = event.target;
    const orig = btn.textContent;
    btn.textContent = '✅ Tersalin!';
    setTimeout(() => btn.textContent = orig, 2000);
  });
}

/* ── Do Mining (AJAX) ───────────────────────── */
function doMining(packageId, btn) {
  btn.disabled = true;
  btn.textContent = '⏳ ...';
  fetch('/api/mining', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.cookie.match(/csrf_token=([^;]+)/)?.[1] || '' },
    body: JSON.stringify({ action: 'mine', package_id: packageId })
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      btn.closest('.nox-pkg-card').querySelector('[style*="flex-shrink"]').innerHTML =
        '<div style="font-size:11px;color:var(--amber);text-align:center"><div>⏳ Menunggu</div><div class="mining-countdown" data-seconds="' + res.data.countdown + '">--:--:--</div></div>';
      document.querySelectorAll('.mining-countdown').forEach(el => {
        let s = parseInt(el.dataset.seconds,10);
        const t = () => { if(s<=0){el.textContent='Selesai ✅';return;} const h=String(Math.floor(s/3600)).padStart(2,'0'),m=String(Math.floor((s%3600)/60)).padStart(2,'0'),sc=String(s%60).padStart(2,'0');el.textContent=h+':'+m+':'+sc;s--;setTimeout(t,1000); }; t();
      });
    } else {
      alert(res.message || 'Gagal melakukan mining');
      btn.disabled = false;
      btn.textContent = '⛏️ Mining';
    }
  })
  .catch(() => { btn.disabled = false; btn.textContent = '⛏️ Mining'; });
}

/* ── Logout Confirm ─────────────────────────── */
function confirmLogout() {
  if (confirm('Yakin ingin keluar dari akun?')) window.location.href = '/logout';
}
</script>
</body>
</html>
