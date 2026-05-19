<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Redirect jika sudah login
if (isLoggedIn()) redirect('/dashboard');

$errors  = [];
$success = '';

// Generate captcha
if (empty($_SESSION['captcha_a']) || empty($_SESSION['captcha_b'])) {
    $_SESSION['captcha_a'] = rand(1, 9);
    $_SESSION['captcha_b'] = rand(1, 9);
}
$captchaA = (int)$_SESSION['captcha_a'];
$captchaB = (int)$_SESSION['captcha_b'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $identifier  = clean($_POST['identifier'] ?? '');
    $password    = $_POST['password'] ?? '';
    $captchaAns  = (int)($_POST['captcha_answer'] ?? -1);

    // Validasi captcha
    if ($captchaAns !== ($captchaA + $captchaB)) {
        $errors[] = 'Jawaban captcha salah. Silakan coba lagi.';
        $_SESSION['captcha_a'] = rand(1, 9);
        $_SESSION['captcha_b'] = rand(1, 9);
        $captchaA = (int)$_SESSION['captcha_a'];
        $captchaB = (int)$_SESSION['captcha_b'];
    } elseif (empty($identifier) || empty($password)) {
        $errors[] = 'Email/username dan password wajib diisi.';
    } else {
        $result = loginUser($identifier, $password);
        if ($result['success']) {
            $_SESSION['show_popup'] = [
                'icon'    => 'success',
                'title'   => 'Selamat Datang!',
                'message' => 'Halo ' . htmlspecialchars($result['user']['full_name']) . '! Selamat datang kembali di NOXARA 🎉',
            ];
            // Regenerate captcha setelah sukses
            unset($_SESSION['captcha_a'], $_SESSION['captcha_b']);
            redirect('/dashboard');
        } else {
            $errors[] = $result['message'];
            // Regenerate captcha setelah gagal
            $_SESSION['captcha_a'] = rand(1, 9);
            $_SESSION['captcha_b'] = rand(1, 9);
            $captchaA = (int)$_SESSION['captcha_a'];
            $captchaB = (int)$_SESSION['captcha_b'];
        }
    }
}

