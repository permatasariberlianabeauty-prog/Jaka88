<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (isLoggedIn()) redirect('/dashboard');

$errors  = [];
$success = false;

// Generate captcha
if (empty($_SESSION['fp_captcha_a']) || empty($_SESSION['fp_captcha_b'])) {
    $_SESSION['fp_captcha_a'] = rand(1, 9);
    $_SESSION['fp_captcha_b'] = rand(1, 9);
}
$captchaA = (int)$_SESSION['fp_captcha_a'];
$captchaB = (int)$_SESSION['fp_captcha_b'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email      = clean($_POST['email'] ?? '');
    $captchaAns = (int)($_POST['captcha_answer'] ?? -1);

    if (empty($email)) {
        $errors[] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } elseif ($captchaAns !== ($captchaA + $captchaB)) {
        $errors[] = 'Jawaban captcha salah.';
        $_SESSION['fp_captcha_a'] = rand(1, 9);
        $_SESSION['fp_captcha_b'] = rand(1, 9);
        $captchaA = (int)$_SESSION['fp_captcha_a'];
        $captchaB = (int)$_SESSION['fp_captcha_b'];
    } else {
        $token = createPasswordResetToken($email);

        // Selalu tampilkan success (keamanan: jangan bocorkan apakah email terdaftar)
        $success = true;

        // Hapus captcha setelah sukses
        unset($_SESSION['fp_captcha_a'], $_SESSION['fp_captcha_b']);

        // Di production: kirim email dengan link reset
        // Di development: simpan di log
        if ($token) {
            $resetLink = BASE_URL . '/auth/reset_password.php?token=' . urlencode($token);
            error_log('[NOXARA] Password reset link for ' . $email . ': ' . $resetLink);

            // TODO: Kirim email via PHPMailer/SMTP
            // sendResetEmail($email, $resetLink);
        }
    }
}

$siteName = getSetting('site_name', 'NOXARA');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lupa Password - <?= htmlspecialchars($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;600;700&family=Orbitron:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
:root{--cyan:#00D4FF;--purple:#7B2FFF;--bg:#0A0E1A;--card:#0F1629;--border:rgba(0,212,255,.12);}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:#e2e8f0;font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:radial-gradient(ellipse 80% 50% at 50% 0%,rgba(123,47,255,.2) 0%,transparent 60%),var(--bg);}
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
.nox-input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.75rem 1rem;color:#e2e8f0;font-size:.9rem;font-family:inherit;transition:all .25s;outline:none;}
.nox-input:focus{border-color:var(--cyan);background:rgba(0,212,255,.04);box-shadow:0 0 0 3px rgba(0,212,255,.1);}
.nox-input::placeholder{color:#374151;}
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
.auth-back-link{text-align:center;margin-top:1.3rem;}
.auth-back-link a{font-size:.85rem;color:#64748b;display:inline-flex;align-items:center;gap:.4rem;transition:color .2s;}
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
      <!-- SUCCESS STATE -->
      <div class="success-box">
        <div class="success-icon">📧</div>
        <div class="success-title">Email Reset Terkirim!</div>
        <div class="success-text">
          Kami telah mengirimkan link reset password ke alamat email kamu.<br>
          Silakan cek inbox (dan folder spam) dan klik link yang diberikan.<br><br>
          <strong>Link berlaku selama 1 jam.</strong>
        </div>
      </div>
      <div class="auth-back-link" style="margin-top:1.5rem;">
        <a href="<?= BASE_URL ?>/auth/login.php">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-arrow-right"/></svg>
          Kembali ke halaman Login
        </a>
      </div>
    <?php else: ?>
      <!-- FORM STATE -->
      <div class="auth-icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-security"/></svg>
      </div>
      <h2 class="auth-card-title">Lupa Password?</h2>
      <p class="auth-card-sub">Masukkan email yang terdaftar, kami akan mengirimkan link untuk reset password kamu.</p>

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

      <form method="POST" action="" id="fpForm" novalidate>
        <?= csrfField() ?>

        <div class="nox-form-group">
          <label for="email">Alamat Email Terdaftar</label>
          <input type="email" id="email" name="email" class="nox-input"
                 placeholder="email@contoh.com"
                 value="<?= isset($_POST['email']) ? htmlspecialchars(clean($_POST['email'])) : '' ?>"
                 autocomplete="email" required>
        </div>

        <div class="nox-form-group">
          <label>Verifikasi Keamanan</label>
          <div class="captcha-row">
            <span class="captcha-label"><?= $captchaA ?> + <?= $captchaB ?> = ?</span>
            <input type="number" name="captcha_answer" class="nox-input captcha-input"
                   placeholder="..." min="0" max="18" required>
          </div>
        </div>

        <button type="submit" class="btn-auth" id="fpBtn">
          Kirim Link Reset Password
        </button>
      </form>

      <div class="auth-back-link">
        <a href="<?= BASE_URL ?>/auth/login.php">← Kembali ke Login</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.getElementById('fpForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('fpBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Mengirim...'; }
});
</script>
</body>
</html>
