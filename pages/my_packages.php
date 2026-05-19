<?php
/**
 * NOXARA - Halaman Paket Saya
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mining.php';

requireLogin();
$user = getCurrentUser();

// ── AJAX: Mining Harian ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mine') {
    header('Content-Type: application/json');
    requireCsrf();
    $pkgId  = (int)($_POST['package_id'] ?? 0);
    $result = doMining((int)$user['id'], $pkgId);
    echo json_encode($result);
    exit;
}

// ── Paket Aktif ─────────────────────────────────────────────
$activePackages = getUserActivePackages((int)$user['id']);

// ── Paket Expired (Riwayat) ─────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$stmtCount = db()->prepare("SELECT COUNT(*) as cnt FROM user_products WHERE user_id=? AND status='expired'");
$stmtCount->bind_param("i", (int)$user['id']);
$stmtCount->execute();
$totalExpired = (int)$stmtCount->get_result()->fetch_assoc()['cnt'];
$stmtCount->close();

$stmtExp = db()->prepare("SELECT up.*, p.name as product_name, p.image, c.name as category_name, c.color as category_color
    FROM user_products up
    JOIN products p ON p.id=up.product_id
    JOIN product_categories c ON c.id=p.category_id
    WHERE up.user_id=? AND up.status='expired'
    ORDER BY up.end_date DESC LIMIT ? OFFSET ?");
$stmtExp->bind_param("iii", (int)$user['id'], $perPage, $offset);
$stmtExp->execute();
$expiredPackages = $stmtExp->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtExp->close();

// ── Statistik Total ──────────────────────────────────────────
$stmtStats = db()->prepare("SELECT
    COUNT(*) as total_packages,
    COALESCE(SUM(total_profit_earned),0) as total_profit,
    COALESCE(SUM(total_days_mined),0) as total_days
    FROM user_products WHERE user_id=?");
$stmtStats->bind_param("i", (int)$user['id']);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();
$stmtStats->close();

$paginationData = paginate($totalExpired, $perPage, $page, BASE_URL . '/my-packages');
$csrfToken      = generateCsrfToken();
$pageTitle      = 'Paket Saya';
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
/* ── My Packages Page ── */
.nox-page-header{margin-bottom:24px}.nox-page-header h1{font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px}.nox-breadcrumb{font-size:12px;color:var(--text-secondary);margin-bottom:8px}.nox-breadcrumb a{color:var(--cyan);text-decoration:none}.nox-breadcrumb span{margin:0 6px;opacity:.4}

/* Stat Cards */
.nox-pkg-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
@media(max-width:600px){.nox-pkg-stat-grid{grid-template-columns:1fr 1fr 1fr;gap:10px}}
.nox-pkg-stat{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px;text-align:center;position:relative;overflow:hidden}
.nox-pkg-stat::before{content:'';position:absolute;top:-20px;right:-20px;width:70px;height:70px;border-radius:50%;opacity:.08}
.nox-pkg-stat__icon{font-size:24px;margin-bottom:8px}
.nox-pkg-stat__val{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;margin-bottom:4px}
.nox-pkg-stat__lbl{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.05em}

/* Package Card */
.nox-mine-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px;margin-bottom:16px;transition:var(--transition)}
.nox-mine-card:hover{border-color:rgba(0,212,255,0.3)}
.nox-mine-card__header{display:flex;gap:14px;align-items:flex-start;margin-bottom:16px}
.nox-mine-card__img{width:64px;height:64px;border-radius:12px;object-fit:cover;flex-shrink:0;background:rgba(0,212,255,0.08)}
.nox-mine-card__title{font-weight:700;font-size:16px;margin-bottom:4px}
.nox-mine-card__meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.nox-mine-card__price{font-family:'Space Grotesk',sans-serif;font-size:13px;color:var(--text-secondary)}
.nox-mine-card__info{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px}
@media(min-width:600px){.nox-mine-card__info{grid-template-columns:repeat(4,1fr)}}
.nox-mine-info-row{background:rgba(255,255,255,0.03);border-radius:8px;padding:10px;text-align:center}
.nox-mine-info-row__val{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:700;margin-bottom:2px}
.nox-mine-info-row__lbl{font-size:10px;color:var(--text-secondary)}
.nox-mine-progress{margin-bottom:16px}
.nox-mine-progress__top{display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px}
.nox-mine-progress__bar{height:8px;background:rgba(255,255,255,0.07);border-radius:99px;overflow:hidden}
.nox-mine-progress__fill{height:100%;background:linear-gradient(90deg,var(--cyan),var(--purple));border-radius:99px;transition:width 1s ease}

