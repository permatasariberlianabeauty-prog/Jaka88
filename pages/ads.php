<?php
/**
 * NOXARA - Nonton Iklan
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Settings iklan ──────────────────────────────────────
$adsActive       = (bool)(int)getSetting('ads_is_active', 1);
$maxPerDay       = (int)getSetting('ads_max_per_day', 5);
$cooldownMinutes = (int)getSetting('ads_cooldown_minutes', 30);
$minVip          = (int)getSetting('ads_min_vip_level', 0);

// ── Handle AJAX POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Token tidak valid']);
        exit;
    }

    if ($_POST['action'] === 'watch') {
        $adId = (int)($_POST['ad_id'] ?? 0);

        if (!$adsActive) { echo json_encode(['success'=>false,'message'=>'Fitur iklan sedang nonaktif']); exit; }
        if ((int)($user['vip_level'] ?? 0) < $minVip) { echo json_encode(['success'=>false,'message'=>'Level VIP tidak mencukupi']); exit; }

        // Cek sudah nonton berapa hari ini
        $stmtW = db()->prepare("SELECT COUNT(*) as c FROM ad_watches WHERE user_id=? AND DATE(watched_at)=CURDATE()");
        $stmtW->bind_param('i', $userId);
        $stmtW->execute();
        $watchedToday = (int)$stmtW->get_result()->fetch_assoc()['c'];
        $stmtW->close();

        if ($watchedToday >= $maxPerDay) { echo json_encode(['success'=>false,'message'=>'Kuota harian habis']); exit; }

        // Cek cooldown
        $stmtC = db()->prepare("SELECT MAX(watched_at) as last_watch FROM ad_watches WHERE user_id=?");
        $stmtC->bind_param('i', $userId);
        $stmtC->execute();
        $lastWatch = $stmtC->get_result()->fetch_assoc()['last_watch'];
        $stmtC->close();
        if ($lastWatch && (time() - strtotime($lastWatch)) < $cooldownMinutes * 60) {
            $remaining = $cooldownMinutes * 60 - (time() - strtotime($lastWatch));
            echo json_encode(['success'=>false,'message'=>'Cooldown ' . ceil($remaining/60) . ' menit lagi']); exit;
        }

        // Ambil reward iklan
        $stmtA = db()->prepare("SELECT * FROM ads WHERE id=? AND is_active=1 LIMIT 1");
        $stmtA->bind_param('i', $adId);
        $stmtA->execute();
        $ad = $stmtA->get_result()->fetch_assoc();
        $stmtA->close();
        if (!$ad) { echo json_encode(['success'=>false,'message'=>'Iklan tidak ditemukan']); exit; }

        $reward = (int)$ad['reward_amount'];
        $wallet = $ad['reward_wallet'] ?? 'bonus';

        // Insert ad_watches
        $stmtI = db()->prepare("INSERT INTO ad_watches (user_id, ad_id, reward_amount, wallet_type, watched_at) VALUES (?,?,?,?,NOW())");
        $stmtI->bind_param('iiis', $userId, $adId, $reward, $wallet);
        $stmtI->execute();
        $stmtI->close();

        // Credit wallet
        $col = 'balance_' . $wallet;
        $allowedCols = ['balance_main','balance_profit','balance_bonus','balance_referral'];
        if (!in_array($col, $allowedCols)) $col = 'balance_bonus';
        $stmtU = db()->prepare("UPDATE user_wallets SET $col = $col + ? WHERE user_id=?");
        $stmtU->bind_param('ii', $reward, $userId);
        $stmtU->execute();
        $stmtU->close();

        // Insert transaction
        $desc = 'Reward nonton iklan: ' . htmlspecialchars($ad['title']);
        $stmtT = db()->prepare("INSERT INTO transactions (user_id, type, amount, wallet_type, description, status, created_at) VALUES (?,?,?,?,?,'completed',NOW())");
        $stmtT->bind_param('iiiss', $userId, 'ad_reward', $reward, $wallet, $desc);
        $stmtT->execute();
        $stmtT->close();

        echo json_encode(['success'=>true,'message'=>'Reward dikreditkan!','reward'=>formatRupiah($reward)]);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Action tidak dikenal']);
    exit;
}

// ── Hitung sudah nonton hari ini ────────────────────────
$stmtW = db()->prepare("SELECT COUNT(*) as c FROM ad_watches WHERE user_id=? AND DATE(watched_at)=CURDATE()");
$stmtW->bind_param('i', $userId);
$stmtW->execute();
$watchedToday = (int)$stmtW->get_result()->fetch_assoc()['c'];
$stmtW->close();

// ── Cooldown tersisa ────────────────────────────────────
$stmtC = db()->prepare("SELECT MAX(watched_at) as last_watch FROM ad_watches WHERE user_id=?");
$stmtC->bind_param('i', $userId);
$stmtC->execute();
$lastWatch = $stmtC->get_result()->fetch_assoc()['last_watch'];
$stmtC->close();
$cooldownLeft = 0;
if ($lastWatch) $cooldownLeft = max(0, $cooldownMinutes * 60 - (time() - strtotime($lastWatch)));

// ── Ambil iklan aktif ───────────────────────────────────
$stmtA = db()->prepare("SELECT * FROM ads WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
$stmtA->execute();
$adList = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtA->close();

// ── Riwayat 10 terakhir ─────────────────────────────────
$stmtH = db()->prepare("SELECT aw.*, a.title FROM ad_watches aw LEFT JOIN ads a ON a.id=aw.ad_id WHERE aw.user_id=? ORDER BY aw.watched_at DESC LIMIT 10");
$stmtH->bind_param('i', $userId);
$stmtH->execute();
$history = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtH->close();

$rewardPerAd = (int)getSetting('ads_reward_per_watch', 1000);
$pageTitle = 'Nonton Iklan';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= getSetting('site_name','NOXARA') ?></title>
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">
<style>
.ads-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px}
.ad-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;overflow:hidden;transition:.25s}
.ad-card:hover{transform:translateY(-3px);border-color:var(--cyan);box-shadow:0 8px 32px rgba(0,212,255,.15)}
.ad-card__img{width:100%;height:160px;object-fit:cover;background:rgba(0,212,255,.06)}
.ad-card__body{padding:14px}
.ad-card__title{font-weight:700;font-size:14px;margin-bottom:6px}
.ad-card__reward{color:var(--cyan);font-weight:700;font-size:13px;margin-bottom:12px}
.progress-bar-wrap{background:rgba(255,255,255,.07);border-radius:99px;height:8px;overflow:hidden;margin:8px 0}
.progress-bar-fill{height:100%;background:linear-gradient(90deg,var(--cyan),var(--purple));border-radius:99px;transition:width .5s}
/* Modal */
.nox-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;display:none;align-items:center;justify-content:center}
.nox-modal-overlay.active{display:flex}
.nox-modal{background:var(--bg-card);border-radius:16px;width:90%;max-width:480px;padding:28px;position:relative;border:1px solid var(--border-light)}
.countdown-circle{width:80px;height:80px;border-radius:50%;background:rgba(0,212,255,.1);border:3px solid var(--cyan);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;font-family:'Space Grotesk',sans-serif;color:var(--cyan);margin:16px auto}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">▶️ Nonton Iklan</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Tonton iklan dan dapatkan reward bonus</p>
</div>

