<?php
/**
 * NOXARA - Hadiah Harian
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Settings ────────────────────────────────────────────
$isActive       = (bool)(int)getSetting('daily_reward_active', 1);
$resetHour      = (int)getSetting('daily_reward_reset_hour', 0);
$rewardWallet   = getSetting('daily_reward_wallet', 'bonus');

// ── Cek sudah klaim hari ini ────────────────────────────
$today = date('Y-m-d');
$stmtC = db()->prepare("SELECT * FROM daily_reward_claims WHERE user_id=? AND claim_date=? LIMIT 1");
$stmtC->bind_param('is', $userId, $today);
$stmtC->execute();
$todayClaim = $stmtC->get_result()->fetch_assoc();
$stmtC->close();
$alreadyClaimed = !empty($todayClaim);

// ── Hitung countdown reset ──────────────────────────────
$now        = time();
$resetToday = mktime($resetHour, 0, 0, date('n'), date('j'), date('Y'));
$resetNext  = $resetToday <= $now ? $resetToday + 86400 : $resetToday;
$countdownSecs = $resetNext - $now;

// ── Handle AJAX POST claim ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Token tidak valid']); exit;
    }
    if ($_POST['action'] === 'claim') {
        if (!$isActive) { echo json_encode(['success'=>false,'message'=>'Fitur tidak aktif']); exit; }
        if ($alreadyClaimed) { echo json_encode(['success'=>false,'message'=>'Sudah diklaim hari ini']); exit; }

        // Ambil hadiah dari DB berdasarkan probabilitas
        $stmtP = db()->prepare("SELECT * FROM daily_reward_items WHERE is_active=1 ORDER BY RAND() * (1/probability) ASC LIMIT 1");
        $stmtP->execute();
        $prize = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();

        if (!$prize) {
            // Default reward jika tidak ada di DB
            $prize = ['name'=>'Bonus Harian','reward_type'=>'cash','reward_value'=>2000,'icon'=>'🎁'];
        }

        $rewardValue = (int)$prize['reward_value'];
        $rewardType  = $prize['reward_type'] ?? 'cash';
        $rewardName  = $prize['name'] ?? 'Hadiah Harian';

        // Insert klaim
        $stmtI = db()->prepare("INSERT INTO daily_reward_claims (user_id, claim_date, reward_item_id, reward_name, reward_type, reward_value, wallet_type) VALUES (?,?,?,?,?,?,?)");
        $itemId = (int)($prize['id'] ?? 0);
        $stmtI->bind_param('ississs', $userId, $today, $itemId, $rewardName, $rewardType, $rewardValue, $rewardWallet);
        $stmtI->execute();
        $stmtI->close();

        // Kredit wallet
        if ($rewardType === 'cash') {
            $col = 'balance_' . $rewardWallet;
            $allowed = ['balance_main','balance_profit','balance_bonus','balance_referral'];
            if (!in_array($col,$allowed)) $col = 'balance_bonus';
            $stmtU = db()->prepare("UPDATE user_wallets SET $col=$col+? WHERE user_id=?");
            $stmtU->bind_param('ii', $rewardValue, $userId);
            $stmtU->execute();
            $stmtU->close();
            // Transaksi
            $desc = 'Hadiah harian: ' . $rewardName;
            $stmtT = db()->prepare("INSERT INTO transactions (user_id,type,amount,wallet_type,description,status,created_at) VALUES (?,?,?,?,?,'completed',NOW())");
            $stmtT->bind_param('iiiss', $userId, 'daily_reward', $rewardValue, $rewardWallet, $desc);
            $stmtT->execute();
            $stmtT->close();
        }

        echo json_encode(['success'=>true,'message'=>'Hadiah berhasil diklaim!','reward_name'=>$rewardName,'reward_value'=>formatRupiah($rewardValue),'reward_type'=>$rewardType,'icon'=>$prize['icon']??'🎁']);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Action tidak dikenal']); exit;
}

// ── Riwayat 7 hari terakhir ─────────────────────────────
$stmtH = db()->prepare("SELECT * FROM daily_reward_claims WHERE user_id=? ORDER BY claim_date DESC LIMIT 7");
$stmtH->bind_param('i', $userId);
$stmtH->execute();
$claimHistory = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtH->close();

// ── Daftar hadiah possible ──────────────────────────────
$stmtItems = db()->prepare("SELECT * FROM daily_reward_items WHERE is_active=1 ORDER BY probability DESC");
$stmtItems->execute();
$rewardItems = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

$pageTitle = 'Hadiah Harian';
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
.gift-box-wrap{display:flex;flex-direction:column;align-items:center;padding:40px 20px;margin-bottom:24px}
.gift-box{font-size:96px;cursor:pointer;transition:.3s;user-select:none;filter:drop-shadow(0 0 24px rgba(0,212,255,.4))}
.gift-box:hover{transform:scale(1.05)}
.gift-box.shake{animation:giftShake .5s ease-in-out}
@keyframes giftShake{0%,100%{transform:rotate(0)}15%{transform:rotate(-8deg)}30%{transform:rotate(8deg)}45%{transform:rotate(-5deg)}60%{transform:rotate(5deg)}75%{transform:rotate(-3deg)}90%{transform:rotate(3deg)}}
.gift-box.open{animation:giftOpen .6s ease-out forwards}
@keyframes giftOpen{0%{transform:scale(1)}30%{transform:scale(1.2) rotate(-5deg)}60%{transform:scale(0.95)}100%{transform:scale(1) rotate(0)}}
.reward-reveal{background:linear-gradient(135deg,rgba(0,212,255,.1),rgba(123,47,255,.1));border:2px solid var(--cyan);border-radius:16px;padding:24px;text-align:center;display:none}
.reward-reveal.show{display:block;animation:revealAnim .5s ease-out}
@keyframes revealAnim{0%{opacity:0;transform:scale(.8)}100%{opacity:1;transform:scale(1)}}
.countdown-box{background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.2);border-radius:12px;padding:16px;text-align:center;margin-bottom:24px}
.prize-table{width:100%;border-collapse:collapse}
.prize-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);padding:8px 12px;border-bottom:1px solid var(--border-light)}
.prize-table td{padding:10px 12px;border-bottom:1px solid rgba(30,42,69,.4);font-size:13px}
.prob-bar{height:6px;background:var(--cyan);border-radius:99px;display:inline-block;min-width:4px}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">🎁 Hadiah Harian</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Klaim hadiah gratis setiap hari!</p>
</div>

<?php if (!$isActive): ?>
<div style="background:rgba(255,179,0,.1);border:1px solid rgba(255,179,0,.3);border-radius:12px;padding:24px;text-align:center;margin-bottom:24px">
  <div style="font-size:48px;margin-bottom:8px">⚠️</div>
  <div style="font-weight:700">Fitur Hadiah Harian Sementara Nonaktif</div>
</div>
<?php else: ?>

<!-- STATUS KLAIM -->
<div class="countdown-box">
  <?php if ($alreadyClaimed): ?>
    <div style="font-size:32px;margin-bottom:8px">✅</div>
    <div style="font-weight:700;font-size:16px;color:var(--green)">Sudah Diklaim Hari Ini!</div>
    <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">Reset berikutnya dalam:</div>
    <div style="font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;color:var(--cyan);margin-top:6px" id="resetCountdown"><?= gmdate('H:i:s', $countdownSecs) ?></div>
  <?php else: ?>
    <div style="font-size:13px;color:var(--text-secondary)">Klaim reset dalam:</div>
    <div style="font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;color:var(--cyan);margin-top:4px" id="resetCountdown"><?= gmdate('H:i:s', $countdownSecs) ?></div>
    <div style="font-size:13px;color:var(--green);font-weight:600;margin-top:6px">🎁 Hadiahmu menunggu! Klik kotak di bawah</div>
  <?php endif; ?>
</div>

<!-- GIFT BOX -->
<div class="gift-box-wrap">
  <?php if ($alreadyClaimed): ?>
    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">Hadiah hari ini sudah dibuka:</div>
    <div class="reward-reveal show">
      <div style="font-size:48px;margin-bottom:12px"><?= htmlspecialchars($todayClaim['reward_name'] ? '🎉' : '🎁') ?></div>
      <div style="font-size:20px;font-weight:800;color:var(--cyan)"><?= htmlspecialchars($todayClaim['reward_name'] ?? 'Hadiah Harian') ?></div>
      <div style="font-size:28px;font-weight:700;color:var(--green);margin-top:6px"><?= formatRupiah((int)($todayClaim['reward_value'] ?? 0)) ?></div>
    </div>
  <?php else: ?>
    <div class="gift-box" id="giftBox" onclick="claimReward()">🎁</div>
    <div style="font-size:13px;color:var(--text-secondary);margin-top:12px">Klik kotak hadiah untuk klaim</div>
  <?php endif; ?>
  <div class="reward-reveal" id="rewardReveal"></div>
</div>

<?php if (!$alreadyClaimed): ?>
<div style="text-align:center;margin-bottom:32px">
  <button class="nox-btn nox-btn--primary" style="padding:14px 40px;font-size:16px" id="claimBtn" onclick="claimReward()">
    🎁 Klaim Hadiah Sekarang
  </button>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- TABEL KEMUNGKINAN HADIAH -->
<?php if (!empty($rewardItems)): ?>
<div style="margin-bottom:32px">
  <h2 style="font-size:16px;font-weight:700;margin-bottom:14px">📊 Daftar Kemungkinan Hadiah</h2>
  <div class="nox-card" style="padding:0;overflow:hidden">
    <table class="prize-table">
      <thead><tr><th>Hadiah</th><th>Nilai</th><th>Peluang</th><th>Visual</th></tr></thead>
      <tbody>
      <?php foreach ($rewardItems as $item): ?>
        <tr>
          <td><span style="font-size:18px"><?= htmlspecialchars($item['icon'] ?? '🎁') ?></span> <?= htmlspecialchars($item['name']) ?></td>
          <td style="font-weight:700;color:var(--cyan)"><?= formatRupiah((int)$item['reward_value']) ?></td>
          <td style="font-weight:600"><?= number_format((float)$item['probability'], 1) ?>%</td>
          <td><span class="prob-bar" style="width:<?= min(100,(float)$item['probability']*2) ?>px"></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- RIWAYAT KLAIM -->
<?php if (!empty($claimHistory)): ?>
<div>
  <h2 style="font-size:16px;font-weight:700;margin-bottom:14px">🕐 Riwayat Klaim (7 Hari)</h2>
  <div class="nox-card" style="padding:0;overflow:hidden">
    <?php foreach ($claimHistory as $h): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid rgba(30,42,69,.4)">
      <div style="width:36px;height:36px;border-radius:10px;background:rgba(0,212,255,.1);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">🎁</div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($h['reward_name'] ?? 'Hadiah Harian') ?></div>
        <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars(date('d M Y', strtotime($h['claim_date']))) ?></div>
      </div>
      <div style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:var(--green)">+<?= formatRupiah((int)$h['reward_value']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// Countdown timer
let cdSecs = <?= $countdownSecs ?>;
const cdEl = document.getElementById('resetCountdown');
setInterval(() => {
  cdSecs--;
  if (cdSecs <= 0) { location.reload(); return; }
  const h = String(Math.floor(cdSecs/3600)).padStart(2,'0');
  const m = String(Math.floor((cdSecs%3600)/60)).padStart(2,'0');
  const s = String(cdSecs%60).padStart(2,'0');
  if (cdEl) cdEl.textContent = h+':'+m+':'+s;
}, 1000);

let claimed = false;
function claimReward() {
  if (claimed) return;
  const giftBox = document.getElementById('giftBox');
  const claimBtn = document.getElementById('claimBtn');
  if (giftBox) { giftBox.classList.add('shake'); setTimeout(()=>giftBox.classList.remove('shake'),500); }
  if (claimBtn) { claimBtn.disabled = true; claimBtn.textContent = 'Membuka...'; }

  const fd = new FormData();
  fd.append('action','claim');
  fd.append('csrf_token','<?= generateCsrfToken() ?>');
  fetch(location.href,{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if (d.success) {
        claimed = true;
        if (giftBox) { giftBox.classList.add('open'); setTimeout(()=>{giftBox.style.display='none'},600); }
        const rev = document.getElementById('rewardReveal');
        if (rev) {
          rev.innerHTML = `<div style="font-size:48px;margin-bottom:12px">${d.icon||'🎉'}</div>
            <div style="font-size:20px;font-weight:800;color:var(--cyan)">${d.reward_name}</div>
            <div style="font-size:28px;font-weight:700;color:var(--green);margin-top:6px">${d.reward_value}</div>
            <div style="font-size:13px;color:var(--text-secondary);margin-top:8px">Dikreditkan ke wallet kamu!</div>`;
          rev.classList.add('show');
        }
        if (claimBtn) { claimBtn.textContent = '✅ Berhasil Diklaim!'; }
        setTimeout(()=>location.reload(), 3000);
      } else {
        alert('❌ ' + d.message);
        if (claimBtn) { claimBtn.disabled = false; claimBtn.textContent = '🎁 Klaim Hadiah Sekarang'; }
      }
    });
}
</script>
</body></html>
