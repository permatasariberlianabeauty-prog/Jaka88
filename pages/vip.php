<?php
/**
 * NOXARA - Halaman VIP
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/vip.php';

requireLogin();
$user    = getCurrentUser();
$vipData = getUserVipInfo((int)$user['id']);
$codes   = getUserVipCodes((int)$user['id']);

// Log copy kode
$copiedCode = clean($_GET['copy'] ?? '');

$current     = $vipData['current'];
$allLevels   = $vipData['all_levels'];
$nextLevel   = $vipData['next_level'];
$vipProgress = $vipData['progress'];
$vipNeeded   = $vipData['needed'];
$userVipLvl  = (int)($current['vip_level'] ?? 0);
$cumDep      = (int)($current['total_deposit_cumulative'] ?? 0);
$minWD       = (int)($current['min_withdraw'] ?? 100000);
$feeWD       = (float)($current['withdraw_fee_percent'] ?? 15);
$vipColor    = htmlspecialchars($current['color'] ?? '#6B7A99');
$vipLabel    = htmlspecialchars($current['badge_label'] ?? 'BASIC');

// Map kode per VIP level
$codesByLevel = [];
foreach ($codes as $c) {
    $codesByLevel[(int)$c['vip_level']] = $c;
}

// VIP color map
$vipColors = [0=>'#6B7A99',1=>'#00D4FF',2=>'#00E676',3=>'#7B2FFF',4=>'#FFB300',5=>'#FF6B6B'];
$vipIcons  = [0=>'🔵',1=>'🥉',2=>'🥈',3=>'🥇',4=>'💎',5=>'👑'];
$vipNames  = [0=>'BASIC',1=>'BRONZE',2=>'SILVER',3=>'GOLD',4=>'PLATINUM',5=>'ELITE'];

$pageTitle = 'Level VIP';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= getSetting('site_name','NOXARA') ?></title>
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">
<style>
/* ── VIP Page ── */
.nox-page-header{margin-bottom:24px}.nox-page-header h1{font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px}.nox-breadcrumb{font-size:12px;color:var(--text-secondary);margin-bottom:8px}.nox-breadcrumb a{color:var(--cyan);text-decoration:none}.nox-breadcrumb span{margin:0 6px;opacity:.4}

/* Current VIP Card */
.nox-current-vip{border-radius:var(--radius-lg);padding:28px;margin-bottom:28px;position:relative;overflow:hidden}
.nox-current-vip::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;opacity:.08;background:var(--vip-color)}
.nox-current-vip__badge{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.nox-vip-icon-lg{width:68px;height:68px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:32px;flex-shrink:0;border:2px solid}
.nox-current-vip__info{flex:1}
.nox-current-vip__title{font-family:'Orbitron',monospace;font-size:22px;font-weight:700;margin-bottom:4px}
.nox-current-vip__sub{font-size:13px;color:var(--text-secondary)}
.nox-vip-prog-bar{height:10px;background:rgba(255,255,255,0.08);border-radius:99px;overflow:hidden;margin:14px 0 8px}
.nox-vip-prog-fill{height:100%;border-radius:99px;transition:width 1.5s cubic-bezier(.4,0,.2,1)}
.nox-vip-meta{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:16px}
@media(min-width:480px){.nox-vip-meta{grid-template-columns:repeat(4,1fr)}}
.nox-vip-meta-item{background:rgba(0,0,0,0.2);border-radius:10px;padding:10px;text-align:center}
.nox-vip-meta-item__val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;margin-bottom:2px}
.nox-vip-meta-item__lbl{font-size:10px;color:var(--text-secondary)}

