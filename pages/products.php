<?php
/**
 * NOXARA - Halaman Produk / Paket Mining
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mining.php';
require_once __DIR__ . '/../includes/wallet.php';

requireLogin();
$user   = getCurrentUser();
$wallet = getUserWallet((int)$user['id']);

// ── AJAX: Validate Voucher ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'validate_voucher') {
    header('Content-Type: application/json');
    $code = clean($_POST['voucher_code'] ?? '');
    if (!$code) { echo json_encode(['valid' => false, 'message' => 'Kode voucher kosong.']); exit; }

    $stmt = db()->prepare("SELECT * FROM vouchers WHERE code=? AND is_active=1 AND (type='product' OR type='general') AND valid_from<=NOW() AND valid_until>=NOW() LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $voucher = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$voucher) { echo json_encode(['valid' => false, 'message' => 'Voucher tidak valid atau sudah expired.']); exit; }

    $stmtU = db()->prepare("SELECT vip_level FROM users WHERE id=? LIMIT 1");
    $stmtU->bind_param("i", (int)$user['id']);
    $stmtU->execute();
    $userVip = (int)$stmtU->get_result()->fetch_assoc()['vip_level'];
    $stmtU->close();

    if ($userVip < (int)$voucher['min_vip_level']) {
        echo json_encode([
            'valid'        => false,
            'message'      => "Voucher ini untuk VIP {$voucher['min_vip_level']} ke atas. Level kamu: VIP {$userVip}.",
            'vip_required' => (int)$voucher['min_vip_level'],
            'user_vip'     => $userVip,
        ]);
        exit;
    }

    if ((int)$voucher['usage_limit'] > 0 && $voucher['usage_count'] >= $voucher['usage_limit']) {
        echo json_encode(['valid' => false, 'message' => 'Voucher sudah habis digunakan.']); exit;
    }

    echo json_encode([
        'valid'          => true,
        'message'        => 'Voucher valid!',
        'id'             => (int)$voucher['id'],
        'discount_type'  => htmlspecialchars($voucher['discount_type']),
        'discount_value' => (float)$voucher['discount_value'],
        'max_discount'   => (int)($voucher['max_discount'] ?? 0),
        'min_amount'     => (int)($voucher['min_amount'] ?? 0),
    ]);
    exit;
}


// ── AJAX: Purchase Produk ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purchase') {
    header('Content-Type: application/json');
    requireCsrf();
    $productId   = (int)($_POST['product_id'] ?? 0);
    $voucherCode = clean($_POST['voucher_code'] ?? '');
    $pin         = $_POST['pin'] ?? '';
    $result      = purchaseProduct((int)$user['id'], $productId, $voucherCode ?: null, $pin);
    echo json_encode($result);
    exit;
}

// ── Ambil Kategori + Produk ─────────────────────────────────
$stmtCat = db()->prepare("SELECT * FROM product_categories WHERE is_active=1 ORDER BY sort_order ASC");
$stmtCat->execute();
$categories = $stmtCat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCat->close();

$products = [];
foreach ($categories as $cat) {
    $stmtP = db()->prepare("SELECT * FROM products WHERE category_id=? AND is_active=1 ORDER BY sort_order ASC");
    $stmtP->bind_param("i", (int)$cat['id']);
    $stmtP->execute();
    $products[(int)$cat['id']] = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtP->close();
}

// ── Detail Produk (?slug=) ───────────────────────────────────
$selectedProduct = null;
if (!empty($_GET['slug'])) {
    $slug = clean($_GET['slug']);
    $stmtSP = db()->prepare("SELECT p.*, c.name as cat_name, c.color as cat_color FROM products p JOIN product_categories c ON c.id=p.category_id WHERE p.slug=? AND p.is_active=1 LIMIT 1");
    $stmtSP->bind_param("s", $slug);
    $stmtSP->execute();
    $selectedProduct = $stmtSP->get_result()->fetch_assoc();
    $stmtSP->close();
}

$vipLevel  = (int)($user['vip_level'] ?? 0);
$pageTitle = $selectedProduct ? htmlspecialchars($selectedProduct['name']) : 'Produk Mining';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= getSetting('site_name','NOXARA') ?></title>
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">

<style>
/* ── Products Page ── */
.nox-page-header{margin-bottom:24px}.nox-page-header h1{font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px}.nox-breadcrumb{font-size:12px;color:var(--text-secondary);margin-bottom:8px}.nox-breadcrumb a{color:var(--cyan);text-decoration:none}.nox-breadcrumb span{margin:0 6px;opacity:.4}