/* Mining Status Box */
.nox-mining-status{border-radius:12px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.nox-mining-status--idle{background:rgba(0,212,255,0.07);border:1px solid rgba(0,212,255,0.2)}
.nox-mining-status--pending{background:rgba(255,179,0,0.07);border:1px solid rgba(255,179,0,0.2)}
.nox-mining-status--done{background:rgba(0,230,118,0.07);border:1px solid rgba(0,230,118,0.2)}
.nox-mining-status__text{font-size:13px;font-weight:600;flex:1}
.nox-mining-status__countdown{font-family:'Orbitron',monospace;font-size:18px;font-weight:700;color:var(--amber);min-width:80px;text-align:right}
.nox-btn-mine{background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;border:none;padding:10px 22px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:6px}
.nox-btn-mine:hover{transform:scale(1.05);box-shadow:0 6px 24px rgba(0,212,255,0.3)}
.nox-btn-mine:disabled{opacity:.5;cursor:not-allowed;transform:none}
.nox-btn-mine.mining{animation:pulse 1s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}

/* Riwayat Tabel */
.nox-table-wrap{overflow-x:auto;border-radius:var(--radius-lg);border:1px solid var(--border-light)}
.nox-table{width:100%;border-collapse:collapse;font-size:13px}
.nox-table th{background:rgba(255,255,255,0.04);padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);white-space:nowrap}
.nox-table td{padding:12px 16px;border-top:1px solid rgba(255,255,255,0.04);vertical-align:middle}
.nox-table tr:hover td{background:rgba(255,255,255,0.02)}
.nox-badge-expired{background:rgba(107,122,153,0.15);color:var(--muted,#6B7A99);padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700}

/* Modal */
.nox-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:9000;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .3s}
.nox-modal-overlay.show{opacity:1;pointer-events:all}
.nox-modal-box{background:var(--bg-card);border:1px solid rgba(0,212,255,0.25);border-radius:20px;padding:28px;max-width:400px;width:100%;transform:scale(.95) translateY(20px);transition:transform .3s;text-align:center}
.nox-modal-overlay.show .nox-modal-box{transform:scale(1) translateY(0)}
.nox-modal-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:20px}
.nox-coin-anim{font-size:52px;animation:coinDrop .5s ease;display:block;margin:0 auto 12px}
@keyframes coinDrop{0%{transform:translateY(-30px);opacity:0}100%{transform:translateY(0);opacity:1}}
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
  <div class="nox-breadcrumb"><a href="<?= BASE_URL ?>/dashboard">Dashboard</a><span>›</span>Paket Saya</div>
  <h1>⛏️ Paket Mining Saya</h1>
  <p style="color:var(--text-secondary);font-size:14px">Kelola semua paket mining aktif dan riwayat paket kamu.</p>
</div>

