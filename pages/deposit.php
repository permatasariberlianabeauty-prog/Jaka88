<?php
/**
 * NOXARA - Halaman Deposit Member
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/notification.php';

requireLogin();
$user   = getCurrentUser();
$errors = [];
$success = false;
$depositResult = null;

/* ── Handle AJAX: Validate Voucher ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'validate_voucher') {
    header('Content-Type: application/json');
    $code = clean($_POST['code'] ?? '');
    if (empty($code)) { echo json_encode(['valid'=>false,'message'=>'Kode kosong']); exit; }
    $stmt = db()->prepare("SELECT * FROM vouchers WHERE code=? AND is_active=1 AND (type='deposit' OR type='general') AND valid_from<=NOW() AND valid_until>=NOW() LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $v = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$v) { echo json_encode(['valid'=>false,'message'=>'Voucher tidak valid atau sudah expired']); exit; }
    if ($v['usage_limit'] > 0 && $v['usage_count'] >= $v['usage_limit']) {
        echo json_encode(['valid'=>false,'message'=>'Voucher sudah habis']); exit;
    }
    echo json_encode(['valid'=>true,'message'=>'Voucher valid! Bonus: '.$v['discount_type'].':'.$v['discount_value'],'id'=>$v['id'],'discount_type'=>$v['discount_type'],'discount_value'=>(float)$v['discount_value'],'max_discount'=>(int)$v['max_discount'],'min_amount'=>(int)$v['min_amount']]);
    exit;
}

/* ── Handle POST: Submit Deposit ──────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    requireCsrf();
    $rawAmount = preg_replace('/[^0-9]/', '', $_POST['amount'] ?? '');
    $amount    = (int)$rawAmount;
    $bankId    = (int)($_POST['bank_id'] ?? 0);
    $voucherId = !empty($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : null;
    $proofFile = null;

    if ($amount <= 0)   $errors[] = 'Masukkan nominal deposit yang valid.';
    if ($bankId <= 0)   $errors[] = 'Pilih rekening bank tujuan.';

    // Upload bukti transfer
    if (empty($errors) && !empty($_FILES['proof']['name'])) {
        $proofFile = uploadImage($_FILES['proof'], 'deposits', 'dep_'.(int)$user['id']);
        if (!$proofFile) $errors[] = 'Format gambar tidak valid. Gunakan JPG/PNG max 5MB.';
    }

    if (empty($errors)) {
        $result = submitDeposit((int)$user['id'], $amount, $bankId, $proofFile, $voucherId);
        if ($result['success']) {
            $_SESSION['last_deposit'] = $result;
            setFlash('success', 'Deposit berhasil disubmit! Kode: '.$result['deposit_code']);
            redirect('/deposit?success=1&code='.urlencode($result['deposit_code']));
        } else {
            $errors[] = $result['message'];
        }
    }
}

/* ── Data Pendukung ─────────────────────────────────── */
// Rekening bank admin
$stmtBanks = db()->prepare("SELECT * FROM admin_bank_accounts WHERE is_active=1 ORDER BY sort_order ASC");
$stmtBanks->execute();
$adminBanks = $stmtBanks->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtBanks->close();

// Deposit pending yang belum expired
$stmtPend = db()->prepare("SELECT d.*, ab.bank_name, ab.account_number, ab.account_name FROM deposits d LEFT JOIN admin_bank_accounts ab ON ab.id=d.bank_target_id WHERE d.user_id=? AND d.status='pending' AND d.expires_at>NOW() ORDER BY d.created_at DESC LIMIT 1");
$stmtPend->bind_param("i", (int)$user['id']);
$stmtPend->execute();
$pendingDeposit = $stmtPend->get_result()->fetch_assoc();
$stmtPend->close();