/* Tab Kategori */
.nox-tab-bar{display:flex;gap:8px;margin-bottom:24px;overflow-x:auto;padding-bottom:4px;scroll-snap-type:x mandatory}
.nox-tab-bar::-webkit-scrollbar{height:0}
.nox-tab{padding:8px 20px;border-radius:99px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border-light);background:var(--bg-card);color:var(--text-secondary);white-space:nowrap;transition:var(--transition);scroll-snap-align:start}
.nox-tab.active,.nox-tab:hover{background:var(--cyan);color:#000;border-color:var(--cyan)}

/* Product Grid */
.nox-product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;margin-bottom:32px}
@media(max-width:600px){.nox-product-grid{grid-template-columns:1fr 1fr;gap:12px}}

.nox-product-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);overflow:hidden;transition:var(--transition);display:flex;flex-direction:column}
.nox-product-card:hover{transform:translateY(-5px);border-color:var(--cyan);box-shadow:0 12px 40px rgba(0,212,255,0.15)}
.nox-product-card__img-wrap{position:relative;padding-top:56%;background:rgba(0,212,255,0.05);overflow:hidden}
.nox-product-card__img-wrap img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.nox-product-card__img-wrap .nox-cat-badge{position:absolute;top:10px;left:10px;padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700;backdrop-filter:blur(8px)}
.nox-product-card__body{padding:16px;flex:1;display:flex;flex-direction:column}
.nox-product-card__name{font-weight:700;font-size:15px;margin-bottom:12px}
.nox-product-card__stats{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px}
.nox-product-card__stat{background:rgba(255,255,255,0.03);border-radius:8px;padding:8px;text-align:center}
.nox-product-card__stat-val{font-family:'Space Grotesk',sans-serif;font-size:13px;font-weight:700;color:var(--cyan)}
.nox-product-card__stat-lbl{font-size:10px;color:var(--text-secondary);margin-top:2px}
.nox-product-card__notes{font-size:11px;color:var(--text-secondary);border-top:1px solid var(--border-light);padding-top:10px;margin-top:auto;display:flex;flex-direction:column;gap:3px}
.nox-product-card__footer{padding:12px 16px;border-top:1px solid var(--border-light)}

/* Detail Layout */
.nox-detail-grid{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start}
@media(max-width:900px){.nox-detail-grid{grid-template-columns:1fr}}
.nox-detail-img-wrap{border-radius:var(--radius-lg);overflow:hidden;position:relative;padding-top:56%;background:rgba(0,212,255,0.05)}
.nox-detail-img-wrap img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.nox-detail-img-wrap::after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(10,14,26,0.7) 0%,transparent 60%)}
.nox-detail-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:16px 0}
@media(max-width:600px){.nox-detail-stats{grid-template-columns:repeat(3,1fr)}}
.nox-detail-stat{background:rgba(0,212,255,0.05);border:1px solid rgba(0,212,255,0.15);border-radius:10px;padding:12px;text-align:center}
.nox-detail-stat__val{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:700;color:var(--cyan)}
.nox-detail-stat__lbl{font-size:10px;color:var(--text-secondary);margin-top:3px}
.nox-highlight-box{background:rgba(123,47,255,0.06);border:1px solid rgba(123,47,255,0.25);border-radius:10px;padding:14px;margin:16px 0;display:flex;flex-direction:column;gap:6px;font-size:13px}

