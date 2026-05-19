<?php
/**
 * NOXARA - Hubungi Kami
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$user   = getCurrentUser();
$userId = (int)$user['id'];
$success = '';
$error   = '';

// Ambil contact settings
$waNumber   = getSetting('contact_whatsapp', '');
$tgUsername = getSetting('contact_telegram', '');
$email      = getSetting('contact_email', '');
$csStatus   = getSetting('cs_status', 'online');
$csHours    = getSetting('cs_hours', '08:00 - 22:00 WIB');
$fbUrl      = getSetting('social_facebook', '');
$igUrl      = getSetting('social_instagram', '');
$ytUrl      = getSetting('social_youtube', '');
$ttUrl      = getSetting('social_tiktok', '');

// Handle form saran/pertanyaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid';
    } else {
        $subject = clean($_POST['subject'] ?? '');
        $message = clean($_POST['message'] ?? '');

        if (empty($subject) || empty($message)) {
            $error = 'Subjek dan pesan wajib diisi';
        } else {
            // Ambil/buat room chat
            $stmtR = db()->prepare("SELECT id FROM chat_rooms WHERE user_id=? LIMIT 1");
            $stmtR->bind_param('i', $userId);
            $stmtR->execute();
            $room = $stmtR->get_result()->fetch_assoc();
            $stmtR->close();

            if (!$room) {
                $stmtC = db()->prepare("INSERT INTO chat_rooms (user_id, status, created_at, updated_at) VALUES (?, 'open', NOW(), NOW())");
                $stmtC->bind_param('i', $userId);
                $stmtC->execute();
                $roomId = (int)$stmtC->insert_id;
                $stmtC->close();
            } else {
                $roomId = (int)$room['id'];
            }

            $fullMsg = "[SARAN/PERTANYAAN]\nSubjek: $subject\n\n$message";
            $senderType = 'user';
            $stmtI = db()->prepare("INSERT INTO chat_messages (room_id, sender_type, sender_id, message, created_at) VALUES (?,?,?,?,NOW())");
            $stmtI->bind_param('isis', $roomId, $senderType, $userId, $fullMsg);
            $stmtI->execute(); $stmtI->close();

            $stmtU = db()->prepare("UPDATE chat_rooms SET last_message=?, unread_by_admin=unread_by_admin+1, updated_at=NOW() WHERE id=?");
            $preview = mb_substr($fullMsg, 0, 100);
            $stmtU->bind_param('si', $preview, $roomId);
            $stmtU->execute(); $stmtU->close();

            $success = 'Pesan berhasil dikirim! Tim CS kami akan segera merespons.';
        }
    }
}

$csStatusConfig = [
    'online'  => ['var(--green)', '● Online', 'Siap membantu Anda sekarang'],
    'busy'    => ['var(--amber)', '● Sibuk',  'Mungkin ada sedikit keterlambatan respons'],
    'offline' => ['var(--red)',   '● Offline','Akan direspons pada jam operasional berikutnya'],
];
$csConfig = $csStatusConfig[$csStatus] ?? $csStatusConfig['online'];
$pageTitle = 'Hubungi Kami';
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
.contact-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:28px}
.contact-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:24px;text-align:center;transition:.2s}
.contact-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,212,255,.1)}
.contact-card__icon{font-size:40px;margin-bottom:12px}
.contact-card__title{font-size:15px;font-weight:700;margin-bottom:4px}
.contact-card__desc{font-size:12px;color:var(--text-secondary);margin-bottom:14px}
.form-group{margin-bottom:16px}
.form-label{font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.form-input,.form-textarea{width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-light);border-radius:10px;padding:11px 14px;color:var(--text-primary);font-size:14px;outline:none;transition:.2s;box-sizing:border-box;font-family:inherit}
.form-input:focus,.form-textarea:focus{border-color:var(--cyan)}
.form-textarea{resize:vertical;min-height:120px}
.socmed-row{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:8px}
.socmed-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:99px;font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">📞 Hubungi Kami</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Kami siap membantu Anda kapan saja</p>
</div>

<!-- STATUS CS -->
<div style="background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.2);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
  <div style="font-size:40px">🎧</div>
  <div style="flex:1">
    <div style="font-weight:700;font-size:16px;color:<?= $csConfig[0] ?>"><?= $csConfig[1] ?> — CS NOXARA</div>
    <div style="font-size:13px;color:var(--text-secondary);margin-top:2px"><?= htmlspecialchars($csConfig[2]) ?></div>
  </div>
  <div style="text-align:right">
    <div style="font-size:11px;color:var(--text-secondary)">Jam Operasional</div>
    <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($csHours) ?></div>
  </div>
</div>

<!-- KONTAK CARDS -->
<div class="contact-grid">
  <?php if ($waNumber): ?>
  <div class="contact-card" style="border-color:rgba(37,211,102,.3)">
    <div class="contact-card__icon">📱</div>
    <div class="contact-card__title">WhatsApp</div>
    <div class="contact-card__desc">Chat langsung via WhatsApp</div>
    <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/\D/','',$waNumber)) ?>?text=Halo+CS+NOXARA,+saya+butuh+bantuan" target="_blank" rel="noopener" class="nox-btn" style="width:100%;background:rgba(37,211,102,.15);color:#25D366;border:1px solid rgba(37,211,102,.3);display:block;text-align:center;padding:10px;border-radius:10px;font-weight:700;text-decoration:none">
      💬 Chat WA
    </a>
  </div>
  <?php endif; ?>

  <?php if ($tgUsername): ?>
  <div class="contact-card" style="border-color:rgba(0,136,204,.3)">
    <div class="contact-card__icon">✈️</div>
    <div class="contact-card__title">Telegram</div>
    <div class="contact-card__desc">Chat via Telegram</div>
    <a href="https://t.me/<?= htmlspecialchars($tgUsername) ?>" target="_blank" rel="noopener" class="nox-btn" style="width:100%;background:rgba(0,136,204,.15);color:#0088CC;border:1px solid rgba(0,136,204,.3);display:block;text-align:center;padding:10px;border-radius:10px;font-weight:700;text-decoration:none">
      ✈️ Buka TG
    </a>
  </div>
  <?php endif; ?>

  <?php if ($email): ?>
  <div class="contact-card" style="border-color:rgba(255,68,68,.3)">
    <div class="contact-card__icon">📧</div>
    <div class="contact-card__title">Email</div>
    <div class="contact-card__desc"><?= htmlspecialchars($email) ?></div>
    <a href="mailto:<?= htmlspecialchars($email) ?>" class="nox-btn" style="width:100%;background:rgba(255,68,68,.12);color:#FF4444;border:1px solid rgba(255,68,68,.3);display:block;text-align:center;padding:10px;border-radius:10px;font-weight:700;text-decoration:none">
      📧 Kirim Email
    </a>
  </div>
  <?php endif; ?>

  <div class="contact-card" style="border-color:rgba(0,212,255,.3)">
    <div class="contact-card__icon">💬</div>
    <div class="contact-card__title">Live Chat</div>
    <div class="contact-card__desc">Chat langsung di platform</div>
    <a href="<?= BASE_URL ?>/pages/chat.php" class="nox-btn nox-btn--primary" style="width:100%;display:block;text-align:center;padding:10px;text-decoration:none">
      💬 Mulai Chat →
    </a>
  </div>
</div>

<!-- SOSIAL MEDIA -->
<?php if ($fbUrl || $igUrl || $ytUrl || $ttUrl): ?>
<div class="nox-card" style="padding:20px;text-align:center;margin-bottom:24px">
  <div style="font-size:15px;font-weight:700;margin-bottom:14px">📲 Ikuti Kami di Media Sosial</div>
  <div class="socmed-row">
    <?php if ($igUrl): ?><a href="<?= htmlspecialchars($igUrl) ?>" target="_blank" rel="noopener" class="socmed-btn" style="background:rgba(233,30,99,.15);color:#E91E63">📸 Instagram</a><?php endif; ?>
    <?php if ($fbUrl): ?><a href="<?= htmlspecialchars($fbUrl) ?>" target="_blank" rel="noopener" class="socmed-btn" style="background:rgba(24,119,242,.15);color:#1877F2">👍 Facebook</a><?php endif; ?>
    <?php if ($ytUrl): ?><a href="<?= htmlspecialchars($ytUrl) ?>" target="_blank" rel="noopener" class="socmed-btn" style="background:rgba(255,0,0,.15);color:#FF0000">▶️ YouTube</a><?php endif; ?>
    <?php if ($ttUrl): ?><a href="<?= htmlspecialchars($ttUrl) ?>" target="_blank" rel="noopener" class="socmed-btn" style="background:rgba(255,255,255,.1);color:var(--text-primary)">🎵 TikTok</a><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- FORM SARAN -->
<div class="nox-card" style="padding:28px">
  <h2 style="font-size:16px;font-weight:700;margin:0 0 6px">💡 Kirim Saran atau Pertanyaan</h2>
  <p style="font-size:13px;color:var(--text-secondary);margin:0 0 20px">Pesan akan diteruskan ke tim CS kami</p>

  <?php if ($success): ?>
    <div style="background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px;color:var(--green)">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div style="background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.3);border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px;color:var(--red)">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" style="max-width:560px">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="send_message">
    <div class="form-group">
      <label class="form-label">Subjek</label>
      <input type="text" name="subject" class="form-input" placeholder="Contoh: Pertanyaan tentang deposit" required maxlength="200">
    </div>
    <div class="form-group">
      <label class="form-label">Pesan</label>
      <textarea name="message" class="form-textarea" placeholder="Tuliskan saran atau pertanyaan Anda di sini..." required maxlength="2000"></textarea>
    </div>
    <button type="submit" class="nox-btn nox-btn--primary">📤 Kirim Pesan</button>
  </form>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body></html>