<?php if (!$adsActive): ?>
<div style="background:rgba(255,179,0,.1);border:1px solid rgba(255,179,0,.3);border-radius:12px;padding:16px;margin-bottom:24px;text-align:center">
  <div style="font-size:32px;margin-bottom:8px">⚠️</div>
  <div style="font-weight:700">Fitur Nonton Iklan Sementara Nonaktif</div>
  <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">Silakan cek kembali nanti</div>
</div>
<?php else: ?>

<!-- INFO BOX -->
<div style="background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.2);border-radius:12px;padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:20px">
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:24px">💰</span>
    <div><div style="font-size:11px;color:var(--text-secondary)">Reward per iklan</div><div style="font-weight:700;color:var(--cyan)"><?= formatRupiah($rewardPerAd) ?></div></div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:24px">📊</span>
    <div><div style="font-size:11px;color:var(--text-secondary)">Kuota harian</div><div style="font-weight:700"><?= $watchedToday ?>/<?= $maxPerDay ?> iklan</div></div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:24px">⏱️</span>
    <div><div style="font-size:11px;color:var(--text-secondary)">Cooldown</div><div style="font-weight:700"><?= $cooldownMinutes ?> menit</div></div>
  </div>
  <?php if ($cooldownLeft > 0): ?>
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:24px">⏳</span>
    <div><div style="font-size:11px;color:var(--text-secondary)">Cooldown tersisa</div><div style="font-weight:700;color:var(--amber)" id="cooldownTimer"><?= gmdate('i:s', $cooldownLeft) ?></div></div>
  </div>
  <?php endif; ?>