/* VIP Level Cards */
.nox-vip-levels-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-bottom:28px}
@media(max-width:640px){.nox-vip-levels-grid{grid-template-columns:1fr}}
.nox-vip-level-card{border-radius:var(--radius-lg);border:1px solid var(--border-light);background:var(--bg-card);padding:22px;transition:var(--transition);position:relative;overflow:hidden}
.nox-vip-level-card.is-current{box-shadow:0 0 0 2px var(--vip-border)}
.nox-vip-level-card.is-unlocked{opacity:1}
.nox-vip-level-card.is-locked{opacity:.75}
.nox-vip-level-card__header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.nox-vip-level-card__icon{font-size:28px}
.nox-vip-level-card__title{font-family:'Orbitron',monospace;font-size:16px;font-weight:700;flex:1;margin-left:10px}
.nox-vip-level-card__badge{font-size:10px;font-weight:700;padding:3px 10px;border-radius:99px}
.nox-vip-level-card__stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.nox-vip-stat{background:rgba(255,255,255,0.03);border-radius:8px;padding:9px;text-align:center}
.nox-vip-stat__val{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:700;margin-bottom:2px}
.nox-vip-stat__lbl{font-size:10px;color:var(--text-secondary)}
.nox-vip-level-card__code-box{border-radius:10px;padding:12px;margin-top:12px}
.nox-vip-code-text{font-family:'Space Grotesk',monospace;font-size:14px;font-weight:700;letter-spacing:.05em;word-break:break-all}
.nox-vip-code-censored{font-size:18px;letter-spacing:4px;color:var(--text-disabled)}
.nox-vip-level-card__glow{position:absolute;top:-40px;right:-40px;width:120px;height:120px;border-radius:50%;opacity:.06;pointer-events:none}

/* Comparison Table */
.nox-vip-compare-wrap{overflow-x:auto;border-radius:var(--radius-lg);border:1px solid var(--border-light);margin-bottom:28px}
.nox-vip-compare{width:100%;border-collapse:collapse;font-size:13px}
.nox-vip-compare th{background:rgba(255,255,255,0.04);padding:12px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);white-space:nowrap}
.nox-vip-compare td{padding:12px 16px;border-top:1px solid rgba(255,255,255,0.04);text-align:center;white-space:nowrap}
.nox-vip-compare tr.active-row td{background:rgba(0,212,255,0.05);font-weight:700}

/* Info Box */
.nox-info-box{background:rgba(123,47,255,0.06);border:1px solid rgba(123,47,255,0.2);border-radius:var(--radius-lg);padding:24px;margin-bottom:28px}
.nox-info-box h3{font-size:15px;font-weight:700;margin:0 0 16px;color:var(--purple)}
.nox-info-box ol{margin:0;padding-left:20px;display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--text-secondary)}
.nox-info-box ol li strong{color:var(--text-primary)}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">
<?= renderFlash() ?>


<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="nox-page-header">
  <div class="nox-breadcrumb"><a href="<?= BASE_URL ?>/dashboard">Dashboard</a><span>›</span>Level VIP</div>
  <h1>💎 Level VIP</h1>
  <p style="color:var(--text-secondary);font-size:14px">Naik level VIP untuk menikmati keuntungan eksklusif & biaya withdraw lebih rendah.</p>
</div>

