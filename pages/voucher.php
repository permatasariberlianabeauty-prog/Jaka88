<?php
/**
 * NOXARA - Voucher & Kode VIP
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];
$errors = []; $success = '';

// ── Handle POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Token tidak valid']); exit; }

    if ($_POST['action'] === 'use_voucher') {
        $uvId = (int)($_POST['user_voucher_id'] ?? 0);
        $stmtV = db()->prepare("SELECT uv.*, v.name, v.discount_type, v.discount_value, v.min_transaction FROM user_vouchers uv JOIN vouchers v ON v.id=uv.voucher_id WHERE uv.id=? AND uv.user_id=? AND uv.status='active' AND v.valid_until >= CURDATE() LIMIT 1");
        $stmtV->bind_param('ii', $uvId, $userId);
        $stmtV->execute();
        $uv = $stmtV->get_result()->fetch_assoc();
        $stmtV->close();

        if (!$uv) { echo json_encode(['success'=>false,'message'=>'Voucher tidak valid atau sudah digunakan']); exit; }

        $stmtU = db()->prepare("UPDATE user_vouchers SET status='used', used_at=NOW() WHERE id=? AND user_id=?");
        $stmtU->bind_param('ii', $uvId, $userId);
        $stmtU->execute(); $stmtU->close();

        echo json_encode(['success'=>true,'message'=>'Voucher berhasil digunakan!','name'=>$uv['name']]);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Action tidak dikenal']); exit;
}

$activeTab = in_array($_GET['tab'] ?? 'voucher', ['voucher','vip_code']) ? ($_GET['tab'] ?? 'voucher') : 'voucher';

// ── Ambil user vouchers ──────────────────────────────────
$stmtUV = db()->prepare("
    SELECT uv.*, v.name, v.code, v.discount_type, v.discount_value, v.min_transaction, v.valid_until, v.description
    FROM user_vouchers uv
    JOIN vouchers v ON v.id = uv.voucher_id
    WHERE uv.user_id = ?
    ORDER BY uv.status ASC, v.valid_until ASC
");
$stmtUV->bind_param('i', $userId);
$stmtUV->execute();
$userVouchers = $stmtUV->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtUV->close();

// ── Ambil semua level VIP ────────────────────────────────
$stmtVL = db()->prepare("SELECT * FROM vip_levels ORDER BY level ASC");
$stmtVL->execute();
$vipLevels = $stmtVL->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtVL->close();

// ── Ambil VIP codes yang sudah diraih user ───────────────
$userVipLevel = (int)($user['vip_level'] ?? 0);
$userTotalDeposit = 0;
$stmtTD = db()->prepare("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE user_id=? AND status='approved'");
$stmtTD->bind_param('i', $userId);
$stmtTD->execute();
$userTotalDeposit = (int)$stmtTD->get_result()->fetch_assoc()['total'];
$stmtTD->close();

// Ambil vip_codes user
$stmtVC = db()->prepare("SELECT vc.*, vl.level, vl.badge_label, vl.color FROM vip_codes vc JOIN vip_levels vl ON vl.level=vc.vip_level WHERE vc.user_id=?");
$stmtVC->bind_param('i', $userId);
$stmtVC->execute();
$userVipCodes = [];
foreach ($stmtVC->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $userVipCodes[$r['vip_level']] = $r;
}
$stmtVC->close();

$pageTitle = 'Voucher & VIP Saya';
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
.voucher-tabs{display:flex;gap:4px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:4px;margin-bottom:24px}
.voucher-tab{flex:1;padding:10px;text-align:center;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;color:var(--text-secondary);transition:.2s}
.voucher-tab.active{background:var(--cyan);color:#000}
.voucher-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;margin-bottom:24px}
.voucher-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:20px;position:relative;overflow:hidden;transition:.2s}
.voucher-card:hover{border-color:rgba(0,212,255,.3)}
.voucher-card--used{opacity:.5}
.voucher-card--expired{opacity:.5}
.voucher-ribbon{position:absolute;top:12px;right:-24px;background:var(--green);color:#000;font-size:10px;font-weight:800;padding:3px 32px;transform:rotate(35deg)}
.voucher-card--used .voucher-ribbon{background:var(--text-secondary)}
.voucher-card--expired .voucher-ribbon{background:var(--red)}
.voucher-code{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:800;letter-spacing:.1em;color:var(--cyan);margin:8px 0}
.voucher-discount{font-size:24px;font-weight:800;color:var(--green)}
.vip-code-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:22px;margin-bottom:14px;display:flex;align-items:center;gap:16px}
.vip-code-card--locked{opacity:.6}
.vip-level-badge{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0}
.vip-code-value{font-family:'Orbitron',sans-serif;font-size:20px;font-weight:700;letter-spacing:.1em;color:var(--cyan)}
.vip-code-blur{filter:blur(6px);user-select:none;pointer-events:none}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">🎫 Voucher & VIP Saya</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Kelola voucher dan kode VIP Anda</p>
</div>

<!-- TABS -->
<div class="voucher-tabs">
  <a href="?tab=voucher"  class="voucher-tab <?= $activeTab==='voucher'?'active':'' ?>">🎫 Voucher Saya (<?= count($userVouchers) ?>)</a>
  <a href="?tab=vip_code" class="voucher-tab <?= $activeTab==='vip_code'?'active':'' ?>">👑 Kode VIP</a>
</div>

<?php if ($activeTab === 'voucher'): ?>
<!-- TAB VOUCHER -->
<?php if (empty($userVouchers)): ?>
<div style="text-align:center;padding:48px;color:var(--text-secondary)">
  <div style="font-size:56px;margin-bottom:12px">🎫</div>
  <div style="font-weight:600;margin-bottom:4px">Belum ada voucher</div>
  <div style="font-size:13px">Voucher Anda akan muncul di sini</div>
</div>
<?php else: ?>
<div class="voucher-grid">
  <?php foreach ($userVouchers as $uv):
    $now     = date('Y-m-d');
    $isExp   = $uv['valid_until'] < $now;
    $isUsed  = $uv['status'] === 'used';
    $status  = $isUsed ? 'Digunakan' : ($isExp ? 'Expired' : 'Aktif');
    $cls     = $isUsed ? 'voucher-card--used' : ($isExp ? 'voucher-card--expired' : '');
    $discStr = $uv['discount_type'] === 'percent'
        ? number_format((float)$uv['discount_value'], 0) . '%'
        : formatRupiah((int)$uv['discount_value']);
  ?>
  <div class="voucher-card <?= $cls ?>">
    <div class="voucher-ribbon"><?= $status ?></div>
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);margin-bottom:4px"><?= htmlspecialchars($uv['discount_type'] === 'percent' ? 'Diskon Persen' : 'Potongan Harga') ?></div>
    <div class="voucher-discount"><?= $discStr ?> OFF</div>
    <div class="voucher-code"><?= htmlspecialchars($uv['code']) ?></div>
    <div style="font-size:13px;font-weight:600;margin-bottom:4px"><?= htmlspecialchars($uv['name']) ?></div>
    <?php if (!empty($uv['description'])): ?>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px"><?= htmlspecialchars($uv['description']) ?></div>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--text-secondary);margin-bottom:12px">
      <?php if ((int)$uv['min_transaction'] > 0): ?>Min. transaksi: <?= formatRupiah((int)$uv['min_transaction']) ?><br><?php endif; ?>
      Berlaku hingga: <?= htmlspecialchars(date('d M Y', strtotime($uv['valid_until']))) ?>
    </div>
    <?php if (!$isUsed && !$isExp): ?>
    <button class="nox-btn nox-btn--primary nox-btn--sm use-voucher-btn" data-id="<?= (int)$uv['id'] ?>" data-code="<?= htmlspecialchars($uv['code']) ?>">
      ✅ Gunakan
    </button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- TAB KODE VIP -->
<div style="background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.2);border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:13px">
  <span style="font-weight:700">Level VIP Anda:</span>
  <span style="color:var(--cyan);font-weight:800;margin-left:6px">VIP <?= $userVipLevel ?></span>
  &nbsp;·&nbsp;
  <span>Total Deposit: <?= formatRupiah($userTotalDeposit) ?></span>
</div>

<?php
$vipIcons = [0=>'🔵',1=>'🥉',2=>'🥈',3=>'🥇',4=>'💎',5=>'👑'];
foreach ($vipLevels as $vl):
  $lvl      = (int)$vl['level'];
  if ($lvl === 0) continue; // Skip level 0
  $unlocked = $userVipLevel >= $lvl;
  $code     = $userVipCodes[$lvl]['code'] ?? null;
  $needed   = max(0, (int)$vl['min_deposit'] - $userTotalDeposit);
  $vColor   = $vl['color'] ?? '#6B7A99';
?>
<div class="vip-code-card <?= !$unlocked?'vip-code-card--locked':'' ?>" style="border-color:<?= $unlocked?$vColor.'44':'var(--border-light)' ?>">
  <div class="vip-level-badge" style="background:<?= $vColor ?>22;border:2px solid <?= $vColor ?>44">
    <?= $vipIcons[$lvl] ?? '⭐' ?>
  </div>
  <div style="flex:1;min-width:0">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <span style="font-weight:800;font-size:15px">VIP <?= $lvl ?></span>
      <span style="background:<?= $vColor ?>22;color:<?= $vColor ?>;padding:2px 10px;border-radius:99px;font-size:10px;font-weight:800"><?= htmlspecialchars($vl['badge_label'] ?? '') ?></span>
      <?php if ($unlocked): ?>
        <span style="background:rgba(0,230,118,.1);color:var(--green);padding:2px 10px;border-radius:99px;font-size:10px;font-weight:800">✅ Tercapai</span>
      <?php else: ?>
        <span style="background:rgba(107,122,153,.1);color:var(--text-secondary);padding:2px 10px;border-radius:99px;font-size:10px;font-weight:800">🔒 Terkunci</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($vl['benefits'])): ?>
      <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px"><?= htmlspecialchars($vl['benefits']) ?></div>
    <?php endif; ?>
    <?php if ($unlocked && $code): ?>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <div class="vip-code-value" id="vipCode<?= $lvl ?>"><?= htmlspecialchars($code) ?></div>
        <button onclick="copyCode('<?= htmlspecialchars(addslashes($code)) ?>',this)" class="nox-btn nox-btn--sm nox-btn--outline">📋 Salin</button>
      </div>
    <?php elseif ($unlocked): ?>
      <div style="font-size:12px;color:var(--amber)">⏳ Kode sedang digenerate...</div>
    <?php else: ?>
      <div class="vip-code-value vip-code-blur">NOXVIP<?= $lvl ?>XXXX</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-top:4px">
        🔒 Butuh deposit <?= formatRupiah($needed) ?> lagi untuk buka level ini
      </div>
      <div style="margin-top:8px">
        <div style="height:4px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden;max-width:200px">
          <div style="height:100%;background:<?= $vColor ?>;border-radius:99px;width:<?= min(100,round($userTotalDeposit/(int)$vl['min_deposit']*100)) ?>%"></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function copyCode(code, btn) {
  navigator.clipboard.writeText(code).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✅ Disalin!'; btn.style.color='var(--green)';
    setTimeout(() => { btn.textContent = orig; btn.style.color=''; }, 2000);
  });
}

document.querySelectorAll('.use-voucher-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    if (!confirm('Gunakan voucher ' + this.dataset.code + ' sekarang?')) return;
    const fd = new FormData();
    fd.append('action','use_voucher'); fd.append('user_voucher_id', this.dataset.id);
    fd.append('csrf_token','<?= generateCsrfToken() ?>');
    this.disabled = true; this.textContent = '⏳...';
    fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      if (d.success) { alert('✅ ' + d.message); location.reload(); }
      else { alert('❌ ' + d.message); this.disabled = false; this.textContent = '✅ Gunakan'; }
    });
  });
});
</script>
</body></html>