// Riwayat deposit
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page-1)*$perPage;
$stmtCnt = db()->prepare("SELECT COUNT(*) as cnt FROM deposits WHERE user_id=?");
$stmtCnt->bind_param("i",(int)$user['id']); $stmtCnt->execute();
$totalDep = (int)$stmtCnt->get_result()->fetch_assoc()['cnt']; $stmtCnt->close();
$stmtHist = db()->prepare("SELECT d.*, ab.bank_name, ab.account_number FROM deposits d LEFT JOIN admin_bank_accounts ab ON ab.id=d.bank_target_id WHERE d.user_id=? ORDER BY d.created_at DESC LIMIT ? OFFSET ?");
$stmtHist->bind_param("iii",(int)$user['id'],$perPage,$offset); $stmtHist->execute();
$depositHistory = $stmtHist->get_result()->fetch_all(MYSQLI_ASSOC); $stmtHist->close();
$pagination = paginate($totalDep, $perPage, $page, '/deposit');

$minDep   = (int)getSetting('min_deposit', 10000);
$maxDep   = (int)getSetting('max_deposit', 50000000);
$expHours = (int)getSetting('deposit_expiry_hours', 3);
$pageTitle = 'Isi Ulang (Deposit)';
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
.nox-page-header { margin-bottom: 6px; }
.nox-page-header h1 { font-family:'Space Grotesk',sans-serif; font-size:24px; font-weight:700; margin:0 0 4px; }
.nox-breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-secondary); margin-bottom:20px; }
.nox-breadcrumb a { color:var(--cyan); text-decoration:none; }

/* Steps */
.nox-steps { display:flex; gap:0; margin-bottom:0; }
.nox-step { flex:1; padding:12px 10px; text-align:center; font-size:11px; font-weight:600; color:var(--text-disabled); border-bottom:2px solid transparent; }
.nox-step__num { width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;margin:0 auto 5px; }

/* Bank Cards */
.nox-bank-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px; }
.nox-bank-card { background:var(--bg-card); border:2px solid var(--border-light); border-radius:var(--radius-md); padding:14px; cursor:pointer; transition:var(--transition); }
.nox-bank-card:hover, .nox-bank-card.selected { border-color:var(--cyan); background:rgba(0,212,255,0.05); }
.nox-bank-card input[type=radio] { display:none; }
.nox-bank-card__name { font-weight:700; font-size:13px; margin-bottom:3px; color:var(--cyan); }
.nox-bank-card__num { font-family:'Space Grotesk',sans-serif; font-size:14px; font-weight:600; margin-bottom:2px; }
.nox-bank-card__owner { font-size:11px; color:var(--text-secondary); }
.nox-bank-card__check { float:right; color:var(--cyan); font-size:18px; display:none; }
.nox-bank-card.selected .nox-bank-card__check { display:block; }

/* Upload Zone */
.nox-upload-zone { border:2px dashed var(--border-light); border-radius:var(--radius-md); padding:30px; text-align:center; cursor:pointer; transition:var(--transition); position:relative; }
.nox-upload-zone:hover, .nox-upload-zone.dragging { border-color:var(--cyan); background:rgba(0,212,255,0.04); }
.nox-upload-zone input { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
.nox-upload-preview { width:100%;max-height:200px;object-fit:contain;border-radius:8px;margin-top:12px;display:none; }

/* Summary */
.nox-dep-summary { background:rgba(0,212,255,0.05); border:1px solid rgba(0,212,255,0.2); border-radius:var(--radius-md); padding:16px; }
.nox-dep-summary__row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:13px; border-bottom:1px solid rgba(0,212,255,0.1); }
.nox-dep-summary__row:last-child { border-bottom:none; font-size:15px; font-weight:700; }
.nox-dep-summary__row--total { color:var(--cyan); }