/* Form Beli */
.nox-buy-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:24px;position:sticky;top:80px}
.nox-buy-card h3{font-size:16px;font-weight:700;margin:0 0 16px}
.nox-voucher-row{display:flex;gap:8px;margin-bottom:8px}
.nox-voucher-row input{flex:1;background:rgba(255,255,255,0.05);border:1px solid var(--border-light);border-radius:8px;padding:9px 12px;color:var(--text-primary);font-size:13px}
.nox-voucher-row input:focus{outline:none;border-color:var(--cyan)}
.nox-voucher-result{font-size:12px;padding:8px 12px;border-radius:8px;margin-bottom:12px;display:none}
.nox-voucher-result.valid{background:rgba(0,230,118,0.1);color:var(--green);border:1px solid rgba(0,230,118,0.25)}
.nox-voucher-result.invalid{background:rgba(255,68,68,0.1);color:var(--red);border:1px solid rgba(255,68,68,0.25)}
.nox-summary-row{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
.nox-summary-row:last-child{border-bottom:none;font-weight:700;font-size:15px;padding-top:10px}
.nox-summary-row .val-discount{color:var(--green)}
.nox-summary-row .val-total{color:var(--cyan);font-family:'Space Grotesk',sans-serif}
.nox-saldo-info{background:rgba(0,212,255,0.05);border:1px solid rgba(0,212,255,0.15);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;display:flex;flex-direction:column;gap:4px}
.nox-saldo-row{display:flex;justify-content:space-between}

/* Modal */
.nox-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:9000;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .3s}
.nox-modal-overlay.show{opacity:1;pointer-events:all}
.nox-modal-box{background:var(--bg-card);border:1px solid rgba(0,212,255,0.25);border-radius:20px;padding:28px;max-width:420px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,0.6);transform:scale(.95) translateY(20px);transition:transform .3s}
.nox-modal-overlay.show .nox-modal-box{transform:scale(1) translateY(0)}
.nox-modal-title{font-family:'Orbitron',monospace;font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.nox-modal-divider{height:1px;background:var(--border-light);margin:14px 0}
.nox-modal-row{display:flex;justify-content:space-between;font-size:13px;padding:5px 0}
.nox-modal-row.total{font-weight:700;font-size:15px;color:var(--cyan);border-top:1px solid var(--border-light);padding-top:10px;margin-top:5px}

/* PIN Input */
.nox-pin-group{display:flex;gap:8px;justify-content:center;margin:16px 0}
.nox-pin-digit{width:46px;height:52px;border-radius:10px;border:2px solid var(--border-light);background:rgba(255,255,255,0.05);color:var(--text-primary);text-align:center;font-size:22px;font-weight:700;caret-color:var(--cyan);transition:var(--transition)}
.nox-pin-digit:focus{outline:none;border-color:var(--cyan);background:rgba(0,212,255,0.06)}
.nox-modal-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:20px}

/* VIP Alert Modal */
.nox-vip-alert{text-align:center;padding:8px 0}
.nox-vip-alert__icon{font-size:48px;margin-bottom:12px}
.nox-vip-alert__title{font-size:18px;font-weight:700;margin-bottom:8px}
.nox-vip-alert__msg{font-size:13px;color:var(--text-secondary);margin-bottom:16px}
.nox-vip-alert__actions{display:flex;flex-direction:column;gap:10px}

/* Success Popup */
.nox-success-popup{text-align:center;padding:8px 0}
.nox-success-popup__icon{font-size:56px;margin-bottom:12px;animation:bounceIn .5s}
@keyframes bounceIn{0%{transform:scale(0)}70%{transform:scale(1.1)}100%{transform:scale(1)}}
.nox-success-popup__title{font-size:20px;font-weight:700;color:var(--green);margin-bottom:8px}
.nox-success-popup__msg{font-size:13px;color:var(--text-secondary);margin-bottom:16px}
</style>

</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">
<?= renderFlash() ?>

<?php if (!$selectedProduct): ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODE LIST                                                   -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="nox-page-header">
  <div class="nox-breadcrumb"><a href="<?= BASE_URL ?>/dashboard">Dashboard</a><span>›</span>Produk Mining</div>
  <h1>📦 Paket Mining NOXARA</h1>
  <p style="color:var(--text-secondary);font-size:14px">Pilih paket sesuai budget kamu, mining setiap hari untuk raih profit maksimal.</p>
</div>

<!-- Tab Kategori -->
<div class="nox-tab-bar" id="categoryTabs">
  <button class="nox-tab active" data-cat="all">Semua Paket</button>
  <?php foreach ($categories as $idx => $cat): ?>
    <button class="nox-tab" data-cat="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></button>
  <?php endforeach; ?>
</div>

