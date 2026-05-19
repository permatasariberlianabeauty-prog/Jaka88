<?php
/**
 * NOXARA - Keamanan
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];
$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token tidak valid';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Ganti Password ──────────────────────────────
        if ($action === 'change_password') {
            $oldPass  = $_POST['old_password'] ?? '';
            $newPass  = $_POST['new_password'] ?? '';
            $confPass = $_POST['confirm_password'] ?? '';

            if (empty($oldPass) || empty($newPass) || empty($confPass)) $errors[] = 'Semua field wajib diisi';
            elseif (!password_verify($oldPass, $user['password'])) $errors[] = 'Password lama tidak sesuai';
            elseif (strlen($newPass) < 8) $errors[] = 'Password baru minimal 8 karakter';
            elseif ($newPass !== $confPass) $errors[] = 'Konfirmasi password tidak cocok';
            elseif ($oldPass === $newPass) $errors[] = 'Password baru tidak boleh sama dengan yang lama';

            if (empty($errors)) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = db()->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param('si', $hash, $userId);
                $stmt->execute(); $stmt->close();
                $success = 'Password berhasil diubah!';
            }
        }

        // ── Set / Ganti PIN ─────────────────────────────
        if ($action === 'set_pin' || $action === 'change_pin') {
            $oldPin  = $_POST['old_pin'] ?? '';
            $newPin  = $_POST['new_pin'] ?? '';
            $confPin = $_POST['confirm_pin'] ?? '';
            $hasPin  = !empty($user['transaction_pin']);

            if ($hasPin && empty($oldPin)) $errors[] = 'PIN lama wajib diisi';
            if (empty($newPin)) $errors[] = 'PIN baru wajib diisi';
            elseif (!preg_match('/^\d{6}$/', $newPin)) $errors[] = 'PIN harus 6 digit angka';
            if ($newPin !== $confPin) $errors[] = 'Konfirmasi PIN tidak cocok';
            if ($hasPin && !password_verify($oldPin, $user['transaction_pin'])) $errors[] = 'PIN lama tidak sesuai';

            if (empty($errors)) {
                $pinHash = password_hash($newPin, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = db()->prepare("UPDATE users SET transaction_pin=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param('si', $pinHash, $userId);
                $stmt->execute(); $stmt->close();
                $success = $hasPin ? 'PIN berhasil diubah!' : 'PIN berhasil dibuat!';
                $user = getCurrentUser();
            }
        }
    }
}

// ── Riwayat Login ────────────────────────────────────────
$stmtL = db()->prepare("SELECT * FROM login_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$stmtL->bind_param('i', $userId);
$stmtL->execute();
$loginLogs = $stmtL->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtL->close();

$hasPin = !empty($user['transaction_pin']);
$pageTitle = 'Keamanan';
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
.sec-section{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:24px;margin-bottom:20px}
.sec-section h2{font-size:16px;font-weight:700;margin:0 0 20px;display:flex;align-items:center;gap:8px}
.form-group{margin-bottom:16px}
.form-label{font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.form-input{width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-light);border-radius:10px;padding:11px 14px;color:var(--text-primary);font-size:14px;outline:none;transition:.2s;box-sizing:border-box}
.form-input:focus{border-color:var(--cyan)}
.strength-bar{height:4px;border-radius:99px;transition:.4s;margin-top:6px}
.pin-inputs{display:flex;gap:8px;justify-content:flex-start}
.pin-input{width:48px;height:56px;border-radius:10px;border:2px solid var(--border-light);background:rgba(255,255,255,.04);text-align:center;font-size:22px;font-weight:700;color:var(--text-primary);outline:none;transition:.2s}
.pin-input:focus{border-color:var(--cyan)}
.login-table{width:100%;border-collapse:collapse}
.login-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);padding:8px 12px;border-bottom:1px solid var(--border-light);text-align:left}
.login-table td{padding:11px 12px;border-bottom:1px solid rgba(30,42,69,.4);font-size:12px}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">🔐 Keamanan</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Kelola keamanan akun Anda</p>
</div>

<?php if ($errors): ?>
  <div style="background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.3);border-radius:10px;padding:14px;margin-bottom:20px">
    <?php foreach ($errors as $e): ?><div style="font-size:13px;color:var(--red)">❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($success): ?>
  <div style="background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px;color:var(--green)">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- GANTI PASSWORD -->
<div class="sec-section">
  <h2>🔑 Ganti Password</h2>
  <form method="POST" style="max-width:480px">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="form-group">
      <label class="form-label">Password Lama</label>
      <input type="password" name="old_password" class="form-input" placeholder="Masukkan password lama" autocomplete="current-password">
    </div>
    <div class="form-group">
      <label class="form-label">Password Baru</label>
      <input type="password" name="new_password" id="newPassInput" class="form-input" placeholder="Min. 8 karakter" oninput="checkStrength(this.value)" autocomplete="new-password">
      <div class="strength-bar" id="strengthBar" style="width:0;background:var(--red)"></div>
      <div style="font-size:11px;color:var(--text-secondary);margin-top:4px" id="strengthLabel"></div>
    </div>
    <div class="form-group">
      <label class="form-label">Konfirmasi Password Baru</label>
      <input type="password" name="confirm_password" class="form-input" placeholder="Ulangi password baru" autocomplete="new-password">
    </div>
    <button type="submit" class="nox-btn nox-btn--primary">💾 Simpan Password</button>
  </form>
</div>

<!-- PIN TRANSAKSI -->
<div class="sec-section">
  <h2>🔢 PIN Transaksi (6 Digit)</h2>
  <?php if (!$hasPin): ?>
    <div style="background:rgba(255,179,0,.08);border:1px solid rgba(255,179,0,.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;color:var(--amber)">
      ⚠️ Anda belum membuat PIN transaksi. PIN diperlukan untuk proses withdraw.
    </div>
  <?php endif; ?>
  <form method="POST" style="max-width:480px" onsubmit="collectPin(event)">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $hasPin ? 'change_pin' : 'set_pin' ?>">
    <input type="hidden" name="new_pin" id="newPinHidden">
    <input type="hidden" name="confirm_pin" id="confPinHidden">
    <?php if ($hasPin): ?>
    <input type="hidden" name="old_pin" id="oldPinHidden">
    <div class="form-group">
      <label class="form-label">PIN Lama</label>
      <div class="pin-inputs" id="oldPinInputs">
        <?php for ($i=0;$i<6;$i++): ?><input type="password" class="pin-input old-pin" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="pinNext(this,'oldPinInputs')" onkeydown="pinBack(event,this,'oldPinInputs')"><?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label class="form-label">PIN Baru</label>
      <div class="pin-inputs" id="newPinInputs">
        <?php for ($i=0;$i<6;$i++): ?><input type="password" class="pin-input new-pin" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="pinNext(this,'newPinInputs')" onkeydown="pinBack(event,this,'newPinInputs')"><?php endfor; ?>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Konfirmasi PIN Baru</label>
      <div class="pin-inputs" id="confPinInputs">
        <?php for ($i=0;$i<6;$i++): ?><input type="password" class="pin-input conf-pin" maxlength="1" inputmode="numeric" pattern="[0-9]" oninput="pinNext(this,'confPinInputs')" onkeydown="pinBack(event,this,'confPinInputs')"><?php endfor; ?>
      </div>
    </div>
    <button type="submit" class="nox-btn nox-btn--primary"><?= $hasPin ? '🔄 Ubah PIN' : '✅ Buat PIN' ?></button>
  </form>
</div>

<!-- RIWAYAT LOGIN -->
<div class="sec-section">
  <h2>📋 Riwayat Login (10 Terakhir)</h2>
  <?php if (empty($loginLogs)): ?>
    <div style="text-align:center;padding:24px;color:var(--text-secondary);font-size:13px">Belum ada riwayat login</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="login-table">
    <thead><tr><th>Tanggal</th><th>IP Address</th><th>Device / Browser</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($loginLogs as $log): ?>
      <tr>
        <td style="white-space:nowrap"><?= htmlspecialchars(date('d M Y H:i', strtotime($log['created_at']))) ?></td>
        <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
        <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($log['user_agent'] ?? '-') ?></td>
        <td>
          <?php $s = $log['status'] ?? 'success'; ?>
          <span style="padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700;background:<?= $s==='success'?'rgba(0,230,118,.12)':'rgba(255,68,68,.12)' ?>;color:<?= $s==='success'?'var(--green)':'var(--red)' ?>">
            <?= $s==='success'?'✅ Berhasil':'❌ Gagal' ?>
          </span>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function checkStrength(val) {
  const bar = document.getElementById('strengthBar');
  const lbl = document.getElementById('strengthLabel');
  if (!bar) return;
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    [0,'',''],
    [25,'var(--red)','Lemah'],
    [50,'var(--amber)','Sedang'],
    [75,'var(--cyan)','Kuat'],
    [100,'var(--green)','Sangat Kuat']
  ];
  const [w,c,t] = levels[score] || levels[0];
  bar.style.width = w+'%'; bar.style.background = c;
  lbl.textContent = t; lbl.style.color = c;
}

function pinNext(el, groupId) {
  if (el.value.length === 1) {
    const inputs = document.querySelectorAll('#'+groupId+' .pin-input');
    const idx = Array.from(inputs).indexOf(el);
    if (idx < inputs.length-1) inputs[idx+1].focus();
  }
}
function pinBack(e, el, groupId) {
  if (e.key === 'Backspace' && el.value === '') {
    const inputs = document.querySelectorAll('#'+groupId+' .pin-input');
    const idx = Array.from(inputs).indexOf(el);
    if (idx > 0) { inputs[idx-1].value=''; inputs[idx-1].focus(); }
  }
}

function collectPin(e) {
  const getPin = (cls) => Array.from(document.querySelectorAll('.'+cls)).map(i=>i.value).join('');
  document.getElementById('newPinHidden').value = getPin('new-pin');
  document.getElementById('confPinHidden').value = getPin('conf-pin');
  const oldHid = document.getElementById('oldPinHidden');
  if (oldHid) oldHid.value = getPin('old-pin');
}
</script>
</body></html>
