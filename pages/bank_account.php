<?php
/**
 * NOXARA - Rekening Bank
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];
$errors = []; $success = '';

// Daftar bank
const BANK_LIST = [
    'BCA'=>'Bank Central Asia (BCA)',
    'BRI'=>'Bank Rakyat Indonesia (BRI)',
    'BNI'=>'Bank Negara Indonesia (BNI)',
    'Mandiri'=>'Bank Mandiri',
    'CIMB'=>'CIMB Niaga',
    'Danamon'=>'Bank Danamon',
    'Permata'=>'Bank Permata',
    'BSI'=>'Bank Syariah Indonesia (BSI)',
    'BTN'=>'Bank Tabungan Negara (BTN)',
    'Maybank'=>'Maybank Indonesia',
    'OCBC'=>'OCBC NISP',
    'Panin'=>'Bank Panin',
    'SeaBank'=>'SeaBank',
    'Jago'=>'Bank Jago',
    'Blu'=>'Blu by BCA Digital',
    'GoPay'=>'GoPay / Bank Jago',
    'OVO'=>'OVO (Via Bank)',
    'DANA'=>'DANA (Via Bank)',
    'ShopeePay'=>'ShopeePay (Via Bank)',
];

// ── Ambil rekening user ──────────────────────────────────
$stmtBA = db()->prepare("SELECT * FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC");
$stmtBA->bind_param('i', $userId);
$stmtBA->execute();
$bankAccounts = $stmtBA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtBA->close();

// ── Handle POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token tidak valid';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            if (count($bankAccounts) >= 3) { $errors[] = 'Maksimal 3 rekening bank'; }
            else {
                $bankName   = clean($_POST['bank_name'] ?? '');
                $accNumber  = preg_replace('/\D/', '', $_POST['account_number'] ?? '');
                $accName    = clean($_POST['account_name'] ?? '');

                if (empty($bankName) || !array_key_exists($bankName, BANK_LIST)) $errors[] = 'Pilih bank yang valid';
                if (empty($accNumber) || strlen($accNumber) < 8) $errors[] = 'Nomor rekening tidak valid (min 8 digit)';
                if (empty($accName)) $errors[] = 'Nama pemilik rekening wajib diisi';
                if (strtolower($accName) !== strtolower($user['full_name'] ?? '')) {
                    // Warning saja, tidak block
                }
                // Cek duplikat
                $stmtDup = db()->prepare("SELECT id FROM bank_accounts WHERE user_id=? AND account_number=?");
                $stmtDup->bind_param('is', $userId, $accNumber);
                $stmtDup->execute();
                if ($stmtDup->get_result()->num_rows > 0) $errors[] = 'Nomor rekening sudah terdaftar';
                $stmtDup->close();

                if (empty($errors)) {
                    $isPrimary = empty($bankAccounts) ? 1 : 0;
                    $stmtI = db()->prepare("INSERT INTO bank_accounts (user_id, bank_name, account_number, account_name, is_primary, created_at) VALUES (?,?,?,?,?,NOW())");
                    $stmtI->bind_param('isssi', $userId, $bankName, $accNumber, $accName, $isPrimary);
                    $stmtI->execute(); $stmtI->close();
                    $success = 'Rekening berhasil ditambahkan!';
                    $stmtBA = db()->prepare("SELECT * FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC");
                    $stmtBA->bind_param('i', $userId); $stmtBA->execute();
                    $bankAccounts = $stmtBA->get_result()->fetch_all(MYSQLI_ASSOC); $stmtBA->close();
                }
            }
        }

        if ($action === 'set_primary') {
            $bid = (int)($_POST['bank_id'] ?? 0);
            db()->prepare("UPDATE bank_accounts SET is_primary=0 WHERE user_id=?")->execute() ?: null;
            $stmtSP1 = db()->prepare("UPDATE bank_accounts SET is_primary=0 WHERE user_id=?");
            $stmtSP1->bind_param('i', $userId); $stmtSP1->execute(); $stmtSP1->close();
            $stmtSP2 = db()->prepare("UPDATE bank_accounts SET is_primary=1 WHERE id=? AND user_id=?");
            $stmtSP2->bind_param('ii', $bid, $userId); $stmtSP2->execute(); $stmtSP2->close();
            $success = 'Rekening utama berhasil diubah!';
            $stmtBA = db()->prepare("SELECT * FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC");
            $stmtBA->bind_param('i', $userId); $stmtBA->execute();
            $bankAccounts = $stmtBA->get_result()->fetch_all(MYSQLI_ASSOC); $stmtBA->close();
        }

        if ($action === 'delete') {
            $bid = (int)($_POST['bank_id'] ?? 0);
            // Cek ada WD pending
            $stmtWD = db()->prepare("SELECT id FROM withdrawals WHERE user_id=? AND bank_account_id=? AND status='pending' LIMIT 1");
            $stmtWD->bind_param('ii', $userId, $bid); $stmtWD->execute();
            $wdPending = $stmtWD->get_result()->num_rows > 0; $stmtWD->close();
            if ($wdPending) { $errors[] = 'Tidak bisa menghapus rekening yang memiliki withdraw pending'; }
            else {
                $stmtDel = db()->prepare("DELETE FROM bank_accounts WHERE id=? AND user_id=?");
                $stmtDel->bind_param('ii', $bid, $userId); $stmtDel->execute(); $stmtDel->close();
                $success = 'Rekening berhasil dihapus!';
                $stmtBA = db()->prepare("SELECT * FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC");
                $stmtBA->bind_param('i', $userId); $stmtBA->execute();
                $bankAccounts = $stmtBA->get_result()->fetch_all(MYSQLI_ASSOC); $stmtBA->close();
            }
        }
    }
}

$pageTitle = 'Rekening Bank';
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
.bank-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px}
.bank-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:20px;position:relative;transition:.2s}
.bank-card--primary{border-color:var(--cyan);box-shadow:0 0 24px rgba(0,212,255,.1)}
.bank-card__bank{font-size:16px;font-weight:800;margin-bottom:4px}
.bank-card__number{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--cyan);letter-spacing:.08em;margin-bottom:4px}
.bank-card__name{font-size:13px;color:var(--text-secondary)}
.bank-card__actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
.primary-badge{position:absolute;top:12px;right:12px;background:var(--cyan);color:#000;font-size:10px;font-weight:800;padding:3px 10px;border-radius:99px;letter-spacing:.06em}
.form-group{margin-bottom:16px}
.form-label{font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.form-input,.form-select{width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-light);border-radius:10px;padding:11px 14px;color:var(--text-primary);font-size:14px;outline:none;transition:.2s;box-sizing:border-box}
.form-input:focus,.form-select:focus{border-color:var(--cyan)}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">🏦 Rekening Bank</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Kelola rekening bank untuk withdraw</p>
</div>

<?php if ($errors): ?>
  <div style="background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.3);border-radius:10px;padding:14px;margin-bottom:20px">
    <?php foreach ($errors as $e): ?><div style="font-size:13px;color:var(--red)">❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($success): ?>
  <div style="background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px;color:var(--green)">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- DAFTAR REKENING -->
<div style="margin-bottom:8px;display:flex;align-items:center;justify-content:space-between">
  <h2 style="font-size:16px;font-weight:700;margin:0">Rekening Terdaftar (<?= count($bankAccounts) ?>/3)</h2>
</div>

<?php if (empty($bankAccounts)): ?>
<div style="background:rgba(255,179,0,.06);border:1px solid rgba(255,179,0,.2);border-radius:12px;padding:24px;text-align:center;margin-bottom:24px">
  <div style="font-size:48px;margin-bottom:12px">🏦</div>
  <div style="font-weight:600;margin-bottom:4px">Belum ada rekening terdaftar</div>
  <div style="font-size:13px;color:var(--text-secondary)">Tambahkan rekening bank untuk bisa melakukan withdraw</div>
</div>
<?php else: ?>
<div class="bank-grid">
  <?php foreach ($bankAccounts as $ba): ?>
  <div class="bank-card <?= $ba['is_primary']?'bank-card--primary':'' ?>">
    <?php if ($ba['is_primary']): ?><div class="primary-badge">UTAMA</div><?php endif; ?>
    <div style="font-size:28px;margin-bottom:8px">🏦</div>
    <div class="bank-card__bank"><?= htmlspecialchars($ba['bank_name']) ?></div>
    <div class="bank-card__number"><?= htmlspecialchars(maskAccountNumber($ba['account_number'])) ?></div>
    <div class="bank-card__name">a/n <?= htmlspecialchars($ba['account_name']) ?></div>
    <div class="bank-card__actions">
      <?php if (!$ba['is_primary']): ?>
      <form method="POST" style="display:inline">
        <?= csrfField() ?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="bank_id" value="<?= (int)$ba['id'] ?>">
        <button type="submit" class="nox-btn nox-btn--sm nox-btn--outline">⭐ Set Utama</button>
      </form>
      <?php endif; ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Hapus rekening ini?')">
        <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="bank_id" value="<?= (int)$ba['id'] ?>">
        <button type="submit" class="nox-btn nox-btn--sm" style="background:rgba(255,68,68,.1);color:var(--red);border:1px solid rgba(255,68,68,.3)">🗑️ Hapus</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- FORM TAMBAH -->
<?php if (count($bankAccounts) < 3): ?>
<div class="nox-card" style="padding:28px">
  <h2 style="font-size:16px;font-weight:700;margin:0 0 20px">➕ Tambah Rekening</h2>
  <form method="POST" style="max-width:520px">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-group">
      <label class="form-label">Nama Bank</label>
      <select name="bank_name" class="form-select" required>
        <option value="">-- Pilih Bank --</option>
        <?php foreach (BANK_LIST as $k=>$v): ?>
          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Nomor Rekening</label>
      <input type="text" name="account_number" class="form-input" placeholder="Contoh: 1234567890" inputmode="numeric" required maxlength="20">
    </div>
    <div class="form-group">
      <label class="form-label">Nama Pemilik Rekening</label>
      <input type="text" name="account_name" class="form-input" placeholder="Harus sesuai dengan nama di akun NOXARA" required>
      <div style="font-size:11px;color:var(--amber);margin-top:4px">⚠️ Nama harus sama persis dengan nama akun: <strong><?= htmlspecialchars($user['full_name'] ?? '') ?></strong></div>
    </div>
    <button type="submit" class="nox-btn nox-btn--primary">➕ Tambah Rekening</button>
  </form>
</div>
<?php else: ?>
<div style="background:rgba(255,68,68,.06);border:1px solid rgba(255,68,68,.2);border-radius:12px;padding:16px;text-align:center;font-size:13px;color:var(--red)">
  ⚠️ Batas maksimal 3 rekening sudah tercapai. Hapus salah satu untuk menambah yang baru.
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body></html>