</div>

<!-- PROGRESS BAR -->
<div class="nox-card" style="padding:16px;margin-bottom:24px">
  <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;font-weight:600">
    <span>Progress Hari Ini</span>
    <span><?= $watchedToday ?>/<?= $maxPerDay ?></span>
  </div>
  <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $maxPerDay > 0 ? round($watchedToday/$maxPerDay*100) : 0 ?>%"></div></div>
  <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Sisa <?= max(0,$maxPerDay-$watchedToday) ?> iklan lagi hari ini</div>
</div>

<?php if ($watchedToday >= $maxPerDay): ?>
<div style="background:rgba(0,230,118,.08);border:1px solid rgba(0,230,118,.2);border-radius:12px;padding:24px;text-align:center;margin-bottom:24px">
  <div style="font-size:48px;margin-bottom:10px">🎉</div>
  <div style="font-weight:700;font-size:16px">Kuota Hari Ini Habis!</div>
  <div style="font-size:13px;color:var(--text-secondary);margin-top:6px">Kamu sudah nonton <?= $maxPerDay ?> iklan hari ini. Kembali besok untuk melanjutkan.</div>
</div>
<?php elseif (empty($adList)): ?>
<div style="text-align:center;padding:48px;color:var(--text-secondary)">
  <div style="font-size:48px;margin-bottom:12px">📺</div>
  <div style="font-weight:600;margin-bottom:4px">Tidak ada iklan tersedia</div>
  <div style="font-size:13px">Cek kembali nanti</div>
</div>
<?php else: ?>
<!-- ADS GRID -->
<div style="margin-bottom:8px;font-weight:700;font-size:15px">Iklan Tersedia</div>
<div class="ads-grid">
  <?php foreach ($adList as $ad): ?>
  <div class="ad-card">
    <?php if (!empty($ad['image'])): ?>
      <img src="<?= UPLOADS_URL ?>/ads/<?= htmlspecialchars($ad['image']) ?>" alt="" class="ad-card__img" onerror="this.style.display='none'">
    <?php else: ?>
      <div class="ad-card__img" style="display:flex;align-items:center;justify-content:center;font-size:48px">📺</div>
    <?php endif; ?>
    <div class="ad-card__body">
      <div class="ad-card__title"><?= htmlspecialchars($ad['title']) ?></div>
      <?php if (!empty($ad['description'])): ?>
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px"><?= htmlspecialchars($ad['description']) ?></div>
      <?php endif; ?>
      <div class="ad-card__reward">💰 Reward: <?= formatRupiah((int)$ad['reward_amount']) ?></div>
      <button class="nox-btn nox-btn--primary" style="width:100%"
        <?= ($cooldownLeft > 0) ? 'disabled title="Cooldown aktif"' : '' ?>
        onclick="openAdModal(<?= (int)$ad['id'] ?>, '<?= htmlspecialchars(addslashes($ad['title'])) ?>', <?= (int)($ad['timer_seconds'] ?? 15) ?>, '<?= htmlspecialchars($ad['image'] ?? '') ?>')">
        <?= $cooldownLeft > 0 ? '⏳ Cooldown...' : '▶ Tonton Iklan' ?>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- RIWAYAT -->