<!-- Grid Produk Per Kategori -->
<?php foreach ($categories as $cat): ?>
  <div class="nox-cat-section" data-cat-id="<?= (int)$cat['id'] ?>" style="margin-bottom:36px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <div style="width:4px;height:20px;background:<?= htmlspecialchars($cat['color'] ?? 'var(--cyan)') ?>;border-radius:99px"></div>
      <h2 style="font-size:16px;font-weight:700;margin:0"><?= htmlspecialchars($cat['name']) ?></h2>
      <span style="font-size:12px;color:var(--text-secondary)"><?= count($products[(int)$cat['id']] ?? []) ?> paket</span>
    </div>
    <?php if (empty($products[(int)$cat['id']])): ?>
      <div style="text-align:center;padding:32px;color:var(--text-secondary);font-size:13px;background:var(--bg-card);border-radius:var(--radius-lg)">Belum ada produk di kategori ini.</div>
    <?php else: ?>
      <div class="nox-product-grid">
        <?php foreach ($products[(int)$cat['id']] as $prod):
          $roi = $prod['price'] > 0 ? round(($prod['profit_per_day'] * $prod['duration_days']) / $prod['price'] * 100, 0) : 0;
          $totalProfit = (int)$prod['profit_per_day'] * (int)$prod['duration_days'];
          $imgSrc = !empty($prod['image']) ? UPLOADS_URL . '/products/' . htmlspecialchars($prod['image']) : ASSETS_URL . '/img/mining/default.png';
        ?>
        <div class="nox-product-card nox-hover-lift">
          <div class="nox-product-card__img-wrap">
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($prod['name']) ?>" onerror="this.src='<?= ASSETS_URL ?>/img/mining/default.png'">
            <span class="nox-cat-badge" style="background:<?= htmlspecialchars($cat['color'] ?? '#00D4FF') ?>33;color:<?= htmlspecialchars($cat['color'] ?? '#00D4FF') ?>;border:1px solid <?= htmlspecialchars($cat['color'] ?? '#00D4FF') ?>55"><?= htmlspecialchars($cat['name']) ?></span>
          </div>
          <div class="nox-product-card__body">
            <div class="nox-product-card__name"><?= htmlspecialchars($prod['name']) ?></div>
            <div class="nox-product-card__stats">
              <div class="nox-product-card__stat">
                <div class="nox-product-card__stat-val"><?= formatRupiah((int)$prod['price'], false) ?></div>
                <div class="nox-product-card__stat-lbl">Harga</div>
              </div>
              <div class="nox-product-card__stat">
                <div class="nox-product-card__stat-val" style="color:var(--green)"><?= formatRupiah((int)$prod['profit_per_day'], false) ?></div>
                <div class="nox-product-card__stat-lbl">Profit/Hari</div>
              </div>
              <div class="nox-product-card__stat">
                <div class="nox-product-card__stat-val" style="color:var(--amber)"><?= (int)$prod['duration_days'] ?> Hari</div>
                <div class="nox-product-card__stat-lbl">Durasi</div>
              </div>
              <div class="nox-product-card__stat">
                <div class="nox-product-card__stat-val" style="color:var(--purple)"><?= $roi ?>%</div>
                <div class="nox-product-card__stat-lbl">ROI</div>
              </div>
            </div>
            <div class="nox-product-card__notes">
              <span>⚠️ Wajib mining 1x/hari</span>
              <span>✅ Modal kembali <?= (int)$prod['duration_days'] ?> hari</span>
              <span>💰 Total: <?= formatRupiah($totalProfit) ?></span>
            </div>
          </div>
          <div class="nox-product-card__footer">
            <a href="?slug=<?= htmlspecialchars($prod['slug']) ?>" class="nox-btn nox-btn--primary" style="width:100%;justify-content:center">Lihat Detail →</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php else: // ── MODE DETAIL ── ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODE DETAIL                                                 -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php
  $prod = $selectedProduct;
  $roi  = $prod['price'] > 0 ? round(($prod['profit_per_day'] * $prod['duration_days']) / $prod['price'] * 100, 0) : 0;
  $totalProfit = (int)$prod['profit_per_day'] * (int)$prod['duration_days'];
  $imgSrc = !empty($prod['image']) ? UPLOADS_URL . '/products/' . htmlspecialchars($prod['image']) : ASSETS_URL . '/img/mining/default.png';
