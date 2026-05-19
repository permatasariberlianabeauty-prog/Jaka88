<?php
/**
 * NOXARA - Halaman Penarikan Dana (Withdraw)
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/vip.php';
require_once __DIR__ . '/../includes/notification.php';

requireLogin();
$user     = getCurrentUser();
$wallet   = getUserWallet((int)$user['id']);
$vipRules = getWithdrawVipRules((int)$user['id']);
$vipData  = getUserVipInfo((int)$user['id']);
$errors   = [];

/* ── Handle POST ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $rawAmount  = preg_replace('/[^0-9]/','',$_POST['amount'] ?? '');
    $amount     = (int)$rawAmount;
    $bankId     = (int)($_POST['bank_account_id'] ?? 0);
    $fromWallet = clean($_POST['from_wallet'] ?? 'main');
    $pin        = $_POST['pin'] ?? '';

    if ($amount <= 0)             $errors[] = 'Masukkan nominal penarikan yang valid.';
    if ($bankId <= 0)             $errors[] = 'Pilih rekening tujuan.';
    if (!validatePin($pin))       $errors[] = 'PIN harus 6 digit angka.';
    if (!in_array($fromWallet,['main','profit','bonus','referral'])) $fromWallet = 'main';

    if (empty($errors)) {
        $result = submitWithdrawal((int)$user['id'], $amount, $bankId, $fromWallet, $pin);
        if ($result['success']) {
            setFlash('success', 'Penarikan berhasil disubmit! Estimasi 1x24 jam kerja.');
            redirect('/withdraw?success=1');
        } else {
            $errors[] = $result['message'];
        }
    }
}

/* ── Data Pendukung ─────────────────────────────────── */
// Rekening bank member
$stmtBanks = db()->prepare("SELECT * FROM bank_accounts WHERE user_id=? AND is_active=1 ORDER BY is_primary DESC, created_at ASC");
$stmtBanks->bind_param("i",(int)$user['id']); $stmtBanks->execute();
$userBanks = $stmtBanks->get_result()->fetch_all(MYSQLI_ASSOC); $stmtBanks->close();

// Riwayat withdraw
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page-1)*$perPage;
$stmtCnt = db()->prepare("SELECT COUNT(*) as cnt FROM withdrawals WHERE user_id=?");
$stmtCnt->bind_param("i",(int)$user['id']); $stmtCnt->execute();
$totalWd = (int)$stmtCnt->get_result()->fetch_assoc()['cnt']; $stmtCnt->close();
$stmtWd  = db()->prepare("SELECT w.*, ba.bank_name, ba.account_number, ba.account_name FROM withdrawals w LEFT JOIN bank_accounts ba ON ba.id=w.bank_account_id WHERE w.user_id=? ORDER BY w.created_at DESC LIMIT ? OFFSET ?");
$stmtWd->bind_param("iii",(int)$user['id'],$perPage,$offset); $stmtWd->execute();
$wdHistory = $stmtWd->get_result()->fetch_all(MYSQLI_ASSOC); $stmtWd->close();
$pagination = paginate($totalWd, $perPage, $page, '/withdraw');

// Cek withdraw hari ini
$stmtTd = db()->prepare("SELECT COUNT(*) as cnt FROM withdrawals WHERE user_id=? AND DATE(created_at)=CURDATE()");
$stmtTd->bind_param("i",(int)$user['id']); $stmtTd->execute();
$todayWdCount = (int)$stmtTd->get_result()->fetch_assoc()['cnt']; $stmtTd->close();

$isWdOpen   = isWithdrawOpen();
$maxPerDay  = (int)getSetting('withdraw_max_per_day', 3);
$wdStart    = getSetting('withdraw_hour_start','08:00');
$wdEnd      = getSetting('withdraw_hour_end','17:00');
$wdDays     = getSetting('withdraw_days','1,2,3,4,5');
$dayNames   = ['1'=>'Sen','2'=>'Sel','3'=>'Rab','4'=>'Kam','5'=>'Jum','6'=>'Sab','7'=>'Min'];

$minWd      = (int)$vipRules['min_withdraw'];
$feePercent = (float)$vipRules['withdraw_fee_percent'];
$vipColor   = $vipRules['color'] ?? '#6B7A99';
$vipLevel   = (int)$vipRules['vip_level'];
$vipLabel   = $vipRules['badge_label'] ?? 'BASIC';

