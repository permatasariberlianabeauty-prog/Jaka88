<?php
/**
 * NOXARA - Misi
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Handle POST klaim misi ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Token tidak valid']); exit;
    }
    if ($_POST['action'] === 'claim_mission') {
        $missionId = (int)($_POST['mission_id'] ?? 0);
        // Ambil user_mission
        $stmtUM = db()->prepare("SELECT um.*, m.reward_amount, m.reward_wallet, m.title FROM user_missions um JOIN missions m ON m.id=um.mission_id WHERE um.user_id=? AND um.mission_id=? LIMIT 1");
        $stmtUM->bind_param('ii', $userId, $missionId);
        $stmtUM->execute();
        $um = $stmtUM->get_result()->fetch_assoc();
        $stmtUM->close();

        if (!$um) { echo json_encode(['success'=>false,'message'=>'Misi tidak ditemukan']); exit; }
        if (!$um['is_completed']) { echo json_encode(['success'=>false,'message'=>'Misi belum selesai']); exit; }
        if ($um['is_claimed']) { echo json_encode(['success'=>false,'message'=>'Reward sudah diklaim']); exit; }

        $reward = (int)$um['reward_amount'];
        $wallet = $um['reward_wallet'] ?? 'bonus';
        $col    = 'balance_' . $wallet;
        $allowed = ['balance_main','balance_profit','balance_bonus','balance_referral'];
        if (!in_array($col,$allowed)) $col = 'balance_bonus';

        // Update is_claimed
        $stmtCl = db()->prepare("UPDATE user_missions SET is_claimed=1, claimed_at=NOW() WHERE user_id=? AND mission_id=?");
        $stmtCl->bind_param('ii', $userId, $missionId);
        $stmtCl->execute();
        $stmtCl->close();

        // Kredit wallet
        $stmtW = db()->prepare("UPDATE user_wallets SET $col=$col+? WHERE user_id=?");
        $stmtW->bind_param('ii', $reward, $userId);
        $stmtW->execute();
        $stmtW->close();

        // Transaksi
        $desc = 'Reward misi: ' . ($um['title'] ?? 'Misi');
        $stmtT = db()->prepare("INSERT INTO transactions (user_id,type,amount,wallet_type,description,status,created_at) VALUES (?,?,?,?,?,'completed',NOW())");
        $stmtT->bind_param('iiiss', $userId, 'mission_reward', $reward, $wallet, $desc);
        $stmtT->execute();
        $stmtT->close();

        echo json_encode(['success'=>true,'message'=>'Reward berhasil diklaim!','reward'=>formatRupiah($reward),'title'=>$um['title']]);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Action tidak dikenal']); exit;
}

// ── Reset daily missions yang expired ───────────────────
$stmtReset = db()->prepare("UPDATE user_missions um JOIN missions m ON m.id=um.mission_id SET um.current_count=0, um.is_completed=0, um.is_claimed=0, um.reset_date=DATE_ADD(CURDATE(),INTERVAL 1 DAY) WHERE um.user_id=? AND m.type='daily' AND um.reset_date<=CURDATE()");
$stmtReset->bind_param('i', $userId);
$stmtReset->execute();
$stmtReset->close();

// ── Ambil semua misi dengan progress ───────────────────
$stmtM = db()->prepare("
    SELECT m.*, COALESCE(um.current_count,0) as current_count,
           COALESCE(um.is_completed,0) as is_completed,
           COALESCE(um.is_claimed,0) as is_claimed,
           um.claimed_at
    FROM missions m
    LEFT JOIN user_missions um ON um.mission_id=m.id AND um.user_id=?
    WHERE m.is_active=1
    ORDER BY m.type, m.sort_order ASC
");
$stmtM->bind_param('i', $userId);
$stmtM->execute();
$allMissions = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtM->close();

$dailyMissions   = array_filter($allMissions, fn($m)=>$m['type']==='daily');
$weeklyMissions  = array_filter($allMissions, fn($m)=>$m['type']==='weekly');
$totalMissions   = array_filter($allMissions, fn($m)=>$m['type']==='total' || $m['type']==='milestone');

$activeTab = in_array($_GET['tab'] ?? 'daily', ['daily','weekly','milestone']) ? ($_GET['tab'] ?? 'daily') : 'daily';
$pageTitle = 'Misi';
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
.mission-tabs{display:flex;gap:4px;background:var(--bg-card);border-radius:12px;padding:4px;margin-bottom:24px;border:1px solid var(--border-light)}
.mission-tab{flex:1;padding:10px;text-align:center;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;color:var(--text-secondary);transition:.2s}
.mission-tab.active{background:var(--cyan);color:#000}
.mission-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:18px;margin-bottom:12px;display:flex;align-items:flex-start;gap:14px;transition:.2s}
.mission-card:hover{border-color:rgba(0,212,255,.3)}
.mission-card__icon{font-size:28px;width:48px;height:48px;border-radius:12px;background:rgba(0,212,255,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mission-card__body{flex:1;min-width:0}
.mission-card__title{font-weight:700;font-size:14px;margin-bottom:4px}
.mission-card__desc{font-size:12px;color:var(--text-secondary);margin-bottom:10px}
.mission-progress-bar{height:6px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden;margin-bottom:6px}
.mission-progress-fill{height:100%;border-radius:99px;transition:width .8s}
.reward-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(0,212,255,.1);color:var(--cyan);border-radius:99px;padding:3px 10px;font-size:11px;font-weight:700}
/* Popup */
.mission-popup{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;display:none;align-items:center;justify-content:center}
.mission-popup.active{display:flex;animation:fadeIn .3s}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.mission-popup__box{background:var(--bg-card);border-radius:20px;padding:40px 32px;text-align:center;max-width:360px;width:90%;border:2px solid var(--cyan);box-shadow:0 0 60px rgba(0,212,255,.2)}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">🎯 Misi</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Selesaikan misi dan dapatkan reward ekstra</p>
</div>