?>
<div class="nox-page-header">
  <div class="nox-breadcrumb">
    <a href="<?= BASE_URL ?>/dashboard">Dashboard</a><span>›</span>
    <a href="<?= BASE_URL ?>/products">Produk</a><span>›</span>
    <span><?= htmlspecialchars($prod['cat_name']) ?></span><span>›</span>
    <?= htmlspecialchars($prod['name']) ?>
  </div>
</div>

<div class="nox-detail-grid">
  <!-- Kolom Kiri: Info Produk -->
  <div>
    <div class="nox-detail-img-wrap">
      <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($prod['name']) ?>" onerror="this.src='<?= ASSETS_URL ?>/img/mining/default.png'">
    </div>
    <div style="margin-top:20px">
      <span class="nox-badge" style="background:<?= htmlspecialchars($prod['cat_color'] ?? '#00D4FF') ?>22;color:<?= htmlspecialchars($prod['cat_color'] ?? '#00D4FF') ?>;padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700;margin-bottom:10px;display:inline-block"><?= htmlspecialchars($prod['cat_name']) ?></span>
      <h1 style="font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;margin:8px 0 4px"><?= htmlspecialchars($prod['name']) ?></h1>
      <div style="font-family:'Orbitron',monospace;font-size:24px;font-weight:700;color:var(--cyan)"><?= formatRupiah((int)$prod['price']) ?></div>
    </div>

    <div class="nox-detail-stats">
      <div class="nox-detail-stat">
        <div class="nox-detail-stat__val"><?= formatRupiah((int)$prod['price'], false) ?></div>
        <div class="nox-detail-stat__lbl">Harga</div>
      </div>
      <div class="nox-detail-stat">
        <div class="nox-detail-stat__val" style="color:var(--green)"><?= formatRupiah((int)$prod['profit_per_day'], false) ?></div>
        <div class="nox-detail-stat__lbl">Profit/Hari</div>
      </div>
      <div class="nox-detail-stat">
        <div class="nox-detail-stat__val" style="color:var(--amber)"><?= (int)$prod['duration_days'] ?> Hari</div>
        <div class="nox-detail-stat__lbl">Durasi</div>
      </div>
      <div class="nox-detail-stat">
        <div class="nox-detail-stat__val" style="color:var(--purple)"><?= formatRupiah($totalProfit, false) ?></div>
        <div class="nox-detail-stat__lbl">Total Profit</div>
      </div>
      <div class="nox-detail-stat">
        <div class="nox-detail-stat__val" style="color:var(--amber)"><?= $roi ?>%</div>
        <div class="nox-detail-stat__lbl">ROI</div>
      </div>
    </div>

    <?php if (!empty($prod['description'])): ?>
    <div style="color:var(--text-secondary);font-size:14px;line-height:1.7;margin-bottom:16px"><?= nl2br(htmlspecialchars($prod['description'])) ?></div>
    <?php endif; ?>

    <div class="nox-highlight-box">
      <div>⚠️ <strong>Wajib mining 1x setiap hari</strong> agar profit harian masuk</div>
      <div>✅ <strong>Modal kembali otomatis</strong> setelah <?= (int)$prod['duration_days'] ?> hari aktif mining</div>
      <div>⏳ Profit masuk ke saldo dalam <strong>3 jam</strong> setelah klik Mining</div>
      <div>💡 Saldo bonus dapat digunakan untuk membeli paket ini</div>
    </div>
  </div>

  <!-- Kolom Kanan: Form Beli -->
  <div>
    <div class="nox-buy-card">
      <h3>🛒 Beli Paket</h3>

      <!-- Saldo Tersedia -->
      <div class="nox-saldo-info">
        <div style="font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Saldo Tersedia</div>
        <div class="nox-saldo-row"><span>💳 Saldo Utama</span><span style="color:var(--cyan);font-weight:600"><?= formatRupiah((int)$wallet['balance_main']) ?></span></div>
        <div class="nox-saldo-row"><span>🎁 Saldo Bonus</span><span style="color:var(--amber);font-weight:600"><?= formatRupiah((int)$wallet['balance_bonus']) ?></span></div>
        <div class="nox-saldo-row" style="border-top:1px solid rgba(255,255,255,0.07);padding-top:4px;margin-top:2px">
          <span style="font-weight:600">Total Tersedia</span>
          <span style="color:var(--green);font-weight:700"><?= formatRupiah((int)$wallet['balance_main'] + (int)$wallet['balance_bonus']) ?></span>
        </div>
      </div>

      <!-- Voucher -->
      <div style="margin-bottom:12px">
        <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:6px">🏷️ Kode Voucher (opsional)</div>
        <div class="nox-voucher-row">
          <input type="text" id="voucherCode" placeholder="Masukkan kode voucher..." maxlength="30">
          <button class="nox-btn nox-btn--sm nox-btn--outline" onclick="validateVoucher()">Gunakan</button>
        </div>
        <div class="nox-voucher-result" id="voucherResult"></div>
      </div>

      <!-- Summary -->
      <div style="background:rgba(255,255,255,0.02);border-radius:10px;padding:14px;margin-bottom:16px">
        <div class="nox-summary-row"><span>Harga Paket</span><span><?= formatRupiah((int)$prod['price']) ?></span></div>
        <div class="nox-summary-row" id="discountRow" style="display:none"><span>Diskon Voucher</span><span class="val-discount" id="discountVal">- Rp 0</span></div>
        <div class="nox-summary-row total"><span>Total Bayar</span><span class="val-total" id="totalPayVal"><?= formatRupiah((int)$prod['price']) ?></span></div>
      </div>

      <!-- Tombol Beli -->
      <button class="nox-btn nox-btn--primary" style="width:100%;font-size:15px;padding:14px;justify-content:center" onclick="openBuyModal()">
        🛒 Beli Sekarang
      </button>
      <div style="font-size:11px;color:var(--text-disabled);text-align:center;margin-top:8px">Pembayaran dari saldo utama + bonus</div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL KONFIRMASI BELI                                       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="nox-modal-overlay" id="modalBuy">
  <div class="nox-modal-box">
    <div class="nox-modal-title">🛒 KONFIRMASI PEMBELIAN</div>
    <div class="nox-modal-divider"></div>
    <div class="nox-modal-row"><span>Produk</span><span style="font-weight:600"><?= htmlspecialchars($prod['name']) ?></span></div>
    <div class="nox-modal-row"><span>Harga</span><span><?= formatRupiah((int)$prod['price']) ?></span></div>
    <div class="nox-modal-row" id="mDiscountRow" style="display:none"><span>Diskon</span><span class="val-discount" id="mDiscountVal"></span></div>
    <div class="nox-modal-row total"><span>Total Bayar</span><span id="mTotalVal"><?= formatRupiah((int)$prod['price']) ?></span></div>
    <div class="nox-modal-divider"></div>
    <div style="background:rgba(255,179,0,0.08);border:1px solid rgba(255,179,0,0.25);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--amber);margin-bottom:16px">
      ⚠️ Wajib mining 1x/hari agar profit harian masuk ke saldo
    </div>
    <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--text-secondary)">🔑 PIN Transaksi:</div>
    <div class="nox-pin-group" id="pinGroupBuy">
      <input type="tel" class="nox-pin-digit" maxlength="1" inputmode="numeric">
      <input type="tel" class="nox-pin-digit" maxlength="1" inputmode="numeric">
      <input type="tel" class="nox-pin-digit" maxlength="1" inputmode="numeric">
      <input type="tel" class="nox-pin-digit" maxlength="1" inputmode="numeric">
      <input type="tel" class="nox-pin-digit" maxlength="1" inputmode="numeric">
      <input type="tel" class="nox-pin-digit" maxlength="1" inputmode="numeric">
    </div>
    <div id="modalBuyError" style="color:var(--red);font-size:12px;text-align:center;min-height:18px;margin-bottom:4px"></div>
    <div class="nox-modal-actions">
      <button class="nox-btn nox-btn--outline" onclick="closeModal('modalBuy')">Batal</button>
      <button class="nox-btn nox-btn--primary" id="btnConfirmBuy" onclick="confirmBuy()">✅ Konfirmasi</button>
    </div>
  </div>