/* Status Badge */
.nox-badge--pending   { background:rgba(255,179,0,0.15); color:#FFB300; }
.nox-badge--confirmed { background:rgba(0,230,118,0.15); color:var(--green); }
.nox-badge--rejected  { background:rgba(255,68,68,0.15);  color:var(--red); }
.nox-badge--expired   { background:rgba(107,122,153,0.15);color:var(--text-disabled); }

/* Table */
.nox-table { width:100%; border-collapse:collapse; font-size:13px; }
.nox-table th { padding:12px 14px; text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-secondary); border-bottom:1px solid var(--border); }
.nox-table td { padding:12px 14px; border-bottom:1px solid rgba(30,42,69,0.4); vertical-align:middle; }
.nox-table tr:last-child td { border-bottom:none; }
.nox-table-wrap { overflow-x:auto; }
@media(max-width:640px){ .nox-table th:nth-child(3),.nox-table td:nth-child(3){ display:none; } }

/* Collapse */
.nox-collapse-btn { background:none; border:none; color:var(--cyan); font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; }
.nox-collapse-content { overflow:hidden; transition:max-height 0.35s ease; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <main class="nox-content nox-page-enter">
    <?= renderFlash() ?>

    <!-- ── Page Header ─────────────────────────────── -->
    <div class="nox-page-header"><h1>💳 Isi Ulang Saldo</h1></div>
    <div class="nox-breadcrumb">
      <a href="/dashboard">Dashboard</a> <span>›</span> <span>Isi Ulang</span>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:20px">
        <?php foreach ($errors as $e): ?>
          <div style="color:var(--red);font-size:13px;display:flex;align-items:center;gap:8px">⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- ── Deposit Pending Warning ──────────────────── -->
    <?php if ($pendingDeposit): ?>
    <div style="background:rgba(255,179,0,0.08);border:1px solid rgba(255,179,0,0.3);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="font-size:20px">⏳</span>
        <div>
          <div style="font-weight:700;color:#FFB300">Deposit Tertunda</div>
          <div style="font-size:12px;color:var(--text-secondary)">Kode: <strong><?= htmlspecialchars($pendingDeposit['deposit_code']) ?></strong></div>
        </div>
        <div style="margin-left:auto;text-align:right">
          <div style="font-size:11px;color:var(--text-secondary)">Expired</div>
          <div id="pendingExpiry" data-expiry="<?= htmlspecialchars($pendingDeposit['expires_at']) ?>" style="font-weight:700;color:#FFB300">--:--:--</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px;margin-bottom:14px">
        <div><span style="color:var(--text-secondary)">Nominal:</span> <strong><?= formatRupiah((int)$pendingDeposit['amount']) ?></strong></div>
        <div><span style="color:var(--text-secondary)">Kode Unik:</span> <strong>+<?= (int)$pendingDeposit['unique_code'] ?></strong></div>
        <div><span style="color:var(--text-secondary)">Total Transfer:</span> <strong style="color:var(--cyan)"><?= formatRupiah((int)$pendingDeposit['total_amount']) ?></strong></div>
        <div><span style="color:var(--text-secondary)">Bank:</span> <strong><?= htmlspecialchars($pendingDeposit['bank_name'] ?? '-') ?></strong></div>
      </div>
      <div style="background:rgba(255,179,0,0.1);border-radius:8px;padding:10px 12px;font-size:12px;color:#FFB300;margin-bottom:12px">
        ⚠️ Transfer PERSIS <strong><?= formatRupiah((int)$pendingDeposit['total_amount']) ?></strong> ke rekening di atas (termasuk kode unik)
      </div>
      <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin membatalkan deposit ini?')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cancel_deposit">
        <input type="hidden" name="deposit_id" value="<?= (int)$pendingDeposit['id'] ?>">
        <button type="submit" class="nox-btn nox-btn--sm" style="background:rgba(255,68,68,0.15);color:var(--red);border-color:var(--red)">❌ Batalkan Deposit</button>
      </form>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">
    <div>
    <!-- ── Cara Deposit (Collapsible) ──────────────── -->
    <div class="nox-card" style="margin-bottom:20px;padding:0;overflow:hidden">
      <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;cursor:pointer" onclick="toggleGuide()">
        <div style="font-weight:700;font-size:14px">📖 Cara Deposit</div>
        <button class="nox-collapse-btn" id="guideBtn">▼ Lihat Panduan</button>
      </div>
      <div class="nox-collapse-content" id="guideContent" style="max-height:0">
        <div style="padding:0 20px 20px">
          <div class="nox-steps">
            <?php $steps=[['1','Pilih nominal & rekening bank tujuan'],['2','Transfer dengan nominal PERSIS (termasuk kode unik)'],['3','Upload bukti transfer atau screenshot'],['4','Tunggu konfirmasi admin (maks '.$expHours.' jam)']];
            foreach($steps as [$n,$txt]): ?>
              <div class="nox-step">
                <div class="nox-step__num" style="background:rgba(0,212,255,0.15);color:var(--cyan)"><?= $n ?></div>
                <div><?= htmlspecialchars($txt) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:14px;background:rgba(123,47,255,0.08);border:1px solid rgba(123,47,255,0.2);border-radius:8px;padding:12px;font-size:12px;color:var(--text-secondary)">
            💡 <strong>Kode Unik</strong> adalah 3 digit angka tambahan agar transaksi bisa diidentifikasi otomatis oleh sistem. Contoh: nominal Rp 50.000 + kode 123 = transfer <strong>Rp 50.123</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Form Deposit ─────────────────────────────── -->
    <div class="nox-card nox-card--glow" style="padding:24px">
      <h2 style="font-size:16px;font-weight:700;margin:0 0 20px">Form Deposit</h2>
      <form method="POST" enctype="multipart/form-data" id="depositForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="submit">
        <input type="hidden" name="voucher_id" id="voucher_id_hidden">

        <!-- Nominal -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Nominal Deposit <span style="color:var(--red)">*</span></label>
          <div style="position:relative">
            <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-weight:600;font-size:14px">Rp</span>
            <input type="text" id="amountInput" name="amount" class="nox-input" style="padding-left:40px;font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700" placeholder="0" autocomplete="off" oninput="onAmountChange(this)">
          </div>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:5px">Min <?= formatRupiah($minDep) ?> · Maks <?= formatRupiah($maxDep) ?></div>
          <!-- Quick Nominal -->
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <?php foreach([50000,100000,200000,500000,1000000] as $q): ?>
              <button type="button" class="nox-btn-xs nox-btn-xs--cyan" onclick="setAmount(<?= $q ?>)"><?= formatRupiah($q,false) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Kode Unik Info -->
        <div id="uniqueCodeInfo" style="display:none;margin-bottom:18px">
          <div style="background:rgba(0,212,255,0.05);border:1px solid var(--cyan);border-radius:var(--radius-md);padding:14px 16px">
            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">💡 Transfer PERSIS nominal berikut:</div>
            <div style="font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;color:var(--cyan)" id="transferTotal">Rp 0</div>
            <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Nominal <span id="nominalDisplay">0</span> + kode unik <span id="uniqueCodeDisplay" style="color:var(--cyan);font-weight:700">0</span></div>
          </div>
        </div>

        <!-- Pilih Bank -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Transfer ke Rekening <span style="color:var(--red)">*</span></label>
          <?php if (empty($adminBanks)): ?>
            <div style="color:var(--text-secondary);font-size:13px;padding:14px;background:var(--bg-card2);border-radius:var(--radius-md)">Tidak ada rekening tersedia saat ini</div>
          <?php else: ?>
          <div class="nox-bank-grid">
            <?php foreach ($adminBanks as $bank): ?>
              <label class="nox-bank-card" onclick="selectBank(this)">
                <input type="radio" name="bank_id" value="<?= (int)$bank['id'] ?>">
                <span class="nox-bank-card__check">✓</span>
                <div class="nox-bank-card__name"><?= htmlspecialchars($bank['bank_name']) ?></div>
                <div class="nox-bank-card__num"><?= htmlspecialchars($bank['account_number']) ?></div>
                <div class="nox-bank-card__owner">a.n. <?= htmlspecialchars($bank['account_name']) ?></div>
              </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Voucher -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Kode Voucher <span style="color:var(--text-disabled)">(Opsional)</span></label>
          <div style="display:flex;gap:8px">
            <input type="text" id="voucherCode" class="nox-input" placeholder="Masukkan kode voucher" style="flex:1;text-transform:uppercase">
            <button type="button" class="nox-btn nox-btn--outline nox-btn--sm" onclick="validateVoucher()" style="white-space:nowrap">Gunakan</button>
          </div>
          <div id="voucherResult" style="margin-top:8px;font-size:12px"></div>
        </div>

        <!-- Upload Bukti -->
        <div class="nox-form-group" style="margin-bottom:18px">
          <label class="nox-label">Upload Bukti Transfer</label>
          <div class="nox-upload-zone" id="uploadZone">
            <input type="file" name="proof" accept="image/jpeg,image/png,image/jpg" onchange="previewProof(this)" id="proofInput">
            <div id="uploadPlaceholder">
              <div style="font-size:32px;margin-bottom:8px">📤</div>
              <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">Klik atau seret gambar ke sini</div>
              <div style="font-size:11px;color:var(--text-disabled);margin-top:4px">JPG, PNG · Maks 5MB</div>
            </div>
            <img id="uploadPreview" class="nox-upload-preview" alt="Preview">
          </div>
        </div>


        <!-- Summary -->
        <div class="nox-dep-summary" id="depositSummary" style="margin-bottom:20px;display:none">
          <div class="nox-dep-summary__row">
            <span style="color:var(--text-secondary)">Nominal</span>
            <span id="sum_nominal">Rp 0</span>
          </div>
          <div class="nox-dep-summary__row">
            <span style="color:var(--text-secondary)">Kode Unik</span>
            <span id="sum_code" style="color:var(--cyan)">+ Rp 0</span>
          </div>
          <div class="nox-dep-summary__row" id="sum_voucher_row" style="display:none">
            <span style="color:var(--text-secondary)">Bonus Voucher</span>
            <span id="sum_voucher" style="color:var(--green)">+ Rp 0</span>
          </div>
          <div class="nox-dep-summary__row nox-dep-summary__row--total">
            <span>Total Transfer</span>
            <span id="sum_total">Rp 0</span>
          </div>
        </div>

        <button type="submit" class="nox-btn nox-btn--primary nox-btn--full" id="submitBtn" style="height:50px;font-size:15px">
          💳 Submit Deposit
        </button>
      </form>
    </div>
    </div>

    <!-- ── Sidebar Info ─────────────────────────────── -->
    <div style="position:sticky;top:24px">
      <div class="nox-card" style="padding:18px;margin-bottom:16px">
        <div style="font-weight:700;font-size:13px;margin-bottom:12px">📊 Saldo Saat Ini</div>
        <?php $w = getUserWallet((int)$user['id']); ?>
        <div style="font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;color:var(--cyan)"><?= formatRupiah((int)$w['balance_main']) ?></div>
        <div style="font-size:11px;color:var(--text-secondary);margin-top:2px">Saldo Utama</div>
      </div>
      <div class="nox-card" style="padding:18px;background:rgba(0,212,255,0.04);border-color:rgba(0,212,255,0.2)">
        <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:var(--cyan)">ℹ️ Informasi</div>
        <div style="font-size:12px;color:var(--text-secondary);line-height:1.7">
          ✅ Konfirmasi dalam <?= $expHours ?> jam<br>
          ✅ Minimal deposit <?= formatRupiah($minDep) ?><br>
          ✅ Maks <?= formatRupiah($maxDep) ?><br>
          ✅ Bukti transfer opsional<br>
          ⚠️ Kode unik WAJIB disertakan
        </div>
      </div>
    </div>
    </div><!-- end grid -->

    <!-- ── Riwayat Deposit ──────────────────────────── -->
    <div style="margin-top:28px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <h2 style="font-size:15px;font-weight:700;margin:0">🕐 Riwayat Deposit</h2>
        <span style="font-size:12px;color:var(--text-secondary)"><?= $totalDep ?> transaksi</span>
      </div>
      <div class="nox-card" style="padding:0;overflow:hidden">
        <?php if (empty($depositHistory)): ?>
          <div style="text-align:center;padding:32px;color:var(--text-secondary);font-size:13px">Belum ada riwayat deposit</div>
        <?php else: ?>
        <div class="nox-table-wrap">
          <table class="nox-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nominal</th>
                <th>Bank</th>
                <th>Status</th>
                <th>Tanggal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($depositHistory as $dep): ?>
                <tr>
                  <td style="font-family:'Space Grotesk',sans-serif;font-size:12px;font-weight:600"><?= htmlspecialchars($dep['deposit_code']) ?></td>
                  <td style="font-weight:700"><?= formatRupiah((int)$dep['amount']) ?>
                    <?php if ($dep['unique_code'] > 0): ?>
                      <span style="font-size:10px;color:var(--cyan)">+<?= (int)$dep['unique_code'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px"><?= htmlspecialchars($dep['bank_name'] ?? '-') ?></td>
                  <td>
                    <?php
                    $badgeMap = ['pending'=>'nox-badge--pending','confirmed'=>'nox-badge--confirmed','rejected'=>'nox-badge--rejected','expired'=>'nox-badge--expired'];
                    $labelMap = ['pending'=>'Menunggu','confirmed'=>'Dikonfirmasi','rejected'=>'Ditolak','expired'=>'Expired'];
                    $cls  = $badgeMap[$dep['status']] ?? '';
                    $lbl  = $labelMap[$dep['status']] ?? ucfirst($dep['status']);
                    ?>
                    <span class="nox-badge <?= $cls ?>" style="font-size:11px;padding:3px 10px;border-radius:99px"><?= $lbl ?></span>
                  </td>
                  <td style="font-size:12px;color:var(--text-secondary)"><?= formatDateTime($dep['created_at']) ?></td>
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
/* ── Kode unik acak sekali load ─── */
const UNIQUE_CODE = <?= rand(100,999) ?>;
let voucherData = null;

/* ── Format Rupiah JS ─── */
function fmtRp(n){ return 'Rp ' + Math.floor(n).toLocaleString('id-ID'); }

/* ── Set Nominal Cepat ─── */
function setAmount(val){
  const inp = document.getElementById('amountInput');
  inp.value = val.toLocaleString('id-ID');
  onAmountChange(inp);
}

/* ── On Amount Change ─── */
function onAmountChange(inp){
  const raw = parseInt(inp.value.replace(/\D/g,''),10)||0;
  inp.value = raw > 0 ? raw.toLocaleString('id-ID') : '';
  const total = raw + UNIQUE_CODE;
  document.getElementById('uniqueCodeInfo').style.display = raw>0 ? 'block' : 'none';
  document.getElementById('transferTotal').textContent = raw > 0 ? fmtRp(total) : 'Rp 0';
  document.getElementById('nominalDisplay').textContent = fmtRp(raw);
  document.getElementById('uniqueCodeDisplay').textContent = UNIQUE_CODE;
  updateSummary(raw);
}

/* ── Update Summary ─── */
function updateSummary(raw){
  const total = raw + UNIQUE_CODE;
  let bonus = 0;
  if(voucherData && raw >= voucherData.min_amount){
    if(voucherData.discount_type==='percent'){
      bonus = Math.floor(raw * voucherData.discount_value / 100);
      if(voucherData.max_discount>0) bonus = Math.min(bonus,voucherData.max_discount);
    } else { bonus = voucherData.discount_value; }
  }
  document.getElementById('depositSummary').style.display = raw>0 ? 'block' : 'none';
  document.getElementById('sum_nominal').textContent = fmtRp(raw);
  document.getElementById('sum_code').textContent = '+ Rp ' + UNIQUE_CODE.toLocaleString('id-ID');
  document.getElementById('sum_total').textContent = fmtRp(total);
  if(bonus > 0){
    document.getElementById('sum_voucher_row').style.display = 'flex';
    document.getElementById('sum_voucher').textContent = '+ ' + fmtRp(bonus);
  } else {
    document.getElementById('sum_voucher_row').style.display = 'none';
  }
}

/* ── Select Bank ─── */
function selectBank(el){
  document.querySelectorAll('.nox-bank-card').forEach(c=>c.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input[type=radio]').checked = true;
}

/* ── Toggle Guide ─── */
function toggleGuide(){
  const c = document.getElementById('guideContent');
  const btn = document.getElementById('guideBtn');
  if(c.style.maxHeight==='0px'||!c.style.maxHeight){
    c.style.maxHeight = c.scrollHeight+'px';
    btn.textContent='▲ Sembunyikan';
  } else {
    c.style.maxHeight='0';
    btn.textContent='▼ Lihat Panduan';
  }
}

/* ── Validate Voucher ─── */
function validateVoucher(){
  const code = document.getElementById('voucherCode').value.trim().toUpperCase();
  if(!code){ showVoucherResult(false,'Masukkan kode voucher'); return; }
  const btn = event.target;
  btn.disabled=true; btn.textContent='...';
  const fd = new FormData();
  fd.append('action','validate_voucher');
  fd.append('code',code);
  fetch(location.href,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>{
      btn.disabled=false; btn.textContent='Gunakan';
      if(res.valid){
        voucherData=res;
        document.getElementById('voucher_id_hidden').value=res.id;
        showVoucherResult(true,'✅ Voucher valid! Bonus akan ditambahkan saat konfirmasi.');
        const raw=parseInt(document.getElementById('amountInput').value.replace(/\D/g,''),10)||0;
        updateSummary(raw);
      } else {
        voucherData=null;
        document.getElementById('voucher_id_hidden').value='';
        showVoucherResult(false,'❌ '+res.message);
      }
    })
    .catch(()=>{btn.disabled=false;btn.textContent='Gunakan';showVoucherResult(false,'Gagal memvalidasi');});
}
function showVoucherResult(ok,msg){
  const el=document.getElementById('voucherResult');
  el.textContent=msg;
  el.style.color=ok?'var(--green)':'var(--red)';
}

/* ── Preview Upload ─── */
function previewProof(inp){
  if(!inp.files[0]) return;
  const reader=new FileReader();
  reader.onload=e=>{
    const img=document.getElementById('uploadPreview');
    const ph=document.getElementById('uploadPlaceholder');
    img.src=e.target.result;
    img.style.display='block';
    ph.style.display='none';
  };
  reader.readAsDataURL(inp.files[0]);
}

/* ── Pending Deposit Countdown ─── */
const pendEl = document.getElementById('pendingExpiry');
if(pendEl){
  const expiry=new Date(pendEl.dataset.expiry.replace(' ','T')).getTime();
  const tick=()=>{
    const diff=expiry-Date.now();
    if(diff<=0){pendEl.textContent='Expired';return;}
    const h=Math.floor(diff/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);
    pendEl.textContent=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    setTimeout(tick,1000);
  };
  tick();
}

/* ── Drag & Drop ─── */
const zone=document.getElementById('uploadZone');
if(zone){
  zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('dragging');});
  zone.addEventListener('dragleave',()=>zone.classList.remove('dragging'));
  zone.addEventListener('drop',e=>{
    e.preventDefault();zone.classList.remove('dragging');
    const f=e.dataTransfer.files[0];
    if(f&&f.type.startsWith('image/')){
      const dt=new DataTransfer();dt.items.add(f);
      document.getElementById('proofInput').files=dt.files;
      previewProof(document.getElementById('proofInput'));
    }
  });
}

/* ── Logout ─── */
function confirmLogout(){ if(confirm('Yakin keluar?')) location.href='/logout'; }
</script>
</body>
</html>