// Semua level VIP untuk tabel perbandingan
$stmtVips = db()->prepare("SELECT * FROM vip_levels WHERE is_active=1 ORDER BY level ASC");
$stmtVips->execute();
$allVipLevels = $stmtVips->get_result()->fetch_all(MYSQLI_ASSOC); $stmtVips->close();

$pageTitle = 'Tarik Dana';
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
<style>
.nox-page-header h1 { font-family:'Space Grotesk',sans-serif; font-size:24px; font-weight:700; margin:0 0 4px; }
.nox-breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-secondary); margin-bottom:20px; }
.nox-breadcrumb a { color:var(--cyan); text-decoration:none; }

/* VIP Table */
.nox-vip-table { width:100%; border-collapse:collapse; font-size:12px; }
.nox-vip-table th { padding:8px 10px; text-align:center; color:var(--text-secondary); font-weight:600; border-bottom:1px solid var(--border); font-size:11px; }
.nox-vip-table td { padding:8px 10px; text-align:center; border-bottom:1px solid rgba(30,42,69,0.3); }
.nox-vip-table tr:last-child td { border-bottom:none; }
.nox-vip-table tr.active-row td { font-weight:700; background:rgba(0,212,255,0.05); }

/* Wallet Selector */
.nox-wallet-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
@media(max-width:480px){ .nox-wallet-grid { grid-template-columns:1fr 1fr; } }
.nox-wallet-option { border:2px solid var(--border-light); border-radius:var(--radius-md); padding:14px; cursor:pointer; transition:var(--transition); }
.nox-wallet-option:hover,.nox-wallet-option.selected { border-color:var(--cyan); background:rgba(0,212,255,0.05); }
.nox-wallet-option input { display:none; }
.nox-wallet-option__label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-secondary); margin-bottom:5px; }
.nox-wallet-option__amount { font-family:'Space Grotesk',sans-serif; font-size:16px; font-weight:700; }

/* Bank Cards */
.nox-bank-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:10px; }
.nox-bank-card { background:var(--bg-card); border:2px solid var(--border-light); border-radius:var(--radius-md); padding:12px; cursor:pointer; transition:var(--transition); }
.nox-bank-card:hover,.nox-bank-card.selected { border-color:var(--cyan); background:rgba(0,212,255,0.05); }
.nox-bank-card input { display:none; }
.nox-bank-card__name { font-weight:700; font-size:12px; margin-bottom:2px; color:var(--cyan); }
.nox-bank-card__num { font-family:'Space Grotesk',sans-serif; font-size:13px; font-weight:600; margin-bottom:2px; }
.nox-bank-card__owner { font-size:11px; color:var(--text-secondary); }
.nox-bank-card__check { float:right; color:var(--cyan); display:none; }
.nox-bank-card.selected .nox-bank-card__check { display:block; }

/* PIN Input */
.nox-pin-input { display:flex; gap:8px; justify-content:center; margin:12px 0; }
.nox-pin-box { width:48px; height:56px; border:2px solid var(--border-light); border-radius:var(--radius-md); background:var(--bg-card); color:var(--text-primary); font-size:22px; font-weight:700; text-align:center; transition:var(--transition); outline:none; font-family:'Space Grotesk',sans-serif; }
.nox-pin-box:focus { border-color:var(--cyan); box-shadow:0 0 0 3px rgba(0,212,255,0.15); }
@media(max-width:360px){ .nox-pin-box{width:40px;height:50px;} }

/* Summary */
.nox-wd-summary { background:rgba(123,47,255,0.06); border:1px solid rgba(123,47,255,0.2); border-radius:var(--radius-md); padding:16px; }
.nox-wd-summary__row { display:flex; justify-content:space-between; padding:7px 0; font-size:13px; border-bottom:1px solid rgba(123,47,255,0.1); }
.nox-wd-summary__row:last-child { border-bottom:none; font-size:15px; font-weight:700; }
.nox-wd-summary__row--final { color:var(--green); }

