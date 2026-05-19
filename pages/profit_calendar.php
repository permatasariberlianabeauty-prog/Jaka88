<?php
/**
 * NOXARA - Kalender Profit
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Bulan yang ditampilkan ───────────────────────────────
$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) $monthParam = date('Y-m');
[$year, $month] = explode('-', $monthParam);
$year  = (int)$year;
$month = (int)$month;
if ($month < 1 || $month > 12) { $month = (int)date('m'); $year = (int)date('Y'); }

$prevMonth = date('Y-m', mktime(0,0,0,$month-1,1,$year));
$nextMonth = date('Y-m', mktime(0,0,0,$month+1,1,$year));
$monthLabel = date('F Y', mktime(0,0,0,$month,1,$year));

// ── Ambil profit per tanggal ─────────────────────────────
$firstDay = sprintf('%04d-%02d-01', $year, $month);
$lastDay  = date('Y-m-t', strtotime($firstDay));

$stmtP = db()->prepare("SELECT DATE(created_at) as d, SUM(amount) as total FROM transactions WHERE user_id=? AND type='profit' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
$stmtP->bind_param('iss', $userId, $firstDay, $lastDay);
$stmtP->execute();
$profitRows = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

$profitByDate = [];
$totalMonth   = 0;
foreach ($profitRows as $r) {
    $profitByDate[$r['d']] = (int)$r['total'];
    $totalMonth += (int)$r['total'];
}

// ── Detail per hari (untuk modal) ───────────────────────
$stmtD = db()->prepare("SELECT DATE(created_at) as d, description, amount FROM transactions WHERE user_id=? AND type='profit' AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at");
$stmtD->bind_param('iss', $userId, $firstDay, $lastDay);
$stmtD->execute();
$detailRows = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

$detailByDate = [];
foreach ($detailRows as $r) {
    $detailByDate[$r['d']][] = $r;
}

// ── Bangun grid kalender ─────────────────────────────────
$daysInMonth  = (int)date('t', strtotime($firstDay));
$startWeekday = (int)date('N', strtotime($firstDay)); // 1=Mon, 7=Sun

$pageTitle = 'Kalender Profit';
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
.cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.cal-nav h2{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;margin:0}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:16px}
.cal-head{text-align:center;font-size:11px;font-weight:700;color:var(--text-secondary);padding:8px 0;text-transform:uppercase}
.cal-cell{border-radius:8px;min-height:70px;padding:6px;cursor:pointer;transition:.2s;border:1px solid transparent;position:relative}
.cal-cell:hover{border-color:rgba(0,212,255,.4)}
.cal-cell--empty{background:transparent;cursor:default}
.cal-cell--none{background:rgba(255,255,255,.03)}
.cal-cell--low{background:rgba(255,179,0,.12);border-color:rgba(255,179,0,.2)}
.cal-cell--mid{background:rgba(0,230,118,.12);border-color:rgba(0,230,118,.2)}
.cal-cell--high{background:rgba(0,230,118,.25);border-color:rgba(0,230,118,.4)}
.cal-cell__day{font-size:12px;font-weight:700;color:var(--text-secondary)}
.cal-cell--low .cal-cell__day,.cal-cell--mid .cal-cell__day,.cal-cell--high .cal-cell__day{color:var(--text-primary)}
.cal-cell__amount{font-size:10px;font-weight:700;margin-top:4px;line-height:1.3}
.cal-cell--low .cal-cell__amount{color:#FFB300}
.cal-cell--mid .cal-cell__amount{color:var(--green)}
.cal-cell--high .cal-cell__amount{color:#00FF88}
.cal-cell--today{border:2px solid var(--cyan)!important}
.legend{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.legend-item{display:flex;align-items:center;gap:6px;font-size:12px}
.legend-dot{width:14px;height:14px;border-radius:4px;flex-shrink:0}
.total-card{background:linear-gradient(135deg,rgba(0,212,255,.08),rgba(123,47,255,.08));border:1px solid rgba(0,212,255,.3);border-radius:14px;padding:20px;text-align:center;margin-bottom:24px}
/* Modal */
.cal-modal{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;display:none;align-items:center;justify-content:center}
.cal-modal.active{display:flex}
.cal-modal__box{background:var(--bg-card);border-radius:16px;width:90%;max-width:420px;max-height:80vh;overflow-y:auto;padding:24px;border:1px solid var(--border-light)}
@media(max-width:480px){.cal-cell{min-height:48px;padding:4px}.cal-cell__amount{display:none}}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">📅 Kalender Profit</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Visualisasi profit harian Anda</p>
</div>

