<?php
/**
 * NOXARA - FAQ
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$user = getCurrentUser();
$pageTitle = 'FAQ';

$faqData = [
  '💳 Seputar Deposit' => [
    ['Berapa minimal deposit di NOXARA?', 'Minimal deposit di NOXARA adalah <strong>Rp 50.000</strong>. Tidak ada batas maksimal deposit.'],
    ['Bagaimana cara melakukan deposit?', 'Caranya: (1) Masuk ke menu <strong>Deposit</strong>, (2) Pilih metode pembayaran (Transfer Bank/E-Wallet), (3) Masukkan jumlah deposit, (4) Transfer ke rekening yang tertera, (5) Upload bukti transfer, (6) Tunggu konfirmasi admin (maks 5 menit pada jam operasional).'],
    ['Apakah ada biaya/potongan saat deposit?', 'Tidak ada biaya tambahan. Jumlah yang Anda transfer = jumlah yang masuk ke saldo Anda.'],
    ['Berapa lama proses konfirmasi deposit?', 'Pada jam operasional (08.00-22.00), konfirmasi deposit biasanya <strong>1-5 menit</strong>. Di luar jam operasional maksimal <strong>1x24 jam</strong>.'],
    ['Saya sudah transfer tapi saldo belum masuk, bagaimana?', 'Pastikan Anda sudah upload bukti transfer. Jika lebih dari 30 menit di jam operasional, segera hubungi CS melalui Live Chat dengan melampirkan bukti transfer.'],
    ['Metode pembayaran apa saja yang tersedia?', 'Saat ini tersedia: Transfer Bank (BCA, BNI, BRI, Mandiri), dan E-Wallet (GoPay, OVO, Dana). Metode dapat berubah, cek menu Deposit untuk info terbaru.'],
  ],
  '💸 Seputar Withdraw' => [
    ['Berapa minimal withdraw di NOXARA?', 'Minimal withdraw adalah <strong>Rp 50.000</strong>. Pastikan saldo Anda mencukupi ditambah biaya admin.'],
    ['Berapa biaya admin withdraw?', 'Biaya admin withdraw sebesar <strong>Rp 5.000 - Rp 10.000</strong> per transaksi tergantung nominal. Cek detail di halaman Withdraw.'],
    ['Kapan jadwal withdraw?', 'Withdraw diproses <strong>Senin-Jumat, pukul 08.00-17.00 WIB</strong>. Di luar jadwal, withdraw akan diproses hari kerja berikutnya.'],
    ['Berapa lama dana sampai ke rekening?', 'Setelah disetujui admin, dana akan sampai dalam <strong>1-3 jam kerja</strong>. Khusus BSI dan bank syariah bisa 1 hari kerja.'],
    ['Kenapa withdraw saya ditolak?', 'Alasan umum penolakan: (1) Nama rekening tidak sesuai akun, (2) Nomor rekening salah, (3) Saldo tidak cukup, (4) Belum verifikasi PIN transaksi, (5) Melebihi limit harian.'],
    ['Berapa limit withdraw per hari?', 'Limit withdraw per hari tergantung level VIP Anda. VIP 0: Rp 500rb, VIP 1: Rp 1jt, VIP 2: Rp 2jt, VIP 3+: Rp 5jt+. Cek detail di halaman VIP.'],
  ],
  '📦 Seputar Paket Mining' => [
    ['Apa itu paket mining di NOXARA?', 'Paket mining adalah investasi di mana Anda membeli paket dengan harga tertentu, lalu mendapatkan profit harian secara otomatis selama masa aktif paket.'],
    ['Bagaimana cara mendapatkan profit dari paket?', 'Profit didapat dengan melakukan <strong>klik mining 1x sehari</strong> pada setiap paket aktif Anda. Ada countdown 3 jam setelah mining. Profit langsung masuk ke saldo profit Anda.'],
    ['Apakah paket bisa berakhir?', 'Ya, setiap paket memiliki durasi hari tertentu. Setelah habis, paket tidak aktif lagi dan perlu beli paket baru.'],
    ['Apa yang terjadi jika saya tidak mining hari ini?', 'Jika tidak melakukan klik mining, Anda tidak mendapat profit untuk hari itu. Tidak ada sistem auto-mining, Anda harus klik manual setiap hari.'],
    ['Bisakah saya punya lebih dari 1 paket aktif?', 'Ya! Anda bisa memiliki beberapa paket aktif sekaligus. Profit dari semua paket bisa diklaim setiap hari.'],
    ['Saldo apa yang digunakan untuk beli paket?', 'Pembelian paket menggunakan <strong>Saldo Utama</strong> atau <strong>Saldo Bonus</strong>. Anda bisa kombinasikan keduanya.'],
  ],
  '👥 Seputar Referral' => [
    ['Bagaimana sistem referral NOXARA?', 'NOXARA menggunakan sistem referral <strong>3 level</strong>: Level 1 (langsung): 5% dari profit downline, Level 2: 3%, Level 3: 1%. Komisi masuk otomatis ke Saldo Referral Anda.'],
    ['Bagaimana cara mengajak teman bergabung?', 'Bagikan kode/link referral unik Anda yang ada di menu Referral. Teman Anda harus mendaftar menggunakan kode tersebut agar terhitung sebagai downline Anda.'],
    ['Kapan komisi referral masuk?', 'Komisi referral masuk otomatis setiap kali downline Anda mendapatkan profit dari mining. Prosesnya real-time.'],
    ['Apakah ada batas maksimal downline?', 'Tidak ada batas! Semakin banyak downline aktif, semakin besar komisi yang Anda dapatkan.'],
    ['Saldo referral bisa ditarik?', 'Ya, saldo referral dapat ditarik melalui menu Withdraw dengan minimal penarikan yang berlaku.'],
  ],
  '⛏️ Seputar Mining' => [
    ['Berapa kali bisa mining dalam sehari?', 'Setiap paket aktif dapat di-mining <strong>1 kali per hari</strong>. Jika punya 3 paket, bisa mining 3 kali.'],
    ['Kenapa ada countdown 3 jam setelah mining?', 'Cooldown 3 jam adalah sistem anti-cheat untuk memastikan mining dilakukan secara fair dan berurutan antar paket.'],
    ['Apakah mining bisa dilakukan kapan saja?', 'Ya, mining bisa dilakukan kapan saja selama 24 jam, tidak ada jam tertentu. Tapi hanya 1x per paket per hari (reset tengah malam).'],
    ['Profit mining langsung bisa ditarik?', 'Profit mining masuk ke <strong>Saldo Profit</strong>. Anda bisa menarik saldo profit langsung atau menggunakannya untuk reinvestasi (beli paket baru).'],
    ['Bagaimana jika server error saat mining?', 'Jika terjadi error, mining tidak akan tercatat. Coba refresh halaman dan coba lagi. Jika masalah berlanjut, hubungi CS.'],
  ],
  '⚙️ Seputar Akun' => [
    ['Bagaimana cara mengganti password?', 'Masuk ke menu <strong>Keamanan</strong> → Ganti Password. Masukkan password lama dan password baru minimal 8 karakter.'],
    ['Apa itu PIN transaksi?', 'PIN transaksi adalah kode 6 digit untuk mengamankan setiap proses withdraw. Wajib dibuat sebelum melakukan penarikan dana.'],
    ['Bagaimana cara ganti foto profil?', 'Masuk ke menu <strong>Profil</strong> → klik foto profil → pilih gambar baru (JPG/PNG maks 2MB).'],
    ['Akun saya diblokir, bagaimana?', 'Hubungi CS melalui Live Chat atau WhatsApp dengan menyebutkan username dan alasan yang mungkin menyebabkan pemblokiran. Tim kami akan membantu verifikasi.'],
    ['Bisakah satu nomor HP untuk beberapa akun?', 'Tidak. Setiap nomor HP dan email hanya bisa digunakan untuk <strong>1 akun</strong>. Membuat akun ganda akan berakibat pemblokiran permanen.'],
  ],
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
.faq-search{width:100%;background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:14px 18px;color:var(--text-primary);font-size:15px;outline:none;margin-bottom:28px;box-sizing:border-box;transition:.2s}
.faq-search:focus{border-color:var(--cyan)}
.faq-category{margin-bottom:28px}
.faq-category-title{font-size:18px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.faq-item{background:var(--bg-card);border:1px solid var(--border-light);border-radius:10px;margin-bottom:8px;overflow:hidden;transition:.2s}
.faq-item:hover{border-color:rgba(0,212,255,.3)}
.faq-question{padding:16px 18px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;user-select:none}
.faq-question:hover{color:var(--cyan)}
.faq-chevron{font-size:18px;transition:.3s;flex-shrink:0;color:var(--text-secondary)}
.faq-item.open .faq-chevron{transform:rotate(180deg);color:var(--cyan)}
.faq-answer{max-height:0;overflow:hidden;transition:max-height .35s ease,padding .35s}
.faq-answer.open{max-height:400px;padding:0 18px 16px}
.faq-answer-inner{font-size:13px;color:var(--text-secondary);line-height:1.7;border-top:1px solid rgba(30,42,69,.5);padding-top:12px}
.empty-search{text-align:center;padding:48px;color:var(--text-secondary)}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">❓ FAQ</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Temukan jawaban atas pertanyaan Anda</p>
</div>

<input type="text" class="faq-search" id="faqSearch" placeholder="🔍 Cari pertanyaan..." oninput="searchFaq(this.value)">

<div id="faqContent">
  <?php foreach ($faqData as $category => $items): ?>
  <div class="faq-category" data-category>
    <div class="faq-category-title"><?= htmlspecialchars($category) ?></div>
    <?php foreach ($items as $idx => $item): ?>
    <div class="faq-item" data-faq>
      <div class="faq-question" onclick="toggleFaq(this)">
        <span><?= htmlspecialchars($item[0]) ?></span>
        <span class="faq-chevron">▾</span>
      </div>
      <div class="faq-answer">
        <div class="faq-answer-inner"><?= $item[1] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>

<div id="emptySearch" class="empty-search" style="display:none">
  <div style="font-size:48px;margin-bottom:12px">🔍</div>
  <div style="font-weight:600;margin-bottom:8px">Tidak ada FAQ yang cocok</div>
  <div style="font-size:13px;margin-bottom:20px">Coba kata kunci lain atau hubungi CS kami</div>
  <a href="<?= BASE_URL ?>/pages/chat.php" class="nox-btn nox-btn--primary">💬 Hubungi Live Chat</a>
</div>

<!-- CTA Chat -->
<div style="background:linear-gradient(135deg,rgba(0,212,255,.08),rgba(123,47,255,.08));border:1px solid rgba(0,212,255,.2);border-radius:14px;padding:24px;text-align:center;margin-top:32px">
  <div style="font-size:32px;margin-bottom:10px">🎧</div>
  <div style="font-size:18px;font-weight:700;margin-bottom:6px">Masih ada pertanyaan?</div>
  <div style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">Tim CS kami siap membantu Anda 24/7</div>
  <a href="<?= BASE_URL ?>/pages/chat.php" class="nox-btn nox-btn--primary">💬 Chat dengan CS</a>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function toggleFaq(btn) {
  const item = btn.parentElement;
  const answer = item.querySelector('.faq-answer');
  const isOpen = item.classList.contains('open');
  // Tutup semua
  document.querySelectorAll('.faq-item.open').forEach(el => {
    el.classList.remove('open');
    el.querySelector('.faq-answer').classList.remove('open');
  });
  if (!isOpen) { item.classList.add('open'); answer.classList.add('open'); }
}

function searchFaq(q) {
  q = q.toLowerCase().trim();
  const categories = document.querySelectorAll('[data-category]');
  let totalVisible = 0;
  categories.forEach(cat => {
    let catVisible = 0;
    cat.querySelectorAll('[data-faq]').forEach(item => {
      const text = item.textContent.toLowerCase();
      const match = !q || text.includes(q);
      item.style.display = match ? '' : 'none';
      if (match) { catVisible++; totalVisible++; }
      if (match && q) { item.classList.add('open'); item.querySelector('.faq-answer').classList.add('open'); }
      else if (q) { item.classList.remove('open'); item.querySelector('.faq-answer').classList.remove('open'); }
    });
    cat.style.display = catVisible ? '' : 'none';
  });
  document.getElementById('emptySearch').style.display = totalVisible === 0 ? 'block' : 'none';
}
</script>
</body></html>