<!-- ── Statistik ───────────────────────────────────────────── -->
<div class="nox-pkg-stat-grid">
  <div class="nox-pkg-stat" style="border-color:rgba(0,212,255,0.3)">
    <div style="position:absolute;top:-20px;right:-20px;width:70px;height:70px;border-radius:50%;background:var(--cyan);opacity:.08"></div>
    <div class="nox-pkg-stat__icon">📦</div>
    <div class="nox-pkg-stat__val" style="color:var(--cyan)"><?= (int)$stats['total_packages'] ?></div>
    <div class="nox-pkg-stat__lbl">Total Paket Dibeli</div>
  </div>
  <div class="nox-pkg-stat" style="border-color:rgba(0,230,118,0.3)">
    <div style="position:absolute;top:-20px;right:-20px;width:70px;height:70px;border-radius:50%;background:var(--green);opacity:.08"></div>
    <div class="nox-pkg-stat__icon">💰</div>
    <div class="nox-pkg-stat__val" style="color:var(--green);font-size:16px"><?= formatRupiah((int)$stats['total_profit'], false) ?></div>
    <div class="nox-pkg-stat__lbl">Total Profit Diperoleh</div>
  </div>
  <div class="nox-pkg-stat" style="border-color:rgba(123,47,255,0.3)">
    <div style="position:absolute;top:-20px;right:-20px;width:70px;height:70px;border-radius:50%;background:var(--purple);opacity:.08"></div>
    <div class="nox-pkg-stat__icon">⛏️</div>
    <div class="nox-pkg-stat__val" style="color:var(--purple)"><?= (int)$stats['total_days'] ?></div>
    <div class="nox-pkg-stat__lbl">Total Hari Mining</div>
  </div>
</div>