<!-- ── VIP Status Saat Ini ─────────────────────────────────── -->
<div class="nox-current-vip" style="background:linear-gradient(135deg,rgba(15,22,41,1),rgba(15,22,41,0.8));border:2px solid <?= $vipColor ?>40;--vip-color:<?= $vipColor ?>">
  <div class="nox-current-vip__badge">
    <div class="nox-vip-icon-lg" style="background:<?= $vipColor ?>18;border-color:<?= $vipColor ?>50">
      <?= $vipIcons[$userVipLvl] ?? '⭐' ?>
    </div>
    <div class="nox-current-vip__info">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <div class="nox-current-vip__title" style="color:<?= $vipColor ?>">VIP <?= $userVipLvl ?></div>
        <span style="background:<?= $vipColor ?>22;color:<?= $vipColor ?>;padding:3px 12px;border-radius:99px;font-size:11px;font-weight:700"><?= $vipLabel ?></span>
      </div>
      <div class="nox-current-vip__sub">Total Isi Ulang Kumulatif: <strong style="color:<?= $vipColor ?>"><?= formatRupiah($cumDep) ?></strong></div>
    </div>
  </div>

  <!-- Progress Bar -->
  <?php if ($nextLevel): ?>
    <div>
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
        <span style="color:var(--text-secondary)">Progress menuju VIP <?= (int)$nextLevel['level'] ?> (<?= htmlspecialchars($nextLevel['badge_label'] ?? '') ?>)</span>
        <span style="color:<?= $vipColor ?>;font-weight:700"><?= $vipProgress ?>%</span>
      </div>
      <div class="nox-vip-prog-bar">
        <div class="nox-vip-prog-fill" id="mainVipBar" style="width:0%;background:linear-gradient(90deg,<?= $vipColor ?>,var(--purple))" data-target="<?= $vipProgress ?>"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary)">
        <span>Sudah: <?= formatRupiah($cumDep) ?></span>
        <span>Butuh <strong style="color:var(--amber)"><?= formatRupiah($vipNeeded) ?></strong> lagi</span>
      </div>
    </div>
  <?php else: ?>
    <div class="nox-vip-prog-bar"><div class="nox-vip-prog-fill" style="width:100%;background:linear-gradient(90deg,#FFB300,#FF6B6B)"></div></div>
    <div style="text-align:center;font-size:13px;color:#FFB300;font-weight:600">🎉 Selamat! Kamu telah mencapai level VIP tertinggi!</div>
  <?php endif; ?>

  <!-- Meta Info -->
  <div class="nox-vip-meta">
    <div class="nox-vip-meta-item">
      <div class="nox-vip-meta-item__val" style="color:var(--cyan)"><?= formatRupiah($minWD) ?></div>
      <div class="nox-vip-meta-item__lbl">Min. Withdraw</div>
    </div>
    <div class="nox-vip-meta-item">
      <div class="nox-vip-meta-item__val" style="color:var(--green)"><?= number_format($feeWD, 0) ?>%</div>
      <div class="nox-vip-meta-item__lbl">Fee Withdraw</div>
    </div>
    <div class="nox-vip-meta-item">
      <div class="nox-vip-meta-item__val"><?= formatRupiah($cumDep) ?></div>
      <div class="nox-vip-meta-item__lbl">Total Deposit</div>
    </div>
    <div class="nox-vip-meta-item">
      <div class="nox-vip-meta-item__val" style="color:var(--amber)"><?= $nextLevel ? formatRupiah($vipNeeded) . ' lagi' : 'Maks!' ?></div>
      <div class="nox-vip-meta-item__lbl">Ke Level Berikutnya</div>
    </div>
  </div>
</div>

<!-- ── Semua Level VIP ─────────────────────────────────────── -->
<div style="margin-bottom:8px">
  <h2 style="font-size:16px;font-weight:700;margin:0 0 16px">🎖️ Semua Level VIP</h2>
