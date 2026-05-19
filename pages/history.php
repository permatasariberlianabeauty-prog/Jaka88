<?php
/**
 * NOXARA - Riwayat Transaksi
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Filter ──────────────────────────────────────────────
$validTypes = ['all','deposit','withdraw','purchase','profit','referral_commission','bonus','daily_reward','ad_reward','mission_reward'];
$filterType   = in_array($_GET['type'] ?? 'all', $validTypes) ? ($_GET['type'] ?? 'all') : 'all';
$filterWallet = in_array($_GET['wallet'] ?? 'all', ['all','main','profit','bonus','referral']) ? ($_GET['wallet'] ?? 'all') : 'all';
$dateFrom  = !empty($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo    = !empty($_GET['date_to'])   ? $_GET['date_to']   : '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;

// ── Export CSV ──────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $cond  = "WHERE user_id = ?";
    $types = ['i'];
    $vals  = [$userId];
    if ($filterType !== 'all')   { $cond .= " AND type = ?";         $types[] = 's'; $vals[] = $filterType; }
    if ($filterWallet !== 'all') { $cond .= " AND wallet_type = ?";  $types[] = 's'; $vals[] = $filterWallet; }
    if ($dateFrom)               { $cond .= " AND DATE(created_at) >= ?"; $types[] = 's'; $vals[] = $dateFrom; }
    if ($dateTo)                 { $cond .= " AND DATE(created_at) <= ?"; $types[] = 's'; $vals[] = $dateTo; }
    $stmt = db()->prepare("SELECT * FROM transactions $cond ORDER BY created_at DESC");
    $stmt->bind_param(implode('', $types), ...$vals);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="riwayat_transaksi_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "Tanggal,Tipe,Deskripsi,Wallet,Nominal,Status\n";
    foreach ($rows as $r) {
        echo '"' . $r['created_at'] . '","' . $r['type'] . '","' . str_replace('"','""',$r['description'] ?? '') . '","' . ($r['wallet_type'] ?? '') . '","' . $r['amount'] . '","' . ($r['status'] ?? 'completed') . "\"\n";
    }
    exit;
}

// ── Build WHERE ─────────────────────────────────────────
$cond  = "WHERE user_id = ?";
$types = ['i'];
$vals  = [$userId];
if ($filterType !== 'all')   { $cond .= " AND type = ?";         $types[] = 's'; $vals[] = $filterType; }
if ($filterWallet !== 'all') { $cond .= " AND wallet_type = ?";  $types[] = 's'; $vals[] = $filterWallet; }
if ($dateFrom)               { $cond .= " AND DATE(created_at) >= ?"; $types[] = 's'; $vals[] = $dateFrom; }
if ($dateTo)                 { $cond .= " AND DATE(created_at) <= ?"; $types[] = 's'; $vals[] = $dateTo; }

// ── Count total ─────────────────────────────────────────
$stmtC = db()->prepare("SELECT COUNT(*) as c FROM transactions $cond");
$stmtC->bind_param(implode('', $types), ...$vals);
$stmtC->execute();
$total  = (int)$stmtC->get_result()->fetch_assoc()['c'];
$stmtC->close();
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($total / $perPage));

// ── Fetch rows ──────────────────────────────────────────
$stmtR = db()->prepare("SELECT * FROM transactions $cond ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmtR->bind_param(implode('', $types) . 'ii', ...[...$vals, $perPage, $offset]);
$stmtR->execute();
$transactions = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtR->close();

// ── Summary totals ──────────────────────────────────────
$stmtS = db()->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id=? GROUP BY type");
$stmtS->bind_param('i', $userId);
$stmtS->execute();
$summaryRows = $stmtS->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtS->close();
$summary = [];
foreach ($summaryRows as $sr) $summary[$sr['type']] = (int)$sr['total'];

$totalIn     = ($summary['deposit'] ?? 0) + ($summary['bonus'] ?? 0) + ($summary['daily_reward'] ?? 0) + ($summary['ad_reward'] ?? 0) + ($summary['mission_reward'] ?? 0);
$totalOut    = abs($summary['withdraw'] ?? 0) + abs($summary['purchase'] ?? 0);
$totalProfit = $summary['profit'] ?? 0;
$totalRef    = $summary['referral_commission'] ?? 0;

$pageTitle = 'Riwayat Transaksi';
$baseUrl   = BASE_URL . '/pages/history.php?' . http_build_query(array_filter(['type'=>$filterType,'wallet'=>$filterWallet,'date_from'=>$dateFrom,'date_to'=>$dateTo]));
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
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
@media(max-width:768px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
.sum-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:18px}
.sum-card__label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);margin-bottom:8px}
.sum-card__val{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700}
.filter-bar{background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-bar label{font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px}
.filter-bar select,.filter-bar input{background:var(--bg-input,#151d30);border:1px solid var(--border-light);border-radius:8px;padding:8px 12px;color:var(--text-primary);font-size:13px;min-width:140px}
.trx-table{width:100%;border-collapse:collapse}
.trx-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);padding:10px 12px;border-bottom:1px solid var(--border-light);text-align:left}
.trx-table td{padding:12px;border-bottom:1px solid rgba(30,42,69,.4);font-size:13px;vertical-align:middle}
.trx-table tr:last-child td{border-bottom:none}
.trx-table tr:hover td{background:rgba(0,212,255,.03)}
.trx-type-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px}
.badge-wallet{display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;text-transform:uppercase}
.nox-pagination{display:flex;gap:6px;justify-content:center;margin-top:24px;flex-wrap:wrap}
.nox-page-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:var(--bg-card);border:1px solid var(--border-light);color:var(--text-primary);text-decoration:none;font-size:13px;font-weight:600;transition:.2s}
.nox-page-btn:hover,.nox-page-btn.active{background:var(--cyan);color:#000;border-color:var(--cyan)}
@media(max-width:640px){.trx-table-wrap{overflow-x:auto}.trx-table th:nth-child(3),.trx-table td:nth-child(3){display:none}}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<!-- PAGE HEADER -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">📋 Riwayat Transaksi</h1>
    <p style="color:var(--text-secondary);font-size:14px;margin:0">Semua aktivitas keuangan akun Anda</p>
  </div>
  <a href="?<?= http_build_query(array_filter(['type'=>$filterType,'wallet'=>$filterWallet,'date_from'=>$dateFrom,'date_to'=>$dateTo,'export'=>'csv'])) ?>" class="nox-btn nox-btn--outline nox-btn--sm">
    ⬇️ Export CSV
  </a>
</div>

<!-- SUMMARY CARDS -->
<div class="summary-grid">
  <div class="sum-card"><div class="sum-card__label">💚 Total Masuk</div><div class="sum-card__val" style="color:var(--green)"><?= formatRupiah($totalIn) ?></div></div>
  <div class="sum-card"><div class="sum-card__label">🔴 Total Keluar</div><div class="sum-card__val" style="color:var(--red)"><?= formatRupiah($totalOut) ?></div></div>
  <div class="sum-card"><div class="sum-card__label">💎 Profit Total</div><div class="sum-card__val" style="color:var(--cyan)"><?= formatRupiah($totalProfit) ?></div></div>
  <div class="sum-card"><div class="sum-card__label">👥 Komisi Total</div><div class="sum-card__val" style="color:var(--purple)"><?= formatRupiah($totalRef) ?></div></div>
</div>

<!-- FILTER BAR -->
<form method="GET" class="filter-bar">
  <div>
    <label>Tipe Transaksi</label>
    <select name="type">
      <option value="all" <?= $filterType==='all'?'selected':'' ?>>Semua Tipe</option>
      <?php foreach (['deposit'=>'💳 Deposit','withdraw'=>'💸 Withdraw','profit'=>'💎 Profit','referral_commission'=>'👥 Komisi Referral','purchase'=>'📦 Pembelian','bonus'=>'🎁 Bonus','daily_reward'=>'📅 Hadiah Harian','ad_reward'=>'▶️ Reward Iklan','mission_reward'=>'🎯 Reward Misi'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filterType===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Wallet</label>
    <select name="wallet">
      <option value="all" <?= $filterWallet==='all'?'selected':'' ?>>Semua Wallet</option>
      <option value="main" <?= $filterWallet==='main'?'selected':'' ?>>Utama</option>
      <option value="profit" <?= $filterWallet==='profit'?'selected':'' ?>>Profit</option>
      <option value="bonus" <?= $filterWallet==='bonus'?'selected':'' ?>>Bonus</option>
      <option value="referral" <?= $filterWallet==='referral'?'selected':'' ?>>Referral</option>
    </select>
  </div>
  <div>
    <label>Dari Tanggal</label>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
  </div>
  <div>
    <label>Sampai Tanggal</label>
    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
  </div>
  <div style="display:flex;gap:8px">
    <button type="submit" class="nox-btn nox-btn--primary nox-btn--sm">🔍 Filter</button>
    <a href="?" class="nox-btn nox-btn--outline nox-btn--sm">↺ Reset</a>
  </div>
</form>

<!-- TABLE -->
<div class="nox-card" style="padding:0;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between">
    <span style="font-weight:700;font-size:14px">Hasil: <?= number_format($total) ?> transaksi</span>
    <span style="font-size:12px;color:var(--text-secondary)">Hal <?= $page ?> dari <?= $totalPages ?></span>
  </div>
  <?php if (empty($transactions)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-secondary)">
      <div style="font-size:48px;margin-bottom:12px">📭</div>
      <div style="font-weight:600;margin-bottom:4px">Tidak ada transaksi</div>
      <div style="font-size:13px">Coba ubah filter pencarian</div>
    </div>
  <?php else: ?>
  <div class="trx-table-wrap">
  <table class="trx-table">
    <thead>
      <tr>
        <th>Tipe</th>
        <th>Deskripsi</th>
        <th>Wallet</th>
        <th>Nominal</th>
        <th>Tanggal</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $typeConfig = [
      'deposit'              => ['🟢', 'rgba(0,230,118,.12)', '#0A0E1A'],
      'withdraw'             => ['🔴', 'rgba(255,68,68,.12)', '#0A0E1A'],
      'profit'               => ['💎', 'rgba(0,212,255,.12)', '#0A0E1A'],
      'referral_commission'  => ['👥', 'rgba(123,47,255,.12)', '#0A0E1A'],
      'purchase'             => ['📦', 'rgba(255,179,0,.12)', '#0A0E1A'],
      'bonus'                => ['🎁', 'rgba(255,179,0,.12)', '#0A0E1A'],
      'daily_reward'         => ['📅', 'rgba(0,230,118,.12)', '#0A0E1A'],
      'ad_reward'            => ['▶️', 'rgba(0,212,255,.12)', '#0A0E1A'],
      'mission_reward'       => ['🎯', 'rgba(0,230,118,.12)', '#0A0E1A'],
    ];
    $walletColors = ['main'=>'var(--cyan)','profit'=>'var(--green)','bonus'=>'var(--amber)','referral'=>'var(--purple)'];
    foreach ($transactions as $t):
      $cfg    = $typeConfig[$t['type']] ?? ['📄','rgba(107,122,153,.12)','#0A0E1A'];
      $isPos  = (float)$t['amount'] >= 0;
      $wallet = $t['wallet_type'] ?? 'main';
      $wColor = $walletColors[$wallet] ?? 'var(--text-secondary)';
    ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="trx-type-icon" style="background:<?= $cfg[1] ?>"><?= $cfg[0] ?></div>
            <span style="font-weight:600"><?= htmlspecialchars(ucwords(str_replace('_',' ',$t['type']))) ?></span>
          </div>
        </td>
        <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-secondary)">
          <?= htmlspecialchars($t['description'] ?? '-') ?>
        </td>
        <td><span class="badge-wallet" style="background:<?= $wColor ?>22;color:<?= $wColor ?>"><?= htmlspecialchars(ucfirst($wallet)) ?></span></td>
        <td style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:<?= $isPos?'var(--green)':'var(--red)' ?>">
          <?= $isPos ? '+' : '' ?><?= formatRupiah((int)$t['amount']) ?>
        </td>
        <td style="color:var(--text-secondary);font-size:12px;white-space:nowrap"><?= htmlspecialchars(date('d M Y H:i', strtotime($t['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="nox-pagination">
  <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_filter(['type'=>$filterType,'wallet'=>$filterWallet,'date_from'=>$dateFrom,'date_to'=>$dateTo,'page'=>$page-1])) ?>" class="nox-page-btn">‹</a>
  <?php endif; ?>
  <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
    <a href="?<?= http_build_query(array_filter(['type'=>$filterType,'wallet'=>$filterWallet,'date_from'=>$dateFrom,'date_to'=>$dateTo,'page'=>$i])) ?>" class="nox-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?<?= http_build_query(array_filter(['type'=>$filterType,'wallet'=>$filterWallet,'date_from'=>$dateFrom,'date_to'=>$dateTo,'page'=>$page+1])) ?>" class="nox-page-btn">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body></html>