<!-- TOTAL BULAN INI -->
<div class="total-card">
  <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Total Profit <?= htmlspecialchars($monthLabel) ?></div>
  <div style="font-family:'Space Grotesk',sans-serif;font-size:32px;font-weight:800;color:var(--cyan)"><?= formatRupiah($totalMonth) ?></div>
  <div style="font-size:12px;color:var(--text-secondary);margin-top:4px"><?= count($profitByDate) ?> hari aktif</div>
</div>

<!-- NAVIGASI BULAN -->
<div class="cal-nav">
  <a href="?month=<?= $prevMonth ?>" class="nox-btn nox-btn--outline nox-btn--sm">‹ Prev</a>
  <h2><?= htmlspecialchars($monthLabel) ?></h2>
  <a href="?month=<?= $nextMonth ?>" class="nox-btn nox-btn--outline nox-btn--sm">Next ›</a>
</div>

<!-- LEGENDA -->
<div class="legend">
  <div class="legend-item"><div class="legend-dot" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1)"></div> Tidak ada profit</div>
  <div class="legend-item"><div class="legend-dot" style="background:rgba(255,179,0,.3)"></div> &lt; Rp 10.000</div>
  <div class="legend-item"><div class="legend-dot" style="background:rgba(0,230,118,.25)"></div> Rp 10.000 – 100.000</div>
  <div class="legend-item"><div class="legend-dot" style="background:rgba(0,255,136,.4)"></div> &gt; Rp 100.000</div>
</div>

<!-- KALENDER GRID -->
<div class="nox-card" style="padding:16px">
  <div class="cal-grid">
    <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d): ?>
      <div class="cal-head"><?= $d ?></div>
    <?php endforeach; ?>

    <?php
    // Sel kosong awal
    for ($i = 1; $i < $startWeekday; $i++) echo '<div class="cal-cell cal-cell--empty"></div>';

    $today = date('Y-m-d');
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $profit   = $profitByDate[$dateStr] ?? 0;
        $isToday  = ($dateStr === $today);

        if ($profit <= 0) $cls = 'cal-cell--none';
        elseif ($profit < 10000) $cls = 'cal-cell--low';
        elseif ($profit < 100000) $cls = 'cal-cell--mid';
        else $cls = 'cal-cell--high';

        $hasDetail = !empty($detailByDate[$dateStr]);
        echo '<div class="cal-cell ' . $cls . ($isToday?' cal-cell--today':'') . '"' .
             ($hasDetail ? ' onclick="showDetail(\'' . $dateStr . '\')" title="Klik untuk detail"' : '') . '>';
        echo '<div class="cal-cell__day">' . $day . '</div>';
        if ($profit > 0) echo '<div class="cal-cell__amount">+' . number_format($profit/1000,0,'.','.') . 'K</div>';
        echo '</div>';
    }

    // Sel kosong akhir
    $endWeekday = (int)date('N', mktime(0,0,0,$month,$daysInMonth,$year));
    for ($i = $endWeekday; $i < 7; $i++) echo '<div class="cal-cell cal-cell--empty"></div>';
    ?>
  </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- MODAL DETAIL -->
<div class="cal-modal" id="calModal">
  <div class="cal-modal__box">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h3 style="margin:0;font-size:16px;font-weight:700" id="modalDate"></h3>
      <button onclick="document.getElementById('calModal').classList.remove('active')" style="background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer">&times;</button>
    </div>
    <div id="modalContent"></div>
  </div>
</div>

<script>
const detailData = <?= json_encode($detailByDate) ?>;
function showDetail(date) {
  const rows = detailData[date] || [];
  document.getElementById('modalDate').textContent = '📅 ' + date;
  let html = '';
  if (!rows.length) { html = '<p style="color:var(--text-secondary)">Tidak ada data</p>'; }
  else {
    let total = 0;
    rows.forEach(r => {
      total += parseInt(r.amount);
      html += `<div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(30,42,69,.4)">
        <span style="font-size:13px;color:var(--text-secondary)">${r.description||'Profit'}</span>
        <span style="font-weight:700;color:var(--green)">+${parseInt(r.amount).toLocaleString('id-ID')}</span>
      </div>`;
    });
    html += `<div style="display:flex;justify-content:space-between;padding:12px 0;font-weight:700">
      <span>Total</span><span style="color:var(--cyan)">+Rp ${total.toLocaleString('id-ID')}</span></div>`;
  }
  document.getElementById('modalContent').innerHTML = html;
  document.getElementById('calModal').classList.add('active');
}
</script>
</body></html>
