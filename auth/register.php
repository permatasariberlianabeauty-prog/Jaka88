<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Redirect jika sudah login
if (isLoggedIn()) redirect('/dashboard');

$errors  = [];
$success = '';

// Generate math captcha
if (empty($_SESSION['reg_captcha_a']) || empty($_SESSION['reg_captcha_b'])) {
    $_SESSION['reg_captcha_a'] = rand(1, 9);
    $_SESSION['reg_captcha_b'] = rand(1, 9);
}
$captchaA = (int)$_SESSION['reg_captcha_a'];
$captchaB = (int)$_SESSION['reg_captcha_b'];

// Referral dari URL
$refCode = clean($_GET['ref'] ?? '');

// Settings
$freeBonusNew = (int)getSetting('free_bonus_new_member', 5000);
$siteName     = getSetting('site_name', 'NOXARA');

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $fullName   = clean($_POST['full_name'] ?? '');
    $username   = clean($_POST['username'] ?? '');
    $email      = clean($_POST['email'] ?? '');
    $phone      = clean($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $passConf   = $_POST['password_confirm'] ?? '';
    $referral   = clean($_POST['referral_code'] ?? '');
    $captchaAns = (int)($_POST['captcha_answer'] ?? -1);
    $agree      = isset($_POST['agree']) ? true : false;

    // Validasi
    if (empty($fullName))   $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($username))   $errors[] = 'Username wajib diisi.';
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) $errors[] = 'Username 3-20 karakter, hanya huruf, angka, dan underscore.';
    if (empty($email))      $errors[] = 'Email wajib diisi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (empty($phone))      $errors[] = 'Nomor HP wajib diisi.';
    elseif (!preg_match('/^[0-9]{9,15}$/', preg_replace('/[^0-9]/', '', $phone))) $errors[] = 'Nomor HP tidak valid.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    elseif ($password !== $passConf) $errors[] = 'Konfirmasi password tidak cocok.';
    if (!$agree)            $errors[] = 'Kamu harus menyetujui syarat & ketentuan.';
    if ($captchaAns !== ($captchaA + $captchaB)) {
        $errors[] = 'Jawaban captcha salah.';
        $_SESSION['reg_captcha_a'] = rand(1, 9);
        $_SESSION['reg_captcha_b'] = rand(1, 9);
        $captchaA = (int)$_SESSION['reg_captcha_a'];
        $captchaB = (int)$_SESSION['reg_captcha_b'];
    }

    if (empty($errors)) {
        // Normalisasi phone (+62 → 0)
        $phoneClean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phoneClean, '62')) $phoneClean = '0' . substr($phoneClean, 2);

        $result = registerUser([
            'full_name'     => $fullName,
            'username'      => $username,
            'email'         => $email,
            'phone'         => $phoneClean,
            'password'      => $password,
            'referral_code' => $referral,
        ]);

        if ($result['success']) {
            unset($_SESSION['reg_captcha_a'], $_SESSION['reg_captcha_b']);
            setFlash('success', '🎉 Akun berhasil dibuat! Kamu mendapat bonus Rp ' . number_format($freeBonusNew, 0, ',', '.') . '. Silakan login.');
            redirect('/auth/login.php');
        } else {
            $errors[] = $result['message'];
            $_SESSION['reg_captcha_a'] = rand(1, 9);
            $_SESSION['reg_captcha_b'] = rand(1, 9);
            $captchaA = (int)$_SESSION['reg_captcha_a'];
            $captchaB = (int)$_SESSION['reg_captcha_b'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar - <?= htmlspecialchars($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<style>
:root{--cyan:#00D4FF;--purple:#7B2FFF;--bg:#0A0E1A;--card:#0F1629;--border:rgba(0,212,255,.12);--green:#00e676;}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{background:var(--bg);color:#e2e8f0;font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;overflow-x:hidden;}
a{color:inherit;text-decoration:none;}
.auth-bg{min-height:100vh;display:grid;grid-template-columns:1fr 1fr;background:radial-gradient(ellipse 70% 50% at 20% 50%,rgba(123,47,255,.2) 0%,transparent 60%),radial-gradient(ellipse 40% 40% at 80% 20%,rgba(0,212,255,.1) 0%,transparent 50%),var(--bg);}
.auth-brand{display:flex;flex-direction:column;justify-content:center;padding:3rem 3rem 3rem 4rem;position:relative;overflow:hidden;}
.auth-brand-glow{position:absolute;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(123,47,255,.2) 0%,transparent 70%);top:-100px;left:-100px;pointer-events:none;}
.auth-logo{display:flex;align-items:center;gap:.7rem;margin-bottom:2rem;}
.auth-logo svg{width:44px;height:44px;}
.auth-logo-text{font-family:'Orbitron',sans-serif;font-weight:700;font-size:1.6rem;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:.06em;}
.auth-brand-title{font-family:'Space Grotesk',sans-serif;font-size:clamp(1.6rem,2.5vw,2.4rem);font-weight:800;line-height:1.2;margin-bottom:.8rem;}
.auth-gradient-text{background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.auth-brand-sub{font-size:.9rem;color:#64748b;margin-bottom:1.5rem;line-height:1.7;max-width:340px;}
.bonus-badge{display:inline-flex;align-items:center;gap:.6rem;background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);border-radius:12px;padding:.8rem 1.2rem;margin-bottom:1.5rem;}
.bonus-badge-icon{font-size:1.4rem;}
.bonus-badge-text strong{display:block;font-size:.95rem;font-weight:700;color:var(--green);}
.bonus-badge-text span{font-size:.78rem;color:#64748b;}
.auth-features{display:flex;flex-direction:column;gap:.7rem;}
.auth-feat{display:flex;align-items:center;gap:.7rem;font-size:.85rem;color:#94a3b8;}
.auth-feat svg{color:var(--green);flex-shrink:0;}
.auth-form-side{display:flex;align-items:center;justify-content:center;padding:2rem 3rem 2rem 2rem;overflow-y:auto;}
.auth-card{background:rgba(15,22,41,.9);border:1px solid var(--border);border-radius:24px;padding:2rem 2.2rem;width:100%;max-width:460px;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);}
.auth-card-title{font-family:'Space Grotesk',sans-serif;font-size:1.45rem;font-weight:800;margin-bottom:.3rem;}
.auth-card-sub{font-size:.83rem;color:#64748b;margin-bottom:1.5rem;}
.auth-card-sub strong{color:var(--green);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
.nox-form-group{margin-bottom:1rem;}
.nox-form-group label{display:block;font-size:.78rem;font-weight:600;color:#94a3b8;margin-bottom:.35rem;}
.nox-input-wrap{position:relative;}
.nox-input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.7rem 1rem;color:#e2e8f0;font-size:.88rem;font-family:inherit;transition:all .25s;outline:none;}
.nox-input:focus{border-color:var(--cyan);background:rgba(0,212,255,.04);box-shadow:0 0 0 3px rgba(0,212,255,.1);}
.nox-input.has-icon-l{padding-left:3rem;}
.nox-input.has-icon-r{padding-right:2.8rem;}
.nox-input.error{border-color:rgba(255,68,68,.5);}
.nox-input.valid{border-color:rgba(0,230,118,.4);}
.nox-input::placeholder{color:#374151;}
.input-prefix{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:.85rem;color:#64748b;pointer-events:none;}
.input-icon-btn{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;padding:.2rem;display:flex;align-items:center;justify-content:center;transition:color .2s;}
.input-icon-btn:hover{color:var(--cyan);}
.input-icon-btn svg{width:17px;height:17px;}
.input-status{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);font-size:.7rem;font-weight:700;pointer-events:none;}
.input-status.ok{color:var(--green);}
.input-status.bad{color:#ff4444;}
/* PASSWORD STRENGTH */
.pw-strength{margin-top:.4rem;}
.pw-strength-bars{display:flex;gap:3px;margin-bottom:.25rem;}
.pw-bar{height:3px;border-radius:2px;flex:1;background:rgba(255,255,255,.08);transition:background .3s;}
.pw-bar.weak{background:#ff4444;}
.pw-bar.medium{background:#FFB300;}
.pw-bar.strong{background:var(--green);}
.pw-label{font-size:.7rem;color:#64748b;}
/* PHONE PREFIX */
.phone-group{display:flex;gap:.4rem;align-items:center;}
.phone-prefix{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:.7rem .8rem;font-size:.85rem;color:#94a3b8;white-space:nowrap;flex-shrink:0;}
/* CAPTCHA */
.captcha-row{display:flex;align-items:center;gap:.7rem;}
.captcha-label{font-size:.88rem;color:#e2e8f0;font-weight:600;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:.5rem .8rem;white-space:nowrap;}
.captcha-input{width:80px!important;text-align:center;}
/* CHECKBOX */
.nox-checkbox{display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;}
.nox-checkbox input{width:16px;height:16px;border-radius:4px;accent-color:var(--cyan);margin-top:.15rem;flex-shrink:0;}
.nox-checkbox span{font-size:.82rem;color:#94a3b8;line-height:1.5;}
.nox-checkbox span a{color:var(--cyan);text-decoration:underline;}
/* BUTTONS */
.btn-auth{width:100%;padding:.8rem 1.5rem;border-radius:10px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;font-size:.95rem;font-family:inherit;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(123,47,255,.3);margin-top:1rem;}
.btn-auth:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(123,47,255,.45);}
.btn-auth:disabled{opacity:.6;cursor:not-allowed;transform:none;}
/* ERRORS */
.auth-errors{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.25);border-radius:10px;padding:.9rem 1rem;margin-bottom:1rem;}
.auth-errors p{font-size:.82rem;color:#ff6b6b;display:flex;align-items:flex-start;gap:.4rem;line-height:1.5;}
.auth-errors p+p{margin-top:.3rem;}
.auth-register-row{text-align:center;margin-top:1rem;font-size:.83rem;color:#64748b;}
.auth-register-row a{color:var(--cyan);font-weight:600;}
@media(max-width:900px){
  .auth-bg{grid-template-columns:1fr;}
  .auth-brand{display:none;}
  .auth-form-side{padding:1.5rem;min-height:100vh;align-items:flex-start;padding-top:2rem;}
  .form-row{grid-template-columns:1fr;}
  .auth-card{max-width:100%;}
}
</style>
</head>
<body>
<?php include ASSETS_PATH . '/img/icons/icons.svg'; ?>
<div class="auth-bg">
  <!-- BRANDING -->
  <div class="auth-brand">
    <div class="auth-brand-glow"></div>
    <a href="<?= BASE_URL ?>" class="auth-logo">
      <svg viewBox="0 0 40 40"><use href="#icon-noxara"/></svg>
      <span class="auth-logo-text">NOXARA</span>
    </a>
    <h1 class="auth-brand-title">Mulai <span class="auth-gradient-text">Perjalanan</span><br>Investasi Kamu</h1>
    <p class="auth-brand-sub">Daftar sekarang dan bergabung dengan ratusan ribu member yang sudah merasakan profit harian bersama NOXARA.</p>
    <div class="bonus-badge">
      <div class="bonus-badge-icon">🎁</div>
      <div class="bonus-badge-text">
        <strong>Bonus Rp <?= number_format($freeBonusNew, 0, ',', '.') ?> untuk member baru!</strong>
        <span>Langsung masuk ke saldo kamu setelah daftar</span>
      </div>
    </div>
    <div class="auth-features">
      <div class="auth-feat">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-check-circle"/></svg>
        Daftar gratis, tanpa biaya apapun
      </div>
      <div class="auth-feat">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-check-circle"/></svg>
        Profit harian masuk otomatis
      </div>
      <div class="auth-feat">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-check-circle"/></svg>
        Komisi referral 3 level
      </div>
      <div class="auth-feat">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-check-circle"/></svg>
        Withdraw kapan saja
      </div>
    </div>
  </div>

  <!-- FORM -->
  <div class="auth-form-side">
    <div class="auth-card">
      <h2 class="auth-card-title">Buat Akun Baru</h2>
      <p class="auth-card-sub">Dapat saldo bonus <strong>Rp <?= number_format($freeBonusNew, 0, ',', '.') ?></strong> setelah daftar!</p>

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

      <form method="POST" action="" id="regForm" novalidate>
        <?= csrfField() ?>

        <div class="form-row">
          <div class="nox-form-group">
            <label for="full_name">Nama Lengkap</label>
            <input type="text" id="full_name" name="full_name" class="nox-input"
                   placeholder="Nama lengkap kamu"
                   value="<?= isset($_POST['full_name']) ? htmlspecialchars(clean($_POST['full_name'])) : '' ?>"
                   autocomplete="name" required>
          </div>
          <div class="nox-form-group">
            <label for="username">Username</label>
            <div class="nox-input-wrap">
              <input type="text" id="username" name="username" class="nox-input has-icon-r"
                     placeholder="username123"
                     value="<?= isset($_POST['username']) ? htmlspecialchars(clean($_POST['username'])) : '' ?>"
                     autocomplete="username" maxlength="20" required>
              <span class="input-status" id="usernameStatus"></span>
            </div>
            <div style="font-size:.7rem;color:#374151;margin-top:.2rem;">3-20 karakter, huruf/angka/_</div>
          </div>
        </div>

        <div class="nox-form-group">
          <label for="email">Alamat Email</label>
          <input type="email" id="email" name="email" class="nox-input"
                 placeholder="email@contoh.com"
                 value="<?= isset($_POST['email']) ? htmlspecialchars(clean($_POST['email'])) : '' ?>"
                 autocomplete="email" required>
        </div>

        <div class="nox-form-group">
          <label for="phone">Nomor HP</label>
          <div class="phone-group">
            <span class="phone-prefix">+62</span>
            <input type="tel" id="phone" name="phone" class="nox-input"
                   placeholder="8xxxxxxxxxx"
                   value="<?= isset($_POST['phone']) ? htmlspecialchars(clean($_POST['phone'])) : '' ?>"
                   autocomplete="tel" required>
          </div>
        </div>

        <div class="form-row">
          <div class="nox-form-group">
            <label for="password">Password</label>
            <div class="nox-input-wrap">
              <input type="password" id="password" name="password" class="nox-input has-icon-r"
                     placeholder="Min. 6 karakter"
                     autocomplete="new-password" required>
              <button type="button" class="input-icon-btn" onclick="togglePass('password','eyePass1')" aria-label="Toggle">
                <svg id="eyePass1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-eye"/></svg>
              </button>
            </div>
            <div class="pw-strength" id="pwStrength" style="display:none;">
              <div class="pw-strength-bars">
                <div class="pw-bar" id="pb1"></div>
                <div class="pw-bar" id="pb2"></div>
                <div class="pw-bar" id="pb3"></div>
                <div class="pw-bar" id="pb4"></div>
              </div>
              <div class="pw-label" id="pwLabel"></div>
            </div>
          </div>
          <div class="nox-form-group">
            <label for="password_confirm">Konfirmasi Password</label>
            <div class="nox-input-wrap">
              <input type="password" id="password_confirm" name="password_confirm" class="nox-input has-icon-r"
                     placeholder="Ulangi password"
                     autocomplete="new-password" required>
              <button type="button" class="input-icon-btn" onclick="togglePass('password_confirm','eyePass2')" aria-label="Toggle">
                <svg id="eyePass2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-eye"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="nox-form-group">
          <label for="referral_code">Kode Referral <span style="color:#374151;">(opsional)</span></label>
          <input type="text" id="referral_code" name="referral_code" class="nox-input"
                 placeholder="Masukkan kode referral jika ada"
                 value="<?= htmlspecialchars($refCode ?: (isset($_POST['referral_code']) ? clean($_POST['referral_code']) : '')) ?>"
                 maxlength="20" autocomplete="off" style="text-transform:uppercase;">
        </div>

        <div class="nox-form-group">
          <label>Verifikasi Keamanan</label>
          <div class="captcha-row">
            <span class="captcha-label"><?= $captchaA ?> + <?= $captchaB ?> = ?</span>
            <input type="number" name="captcha_answer" class="nox-input captcha-input"
                   placeholder="..." min="0" max="18" required>
          </div>
        </div>

        <div class="nox-form-group">
          <label class="nox-checkbox">
            <input type="checkbox" name="agree" value="1" id="agreeChk" <?= isset($_POST['agree']) ? 'checked' : '' ?>>
            <span>Saya setuju dengan <a href="#" target="_blank">Syarat &amp; Ketentuan</a> dan <a href="#" target="_blank">Kebijakan Privasi</a> NOXARA.</span>
          </label>
        </div>

        <button type="submit" class="btn-auth" id="regBtn">
          🚀 Buat Akun &amp; Klaim Bonus
        </button>
      </form>

      <div class="auth-register-row">
        Sudah punya akun?
        <a href="<?= BASE_URL ?>/auth/login.php">Login Sekarang</a>
      </div>
      <div style="text-align:center;margin-top:.5rem;">
        <a href="<?= BASE_URL ?>" style="font-size:.78rem;color:#374151;">← Kembali ke Beranda</a>
      </div>
    </div>
  </div>
</div>

<script>
// Toggle password visibility
function togglePass(fieldId, iconId) {
  const f = document.getElementById(fieldId);
  const i = document.getElementById(iconId);
  if (!f || !i) return;
  if (f.type === 'password') { f.type = 'text'; i.innerHTML = '<use href="#icon-eye-off"/>'; }
  else { f.type = 'password'; i.innerHTML = '<use href="#icon-eye"/>'; }
}

// Password strength
document.getElementById('password').addEventListener('input', function() {
  const v = this.value;
  const str = document.getElementById('pwStrength');
  if (!v) { str.style.display = 'none'; return; }
  str.style.display = 'block';
  let score = 0;
  if (v.length >= 6)  score++;
  if (v.length >= 10) score++;
  if (/[A-Z]/.test(v) && /[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const bars  = ['pb1','pb2','pb3','pb4'];
  const cls   = score <= 1 ? 'weak' : score <= 2 ? 'medium' : 'strong';
  const labels = ['','Lemah','Sedang','Kuat','Sangat Kuat'];
  bars.forEach((id, i) => {
    const el = document.getElementById(id);
    el.className = 'pw-bar' + (i < score ? ' ' + cls : '');
  });
  document.getElementById('pwLabel').textContent = labels[score] || '';
});

// Username availability check
let uTimer;
document.getElementById('username').addEventListener('input', function() {
  clearTimeout(uTimer);
  const v = this.value.trim();
  const st = document.getElementById('usernameStatus');
  if (v.length < 3) { st.textContent = ''; this.classList.remove('valid','error'); return; }
  if (!/^[a-zA-Z0-9_]{3,20}$/.test(v)) {
    st.textContent = '✗'; st.className = 'input-status bad';
    this.classList.add('error'); this.classList.remove('valid'); return;
  }
  uTimer = setTimeout(() => {
    fetch('<?= BASE_URL ?>/api/check_username.php?u=' + encodeURIComponent(v))
      .then(r => r.json())
      .then(d => {
        if (d.available) {
          st.textContent = '✓'; st.className = 'input-status ok';
          document.getElementById('username').classList.add('valid');
          document.getElementById('username').classList.remove('error');
        } else {
          st.textContent = '✗'; st.className = 'input-status bad';
          document.getElementById('username').classList.add('error');
          document.getElementById('username').classList.remove('valid');
        }
      }).catch(() => { st.textContent = ''; });
  }, 500);
});

// Auto-uppercase referral
document.getElementById('referral_code').addEventListener('input', function() {
  const pos = this.selectionStart;
  this.value = this.value.toUpperCase();
  this.setSelectionRange(pos, pos);
});

// Submit guard
document.getElementById('regForm').addEventListener('submit', function(e) {
  if (!document.getElementById('agreeChk').checked) {
    e.preventDefault(); alert('Kamu harus menyetujui syarat & ketentuan terlebih dahulu.'); return;
  }
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password_confirm').value;
  if (p1 !== p2) { e.preventDefault(); alert('Konfirmasi password tidak cocok.'); return; }
  document.getElementById('regBtn').disabled = true;
  document.getElementById('regBtn').textContent = 'Membuat akun...';
});
</script>
</body>
</html>
