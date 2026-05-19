<?php
/**
 * NOXARA - Profil Member
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];
$errors = [];
$success = '';

// ── Handle POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token tidak valid';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $fullName   = clean($_POST['full_name'] ?? '');
            $phone      = clean($_POST['phone'] ?? '');
            $themeMode  = in_array($_POST['theme_mode'] ?? 'dark', ['dark','light']) ? $_POST['theme_mode'] : 'dark';

            if (empty($fullName)) $errors[] = 'Nama lengkap wajib diisi';
            if ($phone && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) $errors[] = 'Format nomor HP tidak valid';

            if (empty($errors)) {
                $stmt = db()->prepare("UPDATE users SET full_name=?, phone=?, theme_mode=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param('sssi', $fullName, $phone, $themeMode, $userId);
                $stmt->execute();
                $stmt->close();
                $success = 'Profil berhasil diperbarui!';
                $user = getCurrentUser();
            }
        }

        if ($action === 'upload_avatar') {
            if (empty($_FILES['avatar']['name'])) {
                $errors[] = 'Pilih file gambar terlebih dahulu';
            } else {
                require_once __DIR__ . '/../includes/functions.php';
                $filename = uploadImage($_FILES['avatar'], 'avatars', 'avatar_' . $userId);
                if ($filename) {
                    // Hapus avatar lama
                    if (!empty($user['avatar'])) deleteUpload('avatars', $user['avatar']);
                    $stmt = db()->prepare("UPDATE users SET avatar=?, updated_at=NOW() WHERE id=?");
                    $stmt->bind_param('si', $filename, $userId);
                    $stmt->execute();
                    $stmt->close();
                    $success = 'Foto profil berhasil diperbarui!';
                    $user = getCurrentUser();
                } else {
                    $errors[] = 'Gagal upload. Pastikan file adalah gambar (JPG/PNG) maks 2MB';
                }
            }
        }
    }
}

$avatarUrl = !empty($user['avatar'])
    ? UPLOADS_URL . '/avatars/' . htmlspecialchars($user['avatar'])
    : ASSETS_URL . '/img/avatars/default.png';

$vipInfo    = getVipInfo((int)($user['vip_level'] ?? 0));
$vipColor   = $vipInfo['color'] ?? '#6B7A99';
$vipLabel   = $vipInfo['badge_label'] ?? 'BASIC';
$pageTitle  = 'Profil Saya';
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
.profile-layout{display:grid;grid-template-columns:300px 1fr;gap:24px}
@media(max-width:768px){.profile-layout{grid-template-columns:1fr}}
.avatar-wrap{position:relative;width:120px;height:120px;margin:0 auto 16px}
.avatar-img{width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--cyan)}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s;cursor:pointer;font-size:24px}
.avatar-wrap:hover .avatar-overlay{opacity:1}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(30,42,69,.5);font-size:13px}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--text-secondary)}
.info-value{font-weight:600;text-align:right;max-width:55%}
.form-group{margin-bottom:18px}
.form-label{font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.form-input{width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-light);border-radius:10px;padding:11px 14px;color:var(--text-primary);font-size:14px;outline:none;transition:.2s;box-sizing:border-box}
.form-input:focus{border-color:var(--cyan);background:rgba(0,212,255,.04)}
.toggle-wrap{display:flex;align-items:center;gap:12px}
.toggle{width:48px;height:26px;background:rgba(255,255,255,.1);border-radius:99px;position:relative;cursor:pointer;transition:.3s}
.toggle.active{background:var(--cyan)}
.toggle::after{content:'';position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.3s}
.toggle.active::after{left:25px}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">👤 Profil Saya</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Kelola informasi akun Anda</p>
</div>

<?php if ($errors): ?>
  <div style="background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.3);border-radius:10px;padding:14px;margin-bottom:20px">
    <?php foreach ($errors as $e): ?><div style="font-size:13px;color:var(--red)">❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($success): ?>
  <div style="background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px;color:var(--green)">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="profile-layout">
  <!-- KOLOM KIRI: Avatar + Info -->
  <div>
    <div class="nox-card" style="padding:24px;text-align:center;margin-bottom:16px">
      <form method="POST" enctype="multipart/form-data" id="avatarForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none" onchange="document.getElementById('avatarForm').submit()">
        <div class="avatar-wrap" onclick="document.getElementById('avatarInput').click()">
          <img src="<?= $avatarUrl ?>" alt="" class="avatar-img" onerror="this.src='<?= ASSETS_URL ?>/img/avatars/default.png'">
          <div class="avatar-overlay">📷</div>
        </div>
      </form>
      <div style="font-size:18px;font-weight:800;margin-bottom:4px"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">@<?= htmlspecialchars($user['username']) ?></div>
      <span style="background:<?= $vipColor ?>22;color:<?= $vipColor ?>;padding:4px 14px;border-radius:99px;font-size:11px;font-weight:800;letter-spacing:.08em">
        VIP <?= (int)($user['vip_level'] ?? 0) ?> · <?= htmlspecialchars($vipLabel) ?>
      </span>
      <div style="font-size:11px;color:var(--text-secondary);margin-top:12px">Klik foto untuk ganti avatar</div>
    </div>

    <!-- Info Readonly -->
    <div class="nox-card" style="padding:16px 20px">
      <div style="font-size:13px;font-weight:700;margin-bottom:12px">📋 Info Akun</div>
      <div class="info-row"><span class="info-label">Username</span><span class="info-value">@<?= htmlspecialchars($user['username']) ?></span></div>
      <div class="info-row"><span class="info-label">Email</span><span class="info-value" style="word-break:break-all"><?= htmlspecialchars($user['email']) ?></span></div>
      <div class="info-row"><span class="info-label">Tgl Daftar</span><span class="info-value"><?= htmlspecialchars(date('d M Y', strtotime($user['created_at']))) ?></span></div>
      <div class="info-row">
        <span class="info-label">Kode Referral</span>
        <span class="info-value" style="display:flex;align-items:center;gap:6px">
          <span style="font-family:'Space Grotesk',sans-serif;color:var(--cyan)"><?= htmlspecialchars($user['referral_code'] ?? '-') ?></span>
          <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($user['referral_code'] ?? '') ?>').then(()=>alert('Disalin!'))" style="background:rgba(0,212,255,.1);color:var(--cyan);border:none;border-radius:6px;padding:3px 8px;font-size:10px;cursor:pointer">Salin</button>
        </span>
      </div>
      <div class="info-row"><span class="info-label">Status Akun</span><span class="info-value" style="color:var(--green)">✅ Aktif</span></div>
    </div>
  </div>

  <!-- KOLOM KANAN: Form Edit -->
  <div class="nox-card" style="padding:28px">
    <div style="font-size:16px;font-weight:700;margin-bottom:24px">✏️ Edit Profil</div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update_profile">

      <div class="form-group">
        <label class="form-label">Nama Lengkap</label>
        <input type="text" name="full_name" class="form-input" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Masukkan nama lengkap" required>
      </div>

      <div class="form-group">
        <label class="form-label">Nomor HP / WhatsApp</label>
        <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Contoh: 08123456789">
      </div>

      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:.5;cursor:not-allowed">
        <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Email tidak dapat diubah</div>
      </div>

      <div class="form-group">
        <label class="form-label">Tema</label>
        <div class="toggle-wrap">
          <div class="toggle <?= ($user['theme_mode'] ?? 'dark')==='dark'?'active':'' ?>" id="themeToggle" onclick="toggleTheme()"></div>
          <span style="font-size:14px" id="themeLabel"><?= ($user['theme_mode'] ?? 'dark')==='dark'?'🌙 Dark Mode':'☀️ Light Mode' ?></span>
          <input type="hidden" name="theme_mode" id="themeModeInput" value="<?= htmlspecialchars($user['theme_mode'] ?? 'dark') ?>">
        </div>
      </div>

      <button type="submit" class="nox-btn nox-btn--primary" style="width:100%;padding:14px;font-size:15px">
        💾 Simpan Perubahan
      </button>
    </form>
  </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function toggleTheme() {
  const toggle = document.getElementById('themeToggle');
  const input  = document.getElementById('themeModeInput');
  const label  = document.getElementById('themeLabel');
  const isDark = toggle.classList.contains('active');
  toggle.classList.toggle('active');
  input.value = isDark ? 'light' : 'dark';
  label.textContent = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
}
</script>
</body></html>