<?php if (!empty($history)): ?>
<div style="margin-top:32px">
  <h2 style="font-size:16px;font-weight:700;margin-bottom:14px">🕐 Riwayat Nonton (10 Terakhir)</h2>
  <div class="nox-card" style="padding:0;overflow:hidden">
    <?php foreach ($history as $h): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid rgba(30,42,69,.4)">
      <div style="width:36px;height:36px;border-radius:10px;background:rgba(0,212,255,.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">▶️</div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($h['title'] ?? 'Iklan') ?></div>
        <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars(date('d M Y H:i', strtotime($h['watched_at']))) ?></div>
      </div>
      <div style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:var(--green)">+<?= formatRupiah((int)$h['reward_amount']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- MODAL NONTON IKLAN -->
<div class="nox-modal-overlay" id="adModal">
  <div class="nox-modal">
    <h3 style="margin:0 0 16px;font-size:18px;font-weight:700" id="adModalTitle">Nonton Iklan</h3>
    <div id="adModalImg" style="text-align:center;margin-bottom:16px"></div>
    <div style="text-align:center">
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">Tonton hingga selesai untuk klaim reward</div>
      <div class="countdown-circle" id="adCountdown">0</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:16px">detik tersisa</div>
    </div>
    <button class="nox-btn nox-btn--primary" style="width:100%" id="claimBtn" disabled onclick="claimAdReward()">
      ⏳ Menunggu...
    </button>
    <button class="nox-btn nox-btn--outline" style="width:100%;margin-top:8px" onclick="closeAdModal()">Batal</button>
  </div>
</div>

<script>
let currentAdId = 0, countdownInterval = null;

function openAdModal(id, title, seconds, img) {
  currentAdId = id;
  document.getElementById('adModalTitle').textContent = title;
  const imgEl = document.getElementById('adModalImg');
  imgEl.innerHTML = img
    ? `<img src="<?= UPLOADS_URL ?>/ads/${img}" style="max-width:100%;max-height:200px;border-radius:8px;object-fit:cover">`
    : '<div style="font-size:64px">📺</div>';
  document.getElementById('adModal').classList.add('active');
  const claimBtn = document.getElementById('claimBtn');
  claimBtn.disabled = true; claimBtn.textContent = '⏳ Menunggu...';
  let s = seconds;
  document.getElementById('adCountdown').textContent = s;
  clearInterval(countdownInterval);
  countdownInterval = setInterval(() => {
    s--;
    document.getElementById('adCountdown').textContent = s;
    if (s <= 0) {
      clearInterval(countdownInterval);
      claimBtn.disabled = false;
      claimBtn.textContent = '✅ Klaim Reward';
      document.getElementById('adCountdown').style.borderColor = 'var(--green)';
      document.getElementById('adCountdown').style.color = 'var(--green)';
    }
  }, 1000);
}

function closeAdModal() {
  clearInterval(countdownInterval);
  document.getElementById('adModal').classList.remove('active');
}

function claimAdReward() {
  const btn = document.getElementById('claimBtn');
  btn.disabled = true; btn.textContent = 'Memproses...';
  const fd = new FormData();
  fd.append('action','watch'); fd.append('ad_id', currentAdId);
  fd.append('csrf_token','<?= generateCsrfToken() ?>');
  fetch(location.href,{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if (d.success) {
        closeAdModal();
        alert('🎉 Berhasil! Reward ' + d.reward + ' dikreditkan ke wallet Anda!');
        location.reload();
      } else {
        alert('❌ ' + d.message);
        btn.disabled = false; btn.textContent = '✅ Klaim Reward';
      }
    });
}

// Cooldown timer
<?php if ($cooldownLeft > 0): ?>
let cdLeft = <?= $cooldownLeft ?>;
const cdEl = document.getElementById('cooldownTimer');
const cdInt = setInterval(() => {
  cdLeft--;
  if (cdLeft <= 0) { clearInterval(cdInt); location.reload(); return; }
  const m = String(Math.floor(cdLeft/60)).padStart(2,'0');
  const s = String(cdLeft%60).padStart(2,'0');
  if (cdEl) cdEl.textContent = m + ':' + s;
}, 1000);
<?php endif; ?>
</script>
</body></html>