/* Status Badges */
.nox-badge--pending   { background:rgba(255,179,0,0.15);  color:#FFB300; }
.nox-badge--approved  { background:rgba(0,230,118,0.15);  color:var(--green); }
.nox-badge--completed { background:rgba(0,230,118,0.15);  color:var(--green); }
.nox-badge--rejected  { background:rgba(255,68,68,0.15);   color:var(--red); }
.nox-badge--processing{ background:rgba(0,212,255,0.15);  color:var(--cyan); }

/* Table */
.nox-table { width:100%; border-collapse:collapse; font-size:13px; }
.nox-table th { padding:12px 14px; text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-secondary); border-bottom:1px solid var(--border); }
.nox-table td { padding:12px 14px; border-bottom:1px solid rgba(30,42,69,0.4); vertical-align:middle; }
.nox-table tr:last-child td { border-bottom:none; }
.nox-table-wrap { overflow-x:auto; }
@media(max-width:640px){ .nox-table th:nth-child(3),.nox-table td:nth-child(3){ display:none; } }

/* Status Operasional */
.nox-status-open  { background:rgba(0,230,118,0.08); border:1px solid rgba(0,230,118,0.25); border-radius:var(--radius-md); padding:14px 16px; }
.nox-status-close { background:rgba(255,68,68,0.08);  border:1px solid rgba(255,68,68,0.25);  border-radius:var(--radius-md); padding:14px 16px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <main class="nox-content nox-page-enter">
    <?= renderFlash() ?>

    <!-- ── Page Header ─────────────────────────────── -->
    <div class="nox-page-header"><h1>💸 Tarik Dana</h1></div>
    <div class="nox-breadcrumb">
      <a href="/dashboard">Dashboard</a> <span>›</span> <span>Tarik Dana</span>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:20px">
        <?php foreach ($errors as $e): ?>
          <div style="color:var(--red);font-size:13px;display:flex;align-items:center;gap:8px">⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">
    <div>


    <!-- ── VIP Info Card ────────────────────────────── -->
    <div class="nox-card" style="margin-bottom:18px;border-color:<?= htmlspecialchars($vipColor) ?>40;padding:18px 20px">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:14px">
        <div style="font-size:30px"><?= ['🔵','🥉','🥈','🥇','💎','👑'][$vipLevel] ?? '⭐' ?></div>
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
            <span style="font-weight:700;font-size:15px">VIP <?= $vipLevel ?></span>
            <span style="background:<?= htmlspecialchars($vipColor) ?>22;color:<?= htmlspecialchars($vipColor) ?>;font-size:10px;padding:2px 9px;border-radius:99px;font-weight:700"><?= htmlspecialchars($vipLabel) ?></span>
          </div>
          <div style="display:flex;gap:16px;font-size:12px;flex-wrap:wrap">
            <span style="color:var(--text-secondary)">Min WD: <strong style="color:var(--text-primary)"><?= formatRupiah($minWd) ?></strong></span>
            <span style="color:var(--text-secondary)">Fee Admin: <strong style="color:var(--red)"><?= number_format($feePercent,0) ?>%</strong></span>
          </div>
        </div>
        <a href="/vip" class="nox-btn nox-btn--sm nox-btn--outline" style="flex-shrink:0">Upgrade VIP</a>
      </div>
      <!-- Tabel perbandingan semua level VIP -->
      <div style="overflow-x:auto">
        <table class="nox-vip-table">
          <thead><tr>
            <th>Level</th><th>Min Deposit</th><th>Min WD</th><th>Fee WD</th>
          </tr></thead>
          <tbody>
          <?php foreach ($allVipLevels as $vl): ?>
            <tr class="<?= (int)$vl['level']===$vipLevel?'active-row':'' ?>">
              <td>
                <span style="background:<?= htmlspecialchars($vl['color']??'#6B7A99') ?>22;color:<?= htmlspecialchars($vl['color']??'#6B7A99') ?>;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700">VIP <?= (int)$vl['level'] ?></span>
              </td>
              <td><?= formatRupiah((int)$vl['min_deposit_cumulative']) ?></td>
              <td><?= formatRupiah((int)$vl['min_withdraw']) ?></td>
              <td style="color:<?= (float)$vl['withdraw_fee_percent']<5?'var(--green)':'var(--text-secondary)' ?>"><?= number_format((float)$vl['withdraw_fee_percent'],0) ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Status Operasional ───────────────────────── -->
    <div style="margin-bottom:18px">
      <?php if (!$isWdOpen): ?>
        <div class="nox-status-close">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:22px">🔴</span>
            <div>
              <div style="font-weight:700;color:var(--red);font-size:14px">Penarikan Sedang Ditutup</div>
              <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">
                Jam operasional: <?= htmlspecialchars($wdStart) ?>–<?= htmlspecialchars($wdEnd) ?> WIB &nbsp;·&nbsp;
                Hari: <?= implode(', ', array_filter(array_map(fn($d)=>$dayNames[trim($d)]??'', explode(',', $wdDays)))) ?>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="nox-status-open">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:22px">🟢</span>
            <div>
              <div style="font-weight:700;color:var(--green);font-size:14px">Penarikan Sedang Buka</div>
              <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">
                Kuota hari ini: <strong style="color:var(--text-primary)"><?= $todayWdCount ?>/<?= $maxPerDay ?></strong>
                &nbsp;·&nbsp; Jam: <?= htmlspecialchars($wdStart) ?>–<?= htmlspecialchars($wdEnd) ?> WIB
              </div>
            </div>
            <div style="margin-left:auto">
              <?php for($i=1;$i<=$maxPerDay;$i++): ?>
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $i<=$todayWdCount?'var(--red)':($i==$todayWdCount+1?'var(--green)':'rgba(255,255,255,0.1)') ?>;margin-left:4px"></span>
              <?php endfor; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Form Penarikan ───────────────────────────── -->
    <?php
    $formDisabled = !$isWdOpen || $todayWdCount >= $maxPerDay;
    $disabledMsg  = '';
    if (!$isWdOpen)                   $disabledMsg = 'Penarikan di luar jam operasional';
    elseif ($todayWdCount >= $maxPerDay) $disabledMsg = 'Batas penarikan harian telah tercapai ('.$maxPerDay.'x/hari)';
    ?>
    <?php if ($formDisabled): ?>
      <div style="background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:18px;font-size:13px;color:var(--red)">
        🚫 <?= htmlspecialchars($disabledMsg) ?>
      </div>
    <?php endif; ?>

    <div class="nox-card nox-card--glow" style="padding:24px">
      <h2 style="font-size:16px;font-weight:700;margin:0 0 20px">Form Penarikan</h2>
      <form method="POST" id="withdrawForm">
        <?= csrfField() ?>

        <!-- Pilih Sumber Saldo -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Sumber Saldo <span style="color:var(--red)">*</span></label>
          <div class="nox-wallet-grid">
            <?php
            $walletOpts = [
              'main'     => ['label'=>'Saldo Utama',   'color'=>'var(--cyan)',   'val'=>(int)$wallet['balance_main']],
              'profit'   => ['label'=>'Saldo Profit',  'color'=>'var(--green)',  'val'=>(int)$wallet['balance_profit']],
              'bonus'    => ['label'=>'Saldo Bonus',   'color'=>'var(--amber)',  'val'=>(int)$wallet['balance_bonus']],
              'referral' => ['label'=>'Saldo Referral','color'=>'var(--purple)', 'val'=>(int)$wallet['balance_referral']],
            ];
            $preselect = $_GET['from'] ?? 'main';
            foreach ($walletOpts as $wk => $wo):
            ?>
              <label class="nox-wallet-option <?= $wk===$preselect?'selected':'' ?>" onclick="selectWallet(this,'<?= $wk ?>')">
                <input type="radio" name="from_wallet" value="<?= $wk ?>" <?= $wk===$preselect?'checked':'' ?>>
                <div class="nox-wallet-option__label"><?= htmlspecialchars($wo['label']) ?></div>
                <div class="nox-wallet-option__amount" style="color:<?= $wo['color'] ?>"><?= formatRupiah($wo['val']) ?></div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Nominal -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Nominal Penarikan <span style="color:var(--red)">*</span></label>
          <div style="position:relative">
            <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-weight:600">Rp</span>
            <input type="text" id="wdAmount" name="amount" class="nox-input" style="padding-left:40px;font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700" placeholder="0" oninput="onWdAmountChange(this)" autocomplete="off" <?= $formDisabled?'disabled':'' ?>>
          </div>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:5px">
            Min: <?= formatRupiah($minWd) ?> &nbsp;·&nbsp; Fee Admin: <?= number_format($feePercent,0) ?>%
          </div>
          <!-- Quick Nominal -->
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <?php foreach([100000,250000,500000,1000000,2000000] as $q): ?>
              <button type="button" class="nox-btn-xs nox-btn-xs--purple" onclick="setWdAmount(<?= $q ?>)" <?= $formDisabled?'disabled':'' ?>><?= formatRupiah($q,false) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Pilih Rekening Tujuan -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Rekening Tujuan <span style="color:var(--red)">*</span></label>
          <?php if (empty($userBanks)): ?>
            <div style="background:rgba(255,179,0,0.08);border:1px solid rgba(255,179,0,0.25);border-radius:var(--radius-md);padding:14px;text-align:center">
              <div style="font-size:22px;margin-bottom:6px">🏦</div>
              <div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px">Belum ada rekening bank terdaftar</div>
              <a href="/bank-accounts" class="nox-btn nox-btn--primary nox-btn--sm">+ Tambah Rekening</a>
            </div>
          <?php else: ?>
          <div class="nox-bank-grid">
            <?php foreach ($userBanks as $bank): ?>
              <label class="nox-bank-card" onclick="selectWdBank(this)">
                <input type="radio" name="bank_account_id" value="<?= (int)$bank['id'] ?>" <?= !empty($bank['is_primary'])?'checked':'' ?>>
                <span class="nox-bank-card__check">✓</span>
                <div class="nox-bank-card__name"><?= htmlspecialchars($bank['bank_name']) ?></div>
                <div class="nox-bank-card__num"><?= htmlspecialchars(maskAccountNumber($bank['account_number'])) ?></div>
                <div class="nox-bank-card__owner">a.n. <?= htmlspecialchars($bank['account_name']) ?></div>
              </label>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:8px"><a href="/bank-accounts" style="font-size:12px;color:var(--cyan)">+ Tambah Rekening Baru</a></div>
          <?php endif; ?>
        </div>


        <!-- PIN Input -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">PIN Transaksi (6 Digit) <span style="color:var(--red)">*</span></label>
          <div class="nox-pin-input" id="pinBoxes">
            <?php for($p=1;$p<=6;$p++): ?>
              <input type="password" class="nox-pin-box" maxlength="1" pattern="[0-9]" inputmode="numeric"
                oninput="pinNext(this,<?= $p ?>)" onkeydown="pinBack(event,this,<?= $p ?>)"
                <?= $formDisabled?'disabled':'' ?>>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="pin" id="pinHidden">
          <div style="text-align:center;margin-top:6px"><a href="/security" style="font-size:12px;color:var(--cyan)">🔑 Lupa PIN?</a></div>
        </div>

        <!-- Summary -->
        <div class="nox-wd-summary" id="wdSummary" style="margin-bottom:20px;display:none">
          <div class="nox-wd-summary__row">
            <span style="color:var(--text-secondary)">Nominal Penarikan</span>
            <span id="ws_nominal">Rp 0</span>
          </div>
          <div class="nox-wd-summary__row">
            <span style="color:var(--text-secondary)">Biaya Admin (<?= number_format($feePercent,0) ?>%)</span>
            <span id="ws_fee" style="color:var(--red)">- Rp 0</span>
          </div>
          <div class="nox-wd-summary__row nox-wd-summary__row--final">
            <span>Yang Diterima</span>
            <span id="ws_receive" style="color:var(--green)">Rp 0</span>
          </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="nox-btn nox-btn--primary nox-btn--full" id="wdSubmitBtn" style="height:50px;font-size:15px;background:linear-gradient(135deg,var(--purple),#5b1fd1)"
          <?= $formDisabled || empty($userBanks) ? 'disabled' : '' ?>>
          💸 Ajukan Penarikan
        </button>
        <?php if (empty($userBanks)): ?>
          <div style="text-align:center;margin-top:8px;font-size:12px;color:var(--text-disabled)">Tambahkan rekening bank terlebih dahulu</div>
        <?php endif; ?>
      </form>
    </div>
    </div><!-- end left col -->

    <!-- ── Sidebar ─────────────────────────────────── -->
    <div style="position:sticky;top:24px">
      <!-- Total Saldo -->
      <div class="nox-card" style="padding:18px;margin-bottom:14px">
        <div style="font-weight:700;font-size:13px;margin-bottom:14px">💰 Saldo Tersedia</div>
        <?php foreach ($walletOpts as $wk => $wo): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid rgba(30,42,69,0.4);font-size:13px" <?= $wk==='referral'?'style="display:flex;justify-content:space-between;border:none;padding-top:7px"':'' ?>>
            <span style="color:var(--text-secondary)"><?= htmlspecialchars($wo['label']) ?></span>
            <span style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:<?= $wo['color'] ?>"><?= formatRupiah($wo['val']) ?></span>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0 0;font-size:14px;font-weight:700">
          <span>Total</span>
          <span style="font-family:'Space Grotesk',sans-serif;color:var(--cyan)"><?= formatRupiah((int)$wallet['balance_main']+(int)$wallet['balance_profit']+(int)$wallet['balance_bonus']+(int)$wallet['balance_referral']) ?></span>
        </div>
      </div>

      <!-- Info Penarikan -->
      <div class="nox-card" style="padding:18px;background:rgba(123,47,255,0.04);border-color:rgba(123,47,255,0.2)">
        <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:var(--purple)">ℹ️ Info Penarikan</div>
        <div style="font-size:12px;color:var(--text-secondary);line-height:1.8">
          ✅ Proses 1x24 jam kerja<br>
          ✅ Maks <?= $maxPerDay ?>x penarikan/hari<br>
          ✅ Min: <?= formatRupiah($minWd) ?><br>
          ⚠️ Fee admin: <?= number_format($feePercent,0) ?>% dari nominal<br>
          ⚠️ Upgrade VIP untuk fee lebih kecil
        </div>
      </div>
    </div>
    </div><!-- end grid -->

    <!-- ── Riwayat Penarikan ─────────────────────────── -->
    <div style="margin-top:28px;margin-bottom:32px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <h2 style="font-size:15px;font-weight:700;margin:0">🕐 Riwayat Penarikan</h2>
        <span style="font-size:12px;color:var(--text-secondary)"><?= $totalWd ?> transaksi</span>
      </div>
      <div class="nox-card" style="padding:0;overflow:hidden">
        <?php if (empty($wdHistory)): ?>
          <div style="text-align:center;padding:32px;color:var(--text-secondary);font-size:13px">Belum ada riwayat penarikan</div>
        <?php else: ?>
        <div class="nox-table-wrap">
          <table class="nox-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nominal</th>
                <th>Rekening</th>
                <th>Diterima</th>
                <th>Status</th>
                <th>Tanggal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($wdHistory as $wd): ?>
                <tr>
                  <td style="font-family:'Space Grotesk',sans-serif;font-size:12px;font-weight:600"><?= htmlspecialchars($wd['withdrawal_code']) ?></td>
                  <td style="font-weight:700"><?= formatRupiah((int)$wd['amount']) ?></td>
                  <td style="font-size:12px">
                    <div style="font-weight:600"><?= htmlspecialchars($wd['bank_name'] ?? '-') ?></div>
                    <div style="color:var(--text-disabled)"><?= htmlspecialchars(maskAccountNumber($wd['account_number'] ?? '')) ?></div>
                  </td>
                  <td style="font-weight:700;color:var(--green)"><?= formatRupiah((int)$wd['amount_received']) ?></td>
                  <td>
                    <?php
                    $bMap=['pending'=>'nox-badge--pending','approved'=>'nox-badge--approved','completed'=>'nox-badge--completed','rejected'=>'nox-badge--rejected','processing'=>'nox-badge--processing'];
                    $lMap=['pending'=>'Menunggu','approved'=>'Disetujui','completed'=>'Selesai','rejected'=>'Ditolak','processing'=>'Diproses'];
                    $bc=$bMap[$wd['status']]??''; $bl=$lMap[$wd['status']]??ucfirst($wd['status']);
                    ?>
                    <span class="nox-badge <?= $bc ?>" style="font-size:11px;padding:3px 10px;border-radius:99px"><?= $bl ?></span>
                  </td>
                  <td style="font-size:12px;color:var(--text-secondary)"><?= formatDateTime($wd['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
          <div style="padding:12px 16px;border-top:1px solid var(--border)"><?= renderPagination($pagination) ?></div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>


<script>
const FEE_PERCENT = <?= $feePercent ?>;
const MIN_WD = <?= $minWd ?>;
const WALLET_BALANCES = {
  main:     <?= (int)$wallet['balance_main'] ?>,
  profit:   <?= (int)$wallet['balance_profit'] ?>,
  bonus:    <?= (int)$wallet['balance_bonus'] ?>,
  referral: <?= (int)$wallet['balance_referral'] ?>
};
let selectedWallet = '<?= htmlspecialchars($_GET['from'] ?? 'main') ?>';

/* ── Format Rupiah ─── */
function fmtRp(n){ return 'Rp ' + Math.floor(n).toLocaleString('id-ID'); }

/* ── Pilih Sumber Saldo ─── */
function selectWallet(el, wk){
  document.querySelectorAll('.nox-wallet-option').forEach(c=>c.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input[type=radio]').checked = true;
  selectedWallet = wk;
  const cur = parseInt(document.getElementById('wdAmount').value.replace(/\D/g,''),10)||0;
  onWdAmountChange(document.getElementById('wdAmount'));
}

/* ── Pilih Bank ─── */
function selectWdBank(el){
  document.querySelectorAll('.nox-bank-card').forEach(c=>c.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input[type=radio]').checked = true;
}

/* ── Set Quick Amount ─── */
function setWdAmount(val){
  const inp = document.getElementById('wdAmount');
  inp.value = val.toLocaleString('id-ID');
  onWdAmountChange(inp);
}

/* ── On Amount Change ─── */
function onWdAmountChange(inp){
  const raw = parseInt(inp.value.replace(/\D/g,''),10)||0;
  inp.value = raw > 0 ? raw.toLocaleString('id-ID') : '';
  const fee = Math.floor(raw * FEE_PERCENT / 100);
  const receive = raw - fee;
  const avail = WALLET_BALANCES[selectedWallet]||0;
  const btn = document.getElementById('wdSubmitBtn');

  document.getElementById('wdSummary').style.display = raw > 0 ? 'block' : 'none';
  document.getElementById('ws_nominal').textContent = fmtRp(raw);
  document.getElementById('ws_fee').textContent = '- ' + fmtRp(fee);
  document.getElementById('ws_receive').textContent = fmtRp(Math.max(0,receive));

  // Validasi realtime
  let errMsg = '';
  if (raw > 0 && raw < MIN_WD) errMsg = 'Min penarikan '+fmtRp(MIN_WD);
  else if (raw > avail) errMsg = 'Saldo '+fmtRp(avail)+' tidak cukup';
  if (btn) {
    if (errMsg || raw <= 0) {
      btn.disabled = true;
      btn.title = errMsg;
    } else {
      btn.disabled = <?= $formDisabled ? 'true' : 'false' ?>;
      btn.title = '';
    }
  }
  // Tampilkan warning saldo
  let warningEl = document.getElementById('wdBalanceWarn');
  if (!warningEl) {
    warningEl = document.createElement('div');
    warningEl.id = 'wdBalanceWarn';
    warningEl.style.cssText = 'font-size:12px;color:var(--red);margin-top:5px';
    document.getElementById('wdAmount').parentElement.after(warningEl);
  }
  warningEl.textContent = errMsg;
}

/* ── PIN Input Handling ─── */
function pinNext(inp, idx){
  const v = inp.value.replace(/\D/g,'');
  inp.value = v ? v[0] : '';
  updatePinHidden();
  if(v && idx < 6){
    const boxes = document.querySelectorAll('.nox-pin-box');
    boxes[idx].focus();
  }
}
function pinBack(e, inp, idx){
  if(e.key==='Backspace' && !inp.value && idx > 1){
    document.querySelectorAll('.nox-pin-box')[idx-2].focus();
  }
}
function updatePinHidden(){
  const boxes = document.querySelectorAll('.nox-pin-box');
  let pin = '';
  boxes.forEach(b=>pin+=b.value);
  document.getElementById('pinHidden').value = pin;
}

/* ── Auto-select primary bank ─── */
window.addEventListener('DOMContentLoaded', ()=>{
  const primary = document.querySelector('.nox-bank-card input[checked]');
  if(primary) primary.closest('.nox-bank-card').classList.add('selected');
  else {
    const first = document.querySelector('.nox-bank-card');
    if(first) { first.classList.add('selected'); first.querySelector('input').checked=true; }
  }
});

/* ── CSS tambahan untuk btn-xs ─── */
const s = document.createElement('style');
s.textContent='.nox-btn-xs{padding:5px 12px;font-size:11px;font-weight:600;border-radius:6px;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:.2s}.nox-btn-xs--purple{background:rgba(123,47,255,0.15);color:var(--purple)}.nox-btn-xs--purple:hover{background:var(--purple);color:#fff}';
document.head.appendChild(s);

/* ── Logout ─── */
function confirmLogout(){ if(confirm('Yakin keluar?')) location.href='/logout'; }
</script>
</body>
</html>
