<?php
/**
 * NOXARA - Info Platform
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$user = getCurrentUser();

$activeTab = in_array($_GET['tab'] ?? 'about', ['about','terms','privacy','withdrawal']) ? ($_GET['tab'] ?? 'about') : 'about';

// Ambil platform_info dari DB
$stmtInfo = db()->prepare("SELECT * FROM platform_info WHERE slug=? LIMIT 1");
$stmtInfo->bind_param('s', $activeTab);
$stmtInfo->execute();
$infoRow = $stmtInfo->get_result()->fetch_assoc();
$stmtInfo->close();

// Statistik dari settings
$totalMembers = getSetting('total_members_display', '10.000+');
$totalPayout  = getSetting('total_payout_display', 'Rp 5 Miliar+');
$foundedYear  = getSetting('founded_year', '2024');
$videoUrl     = getSetting('tutorial_video_url', '');
$rating       = getSetting('platform_rating', '4.9');

$pageTitle = 'Info Platform';
$tabLabels = [
    'about'      => '🏢 Tentang Kami',
    'terms'      => '📄 Syarat & Ketentuan',
    'privacy'    => '🔏 Kebijakan Privasi',
    'withdrawal' => '💸 Kebijakan WD',
];
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
.info-tabs{display:flex;gap:4px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:4px;margin-bottom:24px;flex-wrap:wrap}
.info-tab{flex:1;padding:10px 8px;text-align:center;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:var(--text-secondary);transition:.2s;white-space:nowrap}
.info-tab.active{background:var(--cyan);color:#000}
.info-content{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:28px;margin-bottom:24px;line-height:1.8;font-size:14px;color:var(--text-secondary)}
.info-content h2,.info-content h3{color:var(--text-primary);margin-top:24px}
.info-content ul{padding-left:20px}
.info-content li{margin-bottom:6px}
.info-content a{color:var(--cyan)}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
@media(max-width:640px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
.stat-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:18px;text-align:center}
.stat-card__val{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:800;color:var(--cyan);margin-bottom:4px}
.stat-card__lbl{font-size:11px;color:var(--text-secondary)}
.stars{color:#FFB300;font-size:18px;letter-spacing:2px}
.video-wrap{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;margin-bottom:24px}
.video-wrap iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:none}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">ℹ️ Info Platform</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Informasi lengkap tentang NOXARA</p>
</div>

<!-- STATISTIK -->
<div class="stat-grid">
  <div class="stat-card">
    <div style="font-size:28px;margin-bottom:6px">👥</div>
    <div class="stat-card__val"><?= htmlspecialchars($totalMembers) ?></div>
    <div class="stat-card__lbl">Total Member</div>
  </div>
  <div class="stat-card">
    <div style="font-size:28px;margin-bottom:6px">💰</div>
    <div class="stat-card__val"><?= htmlspecialchars($totalPayout) ?></div>
    <div class="stat-card__lbl">Total Payout</div>
  </div>
  <div class="stat-card">
    <div style="font-size:28px;margin-bottom:6px">⭐</div>
    <div class="stat-card__val"><?= htmlspecialchars($rating) ?>/5</div>
    <div class="stat-card__lbl">Rating Member</div>
    <div class="stars">★★★★★</div>
  </div>
  <div class="stat-card">
    <div style="font-size:28px;margin-bottom:6px">📅</div>
    <div class="stat-card__val"><?= htmlspecialchars($foundedYear) ?></div>
    <div class="stat-card__lbl">Berdiri Sejak</div>
  </div>
</div>

<!-- TABS -->
<div class="info-tabs">
  <?php foreach ($tabLabels as $k=>$v): ?>
    <a href="?tab=<?= $k ?>" class="info-tab <?= $activeTab===$k?'active':'' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<!-- KONTEN TAB -->
<div class="info-content">
  <?php if ($infoRow && !empty($infoRow['content'])): ?>
    <?= $infoRow['content'] // HTML dari DB, diasumsikan sudah disanitasi admin ?>
  <?php else: ?>
    <!-- Default content jika DB kosong -->
    <?php if ($activeTab === 'about'): ?>
    <h2>Tentang NOXARA</h2>
    <p>NOXARA adalah platform <strong>mining digital</strong> terpercaya yang beroperasi sejak <?= htmlspecialchars($foundedYear) ?>. Kami menyediakan sistem investasi paket mining yang menghasilkan profit harian bagi seluruh member kami.</p>
    <h3>🎯 Visi Kami</h3>
    <p>Menjadi platform investasi digital terbesar dan terpercaya di Indonesia dengan mengutamakan transparansi, keamanan, dan kepuasan member.</p>
    <h3>💡 Keunggulan NOXARA</h3>
    <ul>
      <li>✅ Platform terpercaya dengan track record payout lancar</li>
      <li>✅ Sistem referral 3 level yang menguntungkan</li>
      <li>✅ CS profesional siap membantu 24/7</li>
      <li>✅ Proses deposit & withdraw cepat</li>
      <li>✅ Beragam pilihan paket mining dari yang terjangkau</li>
      <li>✅ Sistem keamanan berlapis dengan PIN transaksi</li>
    </ul>
    <h3>📊 Sistem Mining</h3>
    <p>Member membeli paket mining dan mendapatkan profit harian dengan cara klik mining 1x sehari. Profit langsung masuk ke saldo Anda dan dapat ditarik kapan saja sesuai jadwal withdraw.</p>

    <?php elseif ($activeTab === 'terms'): ?>
    <h2>Syarat & Ketentuan</h2>
    <p>Dengan mendaftar dan menggunakan layanan NOXARA, Anda dianggap telah membaca, memahami, dan menyetujui syarat & ketentuan berikut:</p>
    <h3>1. Pendaftaran Akun</h3>
    <ul>
      <li>Setiap pengguna hanya diperbolehkan memiliki <strong>1 (satu) akun</strong></li>
      <li>Pengguna wajib memberikan data yang benar dan akurat</li>
      <li>Usia minimal pengguna adalah 17 tahun</li>
    </ul>
    <h3>2. Investasi & Profit</h3>
    <ul>
      <li>Investasi yang dilakukan merupakan tanggung jawab penuh pengguna</li>
      <li>NOXARA tidak menjamin keuntungan tetap karena bergantung pada aktivitas member</li>
      <li>Profit didapat dengan melakukan klik mining 1x sehari pada setiap paket aktif</li>
    </ul>
    <h3>3. Pelarangan</h3>
    <ul>
      <li>Dilarang melakukan penipuan, manipulasi, atau aktivitas ilegal</li>
      <li>Dilarang membuat akun ganda</li>
      <li>Dilarang menggunakan bot atau software otomatis</li>
      <li>Pelanggaran berakibat pemblokiran akun tanpa pemberitahuan</li>
    </ul>
    <h3>4. Perubahan Syarat</h3>
    <p>NOXARA berhak mengubah syarat & ketentuan kapan saja. Perubahan akan diinformasikan melalui notifikasi platform.</p>

    <?php elseif ($activeTab === 'privacy'): ?>
    <h2>Kebijakan Privasi</h2>
    <p>NOXARA berkomitmen menjaga kerahasiaan dan keamanan data pribadi seluruh member kami.</p>
    <h3>Data yang Kami Kumpulkan</h3>
    <ul>
      <li>Informasi identitas (nama, email, nomor HP)</li>
      <li>Data keuangan (nomor rekening untuk keperluan withdraw)</li>
      <li>Data aktivitas platform (transaksi, login, dll)</li>
      <li>Data teknis (IP address, browser, perangkat)</li>
    </ul>
    <h3>Penggunaan Data</h3>
    <ul>
      <li>Memproses transaksi deposit dan withdraw</li>
      <li>Memberikan layanan customer service</li>
      <li>Meningkatkan keamanan akun</li>
      <li>Mengirimkan informasi platform yang relevan</li>
    </ul>
    <h3>Keamanan Data</h3>
    <p>Data Anda disimpan menggunakan enkripsi dan tidak dibagikan kepada pihak ketiga tanpa persetujuan Anda, kecuali diwajibkan oleh hukum yang berlaku.</p>

    <?php elseif ($activeTab === 'withdrawal'): ?>
    <h2>Kebijakan Withdraw</h2>
    <h3>Ketentuan Umum</h3>
    <ul>
      <li>Minimal withdraw: <strong>Rp 50.000</strong></li>
      <li>Biaya admin: <strong>Rp 5.000 - Rp 10.000</strong> per transaksi</li>
      <li>Jadwal: <strong>Senin-Jumat, 08.00-17.00 WIB</strong></li>
      <li>Wajib verifikasi PIN transaksi 6 digit</li>
    </ul>
    <h3>Limit Withdraw Harian per VIP</h3>
    <ul>
      <li>VIP 0 (Basic): Rp 500.000/hari</li>
      <li>VIP 1 (Bronze): Rp 1.000.000/hari</li>
      <li>VIP 2 (Silver): Rp 2.000.000/hari</li>
      <li>VIP 3 (Gold): Rp 5.000.000/hari</li>
      <li>VIP 4 (Diamond): Rp 10.000.000/hari</li>
      <li>VIP 5 (Elite): Tidak terbatas</li>
    </ul>
    <h3>Proses Withdraw</h3>
    <ul>
      <li>Withdraw diproses dalam <strong>1-3 jam kerja</strong> setelah disetujui</li>
      <li>Nama rekening harus sesuai dengan nama akun NOXARA</li>
      <li>NOXARA tidak bertanggung jawab atas kesalahan nomor rekening yang diinput member</li>
    </ul>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- VIDEO TUTORIAL -->
<?php if ($videoUrl && $activeTab === 'about'): ?>
<div style="margin-bottom:24px">
  <h2 style="font-size:16px;font-weight:700;margin-bottom:14px">🎬 Video Tutorial</h2>
  <?php
  $ytId = '';
  if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $m)) $ytId = $m[1];
  if ($ytId):
  ?>
  <div class="video-wrap">
    <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>" allowfullscreen title="Video Tutorial NOXARA"></iframe>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body></html>
