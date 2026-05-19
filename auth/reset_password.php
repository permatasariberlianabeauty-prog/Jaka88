<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (isLoggedIn()) redirect('/dashboard');

$errors   = [];
$success  = false;
$token    = clean($_GET['token'] ?? '');
$tokenValid = false;

// Generate captcha
if (empty($_SESSION['rp_captcha_a']) || empty($_SESSION['rp_captcha_b'])) {
    $_SESSION['rp_captcha_a'] = rand(1, 9);
    $_SESSION['rp_captcha_b'] = rand(1, 9);
}
$captchaA = (int)$_SESSION['rp_captcha_a'];
$captchaB = (int)$_SESSION['rp_captcha_b'];

// Validasi token dari URL
if (!empty($token)) {
    $stmt = db()->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resetRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $tokenValid = !empty($resetRow);
}

// Jika tidak ada token atau tidak valid dan bukan POST
if (empty($token) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Link reset password tidak valid atau sudah kedaluwarsa.');
    redirect('/auth/forgot_password.php');
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $token      = clean($_POST['token'] ?? '');
    $password   = $_POST['password'] ?? '';
    $passConf   = $_POST['password_confirm'] ?? '';
    $captchaAns = (int)($_POST['captcha_answer'] ?? -1);

    if (empty($token)) {
        $errors[] = 'Token tidak valid.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    } elseif ($password !== $passConf) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    } elseif ($captchaAns !== ($captchaA + $captchaB)) {
        $errors[] = 'Jawaban captcha salah.';
        $_SESSION['rp_captcha_a'] = rand(1, 9);
        $_SESSION['rp_captcha_b'] = rand(1, 9);
        $captchaA = (int)$_SESSION['rp_captcha_a'];
        $captchaB = (int)$_SESSION['rp_captcha_b'];
    } else {
        $result = resetPasswordWithToken($token, $password);
        if ($result['success']) {
            $success = true;
            unset($_SESSION['rp_captcha_a'], $_SESSION['rp_captcha_b']);
            setFlash('success', '✅ Password berhasil direset! Silakan login dengan password baru kamu.');
            // Redirect ke login setelah 2 detik (via JS)
        } else {
            $errors[] = $result['message'];
        }
    }

    // Re-validate token setelah POST
    if (!empty($token) && !$success) {
        $stmt2 = db()->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
        $stmt2->bind_param("s", $token);
        $stmt2->execute();
        $resetRow = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $tokenValid = !empty($resetRow);
    }
}