</div>
<div class="nox-vip-levels-grid">
  <?php foreach ($allLevels as $lvl):
    $lv       = (int)$lvl['level'];
    $isUnlocked = $lv <= $userVipLvl;
    $isCurrent  = $lv === $userVipLvl;
    $lColor   = htmlspecialchars($lvl['color'] ?? ($vipColors[$lv] ?? '#6B7A99'));
    $lIcon    = $vipIcons[$lv] ?? '⭐';
    $lName    = htmlspecialchars($lvl['badge_label'] ?? ($vipNames[$lv] ?? 'VIP ' . $lv));
    $lMinDep  = (int)$lvl['min_deposit_cumulative'];
    $lMinWD   = (int)$lvl['min_withdraw'];
    $lFee     = (float)$lvl['withdraw_fee_percent'];
    $code     = $codesByLevel[$lv] ?? null;
    $neededForThis = max(0, $lMinDep - $cumDep);
  ?>
  <div class="nox-vip-level-card <?= $isUnlocked ? 'is-unlocked' : 'is-locked' ?> <?= $isCurrent ? 'is-current' : '' ?>"
       style="--vip-border:<?= $lColor ?>;border-color:<?= $isUnlocked ? $lColor . '40' : 'var(--border-light)' ?>">
    <div class="nox-vip-level-card__glow" style="background:<?= $lColor ?>"></div>

    <!-- Header -->
    <div class="nox-vip-level-card__header">
      <div class="nox-vip-level-card__icon"><?= $lIcon ?></div>
      <div class="nox-vip-level-card__title" style="color:<?= $isUnlocked ? $lColor : 'var(--text-secondary)' ?>">VIP <?= $lv ?></div>
      <?php if ($isCurrent): ?>
        <span class="nox-vip-level-card__badge" style="background:<?= $lColor ?>22;color:<?= $lColor ?>">✅ Level Kamu</span>
      <?php elseif ($isUnlocked): ?>
        <span class="nox-vip-level-card__badge" style="background:rgba(0,230,118,0.1);color:var(--green)">✅ Dicapai</span>
      <?php else: ?>
        <span class="nox-vip-level-card__badge" style="background:rgba(107,122,153,0.1);color:#6B7A99">🔒 Terkunci</span>
      <?php endif; ?>
    </div>

    <!-- Name Badge -->
    <div style="margin-bottom:14px">
      <span style="font-size:13px;font-weight:700;background:<?= $lColor ?>18;color:<?= $lColor ?>;padding:4px 14px;border-radius:99px"><?= $lName ?></span>
    </div>

    <!-- Stats -->
    <div class="nox-vip-level-card__stats">
      <div class="nox-vip-stat">
        <div class="nox-vip-stat__val" style="color:<?= $lColor ?>"><?= $lv === 0 ? 'Gratis' : formatRupiah($lMinDep) ?></div>
        <div class="nox-vip-stat__lbl">Syarat Deposit</div>
      </div>
      <div class="nox-vip-stat">
        <div class="nox-vip-stat__val" style="color:var(--green)"><?= formatRupiah($lMinWD) ?></div>
        <div class="nox-vip-stat__lbl">Min. Withdraw</div>
      </div>
      <div class="nox-vip-stat">
        <div class="nox-vip-stat__val" style="color:var(--amber)"><?= number_format($lFee, 0) ?>%</div>
        <div class="nox-vip-stat__lbl">Fee WD</div>
      </div>
      <div class="nox-vip-stat">
        <div class="nox-vip-stat__val"><?= number_format(100 - $lFee, 0) ?>%</div>
        <div class="nox-vip-stat__lbl">Diterima</div>
      </div>
    </div>


    <!-- Kode VIP -->
    <div class="nox-vip-level-card__code-box" style="background:<?= $isUnlocked ? $lColor . '0A' : 'rgba(107,122,153,0.06)' ?>;border:1px solid <?= $isUnlocked ? $lColor . '30' : 'rgba(107,122,153,0.2)' ?>">
      <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">🔑 KODE AKSES VIP <?= $lv ?></div>
      <?php if ($isUnlocked && $code): ?>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div class="nox-vip-code-text" style="color:<?= $lColor ?>"><?= htmlspecialchars($code['code']) ?></div>
          <button class="nox-btn nox-btn--sm" style="background:<?= $lColor ?>18;color:<?= $lColor ?>;border:1px solid <?= $lColor ?>40;flex-shrink:0"
                  onclick="copyVipCode('<?= htmlspecialchars(addslashes($code['code'])) ?>', this)">📋 Copy</button>
        </div>
        <?php if (!empty($code['description'])): ?>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:6px"><?= htmlspecialchars($code['description']) ?></div>
        <?php endif; ?>
        <?php if (!empty($code['benefit'])): ?>
          <div style="font-size:11px;color:<?= $lColor ?>;margin-top:4px">✨ <?= htmlspecialchars($code['benefit']) ?></div>
        <?php endif; ?>
      <?php elseif ($isUnlocked && !$code): ?>
        <div style="color:var(--text-secondary);font-size:12px">Tidak ada kode untuk level ini.</div>
      <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <div class="nox-vip-code-censored">████████████████</div>
          <span style="font-size:11px;color:#6B7A99">🔒 Terkunci</span>
        </div>
        <?php if ($neededForThis > 0): ?>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:6px">
            Isi ulang <strong style="color:var(--amber)"><?= formatRupiah($neededForThis) ?></strong> lagi untuk membuka level ini
          </div>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/deposit" class="nox-btn nox-btn--sm" style="width:100%;justify-content:center;margin-top:10px;background:rgba(123,47,255,0.15);color:var(--purple);border:1px solid rgba(123,47,255,0.3)">
          💳 Isi Ulang Sekarang
        </a>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<!-- ── Tabel Perbandingan Keuntungan ──────────────────────── -->
<div style="margin-bottom:8px">
  <h2 style="font-size:16px;font-weight:700;margin:0 0 16px">📊 Perbandingan Keuntungan VIP</h2>