</div>

<!-- Modal VIP Required -->
<div class="nox-modal-overlay" id="modalVipRequired">
  <div class="nox-modal-box">
    <div class="nox-vip-alert">
      <div class="nox-vip-alert__icon">🔒</div>
      <div class="nox-vip-alert__title">Voucher Khusus VIP</div>
      <div class="nox-vip-alert__msg" id="vipAlertMsg">Voucher ini membutuhkan level VIP lebih tinggi.</div>
      <div class="nox-vip-alert__actions">
        <a href="<?= BASE_URL ?>/vip" class="nox-btn nox-btn--primary" style="justify-content:center">💎 Upgrade VIP Sekarang →</a>
        <button class="nox-btn nox-btn--outline" onclick="closeModal('modalVipRequired')" style="justify-content:center">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Sukses -->
<div class="nox-modal-overlay" id="modalSuccess">
  <div class="nox-modal-box">
    <div class="nox-success-popup">
      <div class="nox-success-popup__icon">🎉</div>
      <div class="nox-success-popup__title">Pembelian Berhasil!</div>
      <div class="nox-success-popup__msg" id="successMsg">Paket mining kamu sudah aktif!</div>
      <div style="background:rgba(0,230,118,0.08);border:1px solid rgba(0,230,118,0.25);border-radius:10px;padding:12px;font-size:13px;margin-bottom:16px;color:var(--green)" id="successDetail"></div>
      <a href="<?= BASE_URL ?>/my-packages" class="nox-btn nox-btn--primary" style="width:100%;justify-content:center">⛏️ Lihat Paket Saya</a>
    </div>
  </div>