<!-- ── Paket Aktif ─────────────────────────────────────────── -->
<div style="margin-bottom:32px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h2 style="font-size:16px;font-weight:700;margin:0">⚡ Paket Aktif (<?= count($activePackages) ?>)</h2>
    <a href="<?= BASE_URL ?>/products" class="nox-btn nox-btn--sm nox-btn--outline">+ Beli Paket Baru</a>
  </div>

  <?php if (empty($activePackages)): ?>
    <div class="nox-card" style="text-align:center;padding:48px 24px">
      <div style="font-size:48px;margin-bottom:12px">📦</div>
      <div style="font-weight:700;font-size:16px;margin-bottom:8px">Belum ada paket aktif</div>
      <div style="color:var(--text-secondary);font-size:13px;margin-bottom:20px">Beli paket mining untuk mulai menghasilkan profit harian!</div>
      <a href="<?= BASE_URL ?>/products" class="nox-btn nox-btn--primary">Lihat Produk Mining</a>
    </div>
  <?php else: ?>
    <?php foreach ($activePackages as $pkg):
      $imgSrc = !empty($pkg['image']) ? UPLOADS_URL . '/products/' . htmlspecialchars($pkg['image']) : ASSETS_URL . '/img/mining/default.png';
      $daysPassed = (int)max(0, $pkg['days_passed'] ?? 0);
      $totalDays  = (int)max(1, ($pkg['duration_days'] ?? 30));
      $daysLeft   = max(0, (int)($pkg['days_remaining'] ?? 0));
      $pct        = min(100, round($daysPassed / $totalDays * 100, 0));
      $miningStatus = $pkg['today_mining_status'] ?? '';
      $countdown    = (int)($pkg['mining_countdown'] ?? 0);
    ?>
    <div class="nox-mine-card" id="pkg-card-<?= (int)$pkg['id'] ?>">
      <!-- Header -->
      <div class="nox-mine-card__header">
        <img src="<?= $imgSrc ?>" alt="" class="nox-mine-card__img" onerror="this.src='<?= ASSETS_URL ?>/img/mining/default.png'">
        <div style="flex:1">
          <div class="nox-mine-card__title"><?= htmlspecialchars($pkg['name']) ?></div>
          <div class="nox-mine-card__meta">
            <span class="nox-badge" style="background:<?= htmlspecialchars($pkg['category_color'] ?? '#00D4FF') ?>22;color:<?= htmlspecialchars($pkg['category_color'] ?? '#00D4FF') ?>;font-size:10px;padding:2px 8px;border-radius:99px;font-weight:700"><?= htmlspecialchars($pkg['category_name']) ?></span>
            <span class="nox-mine-card__price"><?= formatRupiah((int)$pkg['purchase_price']) ?></span>
            <span class="nox-badge" style="background:rgba(0,230,118,0.1);color:var(--green);font-size:10px;padding:2px 8px;border-radius:99px;font-weight:700">● Aktif</span>
          </div>
        </div>
      </div>

      <!-- Info Grid -->
      <div class="nox-mine-card__info">
        <div class="nox-mine-info-row">
          <div class="nox-mine-info-row__val" style="color:var(--green)"><?= formatRupiah((int)$pkg['profit_per_day']) ?></div>
          <div class="nox-mine-info-row__lbl">💰 Profit/Hari</div>
        </div>
        <div class="nox-mine-info-row">
          <div class="nox-mine-info-row__val"><?= formatDate($pkg['start_date']) ?></div>
          <div class="nox-mine-info-row__lbl">📅 Mulai</div>
        </div>
        <div class="nox-mine-info-row">
          <div class="nox-mine-info-row__val" style="color:var(--amber)"><?= formatDate($pkg['end_date']) ?></div>
          <div class="nox-mine-info-row__lbl">📅 Berakhir</div>
        </div>
        <div class="nox-mine-info-row">
          <div class="nox-mine-info-row__val" style="color:var(--cyan)"><?= formatRupiah((int)$pkg['total_profit_earned']) ?></div>
          <div class="nox-mine-info-row__lbl">📈 Total Profit</div>
        </div>
        <div class="nox-mine-info-row">
          <div class="nox-mine-info-row__val" style="color:var(--purple)"><?= (int)($pkg['total_days_mined'] ?? 0) ?>/<?= $totalDays ?></div>
          <div class="nox-mine-info-row__lbl">⛏️ Hari Mining</div>
        </div>
        <div class="nox-mine-info-row">
          <div class="nox-mine-info-row__val"><?= $daysLeft ?> hari</div>
          <div class="nox-mine-info-row__lbl">⏳ Sisa</div>
        </div>
      </div>

      <!-- Progress Bar -->
      <div class="nox-mine-progress">
        <div class="nox-mine-progress__top">
          <span>Progress Durasi</span>
          <span style="color:var(--cyan);font-weight:600"><?= $pct ?>%</span>
        </div>
        <div class="nox-mine-progress__bar">
          <div class="nox-mine-progress__fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>

      <!-- Mining Status -->
      <div id="mining-status-<?= (int)$pkg['id'] ?>">
        <?php if (empty($miningStatus)): ?>
          <div class="nox-mining-status nox-mining-status--idle">
            <div class="nox-mining-status__text">⛏️ <strong>Belum Mining Hari Ini</strong> — Klik tombol untuk mulai</div>
            <button class="nox-btn-mine" onclick="askMining(<?= (int)$pkg['id'] ?>, '<?= htmlspecialchars($pkg['name']) ?>', <?= (int)$pkg['profit_per_day'] ?>)">
              ⛏️ Mining Sekarang
            </button>
          </div>
        <?php elseif ($miningStatus === 'pending'): ?>
          <div class="nox-mining-status nox-mining-status--pending">
            <div class="nox-mining-status__text">⏳ <strong>Mining Berlangsung</strong> — Profit masuk dalam:</div>
            <div class="nox-mining-status__countdown mining-countdown" data-seconds="<?= $countdown ?>">--:--:--</div>
          </div>
        <?php else: ?>
          <div class="nox-mining-status nox-mining-status--done">
            <div class="nox-mining-status__text">✅ <strong>Mining Selesai!</strong> Profit <strong><?= formatRupiah((int)$pkg['profit_per_day']) ?></strong> sudah masuk ke saldo profit!</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