</div>
<div class="nox-vip-compare-wrap">
  <table class="nox-vip-compare">
    <thead>
      <tr>
        <th>Level</th>
        <th>Nama</th>
        <th>Syarat Deposit</th>
        <th>Min. WD</th>
        <th>Fee WD</th>
        <th>Dana Diterima</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allLevels as $lvl):
        $lv     = (int)$lvl['level'];
        $isCur  = $lv === $userVipLvl;
        $lColor2 = htmlspecialchars($lvl['color'] ?? ($vipColors[$lv] ?? '#6B7A99'));
        $lIcon2  = $vipIcons[$lv] ?? '⭐';
        $lName2  = htmlspecialchars($lvl['badge_label'] ?? 'VIP ' . $lv);
        $lFee2   = (float)$lvl['withdraw_fee_percent'];
      ?>
      <tr class="<?= $isCur ? 'active-row' : '' ?>" style="<?= $isCur ? 'background:' . $lColor2 . '08' : '' ?>">
        <td>
          <div style="display:flex;align-items:center;gap:6px;justify-content:center">
            <span><?= $lIcon2 ?></span>
            <span style="font-weight:700;color:<?= $lColor2 ?>"><?= $isCur ? '▶ VIP ' . $lv : 'VIP ' . $lv ?></span>
            <?php if ($isCur): ?><span style="font-size:9px;background:<?= $lColor2 ?>22;color:<?= $lColor2 ?>;padding:1px 6px;border-radius:99px">Kamu</span><?php endif; ?>
          </div>
        </td>
        <td><span style="color:<?= $lColor2 ?>;font-weight:600"><?= $lName2 ?></span></td>
        <td><?= $lv === 0 ? '—' : formatRupiah((int)$lvl['min_deposit_cumulative']) ?></td>
        <td style="color:var(--green);font-weight:600"><?= formatRupiah((int)$lvl['min_withdraw']) ?></td>
        <td style="color:<?= $lFee2 <= 5 ? 'var(--green)' : ($lFee2 <= 10 ? 'var(--amber)' : 'var(--red)') ?>;font-weight:700"><?= number_format($lFee2, 0) ?>%</td>
        <td style="color:var(--cyan);font-weight:700"><?= number_format(100 - $lFee2, 0) ?>%</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ── Cara Naik VIP ──────────────────────────────────────── -->
<div class="nox-info-box">
  <h3>💡 Cara Naik Level VIP</h3>
  <ol>
    <li><strong>Isi Ulang Saldo</strong> — Lakukan deposit ke NOXARA. Setiap rupiah deposit dihitung kumulatif.</li>
    <li><strong>VIP Naik Otomatis</strong> — Sistem memproses kenaikan VIP secara otomatis setelah deposit dikonfirmasi.</li>
    <li><strong>VIP Tidak Turun</strong> — Level VIP kamu tidak akan turun meskipun saldo habis digunakan.</li>
    <li><strong>Nikmati Keuntungan</strong> — Min. withdraw lebih rendah & fee withdraw makin kecil di setiap level.</li>
    <li><strong>Kode Akses VIP</strong> — Setiap level memberikan kode eksklusif yang bisa digunakan untuk benefit tambahan.</li>
  </ol>
  <div style="margin-top:20px;text-align:center">
    <a href="<?= BASE_URL ?>/deposit" class="nox-btn nox-btn--primary" style="font-size:15px;padding:12px 32px">
      💳 Isi Ulang Sekarang <?= $nextLevel ? '— Butuh ' . formatRupiah($vipNeeded) . ' ke VIP ' . (int)$nextLevel['level'] : '' ?>
    </a>
  </div>
</div>
</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
/* ── VIP Page JS ─────────────────────────────────────────── */

/* Progress bar animate on load */
window.addEventListener('load', () => {
  const bar = document.getElementById('mainVipBar');
  if (bar) {
    const target = bar.dataset.target || '0';
    setTimeout(() => { bar.style.width = target + '%'; }, 400);
  }
});

/* Copy VIP Code */
function copyVipCode(code, btn) {
  navigator.clipboard.writeText(code).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✅ Tersalin!';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = code; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    const orig = btn.textContent;
    btn.textContent = '✅ Tersalin!';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  });
}
</script>
</body>
</html>