$flash    = getFlash();
$siteName = getSetting('site_name', 'NOXARA');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?= htmlspecialchars($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<style>
:root{--cyan:#00D4FF;--purple:#7B2FFF;--bg:#0A0E1A;--card:#0F1629;--border:rgba(0,212,255,.12);--border-p:rgba(123,47,255,.15);--red:#FF4444;--green:#00e676;}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{background:var(--bg);color:#e2e8f0;font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;overflow-x:hidden;}
a{color:inherit;text-decoration:none;}
/* BG */
.auth-bg{min-height:100vh;display:grid;grid-template-columns:1fr 1fr;background:radial-gradient(ellipse 70% 50% at 20% 50%,rgba(123,47,255,.2) 0%,transparent 60%),radial-gradient(ellipse 40% 40% at 80% 20%,rgba(0,212,255,.1) 0%,transparent 50%),var(--bg);}
/* BRANDING SIDE */
.auth-brand{display:flex;flex-direction:column;justify-content:center;padding:3rem 3rem 3rem 4rem;position:relative;overflow:hidden;}
.auth-brand-glow{position:absolute;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(123,47,255,.2) 0%,transparent 70%);top:-100px;left:-100px;pointer-events:none;}
.auth-logo{display:flex;align-items:center;gap:.7rem;margin-bottom:2.5rem;}
.auth-logo svg{width:44px;height:44px;}
.auth-logo-text{font-family:'Orbitron',sans-serif;font-weight:700;font-size:1.6rem;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:.06em;}
.auth-brand-title{font-family:'Space Grotesk',sans-serif;font-size:clamp(1.8rem,3vw,2.8rem);font-weight:800;line-height:1.2;margin-bottom:1rem;}
.auth-gradient-text{background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.auth-brand-sub{font-size:.95rem;color:#64748b;margin-bottom:2.5rem;line-height:1.7;max-width:360px;}
.auth-features{display:flex;flex-direction:column;gap:.9rem;}
.auth-feat{display:flex;align-items:flex-start;gap:.8rem;}
.auth-feat-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.15));border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:.1rem;}
.auth-feat-icon svg{width:18px;height:18px;color:var(--cyan);}
.auth-feat-text strong{display:block;font-size:.9rem;font-weight:700;margin-bottom:.15rem;}
.auth-feat-text span{font-size:.82rem;color:#64748b;}
/* FORM SIDE */
.auth-form-side{display:flex;align-items:center;justify-content:center;padding:2rem 3rem 2rem 2rem;}
.auth-card{background:rgba(15,22,41,.9);border:1px solid var(--border);border-radius:24px;padding:2.5rem;width:100%;max-width:440px;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);}
.auth-card-title{font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:800;margin-bottom:.4rem;}
.auth-card-sub{font-size:.88rem;color:#64748b;margin-bottom:1.8rem;}
/* FORM ELEMENTS */
.nox-form-group{margin-bottom:1.2rem;}
.nox-form-group label{display:block;font-size:.82rem;font-weight:600;color:#94a3b8;margin-bottom:.4rem;}
.nox-input-wrap{position:relative;}
.nox-input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.75rem 1rem;color:#e2e8f0;font-size:.9rem;font-family:inherit;transition:all .25s;outline:none;}
.nox-input:focus{border-color:var(--cyan);background:rgba(0,212,255,.04);box-shadow:0 0 0 3px rgba(0,212,255,.1);}
.nox-input.has-icon{padding-right:2.8rem;}
.nox-input::placeholder{color:#374151;}
.input-icon-btn{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;padding:.2rem;display:flex;align-items:center;justify-content:center;transition:color .2s;}
.input-icon-btn:hover{color:var(--cyan);}
.input-icon-btn svg{width:18px;height:18px;}
/* CHECKBOX */
.nox-checkbox-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;}
.nox-checkbox{display:flex;align-items:center;gap:.5rem;cursor:pointer;}
.nox-checkbox input{width:16px;height:16px;border-radius:4px;accent-color:var(--cyan);}
.nox-checkbox span{font-size:.82rem;color:#94a3b8;}
.auth-link{font-size:.82rem;color:var(--cyan);transition:opacity .2s;}
.auth-link:hover{opacity:.75;}
/* CAPTCHA */
.captcha-row{display:flex;align-items:center;gap:.8rem;}
.captcha-label{font-size:.92rem;color:#e2e8f0;font-weight:600;white-space:nowrap;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:.5rem .9rem;}
.captcha-input{width:80px!important;text-align:center;}
/* BUTTONS */
.btn-auth{width:100%;padding:.85rem 1.5rem;border-radius:10px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;font-size:1rem;font-family:inherit;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(123,47,255,.3);}
.btn-auth:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(123,47,255,.45);}
.btn-auth:disabled{opacity:.6;cursor:not-allowed;transform:none;}
/* DIVIDER */
.auth-divider{display:flex;align-items:center;gap:.8rem;margin:1.2rem 0;}
.auth-divider-line{flex:1;height:1px;background:rgba(255,255,255,.08);}
.auth-divider span{font-size:.78rem;color:#374151;white-space:nowrap;}
/* ERRORS */
.auth-errors{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.25);border-radius:10px;padding:.9rem 1rem;margin-bottom:1.2rem;}
.auth-errors p{font-size:.85rem;color:#ff6b6b;display:flex;align-items:flex-start;gap:.5rem;line-height:1.5;}
.auth-errors p+p{margin-top:.4rem;}
/* FLASH */
.auth-flash{border-radius:10px;padding:.9rem 1rem;margin-bottom:1.2rem;font-size:.85rem;display:flex;align-items:flex-start;gap:.5rem;}
.auth-flash--success{background:rgba(0,230,118,.08);border:1px solid rgba(0,230,118,.25);color:#00e676;}
.auth-flash--info{background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.25);color:var(--cyan);}
.auth-flash--error{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.25);color:#ff6b6b;}
/* REGISTER LINK */
.auth-register-row{text-align:center;margin-top:1.2rem;font-size:.85rem;color:#64748b;}
.auth-register-row a{color:var(--cyan);font-weight:600;}
/* RESPONSIVE */
@media(max-width:900px){
  .auth-bg{grid-template-columns:1fr;}
  .auth-brand{display:none;}
  .auth-form-side{padding:1.5rem;min-height:100vh;}
  .auth-card{max-width:100%;}
}
</style>
</head>
<body>
<!-- SVG SPRITE -->
<?php include ASSETS_PATH . '/img/icons/icons.svg'; ?>

<div class="auth-bg">
  <!-- BRANDING -->
  <div class="auth-brand">
    <div class="auth-brand-glow"></div>
    <a href="<?= BASE_URL ?>" class="auth-logo">
      <svg viewBox="0 0 40 40"><use href="#icon-noxara"/></svg>
      <span class="auth-logo-text">NOXARA</span>
    </a>
    <h1 class="auth-brand-title">
      Platform <span class="auth-gradient-text">Mining Digital</span> Terpercaya
    </h1>
    <p class="auth-brand-sub">Investasi cerdas dengan profit harian otomatis. Bergabung dengan 284.750+ member yang sudah merasakan manfaatnya.</p>
    <div class="auth-features">
      <div class="auth-feat">
        <div class="auth-feat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-trending-up"/></svg>
        </div>
        <div class="auth-feat-text">
          <strong>Profit Harian Otomatis</strong>
          <span>Sistem mining berjalan 24/7 tanpa perlu tindakan manual</span>
        </div>
      </div>
      <div class="auth-feat">
        <div class="auth-feat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-security"/></svg>
        </div>
        <div class="auth-feat-text">
          <strong>Keamanan Dana Terjamin</strong>
          <span>Enkripsi SSL, 2FA, dan sistem keamanan berlapis</span>
        </div>
      </div>
      <div class="auth-feat">
        <div class="auth-feat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-referral"/></svg>
        </div>
        <div class="auth-feat-text">
          <strong>Komisi Referral 3 Level</strong>
          <span>Hasilkan penghasilan tambahan dari jaringan referral kamu</span>
        </div>
      </div>
    </div>
  </div>

  <!-- FORM -->
  <div class="auth-form-side">
    <div class="auth-card">
      <h2 class="auth-card-title">Masuk ke NOXARA</h2>
      <p class="auth-card-sub">Selamat datang kembali! Masuk untuk melanjutkan.</p>

      <?php if ($flash): ?>
        <div class="auth-flash auth-flash--<?= htmlspecialchars($flash['type']) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-info"/></svg>
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="auth-errors">
          <?php foreach ($errors as $err): ?>
            <p>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><use href="#icon-x-circle"/></svg>
              <?= htmlspecialchars($err) ?>
            </p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="loginForm" novalidate>
        <?= csrfField() ?>

        <div class="nox-form-group">
          <label for="identifier">Email atau Username</label>
          <input type="text" id="identifier" name="identifier" class="nox-input"
                 placeholder="Masukkan email atau username"
                 value="<?= isset($_POST['identifier']) ? htmlspecialchars(clean($_POST['identifier'])) : '' ?>"
                 autocomplete="username" required>
        </div>

        <div class="nox-form-group">
          <label for="password">Password</label>
          <div class="nox-input-wrap">
            <input type="password" id="password" name="password" class="nox-input has-icon"
                   placeholder="Masukkan password kamu"
                   autocomplete="current-password" required>
            <button type="button" class="input-icon-btn" onclick="togglePass('password', 'eyePassword')" aria-label="Toggle password">
              <svg id="eyePassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-eye"/></svg>
            </button>
          </div>
        </div>

        <div class="nox-checkbox-row">
          <label class="nox-checkbox">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat saya</span>
          </label>
          <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="auth-link">Lupa Password?</a>
        </div>

        <!-- CAPTCHA -->
        <div class="nox-form-group">
          <label>Verifikasi Keamanan</label>
          <div class="captcha-row">
            <span class="captcha-label"><?= $captchaA ?> + <?= $captchaB ?> = ?</span>
            <input type="number" name="captcha_answer" class="nox-input captcha-input"
                   placeholder="..." min="0" max="18" required>
          </div>
        </div>

        <button type="submit" class="btn-auth" id="loginBtn">
          Masuk Sekarang
        </button>
      </form>

      <div class="auth-divider">
        <div class="auth-divider-line"></div>
        <span>atau</span>
        <div class="auth-divider-line"></div>
      </div>

      <div class="auth-register-row">
        Belum punya akun?
        <a href="<?= BASE_URL ?>/auth/register.php">Daftar Gratis</a>
      </div>
      <div style="text-align:center;margin-top:.6rem;">
        <a href="<?= BASE_URL ?>" style="font-size:.8rem;color:#374151;">
          ← Kembali ke Beranda
        </a>
      </div>
    </div>
  </div>
</div>

<script>
function togglePass(fieldId, iconId) {
  const field = document.getElementById(fieldId);
  const icon  = document.getElementById(iconId);
  if (!field || !icon) return;
  if (field.type === 'password') {
    field.type = 'text';
    icon.innerHTML = '<use href="#icon-eye-off"/>';
  } else {
    field.type = 'password';
    icon.innerHTML = '<use href="#icon-eye"/>';
  }
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  const identifier = document.getElementById('identifier').value.trim();
  const password   = document.getElementById('password').value;
  if (!identifier || !password) {
    e.preventDefault();
    return;
  }
  document.getElementById('loginBtn').disabled = true;
  document.getElementById('loginBtn').textContent = 'Memproses...';
});
</script>
</body>
</html>