</div>

<?php endif; // end mode detail/list ?>
</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>


<script>
/* ── Products Page JS ─────────────────────────────────────── */
const PRODUCT_PRICE = <?= isset($prod) ? (int)$prod['price'] : 0 ?>;
const PRODUCT_ID    = <?= isset($prod) ? (int)$prod['id'] : 0 ?>;
const CSRF_TOKEN    = '<?= generateCsrfToken() ?>';

let appliedVoucher = null;

/* Tab Category Switching (list mode) */
document.querySelectorAll('.nox-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.nox-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    const cat = this.dataset.cat;
    document.querySelectorAll('.nox-cat-section').forEach(sec => {
      sec.style.display = (cat === 'all' || sec.dataset.catId === cat) ? 'block' : 'none';
    });
  });
});

/* Validate Voucher */
async function validateVoucher() {
  const code = document.getElementById('voucherCode')?.value?.trim();
  const resultEl = document.getElementById('voucherResult');
  if (!code) { showVoucherResult(false, 'Masukkan kode voucher terlebih dahulu.'); return; }

  const fd = new FormData();
  fd.append('action', 'validate_voucher');
  fd.append('voucher_code', code);

  try {
    const res = await fetch(location.href, { method:'POST', body: fd });
    const data = await res.json();
    if (data.valid) {
      appliedVoucher = data;
      const disc = calcDiscount(data, PRODUCT_PRICE);
      showVoucherResult(true, `✅ ${data.message} — Diskon: ${formatRp(disc)}`);
      updateSummary(disc);
    } else {
      appliedVoucher = null;
      updateSummary(0);
      if (data.vip_required) {
        document.getElementById('vipAlertMsg').textContent =
          `Voucher ini untuk VIP ${data.vip_required} ke atas. Level kamu: VIP ${data.user_vip}. Isi ulang lebih banyak untuk naik VIP!`;
        openModal('modalVipRequired');
      } else {
        showVoucherResult(false, '❌ ' + data.message);
      }
    }
  } catch(e) { showVoucherResult(false, 'Gagal validasi voucher.'); }
}

function calcDiscount(v, price) {
  if (!v) return 0;
  let disc = v.discount_type === 'percent' ? Math.floor(price * v.discount_value / 100) : v.discount_value;
  if (v.max_discount > 0) disc = Math.min(disc, v.max_discount);
  return Math.min(disc, price);
}

function formatRp(n) {
  return 'Rp ' + parseInt(n).toLocaleString('id-ID');
}