<!-- TABS -->
<div class="mission-tabs">
  <a href="?tab=daily"     class="mission-tab <?= $activeTab==='daily'?'active':'' ?>">📅 Harian</a>
  <a href="?tab=weekly"    class="mission-tab <?= $activeTab==='weekly'?'active':'' ?>">📆 Mingguan</a>
  <a href="?tab=milestone" class="mission-tab <?= $activeTab==='milestone'?'active':'' ?>">🏆 Milestone</a>
</div>

<?php
$currentMissions = $activeTab === 'daily' ? $dailyMissions : ($activeTab === 'weekly' ? $weeklyMissions : $totalMissions);
if (empty($currentMissions)):
?>
<div style="text-align:center;padding:48px;color:var(--text-secondary)">
  <div style="font-size:48px;margin-bottom:12px">🎯</div>
  <div style="font-weight:600;margin-bottom:4px">Tidak ada misi tersedia</div>
  <div style="font-size:13px">Cek kembali nanti</div>
</div>
<?php else: ?>
<?php foreach ($currentMissions as $m):
  $cur    = (int)$m['current_count'];
  $target = (int)$m['target_count'];
  $pct    = $target > 0 ? min(100, round($cur/$target*100)) : 0;
  $done   = (bool)$m['is_completed'];
  $claimed = (bool)$m['is_claimed'];
  if ($claimed) $barColor = 'var(--green)';
  elseif ($done) $barColor = 'var(--cyan)';
  else $barColor = 'rgba(0,212,255,.5)';
?>
<div class="mission-card">
  <div class="mission-card__icon"><?= htmlspecialchars($m['icon'] ?? '🎯') ?></div>
  <div class="mission-card__body">
    <div class="mission-card__title"><?= htmlspecialchars($m['title']) ?></div>
    <div class="mission-card__desc"><?= htmlspecialchars($m['description'] ?? '') ?></div>
    <div class="mission-progress-bar">
      <div class="mission-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-secondary)">
      <span><?= $cur ?>/<?= $target ?></span>
      <span class="reward-badge">💰 <?= formatRupiah((int)$m['reward_amount']) ?></span>
    </div>
  </div>
  <div style="flex-shrink:0;min-width:90px;text-align:right">
    <?php if ($claimed): ?>
      <span style="font-size:11px;background:rgba(0,230,118,.1);color:var(--green);padding:5px 12px;border-radius:99px;font-weight:700;white-space:nowrap">✅ Selesai</span>
    <?php elseif ($done): ?>
      <button class="nox-btn nox-btn--primary nox-btn--sm claim-btn" data-id="<?= (int)$m['id'] ?>" data-title="<?= htmlspecialchars($m['title']) ?>" data-reward="<?= formatRupiah((int)$m['reward_amount']) ?>">
        🎁 Klaim!
      </button>
    <?php elseif ($pct > 0): ?>
      <span style="font-size:12px;color:var(--cyan);font-weight:600"><?= $pct ?>%</span>
    <?php else: ?>
      <span style="font-size:11px;color:var(--text-disabled)">Belum Mulai</span>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- POPUP MISI SELESAI -->
<div class="mission-popup" id="missionPopup">
  <div class="mission-popup__box">
    <div style="font-size:64px;margin-bottom:16px">🎉</div>
    <div style="font-size:22px;font-weight:800;margin-bottom:8px">Misi Selesai!</div>
    <div style="font-size:16px;color:var(--cyan);font-weight:700" id="popupTitle"></div>
    <div style="font-size:28px;font-weight:800;color:var(--green);margin:12px 0" id="popupReward"></div>
    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">Reward dikreditkan ke wallet Anda!</div>
    <button class="nox-btn nox-btn--primary" onclick="document.getElementById('missionPopup').classList.remove('active');location.reload()">Tutup</button>
  </div>
</div>

<script>
document.querySelectorAll('.claim-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const id = this.dataset.id;
    const title = this.dataset.title;
    const reward = this.dataset.reward;
    this.disabled = true; this.textContent = '...';
    const fd = new FormData();
    fd.append('action','claim_mission');
    fd.append('mission_id', id);
    fd.append('csrf_token','<?= generateCsrfToken() ?>');
    fetch(location.href,{method:'POST',body:fd})
      .then(r=>r.json()).then(d=>{
        if (d.success) {
          document.getElementById('popupTitle').textContent = d.title;
          document.getElementById('popupReward').textContent = d.reward;
          document.getElementById('missionPopup').classList.add('active');
        } else {
          alert('❌ ' + d.message);
          this.disabled = false; this.textContent = '🎁 Klaim!';
        }
      });
  });
});
</script>
</body></html>