$siteName = getSetting('site_name', 'NOXARA');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - <?= htmlspecialchars($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;600;700&family=Orbitron:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
:root{--cyan:#00D4FF;--purple:#7B2FFF;--bg:#0A0E1A;--card:#0F1629;--border:rgba(0,212,255,.12);}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:#e2e8f0;font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:radial-gradient(ellipse 80% 50% at 50% 0%,rgba(0,212,255,.15) 0%,transparent 60%),var(--bg);}
a{color:inherit;text-decoration:none;}
.auth-wrap{width:100%;max-width:420px;}
.auth-logo{display:flex;align-items:center;gap:.7rem;margin-bottom:2rem;justify-content:center;}
.auth-logo svg{width:40px;height:40px;}
.auth-logo-text{font-family:'Orbitron',sans-serif;font-weight:700;font-size:1.4rem;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.auth-card{background:rgba(15,22,41,.92);border:1px solid var(--border);border-radius:22px;padding:2.2rem;backdrop-filter:blur(20px);}
.auth-icon-wrap{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.2));border:1px solid var(--border);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;}
.auth-icon-wrap svg{width:28px;height:28px;color:var(--cyan);}
.auth-card-title{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:800;text-align:center;margin-bottom:.4rem;}
.auth-card-sub{font-size:.85rem;color:#64748b;text-align:center;margin-bottom:1.8rem;line-height:1.6;}
.nox-form-group{margin-bottom:1.1rem;}
.nox-form-group label{display:block;font-size:.8rem;font-weight:600;color:#94a3b8;margin-bottom:.4rem;}
.nox-input-wrap{position:relative;}
.nox-input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.75rem 1rem;color:#e2e8f0;font-size:.9rem;font-family:inherit;transition:all .25s;outline:none;}
.nox-input:focus{border-color:var(--cyan);background:rgba(0,212,255,.04);box-shadow:0 0 0 3px rgba(0,212,255,.1);}
.nox-input.has-icon{padding-right:2.8rem;}
.nox-input::placeholder{color:#374151;}
.input-icon-btn{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;padding:.2rem;display:flex;align-items:center;justify-content:center;transition:color .2s;}
.input-icon-btn:hover{color:var(--cyan);}
.input-icon-btn svg{width:17px;height:17px;}
.pw-strength{margin-top:.4rem;}
.pw-strength-bars{display:flex;gap:3px;margin-bottom:.25rem;}
.pw-bar{height:3px;border-radius:2px;flex:1;background:rgba(255,255,255,.08);transition:background .3s;}
.pw-bar.weak{background:#ff4444;}
.pw-bar.medium{background:#FFB300;}
.pw-bar.strong{background:#00e676;}
.pw-label{font-size:.7rem;color:#64748b;}
.captcha-row{display:flex;align-items:center;gap:.7rem;}
.captcha-label{font-size:.9rem;color:#e2e8f0;font-weight:600;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:.5rem .8rem;white-space:nowrap;}
.captcha-input{width:80px!important;text-align:center;}
.btn-auth{width:100%;padding:.82rem 1.5rem;border-radius:10px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;font-size:.95rem;font-family:inherit;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(123,47,255,.3);margin-top:.4rem;}
.btn-auth:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(123,47,255,.45);}
.auth-errors{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.25);border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;}
.auth-errors p{font-size:.83rem;color:#ff6b6b;display:flex;align-items:flex-start;gap:.4rem;}
.success-box{background:rgba(0,230,118,.08);border:1px solid rgba(0,230,118,.3);border-radius:14px;padding:1.5rem;text-align:center;}
.success-icon{font-size:2.5rem;margin-bottom:.8rem;}
.success-title{font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:700;color:#00e676;margin-bottom:.5rem;}
.success-text{font-size:.85rem;color:#94a3b8;line-height:1.6;}
.invalid-box{background:rgba(255,68,68,.06);border:1px solid rgba(255,68,68,.25);border-radius:14px;padding:1.5rem;text-align:center;}
.invalid-icon{font-size:2.5rem;margin-bottom:.8rem;}
.invalid-title{font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700;color:#ff4444;margin-bottom:.5rem;}
.invalid-text{font-size:.85rem;color:#94a3b8;line-height:1.6;margin-bottom:1rem;}
.btn-outline{display:inline-block;padding:.65rem 1.5rem;border-radius:10px;border:1px solid var(--border);color:var(--cyan);font-weight:600;font-size:.88rem;transition:all .25s;}
.btn-outline:hover{background:rgba(0,212,255,.1);border-color:var(--cyan);}
.auth-back-link{text-align:center;margin-top:1.3rem;}
.auth-back-link a{font-size:.85rem;color:#64748b;transition:color .2s;}
.auth-back-link a:hover{color:var(--cyan);}
</style>
</head>
<body>
<?php include ASSETS_PATH . '/img/icons/icons.svg'; ?>

<div class="auth-wrap">
  <div class="auth-logo">
    <svg viewBox="0 0 40 40"><use href="#icon-noxara"/></svg>
    <span class="auth-logo-text">NOXARA</span>
  </div>

  <div class="auth-card">
    <?php if ($success): ?>
      <!-- SUCCESS -->
      <div class="success-box">
        <div class="success-icon">✅</div>
        <div class="success-title">Password Berhasil Direset!</div>
        <div class="success-text">
          Password kamu telah berhasil diperbarui.<br>
          Kamu akan diarahkan ke halaman login dalam beberapa detik...
        </div>
      </div>
      <div class="auth-back-link">
        <a href="<?= BASE_URL ?>/auth/login.php">→ Ke halaman Login sekarang</a>
      </div>
      <script>
        setTimeout(() => { window.location.href = '<?= BASE_URL ?>/auth/login.php'; }, 2500);
      </script>

    <?php elseif (!$tokenValid && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
      <!-- TOKEN INVALID -->
      <div class="invalid-box">
        <div class="invalid-icon">⛔</div>
        <div class="invalid-title">Link Tidak Valid!</div>
        <div class="invalid-text">
          Link reset password sudah kedaluwarsa atau tidak valid.<br>
          Silakan minta link reset password yang baru.
        </div>
        <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="btn-outline">Minta Link Baru</a>
      </div>
      <div class="auth-back-link">
        <a href="<?= BASE_URL ?>/auth/login.php">← Kembali ke Login</a>
      </div>

    <?php else: ?>
      <!-- FORM RESET -->
      <div class="auth-icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-security"/></svg>
      </div>
      <h2 class="auth-card-title">Reset Password</h2>
      <p class="auth-card-sub">Buat password baru yang kuat untuk akun NOXARA kamu.</p>

      <?php if (!empty($errors)): ?>
        <div class="auth-errors">
          <?php foreach ($errors as $err): ?>
            <p>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><use href="#icon-x-circle"/></svg>
              <?= htmlspecialchars($err) ?>
            </p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="rpForm" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="nox-form-group">
          <label for="password">Password Baru</label>
          <div class="nox-input-wrap">
            <input type="password" id="password" name="password" class="nox-input has-icon"
                   placeholder="Min. 6 karakter"
                   autocomplete="new-password" required>
            <button type="button" class="input-icon-btn" onclick="togglePass('password','eyePass1')" aria-label="Toggle">
              <svg id="eyePass1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-eye"/></svg>
            </button>
          </div>
          <div class="pw-strength" id="pwStrength" style="display:none;">
            <div class="pw-strength-bars">
              <div class="pw-bar" id="pb1"></div><div class="pw-bar" id="pb2"></div>
              <div class="pw-bar" id="pb3"></div><div class="pw-bar" id="pb4"></div>
            </div>
            <div class="pw-label" id="pwLabel"></div>
          </div>
        </div>

        <div class="nox-form-group">
          <label for="password_confirm">Konfirmasi Password Baru</label>
          <div class="nox-input-wrap">
            <input type="password" id="password_confirm" name="password_confirm" class="nox-input has-icon"
                   placeholder="Ulangi password baru"
                   autocomplete="new-password" required>
            <button type="button" class="input-icon-btn" onclick="togglePass('password_confirm','eyePass2')" aria-label="Toggle">
              <svg id="eyePass2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-eye"/></svg>
            </button>
          </div>
        </div>

        <div class="nox-form-group">
          <label>Verifikasi Keamanan</label>
          <div class="captcha-row">
            <span class="captcha-label"><?= $captchaA ?> + <?= $captchaB ?> = ?</span>
            <input type="number" name="captcha_answer" class="nox-input captcha-input"
                   placeholder="..." min="0" max="18" required>
          </div>
        </div>

        <button type="submit" class="btn-auth" id="rpBtn">
          🔐 Reset Password Sekarang
        </button>
      </form>

      <div class="auth-back-link">
        <a href="<?= BASE_URL ?>/auth/login.php">← Kembali ke Login</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function togglePass(fieldId, iconId) {
  const f = document.getElementById(fieldId);
  const i = document.getElementById(iconId);
  if (!f || !i) return;
  if (f.type === 'password') { f.type = 'text'; i.innerHTML = '<use href="#icon-eye-off"/>'; }
  else { f.type = 'password'; i.innerHTML = '<use href="#icon-eye"/>'; }
}

document.getElementById('password')?.addEventListener('input', function() {
  const v = this.value;
  const str = document.getElementById('pwStrength');
  if (!v) { str.style.display = 'none'; return; }
  str.style.display = 'block';
  let score = 0;
  if (v.length >= 6) score++;
  if (v.length >= 10) score++;
  if (/[A-Z]/.test(v) && /[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const cls = score <= 1 ? 'weak' : score <= 2 ? 'medium' : 'strong';
  const labels = ['','Lemah','Sedang','Kuat','Sangat Kuat'];
  ['pb1','pb2','pb3','pb4'].forEach((id, i) => {
    document.getElementById(id).className = 'pw-bar' + (i < score ? ' ' + cls : '');
  });
  document.getElementById('pwLabel').textContent = labels[score] || '';
});

document.getElementById('rpForm')?.addEventListener('submit', function(e) {
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password_confirm').value;
  if (p1 !== p2) { e.preventDefault(); alert('Konfirmasi password tidak cocok.'); return; }
  if (p1.length < 6) { e.preventDefault(); alert('Password minimal 6 karakter.'); return; }
  const btn = document.getElementById('rpBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Memproses...'; }
});
</script>
</body>
</html>