function updateSummary(discount) {
  const total = Math.max(0, PRODUCT_PRICE - discount);
  const dRow  = document.getElementById('discountRow');
  const dVal  = document.getElementById('discountVal');
  const tVal  = document.getElementById('totalPayVal');
  if (discount > 0) {
    dRow.style.display = 'flex';
    dVal.textContent   = '- ' + formatRp(discount);
  } else {
    dRow.style.display = 'none';
  }
  tVal.textContent = formatRp(total);
  // Update modal
  const mDiscRow = document.getElementById('mDiscountRow');
  const mDiscVal = document.getElementById('mDiscountVal');
  const mTotalVal = document.getElementById('mTotalVal');
  if (mDiscRow && mDiscVal && mTotalVal) {
    mDiscRow.style.display = discount > 0 ? 'flex' : 'none';
    if (mDiscVal) mDiscVal.textContent = '- ' + formatRp(discount);
    if (mTotalVal) mTotalVal.textContent = formatRp(total);
  }
}

function showVoucherResult(valid, msg) {
  const el = document.getElementById('voucherResult');
  if (!el) return;
  el.style.display = 'block';
  el.className = 'nox-voucher-result ' + (valid ? 'valid' : 'invalid');
  el.textContent = msg;
}

/* Open Buy Modal */
function openBuyModal() {
  clearPinInputs('pinGroupBuy');
  document.getElementById('modalBuyError').textContent = '';
  openModal('modalBuy');
  setTimeout(() => {
    const first = document.querySelector('#pinGroupBuy .nox-pin-digit');
    if (first) first.focus();
  }, 300);
}

/* Confirm Buy */
async function confirmBuy() {
  const pin = getPinValue('pinGroupBuy');
  const errEl = document.getElementById('modalBuyError');
  if (pin.length < 6) { errEl.textContent = 'Masukkan 6 digit PIN.'; return; }

  const btn = document.getElementById('btnConfirmBuy');
  btn.disabled = true;
  btn.textContent = '⏳ Memproses...';
  errEl.textContent = '';

  const fd = new FormData();
  fd.append('action', 'purchase');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('product_id', PRODUCT_ID);
  fd.append('voucher_code', document.getElementById('voucherCode')?.value?.trim() || '');
  fd.append('pin', pin);

  try {
    const res = await fetch(location.href, { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closeModal('modalBuy');
      document.getElementById('successMsg').textContent = `Paket "${data.product_name}" berhasil diaktifkan!`;
      document.getElementById('successDetail').textContent =
        `Harga: ${formatRp(data.final_price)} · Berakhir: ${data.end_date}`;
      openModal('modalSuccess');
    } else {
      if (data.show_upgrade) {
        closeModal('modalBuy');
        document.getElementById('vipAlertMsg').textContent = data.message;
        openModal('modalVipRequired');
      } else {
        errEl.textContent = data.message;
      }
    }
  } catch(e) {
    errEl.textContent = 'Terjadi kesalahan. Coba lagi.';
  } finally {
    btn.disabled = false;
    btn.textContent = '✅ Konfirmasi';
  }
}

/* PIN helpers */
function getPinValue(groupId) {
  return [...document.querySelectorAll(`#${groupId} .nox-pin-digit`)].map(i => i.value).join('');
}

function clearPinInputs(groupId) {
  document.querySelectorAll(`#${groupId} .nox-pin-digit`).forEach(i => i.value = '');
}

document.addEventListener('input', function(e) {
  if (!e.target.classList.contains('nox-pin-digit')) return;
  const val = e.target.value.replace(/\D/g,'');
  e.target.value = val.slice(-1);
  if (val) {
    const next = e.target.nextElementSibling;
    if (next && next.classList.contains('nox-pin-digit')) next.focus();
  }
});

document.addEventListener('keydown', function(e) {
  if (!e.target.classList.contains('nox-pin-digit')) return;
  if (e.key === 'Backspace' && !e.target.value) {
    const prev = e.target.previousElementSibling;
    if (prev && prev.classList.contains('nox-pin-digit')) { prev.focus(); prev.value = ''; }
  }
});

/* Modal helpers */
function openModal(id)  { document.getElementById(id)?.classList.add('show'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('show'); }

document.querySelectorAll('.nox-modal-overlay').forEach(el => {
  el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
});
</script>
</body>
</html>