<!-- ── Riwayat Paket (Expired) ─────────────────────────────── -->
<div style="margin-bottom:32px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h2 style="font-size:16px;font-weight:700;margin:0">📜 Riwayat Paket (<?= $totalExpired ?>)</h2>
  </div>

  <?php if (empty($expiredPackages)): ?>
    <div class="nox-card" style="text-align:center;padding:32px;color:var(--text-secondary);font-size:13px">
      <div style="font-size:32px;margin-bottom:8px">📭</div>
      Belum ada riwayat paket yang selesai.
    </div>
  <?php else: ?>
    <div class="nox-table-wrap">
      <table class="nox-table">
        <thead>
          <tr>
            <th>Paket</th>
            <th>Harga Beli</th>
            <th>Total Profit</th>
            <th>Mulai</th>
            <th>Selesai</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expiredPackages as $ep):
            $eImg = !empty($ep['image']) ? UPLOADS_URL . '/products/' . htmlspecialchars($ep['image']) : ASSETS_URL . '/img/mining/default.png';
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <img src="<?= $eImg ?>" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:cover;background:rgba(0,212,255,0.08)" onerror="this.src='<?= ASSETS_URL ?>/img/mining/default.png'">
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars($ep['product_name']) ?></div>
                  <div style="font-size:11px;color:<?= htmlspecialchars($ep['category_color'] ?? '#6B7A99') ?>"><?= htmlspecialchars($ep['category_name']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-family:'Space Grotesk',sans-serif;font-weight:600"><?= formatRupiah((int)$ep['purchase_price']) ?></td>
            <td style="color:var(--green);font-weight:700;font-family:'Space Grotesk',sans-serif"><?= formatRupiah((int)$ep['total_profit_earned']) ?></td>
            <td style="color:var(--text-secondary)"><?= formatDate($ep['start_date']) ?></td>
            <td style="color:var(--text-secondary)"><?= formatDate($ep['end_date']) ?></td>
            <td><span class="nox-badge-expired">Selesai</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= renderPagination($paginationData) ?>
  <?php endif; ?>
</div>

<!-- ── Modal Konfirmasi Mining ─────────────────────────────── -->
<div class="nox-modal-overlay" id="modalMining">
  <div class="nox-modal-box">
    <div style="font-size:40px;margin-bottom:12px">⛏️</div>
    <div style="font-size:18px;font-weight:700;margin-bottom:6px">Mulai Mining?</div>
    <div style="color:var(--text-secondary);font-size:14px;margin-bottom:4px" id="mineModalPkg">—</div>
    <div style="color:var(--cyan);font-size:13px;margin-bottom:16px">Profit masuk dalam 3 jam setelah mining</div>
    <div style="background:rgba(255,179,0,0.07);border:1px solid rgba(255,179,0,0.2);border-radius:8px;padding:10px;font-size:12px;color:var(--amber);margin-bottom:16px">
      ⚠️ Pastikan kamu mining setiap hari agar profit maksimal
    </div>
    <div id="mineModalError" style="color:var(--red);font-size:12px;min-height:16px;margin-bottom:8px"></div>
    <div class="nox-modal-actions">
      <button class="nox-btn nox-btn--outline" onclick="closeModal('modalMining')">Batal</button>
      <button class="nox-btn-mine" id="btnDoMining" onclick="doMining()">⛏️ Ya, Mining!</button>
    </div>
  </div>
</div>

<!-- ── Modal Profit Masuk ──────────────────────────────────── -->
<div class="nox-modal-overlay" id="modalProfit">
  <div class="nox-modal-box">
    <span class="nox-coin-anim">🪙</span>
    <div style="font-size:20px;font-weight:700;color:var(--green);margin-bottom:8px">Profit Masuk!</div>
    <div style="font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;color:var(--cyan);margin-bottom:8px" id="profitAmount"></div>
    <div style="color:var(--text-secondary);font-size:13px;margin-bottom:20px">Sudah masuk ke saldo profit kamu 🎉</div>
    <button class="nox-btn nox-btn--primary" style="width:100%;justify-content:center" onclick="closeModal('modalProfit')">Lanjut Mining ⛏️</button>
  </div>
</div>
</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>


<script>
/* ── My Packages JS ──────────────────────────────────────── */
const CSRF = '<?= $csrfToken ?>';
let pendingPkgId   = 0;
let pendingPkgName = '';
let pendingProfit  = 0;

/* Countdown real-time */
function startCountdowns() {
  document.querySelectorAll('.mining-countdown').forEach(el => {
    let sec = parseInt(el.dataset.seconds) || 0;
    if (sec <= 0) { el.textContent = '00:00:00'; return; }
    const tick = () => {
      if (sec <= 0) { el.textContent = '00:00:00'; return; }
      const h = String(Math.floor(sec/3600)).padStart(2,'0');
      const m = String(Math.floor((sec%3600)/60)).padStart(2,'0');
      const s = String(sec%60).padStart(2,'0');
      el.textContent = `${h}:${m}:${s}`;
      sec--;
      setTimeout(tick, 1000);
    };
    tick();
  });
}
startCountdowns();

/* Buka modal konfirmasi mining */
function askMining(pkgId, pkgName, profit) {
  pendingPkgId   = pkgId;
  pendingPkgName = pkgName;
  pendingProfit  = profit;
  document.getElementById('mineModalPkg').textContent = pkgName + ' — Profit Rp ' + parseInt(profit).toLocaleString('id-ID');
  document.getElementById('mineModalError').textContent = '';
  openModal('modalMining');
}

/* Eksekusi mining */
async function doMining() {
  if (!pendingPkgId) return;
  const btn = document.getElementById('btnDoMining');
  btn.disabled = true;
  btn.textContent = '⏳ Memproses...';
  document.getElementById('mineModalError').textContent = '';

  const fd = new FormData();
  fd.append('action', 'mine');
  fd.append('csrf_token', CSRF);
  fd.append('package_id', pendingPkgId);

  try {
    const res  = await fetch(location.href, { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      closeModal('modalMining');
      updateMiningStatus(pendingPkgId, 'pending', data.countdown);
      document.getElementById('profitAmount').textContent = 'Rp ' + parseInt(pendingProfit).toLocaleString('id-ID');
      /* Tunda tampil profit popup = setelah countdown habis */
      setTimeout(() => {
        closeModal('modalMining');
        openModal('modalProfit');
        updateMiningStatus(pendingPkgId, 'done', 0);
      }, data.countdown * 1000);
    } else {
      document.getElementById('mineModalError').textContent = data.message || 'Gagal mining.';
    }
  } catch(e) {
    document.getElementById('mineModalError').textContent = 'Terjadi kesalahan. Coba lagi.';
  } finally {
    btn.disabled = false;
    btn.textContent = '⛏️ Ya, Mining!';
  }
}

function updateMiningStatus(pkgId, status, countdown) {
  const box = document.getElementById('mining-status-' + pkgId);
  if (!box) return;
  if (status === 'pending') {
    box.innerHTML = `<div class="nox-mining-status nox-mining-status--pending">
      <div class="nox-mining-status__text">⏳ <strong>Mining Berlangsung</strong> — Profit masuk dalam:</div>
      <div class="nox-mining-status__countdown mining-countdown" data-seconds="${countdown}">--:--:--</div>
    </div>`;
    startCountdowns();
  } else if (status === 'done') {
    box.innerHTML = `<div class="nox-mining-status nox-mining-status--done">
      <div class="nox-mining-status__text">✅ <strong>Mining Selesai!</strong> Profit sudah masuk ke saldo profit!</div>
    </div>`;
  }
}

/* Progress bar animate on load */
window.addEventListener('load', () => {
  document.querySelectorAll('.nox-mine-progress__fill').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = w; }, 200);
  });
});

function openModal(id)  { document.getElementById(id)?.classList.add('show'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('show'); }
document.querySelectorAll('.nox-modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('show'); });
});
</script>
</body>
</html>
