<?php
require_once __DIR__ . '/config/bootstrap.php';

// Ambil data untuk landing page
$db = db();

// Statistik
$totalMembersDisplay = getSetting('total_members_display', '284,750');
$totalPayoutDisplay  = getSetting('total_payout_display', 'Rp 15.8M');
$siteName            = getSetting('site_name', 'NOXARA');
$siteTagline         = getSetting('site_tagline', 'Platform Investasi Mining Digital Terpercaya');
$freeBonusNew        = (int)getSetting('free_bonus_new_member', 5000);

// Running text / marquee
$marquees = [];
$stmtM = $db->prepare("SELECT content FROM announcements WHERE type='marquee' AND is_active=1 ORDER BY sort_order ASC LIMIT 10");
if ($stmtM) {
    $stmtM->execute();
    $resM = $stmtM->get_result();
    while ($row = $resM->fetch_assoc()) $marquees[] = $row['content'];
    $stmtM->close();
}
if (empty($marquees)) {
    $marquees = [
        '🚀 Selamat datang di NOXARA! Platform mining digital terpercaya',
        '💰 Profit harian otomatis, mulai dari Rp 10.000',
        '🎁 Bonus registrasi Rp 5.000 untuk member baru!',
        '⚡ Lebih dari 284.750+ member aktif',
        '🔒 Keamanan dana terjamin 100%',
    ];
}

// Banners
$banners = [];
$stmtB = $db->prepare("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC LIMIT 8");
if ($stmtB) {
    $stmtB->execute();
    $resB = $stmtB->get_result();
    while ($row = $resB->fetch_assoc()) $banners[] = $row;
    $stmtB->close();
}

// Produk kategori dengan harga termurah
$produkKategori = [];
$stmtP = $db->prepare("
    SELECT pc.id, pc.name, pc.description, pc.icon,
           MIN(p.price) as min_price,
           MAX(p.daily_profit_percent) as max_profit,
           MAX(p.roi_percent) as max_roi
    FROM product_categories pc
    LEFT JOIN products p ON p.category_id = pc.id AND p.is_active = 1
    WHERE pc.is_active = 1
    GROUP BY pc.id
    ORDER BY pc.sort_order ASC
    LIMIT 3
");
if ($stmtP) {
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($row = $resP->fetch_assoc()) $produkKategori[] = $row;
    $stmtP->close();
}


// Popup announcement
$popup = null;
$stmtPop = $db->prepare("SELECT * FROM announcements WHERE type='popup' AND is_active=1 ORDER BY created_at DESC LIMIT 1");
if ($stmtPop) {
    $stmtPop->execute();
    $popup = $stmtPop->get_result()->fetch_assoc();
    $stmtPop->close();
}

// Contact / Sosmed settings
$waLink      = getSetting('contact_whatsapp', '#');
$tgLink      = getSetting('contact_telegram', '#');
$igLink      = getSetting('contact_instagram', '#');
$ytLink      = getSetting('contact_youtube', '#');
$fbLink      = getSetting('contact_facebook', '#');

// Flash message
$flash = getFlash();

// Math captcha for forms (just page-level)
$_SESSION['math_a'] = rand(1, 9);
$_SESSION['math_b'] = rand(1, 9);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="NOXARA - Platform investasi mining digital terpercaya dengan profit harian otomatis">
<meta name="keywords" content="investasi mining, NOXARA, profit harian, mining digital">
<meta property="og:title" content="NOXARA - Mining Digital Investment">
<meta property="og:description" content="Platform investasi terpercaya dengan profit harian otomatis">
<meta property="og:url" content="<?= BASE_URL ?>">
<title>NOXARA - Investasi Mining Digital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<style>
/* ===== LANDING PAGE STYLES ===== */
:root{--cyan:#00D4FF;--purple:#7B2FFF;--bg:#0A0E1A;--card:#0F1629;--card2:#111827;--border:rgba(0,212,255,.12);--border-p:rgba(123,47,255,.15);}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{background:var(--bg);color:#e2e8f0;font-family:'Plus Jakarta Sans',sans-serif;overflow-x:hidden;line-height:1.6;}
a{color:inherit;text-decoration:none;}
img{max-width:100%;height:auto;}
/* WAVE BG */
.nox-wave-bg{background:radial-gradient(ellipse 80% 40% at 50% -10%,rgba(123,47,255,.25) 0%,transparent 60%),radial-gradient(ellipse 50% 30% at 80% 20%,rgba(0,212,255,.12) 0%,transparent 50%),var(--bg);}
/* NAVBAR */
.lp-nav{position:fixed;top:0;left:0;right:0;z-index:999;padding:0 1.5rem;height:68px;display:flex;align-items:center;justify-content:space-between;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);background:rgba(10,14,26,.82);border-bottom:1px solid var(--border);transition:all .3s;}
.lp-nav.scrolled{background:rgba(10,14,26,.96);box-shadow:0 4px 30px rgba(0,0,0,.4);}
.lp-logo{display:flex;align-items:center;gap:.6rem;text-decoration:none;}
.lp-logo svg{width:36px;height:36px;flex-shrink:0;}
.lp-logo-text{font-family:'Orbitron',sans-serif;font-weight:700;font-size:1.3rem;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:.08em;}
.lp-nav-links{display:flex;align-items:center;gap:.25rem;}
.lp-nav-links a{padding:.45rem .85rem;border-radius:8px;font-size:.9rem;font-weight:500;color:#94a3b8;transition:all .2s;}
.lp-nav-links a:hover{color:#e2e8f0;background:rgba(255,255,255,.06);}
.lp-nav-actions{display:flex;align-items:center;gap:.6rem;}
.btn-lp-outline{padding:.45rem 1.1rem;border-radius:8px;border:1px solid var(--border);color:var(--cyan);font-weight:600;font-size:.88rem;transition:all .25s;}
.btn-lp-outline:hover{background:rgba(0,212,255,.1);border-color:var(--cyan);}
.btn-lp-grad{padding:.45rem 1.2rem;border-radius:8px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;font-size:.88rem;transition:all .25s;box-shadow:0 4px 15px rgba(123,47,255,.3);}
.btn-lp-grad:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(123,47,255,.45);}
.lp-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:.4rem;background:none;border:none;}
.lp-hamburger span{width:24px;height:2px;background:#e2e8f0;border-radius:2px;transition:all .3s;}
/* MOBILE MENU */
.lp-mobile-menu{display:none;position:fixed;top:68px;left:0;right:0;background:rgba(10,14,26,.98);border-bottom:1px solid var(--border);z-index:998;padding:1rem 1.5rem;flex-direction:column;gap:.5rem;}
.lp-mobile-menu.open{display:flex;}
.lp-mobile-menu a{padding:.7rem 1rem;border-radius:8px;color:#94a3b8;font-weight:500;transition:all .2s;}
.lp-mobile-menu a:hover{color:#e2e8f0;background:rgba(255,255,255,.06);}
.lp-mobile-menu .btn-lp-grad{text-align:center;margin-top:.5rem;}
/* SECTIONS */
section{padding:80px 0;}
.lp-container{max-width:1180px;margin:0 auto;padding:0 1.5rem;}
/* HERO */
#hero{padding:140px 0 80px;min-height:100vh;display:flex;align-items:center;position:relative;overflow:hidden;}
.hero-inner{display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;}
.hero-badge{display:inline-flex;align-items:center;gap:.5rem;background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.3);border-radius:50px;padding:.35rem 1rem;font-size:.8rem;font-weight:600;color:var(--cyan);margin-bottom:1.5rem;}
.hero-badge-dot{width:7px;height:7px;background:var(--cyan);border-radius:50%;animation:pulse 1.5s infinite;}
.hero-h1{font-family:'Space Grotesk',sans-serif;font-size:clamp(2.2rem,5vw,3.6rem);font-weight:800;line-height:1.15;margin-bottom:1.2rem;}
.hero-gradient-text{background:linear-gradient(135deg,var(--cyan) 0%,var(--purple) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{font-size:1.05rem;color:#94a3b8;margin-bottom:2rem;max-width:480px;}
.hero-actions{display:flex;gap:1rem;flex-wrap:wrap;}
.btn-hero-primary{padding:.75rem 1.8rem;border-radius:10px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;font-size:1rem;transition:all .3s;box-shadow:0 6px 25px rgba(123,47,255,.35);border:none;cursor:pointer;}
.btn-hero-primary:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(123,47,255,.5);}
.btn-hero-outline{padding:.75rem 1.8rem;border-radius:10px;border:1px solid var(--border);color:#e2e8f0;font-weight:600;font-size:1rem;transition:all .3s;background:transparent;}
.btn-hero-outline:hover{border-color:var(--cyan);color:var(--cyan);background:rgba(0,212,255,.06);}
/* Hero visual */
.hero-visual{position:relative;display:flex;justify-content:center;align-items:center;}
.hero-glow{position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(123,47,255,.3) 0%,transparent 70%);animation:glow-pulse 3s ease-in-out infinite;}
.hero-card-float{background:rgba(15,22,41,.85);border:1px solid var(--border);border-radius:20px;padding:1.5rem;backdrop-filter:blur(12px);width:280px;position:relative;z-index:2;}
.hero-card-float .hcf-label{font-size:.75rem;color:#64748b;margin-bottom:.3rem;}
.hero-card-float .hcf-val{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-card-float .hcf-sub{font-size:.78rem;color:var(--cyan);margin-top:.5rem;}
.hero-float-cards{display:flex;flex-direction:column;gap:1rem;}
.hfc-item{background:rgba(15,22,41,.8);border:1px solid var(--border-p);border-radius:12px;padding:.8rem 1rem;display:flex;align-items:center;gap:.8rem;animation:float-y 3s ease-in-out infinite;}
.hfc-item:nth-child(2){animation-delay:1s;}
.hfc-item:nth-child(3){animation-delay:2s;}
.hfc-icon{width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.15));display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.hfc-icon svg{width:18px;height:18px;color:var(--cyan);}
.hfc-text{font-size:.82rem;}
.hfc-text strong{display:block;color:#e2e8f0;font-weight:600;}
.hfc-text span{color:#64748b;font-size:.75rem;}
/* particles */
.hero-particles{position:absolute;inset:0;pointer-events:none;overflow:hidden;}
.particle{position:absolute;width:3px;height:3px;border-radius:50%;background:var(--cyan);opacity:.5;animation:particle-float linear infinite;}
@keyframes particle-float{0%{transform:translateY(0) translateX(0);opacity:.5;}100%{transform:translateY(-100vh) translateX(30px);opacity:0;}}
@keyframes glow-pulse{0%,100%{transform:scale(1);opacity:.7;}50%{transform:scale(1.15);opacity:1;}}
@keyframes float-y{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
</style>

<style>
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.8rem 1.5rem;text-align:center;transition:all .3s;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:80%;height:2px;background:linear-gradient(90deg,transparent,var(--cyan),transparent);}
.stat-card:hover{transform:translateY(-4px);border-color:rgba(0,212,255,.3);box-shadow:0 12px 30px rgba(0,212,255,.1);}
.stat-val{font-family:'Space Grotesk',sans-serif;font-size:2.2rem;font-weight:800;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.stat-label{font-size:.85rem;color:#64748b;margin-top:.5rem;font-weight:500;}
/* MARQUEE */
.marquee-wrap{background:rgba(0,212,255,.06);border-top:1px solid rgba(0,212,255,.15);border-bottom:1px solid rgba(0,212,255,.15);padding:.7rem 0;overflow:hidden;}
.marquee-track{display:flex;animation:marquee-scroll 30s linear infinite;width:max-content;}
.marquee-track:hover{animation-play-state:paused;}
.marquee-item{white-space:nowrap;padding:0 3rem;font-size:.88rem;color:var(--cyan);font-weight:500;}
.marquee-sep{color:rgba(0,212,255,.35);padding:0 .5rem;}
@keyframes marquee-scroll{0%{transform:translateX(0);}100%{transform:translateX(-50%)}}
/* SECTION HEADERS */
.section-label{display:inline-flex;align-items:center;gap:.5rem;background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.2);border-radius:50px;padding:.3rem .9rem;font-size:.78rem;font-weight:700;color:var(--cyan);text-transform:uppercase;letter-spacing:.08em;margin-bottom:1rem;}
.section-title{font-family:'Space Grotesk',sans-serif;font-size:clamp(1.8rem,3.5vw,2.6rem);font-weight:800;line-height:1.2;margin-bottom:.8rem;}
.section-sub{color:#64748b;font-size:1rem;max-width:520px;}
.section-head{text-align:center;margin-bottom:3.5rem;}
.section-head .section-sub{margin:0 auto;}
/* BANNER SLIDER */
.banner-slider{position:relative;border-radius:20px;overflow:hidden;aspect-ratio:3/1;min-height:180px;max-height:320px;background:var(--card);}
.banner-slide{position:absolute;inset:0;opacity:0;transition:opacity .6s ease;}
.banner-slide.active{opacity:1;}
.banner-slide img{width:100%;height:100%;object-fit:cover;}
.banner-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.2));flex-direction:column;gap:1rem;}
.banner-placeholder h3{font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.banner-placeholder p{color:#64748b;font-size:.9rem;}
.slider-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:6px;}
.slider-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.3);cursor:pointer;transition:all .3s;border:none;}
.slider-dot.active{background:var(--cyan);width:20px;border-radius:4px;}
.slider-prev,.slider-next{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.5);border:1px solid var(--border);color:#e2e8f0;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;transition:all .2s;}
.slider-prev{left:12px;}
.slider-next{right:12px;}
.slider-prev:hover,.slider-next:hover{background:rgba(0,212,255,.2);}
/* PRODUK */
.produk-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;}
.produk-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:1.8rem;transition:all .35s;position:relative;overflow:hidden;cursor:pointer;}
.produk-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,212,255,.04),rgba(123,47,255,.04));opacity:0;transition:opacity .35s;}
.produk-card:hover{transform:translateY(-6px);border-color:rgba(0,212,255,.35);box-shadow:0 16px 40px rgba(0,212,255,.12);}
.produk-card:hover::before{opacity:1;}
.produk-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.15));border:1px solid var(--border);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;}
.produk-icon svg{width:26px;height:26px;color:var(--cyan);}
.produk-name{font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:700;margin-bottom:.4rem;}
.produk-desc{font-size:.83rem;color:#64748b;margin-bottom:1.2rem;line-height:1.5;}
.produk-stats{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1.3rem;}
.produk-stat{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:8px;padding:.5rem .7rem;}
.produk-stat-label{font-size:.7rem;color:#64748b;margin-bottom:.15rem;}
.produk-stat-val{font-size:.95rem;font-weight:700;color:var(--cyan);}
.produk-price{font-size:.82rem;color:#94a3b8;margin-bottom:1rem;}
.produk-price strong{color:#e2e8f0;}
.btn-produk{display:block;text-align:center;padding:.6rem 1rem;border-radius:8px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;font-size:.88rem;transition:all .25s;}
.btn-produk:hover{opacity:.85;transform:translateY(-1px);}
/* CARA KERJA */
#cara-kerja{background:rgba(123,47,255,.04);}
.steps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;position:relative;}
.steps-grid::before{content:'';position:absolute;top:36px;left:calc(16.67% + 36px);right:calc(16.67% + 36px);height:2px;background:linear-gradient(90deg,var(--cyan),var(--purple));z-index:0;}
.step-card{text-align:center;position:relative;z-index:1;}
.step-num{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.2));border:2px solid var(--border);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;position:relative;}
.step-num-inner{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:800;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.step-icon{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,rgba(0,212,255,.15),rgba(123,47,255,.2));border:2px solid var(--border);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;}
.step-icon svg{width:28px;height:28px;color:var(--cyan);}
.step-title{font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:.5rem;}
.step-desc{font-size:.88rem;color:#64748b;line-height:1.6;}
/* REFERRAL */
.ref-grid{display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:start;}
.ref-tree{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:2rem;}
.ref-tree-title{font-family:'Space Grotesk',sans-serif;font-weight:700;margin-bottom:1.5rem;font-size:1.05rem;}
.ref-node{display:flex;align-items:center;gap:.8rem;padding:.7rem 1rem;border-radius:10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);margin-bottom:.6rem;transition:all .3s;}
.ref-node:hover{border-color:rgba(0,212,255,.25);background:rgba(0,212,255,.04);}
.ref-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--cyan),var(--purple));display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0;}
.ref-node-info{flex:1;}
.ref-node-name{font-size:.88rem;font-weight:600;}
.ref-node-level{font-size:.72rem;color:#64748b;}
.ref-node-earn{font-size:.82rem;color:var(--cyan);font-weight:600;margin-left:auto;}
.ref-l1{border-left:3px solid var(--cyan);}
.ref-l2{border-left:3px solid var(--purple);margin-left:1.5rem;}
.ref-l3{border-left:3px solid #00e676;margin-left:3rem;}
.ref-table-wrap{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:2rem;}
.ref-table{width:100%;border-collapse:collapse;}
.ref-table th{text-align:left;font-size:.78rem;font-weight:600;color:#64748b;padding:.6rem .8rem;border-bottom:1px solid var(--border);}
.ref-table td{padding:.7rem .8rem;font-size:.88rem;border-bottom:1px solid rgba(255,255,255,.04);}
.ref-table tr:last-child td{border-bottom:none;}
.ref-badge{display:inline-block;padding:.2rem .6rem;border-radius:6px;font-size:.75rem;font-weight:700;}
.ref-badge-l1{background:rgba(0,212,255,.15);color:var(--cyan);}
.ref-badge-l2{background:rgba(123,47,255,.15);color:#9b59b6;}
.ref-badge-l3{background:rgba(0,230,118,.15);color:#00e676;}
/* FAQ */
.faq-list{max-width:800px;margin:0 auto;display:flex;flex-direction:column;gap:.8rem;}
.faq-item{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:border-color .3s;}
.faq-item.open{border-color:rgba(0,212,255,.3);}
.faq-q{display:flex;align-items:center;justify-content:space-between;padding:1.2rem 1.5rem;cursor:pointer;user-select:none;gap:1rem;}
.faq-q-text{font-weight:600;font-size:.95rem;}
.faq-icon{width:24px;height:24px;border-radius:50%;background:rgba(0,212,255,.1);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .3s;}
.faq-item.open .faq-icon{transform:rotate(45deg);background:rgba(0,212,255,.2);}
.faq-a{max-height:0;overflow:hidden;transition:max-height .35s ease,padding .35s ease;padding:0 1.5rem;color:#64748b;font-size:.9rem;line-height:1.7;}
.faq-item.open .faq-a{max-height:200px;padding-bottom:1.2rem;}
/* FOOTER */
footer{background:rgba(0,0,0,.4);border-top:1px solid var(--border);padding:3rem 0 1.5rem;}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:2rem;margin-bottom:2.5rem;}
.footer-brand-desc{font-size:.88rem;color:#64748b;line-height:1.7;margin-top:.8rem;max-width:260px;}
.footer-col-title{font-weight:700;font-size:.9rem;margin-bottom:1rem;color:#e2e8f0;}
.footer-links{display:flex;flex-direction:column;gap:.5rem;}
.footer-links a{font-size:.85rem;color:#64748b;transition:color .2s;}
.footer-links a:hover{color:var(--cyan);}
.footer-socmed{display:flex;gap:.7rem;margin-top:1rem;}
.socmed-btn{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.05);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:#64748b;transition:all .25s;font-size:.8rem;font-weight:700;}
.socmed-btn:hover{background:rgba(0,212,255,.12);border-color:var(--cyan);color:var(--cyan);}
.footer-bottom{border-top:1px solid var(--border);padding-top:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
.footer-copy{font-size:.82rem;color:#64748b;}
.footer-copy span{color:var(--cyan);}
/* POPUP */
.lp-popup-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;animation:fadeIn .3s ease;}
.lp-popup-box{background:var(--card2);border:1px solid var(--border);border-radius:20px;max-width:480px;width:100%;position:relative;overflow:hidden;animation:slideUp .4s ease;}
.lp-popup-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.08);border:1px solid var(--border);color:#94a3b8;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;line-height:1;transition:all .2s;}
.lp-popup-close:hover{background:rgba(255,0,0,.15);color:#fff;}
.lp-popup-img img{width:100%;max-height:220px;object-fit:cover;}
.lp-popup-body{padding:1.8rem 2rem 2rem;}
.lp-popup-title{font-family:'Space Grotesk',sans-serif;font-size:1.3rem;font-weight:700;margin-bottom:.6rem;}
.lp-popup-text{font-size:.9rem;color:#94a3b8;line-height:1.7;margin-bottom:1.2rem;}
.lp-popup-cta{display:block;text-align:center;padding:.7rem 1.5rem;border-radius:10px;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;font-weight:700;}
/* FLASH */
.lp-flash{position:fixed;top:80px;right:1.5rem;z-index:9000;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1rem 1.2rem;display:flex;align-items:center;gap:.8rem;min-width:280px;max-width:380px;box-shadow:0 8px 30px rgba(0,0,0,.4);animation:slideInRight .4s ease;}
.lp-flash--success{border-color:rgba(0,230,118,.4);}
.lp-flash--error{border-color:rgba(255,68,68,.4);}
.lp-flash--info{border-color:rgba(0,212,255,.4);}
.lp-flash svg{width:20px;height:20px;flex-shrink:0;}
.lp-flash--success svg{color:#00e676;}
.lp-flash--error svg{color:#ff4444;}
.lp-flash--info svg{color:var(--cyan);}
.lp-flash-close{margin-left:auto;background:none;border:none;color:#64748b;cursor:pointer;font-size:1.1rem;line-height:1;}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideInRight{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
/* RESPONSIVE */
@media(max-width:1024px){
  .hero-inner{grid-template-columns:1fr;}.hero-visual{display:none;}
  .stats-grid{grid-template-columns:repeat(2,1fr);}
  .produk-grid{grid-template-columns:repeat(2,1fr);}
  .footer-grid{grid-template-columns:1fr 1fr;}
  .ref-grid{grid-template-columns:1fr;}
  .steps-grid::before{display:none;}
}
@media(max-width:768px){
  .lp-nav-links,.lp-nav-actions{display:none;}
  .lp-hamburger{display:flex;}
  .stats-grid{grid-template-columns:repeat(2,1fr);}
  .produk-grid{grid-template-columns:1fr;}
  .footer-grid{grid-template-columns:1fr;}
  .steps-grid{grid-template-columns:1fr;}
  .banner-slider{aspect-ratio:2/1;}
  section{padding:60px 0;}
  #hero{padding:110px 0 60px;min-height:auto;}
}
</style>

</head>
<body class="nox-wave-bg">
<!-- SVG SPRITE -->
<?php include ASSETS_PATH . '/img/icons/icons.svg'; ?>

<!-- FLASH MESSAGE -->
<?php if ($flash): ?>
<div class="lp-flash lp-flash--<?= htmlspecialchars($flash['type']) ?>" id="lpFlash">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-info"/></svg>
  <span><?= htmlspecialchars($flash['message']) ?></span>
  <button class="lp-flash-close" onclick="document.getElementById('lpFlash').remove()">&times;</button>
</div>
<?php endif; ?>

<!-- NAVBAR -->
<nav class="lp-nav" id="lpNav">
  <a href="<?= BASE_URL ?>" class="lp-logo">
    <svg viewBox="0 0 40 40" width="36" height="36"><use href="#icon-noxara"/></svg>
    <span class="lp-logo-text">NOXARA</span>
  </a>
  <div class="lp-nav-links">
    <a href="#hero">Beranda</a>
    <a href="#produk">Produk</a>
    <a href="#cara-kerja">Cara Kerja</a>
    <a href="#faq">FAQ</a>
  </div>
  <div class="lp-nav-actions">
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn-lp-outline">Login</a>
    <a href="<?= BASE_URL ?>/auth/register.php" class="btn-lp-grad">Daftar</a>
  </div>
  <button class="lp-hamburger" id="hamburgerBtn" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- MOBILE MENU -->
<div class="lp-mobile-menu" id="mobileMenu">
  <a href="#hero" onclick="closeMobileMenu()">Beranda</a>
  <a href="#produk" onclick="closeMobileMenu()">Produk</a>
  <a href="#cara-kerja" onclick="closeMobileMenu()">Cara Kerja</a>
  <a href="#faq" onclick="closeMobileMenu()">FAQ</a>
  <a href="<?= BASE_URL ?>/auth/login.php" class="btn-lp-outline" style="text-align:center;display:block;padding:.7rem 1rem;">Login</a>
  <a href="<?= BASE_URL ?>/auth/register.php" class="btn-lp-grad">Daftar Sekarang</a>
</div>

<!-- HERO SECTION -->
<section id="hero">
  <div class="lp-container">
    <div class="hero-inner">
      <div class="hero-content">
        <div class="hero-badge">
          <span class="hero-badge-dot"></span>
          Platform Mining Terpercaya #1 Indonesia
        </div>
        <h1 class="hero-h1">
          Investasi<br>
          <span class="hero-gradient-text">Mining Digital</span><br>
          Lebih Mudah
        </h1>
        <p class="hero-sub">Platform investasi terpercaya dengan profit harian otomatis. Mulai dari Rp 10.000 dan raih penghasilan pasif setiap hari.</p>
        <div class="hero-actions">
          <a href="<?= BASE_URL ?>/auth/register.php" class="btn-hero-primary">🚀 Mulai Investasi</a>
          <a href="#cara-kerja" class="btn-hero-outline">Pelajari Lebih</a>
        </div>
        <div style="margin-top:1.5rem;display:flex;gap:1.5rem;flex-wrap:wrap;">
          <div style="font-size:.82rem;color:#64748b;display:flex;align-items:center;gap:.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00e676" stroke-width="2"><use href="#icon-check-circle"/></svg>
            Tanpa biaya pendaftaran
          </div>
          <div style="font-size:.82rem;color:#64748b;display:flex;align-items:center;gap:.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00e676" stroke-width="2"><use href="#icon-check-circle"/></svg>
            Profit harian otomatis
          </div>
          <div style="font-size:.82rem;color:#64748b;display:flex;align-items:center;gap:.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00e676" stroke-width="2"><use href="#icon-check-circle"/></svg>
            Bonus referral 3 level
          </div>
        </div>
      </div>
      <div class="hero-visual">
        <div class="hero-glow"></div>
        <div class="hero-float-cards">
          <div class="hfc-item" style="align-self:flex-start;">
            <div class="hfc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-trending-up"/></svg></div>
            <div class="hfc-text"><strong>Profit Hari Ini</strong><span style="color:#00e676;">+Rp 127.500</span></div>
          </div>
          <div class="hfc-item">
            <div class="hfc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-mining"/></svg></div>
            <div class="hfc-text"><strong>Mining Aktif</strong><span>284.750+ Pengguna</span></div>
          </div>
          <div class="hfc-item" style="align-self:flex-end;">
            <div class="hfc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-coin"/></svg></div>
            <div class="hfc-text"><strong>Total Payout</strong><span>Rp 15.8M+</span></div>
          </div>
        </div>
      </div>
    </div>
    <!-- particles -->
    <div class="hero-particles" id="heroParticles"></div>
  </div>
</section>

<!-- STATISTIK COUNTER -->
<section id="statistik" style="padding:50px 0;background:rgba(0,0,0,.2);">
  <div class="lp-container">
    <div class="stats-grid">
      <div class="stat-card" data-count-target="<?= htmlspecialchars($totalMembersDisplay) ?>">
        <div class="stat-val" id="stat-member">0</div>
        <div class="stat-label">Total Member Aktif</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= htmlspecialchars($totalPayoutDisplay) ?>+</div>
        <div class="stat-label">Total Payout</div>
      </div>
      <div class="stat-card">
        <div class="stat-val" id="stat-rating">0</div>
        <div class="stat-label">Rating Pengguna</div>
      </div>
      <div class="stat-card">
        <div class="stat-val">2024</div>
        <div class="stat-label">Berdiri Sejak</div>
      </div>
    </div>
  </div>
</section>


<!-- RUNNING TEXT / MARQUEE -->
<div class="marquee-wrap">
  <div class="marquee-track" id="marqueeTrack">
    <?php
    $marqueeAll = array_merge($marquees, $marquees); // duplicate for seamless loop
    foreach ($marqueeAll as $m): ?>
      <span class="marquee-item">
        <?= htmlspecialchars($m) ?>
        <span class="marquee-sep">•</span>
      </span>
    <?php endforeach; ?>
  </div>
</div>

<!-- BANNER SLIDER -->
<section style="padding:40px 0;">
  <div class="lp-container">
    <div class="banner-slider" id="bannerSlider">
      <?php if (!empty($banners)): ?>
        <?php foreach ($banners as $idx => $banner): ?>
          <div class="banner-slide <?= $idx === 0 ? 'active' : '' ?>">
            <?php if (!empty($banner['image'])): ?>
              <img src="<?= UPLOADS_URL ?>/banners/<?= htmlspecialchars($banner['image']) ?>"
                   alt="<?= htmlspecialchars($banner['title'] ?? 'Banner') ?>">
            <?php else: ?>
              <div class="banner-placeholder">
                <h3><?= htmlspecialchars($banner['title'] ?? 'NOXARA Mining') ?></h3>
                <p><?= htmlspecialchars($banner['subtitle'] ?? 'Platform Investasi Digital Terpercaya') ?></p>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="banner-slide active">
          <div class="banner-placeholder">
            <svg width="64" height="64" viewBox="0 0 40 40"><use href="#icon-noxara"/></svg>
            <h3>🚀 NOXARA Mining Platform</h3>
            <p>Profit Harian • Mining Digital • Terpercaya Sejak 2024</p>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-hero-primary" style="margin-top:.5rem;">Daftar Sekarang</a>
          </div>
        </div>
      <?php endif; ?>
      <button class="slider-prev" id="sliderPrev" aria-label="Previous">&#8249;</button>
      <button class="slider-next" id="sliderNext" aria-label="Next">&#8250;</button>
      <div class="slider-dots" id="sliderDots"></div>
    </div>
  </div>
</section>

<!-- PRODUK MINING -->
<section id="produk">
  <div class="lp-container">
    <div class="section-head">
      <div class="section-label">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-mining"/></svg>
        Paket Mining
      </div>
      <h2 class="section-title">Pilih Paket <span class="hero-gradient-text">Mining</span> Kamu</h2>
      <p class="section-sub">Investasi aman dengan profit harian otomatis. Mulai dari paket terjangkau hingga premium.</p>
    </div>
    <div class="produk-grid">
      <?php if (!empty($produkKategori)): ?>
        <?php foreach ($produkKategori as $kat): ?>
          <div class="produk-card">
            <div class="produk-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-mining"/></svg>
            </div>
            <div class="produk-name"><?= htmlspecialchars($kat['name']) ?></div>
            <div class="produk-desc"><?= htmlspecialchars(truncate($kat['description'] ?? 'Paket mining digital dengan profit harian', 80)) ?></div>
            <div class="produk-stats">
              <div class="produk-stat">
                <div class="produk-stat-label">Profit/Hari</div>
                <div class="produk-stat-val"><?= number_format((float)($kat['max_profit'] ?? 1.5), 1) ?>%</div>
              </div>
              <div class="produk-stat">
                <div class="produk-stat-label">Total ROI</div>
                <div class="produk-stat-val"><?= number_format((float)($kat['max_roi'] ?? 150), 0) ?>%</div>
              </div>
            </div>
            <div class="produk-price">Mulai dari <strong><?= formatRupiah((int)($kat['min_price'] ?? 10000)) ?></strong></div>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-produk">Beli Sekarang</a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php
        $dummyProduk = [
          ['name'=>'Starter Mining','desc'=>'Cocok untuk pemula, modal kecil profit konsisten','profit'=>'1.5','roi'=>'150','price'=>'10,000'],
          ['name'=>'Professional Mining','desc'=>'Paket menengah dengan hashrate lebih tinggi','profit'=>'2.5','roi'=>'200','price'=>'100,000'],
          ['name'=>'Elite Mining','desc'=>'Paket premium dengan profit maksimal harian','profit'=>'4.0','roi'=>'300','price'=>'1,000,000'],
        ];
        foreach ($dummyProduk as $dp): ?>
          <div class="produk-card">
            <div class="produk-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-mining"/></svg>
            </div>
            <div class="produk-name"><?= $dp['name'] ?></div>
            <div class="produk-desc"><?= $dp['desc'] ?></div>
            <div class="produk-stats">
              <div class="produk-stat"><div class="produk-stat-label">Profit/Hari</div><div class="produk-stat-val"><?= $dp['profit'] ?>%</div></div>
              <div class="produk-stat"><div class="produk-stat-label">Total ROI</div><div class="produk-stat-val"><?= $dp['roi'] ?>%</div></div>
            </div>
            <div class="produk-price">Mulai dari <strong>Rp <?= $dp['price'] ?></strong></div>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-produk">Beli Sekarang</a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div style="text-align:center;margin-top:2rem;">
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn-hero-outline">Lihat Semua Paket &rarr;</a>
    </div>
  </div>
</section>


<!-- CARA KERJA -->
<section id="cara-kerja">
  <div class="lp-container">
    <div class="section-head">
      <div class="section-label">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-info"/></svg>
        Cara Kerja
      </div>
      <h2 class="section-title">Mudah dalam <span class="hero-gradient-text">3 Langkah</span></h2>
      <p class="section-sub">Mulai investasi mining digital hanya dalam beberapa menit.</p>
    </div>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-profile"/></svg>
        </div>
        <h3 class="step-title">1. Daftar &amp; Deposit</h3>
        <p class="step-desc">Buat akun gratis dalam 1 menit. Verifikasi data dan lakukan deposit pertama kamu mulai Rp 10.000.</p>
      </div>
      <div class="step-card">
        <div class="step-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-package"/></svg>
        </div>
        <h3 class="step-title">2. Pilih Paket Mining</h3>
        <p class="step-desc">Pilih paket mining yang sesuai budget kamu. Dari paket starter hingga elite untuk profit maksimal.</p>
      </div>
      <div class="step-card">
        <div class="step-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-trending-up"/></svg>
        </div>
        <h3 class="step-title">3. Mining &amp; Profit</h3>
        <p class="step-desc">Sistem mining berjalan otomatis 24/7. Profit masuk ke dompet kamu setiap hari secara otomatis.</p>
      </div>
    </div>
    <div style="text-align:center;margin-top:2.5rem;">
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn-hero-primary">Mulai Sekarang &rarr;</a>
    </div>
  </div>
</section>

<!-- REFERRAL SECTION -->
<section id="referral" style="background:rgba(0,0,0,.2);">
  <div class="lp-container">
    <div class="section-head">
      <div class="section-label">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-referral"/></svg>
        Program Referral
      </div>
      <h2 class="section-title">Hasilkan Lebih Banyak dengan <span class="hero-gradient-text">Referral</span></h2>
      <p class="section-sub">Ajak teman bergabung dan dapatkan komisi hingga 3 level downline kamu.</p>
    </div>
    <div class="ref-grid">
      <div class="ref-tree">
        <div class="ref-tree-title">🌳 Pohon Referral Kamu</div>
        <!-- L1 -->
        <div class="ref-node ref-l1">
          <div class="ref-avatar">L1</div>
          <div class="ref-node-info">
            <div class="ref-node-name">Ahmad S***</div>
            <div class="ref-node-level">Level 1 · Referral Langsung</div>
          </div>
          <div class="ref-node-earn">+Rp 5.000</div>
        </div>
        <div class="ref-node ref-l1">
          <div class="ref-avatar" style="background:linear-gradient(135deg,#00D4FF,#0099cc);">L1</div>
          <div class="ref-node-info">
            <div class="ref-node-name">Budi W***</div>
            <div class="ref-node-level">Level 1 · Referral Langsung</div>
          </div>
          <div class="ref-node-earn">+Rp 8.000</div>
        </div>
        <!-- L2 -->
        <div class="ref-node ref-l2">
          <div class="ref-avatar" style="background:linear-gradient(135deg,#7B2FFF,#5500cc);">L2</div>
          <div class="ref-node-info">
            <div class="ref-node-name">Citra M***</div>
            <div class="ref-node-level">Level 2 · Downline Ahmad</div>
          </div>
          <div class="ref-node-earn">+Rp 2.500</div>
        </div>
        <!-- L3 -->
        <div class="ref-node ref-l3">
          <div class="ref-avatar" style="background:linear-gradient(135deg,#00e676,#00aa55);">L3</div>
          <div class="ref-node-info">
            <div class="ref-node-name">Dian P***</div>
            <div class="ref-node-level">Level 3 · Downline Citra</div>
          </div>
          <div class="ref-node-earn">+Rp 1.000</div>
        </div>
        <div style="margin-top:1.2rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,.06);font-size:.82rem;color:#64748b;">
          💡 Semakin banyak referral = semakin besar komisi kamu!
        </div>
      </div>
      <div class="ref-table-wrap">
        <div class="ref-tree-title">💰 Tabel Komisi Referral</div>
        <table class="ref-table">
          <thead>
            <tr>
              <th>Level</th>
              <th>Komisi Deposit</th>
              <th>Komisi Transaksi</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="ref-badge ref-badge-l1">Level 1</span></td>
              <td style="color:var(--cyan);font-weight:600;"><?= getSetting('ref_deposit_l1', '5') ?>%</td>
              <td style="color:var(--cyan);font-weight:600;"><?= getSetting('ref_trx_l1', '2') ?>%</td>
            </tr>
            <tr>
              <td><span class="ref-badge ref-badge-l2">Level 2</span></td>
              <td style="color:#9b59b6;font-weight:600;"><?= getSetting('ref_deposit_l2', '2') ?>%</td>
              <td style="color:#9b59b6;font-weight:600;"><?= getSetting('ref_trx_l2', '1') ?>%</td>
            </tr>
            <tr>
              <td><span class="ref-badge ref-badge-l3">Level 3</span></td>
              <td style="color:#00e676;font-weight:600;"><?= getSetting('ref_deposit_l3', '1') ?>%</td>
              <td style="color:#00e676;font-weight:600;"><?= getSetting('ref_trx_l3', '0.5') ?>%</td>
            </tr>
          </tbody>
        </table>
        <div style="margin-top:1.5rem;background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.2);border-radius:10px;padding:1rem;">
          <div style="font-size:.82rem;color:#64748b;margin-bottom:.5rem;">🎯 Contoh komisi kamu jika teman deposit Rp 1.000.000:</div>
          <div style="font-size:.88rem;">L1: <strong style="color:var(--cyan);">Rp <?= number_format((int)getSetting('ref_deposit_l1',5)*10000,0,',','.') ?></strong> &nbsp;|&nbsp; L2: <strong style="color:#9b59b6;">Rp <?= number_format((int)getSetting('ref_deposit_l2',2)*10000,0,',','.') ?></strong> &nbsp;|&nbsp; L3: <strong style="color:#00e676;">Rp <?= number_format((int)getSetting('ref_deposit_l3',1)*10000,0,',','.') ?></strong></div>
        </div>
        <div style="margin-top:1.5rem;">
          <a href="<?= BASE_URL ?>/auth/register.php" class="btn-produk">Mulai Referral Sekarang</a>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- FAQ -->
<section id="faq">
  <div class="lp-container">
    <div class="section-head">
      <div class="section-label">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-faq"/></svg>
        FAQ
      </div>
      <h2 class="section-title">Pertanyaan yang Sering <span class="hero-gradient-text">Ditanyakan</span></h2>
    </div>
    <div class="faq-list">
      <?php
      $faqs = [
        ['q'=>'Apa itu NOXARA dan bagaimana cara kerjanya?','a'=>'NOXARA adalah platform investasi mining digital yang memungkinkan kamu mendapatkan profit harian dari aktivitas mining cryptocurrency. Kamu cukup membeli paket mining, dan sistem akan secara otomatis memproses mining dan mendistribusikan profit ke dompet kamu setiap hari.'],
        ['q'=>'Berapa minimal deposit untuk mulai investasi?','a'=>'Minimal deposit di NOXARA sangat terjangkau, mulai dari Rp 10.000. Kami menyediakan berbagai pilihan paket mining yang cocok untuk berbagai budget, dari pemula hingga investor berpengalaman.'],
        ['q'=>'Kapan profit mining saya cair?','a'=>'Profit mining dicairkan secara otomatis setiap hari ke dompet profit kamu. Kamu bisa melihat riwayat profit di dashboard dan melakukan withdraw kapan saja sesuai dengan jam operasional (hari kerja, 08.00-17.00 WIB).'],
        ['q'=>'Apakah dana saya aman di NOXARA?','a'=>'Keamanan dana member adalah prioritas utama kami. Sistem kami dilengkapi dengan enkripsi SSL, 2FA, PIN keamanan, dan monitoring 24/7. Seluruh transaksi diproses dengan protokol keamanan tinggi.'],
        ['q'=>'Bagaimana cara kerja program referral NOXARA?','a'=>'Program referral NOXARA memberikan komisi hingga 3 level downline. Level 1 mendapat komisi deposit dan transaksi terbesar, diikuti Level 2 dan Level 3. Semakin banyak referral aktif, semakin besar penghasilan pasif kamu.'],
        ['q'=>'Berapa lama proses withdraw?','a'=>'Proses withdraw biasanya diselesaikan dalam 1x24 jam kerja. Tim keuangan kami memverifikasi setiap permintaan withdraw untuk keamanan. Pastikan data rekening bank/e-wallet kamu sudah terverifikasi.'],
      ];
      foreach ($faqs as $i => $faq): ?>
        <div class="faq-item" id="faqItem<?= $i ?>">
          <div class="faq-q" onclick="toggleFaq(<?= $i ?>)">
            <span class="faq-q-text"><?= htmlspecialchars($faq['q']) ?></span>
            <span class="faq-icon">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </span>
          </div>
          <div class="faq-a"><?= htmlspecialchars($faq['a']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA BANNER -->
<section style="padding:60px 0;background:linear-gradient(135deg,rgba(0,212,255,.08),rgba(123,47,255,.12));">
  <div class="lp-container" style="text-align:center;">
    <h2 class="section-title" style="margin-bottom:.8rem;">Siap Mulai Investasi <span class="hero-gradient-text">Mining</span>?</h2>
    <p style="color:#64748b;margin-bottom:1.8rem;font-size:1rem;">Daftar sekarang dan dapatkan bonus Rp <?= formatRupiah($freeBonusNew, false) ?> untuk member baru!</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn-hero-primary">🎁 Daftar &amp; Klaim Bonus</a>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn-hero-outline">Sudah Punya Akun</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="lp-container">
    <div class="footer-grid">
      <div>
        <a href="<?= BASE_URL ?>" class="lp-logo" style="margin-bottom:.8rem;display:inline-flex;">
          <svg viewBox="0 0 40 40" width="32" height="32"><use href="#icon-noxara"/></svg>
          <span class="lp-logo-text">NOXARA</span>
        </a>
        <p class="footer-brand-desc">Platform investasi mining digital terpercaya. Profit harian otomatis untuk semua orang.</p>
        <div class="footer-socmed">
          <?php if ($waLink !== '#'): ?>
            <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener" class="socmed-btn" title="WhatsApp">WA</a>
          <?php endif; ?>
          <?php if ($tgLink !== '#'): ?>
            <a href="<?= htmlspecialchars($tgLink) ?>" target="_blank" rel="noopener" class="socmed-btn" title="Telegram">TG</a>
          <?php endif; ?>
          <?php if ($igLink !== '#'): ?>
            <a href="<?= htmlspecialchars($igLink) ?>" target="_blank" rel="noopener" class="socmed-btn" title="Instagram">IG</a>
          <?php endif; ?>
          <?php if ($ytLink !== '#'): ?>
            <a href="<?= htmlspecialchars($ytLink) ?>" target="_blank" rel="noopener" class="socmed-btn" title="YouTube">YT</a>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="footer-col-title">Platform</div>
        <div class="footer-links">
          <a href="#produk">Paket Mining</a>
          <a href="#referral">Referral</a>
          <a href="<?= BASE_URL ?>/auth/register.php">VIP Program</a>
          <a href="#faq">FAQ</a>
        </div>
      </div>
      <div>
        <div class="footer-col-title">Akun</div>
        <div class="footer-links">
          <a href="<?= BASE_URL ?>/auth/login.php">Login</a>
          <a href="<?= BASE_URL ?>/auth/register.php">Daftar</a>
          <a href="<?= BASE_URL ?>/auth/forgot_password.php">Lupa Password</a>
        </div>
      </div>
      <div>
        <div class="footer-col-title">Kontak</div>
        <div class="footer-links">
          <?php if ($waLink !== '#'): ?>
            <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">WhatsApp CS</a>
          <?php endif; ?>
          <?php if ($tgLink !== '#'): ?>
            <a href="<?= htmlspecialchars($tgLink) ?>" target="_blank" rel="noopener">Telegram</a>
          <?php endif; ?>
          <a href="#faq">Pusat Bantuan</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="footer-copy">&copy; <?= date('Y') ?> <span>NOXARA</span>. All rights reserved.</div>
      <div class="footer-copy" style="display:flex;gap:1rem;">
        <a href="#" style="color:#64748b;font-size:.82rem;">Syarat &amp; Ketentuan</a>
        <a href="#" style="color:#64748b;font-size:.82rem;">Kebijakan Privasi</a>
      </div>
    </div>
  </div>
</footer>

<!-- POPUP ANNOUNCEMENT -->
<?php if ($popup): ?>
<div class="lp-popup-overlay" id="lpPopup" style="display:none;">
  <div class="lp-popup-box">
    <button class="lp-popup-close" onclick="closePopup()" aria-label="Tutup">&times;</button>
    <?php if (!empty($popup['image'])): ?>
      <div class="lp-popup-img">
        <img src="<?= UPLOADS_URL ?>/banners/<?= htmlspecialchars($popup['image']) ?>" alt="Promo">
      </div>
    <?php endif; ?>
    <div class="lp-popup-body">
      <?php if (!empty($popup['title'])): ?>
        <div class="lp-popup-title"><?= htmlspecialchars($popup['title']) ?></div>
      <?php endif; ?>
      <?php if (!empty($popup['content'])): ?>
        <div class="lp-popup-text"><?= nl2br(htmlspecialchars($popup['content'])) ?></div>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/auth/register.php" class="lp-popup-cta">🚀 Daftar Sekarang</a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="lp-popup-overlay" id="lpPopup" style="display:none;">
  <div class="lp-popup-box">
    <button class="lp-popup-close" onclick="closePopup()" aria-label="Tutup">&times;</button>
    <div class="lp-popup-body" style="text-align:center;padding:2.5rem 2rem;">
      <svg width="64" height="64" viewBox="0 0 40 40" style="margin-bottom:1rem;"><use href="#icon-noxara"/></svg>
      <div class="lp-popup-title">🎉 Selamat Datang di NOXARA!</div>
      <div class="lp-popup-text">Daftar sekarang dan dapatkan <strong style="color:var(--cyan);">bonus Rp <?= formatRupiah($freeBonusNew, false) ?></strong> untuk memulai mining kamu!</div>
      <a href="<?= BASE_URL ?>/auth/register.php" class="lp-popup-cta">Klaim Bonus Sekarang</a>
      <div style="margin-top:.8rem;font-size:.78rem;color:#64748b;cursor:pointer;" onclick="closePopup()">Lewati, lain kali saja</div>
    </div>
  </div>
</div>
<?php endif; ?>


<script>
// ===== NAVBAR SCROLL =====
window.addEventListener('scroll', () => {
  document.getElementById('lpNav').classList.toggle('scrolled', window.scrollY > 30);
});

// ===== HAMBURGER =====
function closeMobileMenu() { document.getElementById('mobileMenu').classList.remove('open'); }
document.getElementById('hamburgerBtn').addEventListener('click', () => {
  document.getElementById('mobileMenu').classList.toggle('open');
});
document.addEventListener('click', e => {
  if (!e.target.closest('#mobileMenu') && !e.target.closest('#hamburgerBtn')) closeMobileMenu();
});

// ===== PARTICLES =====
(function() {
  const container = document.getElementById('heroParticles');
  if (!container) return;
  for (let i = 0; i < 20; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.cssText = `left:${Math.random()*100}%;top:${Math.random()*100}%;animation-duration:${5+Math.random()*10}s;animation-delay:${Math.random()*5}s;width:${1+Math.random()*3}px;height:${1+Math.random()*3}px;opacity:${0.2+Math.random()*0.5};background:${Math.random()>.5?'#00D4FF':'#7B2FFF'};`;
    container.appendChild(p);
  }
})();

// ===== BANNER SLIDER =====
(function() {
  const slides = document.querySelectorAll('.banner-slide');
  const dotsContainer = document.getElementById('sliderDots');
  if (!slides.length || !dotsContainer) return;
  let current = 0;
  slides.forEach((_, i) => {
    const d = document.createElement('button');
    d.className = 'slider-dot' + (i === 0 ? ' active' : '');
    d.setAttribute('aria-label', 'Slide ' + (i+1));
    d.addEventListener('click', () => goTo(i));
    dotsContainer.appendChild(d);
  });
  function goTo(n) {
    slides[current].classList.remove('active');
    dotsContainer.children[current].classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current].classList.add('active');
    dotsContainer.children[current].classList.add('active');
  }
  document.getElementById('sliderPrev').addEventListener('click', () => goTo(current - 1));
  document.getElementById('sliderNext').addEventListener('click', () => goTo(current + 1));
  let timer = setInterval(() => goTo(current + 1), 4000);
  document.getElementById('bannerSlider').addEventListener('mouseenter', () => clearInterval(timer));
  document.getElementById('bannerSlider').addEventListener('mouseleave', () => { timer = setInterval(() => goTo(current + 1), 4000); });
})();

// ===== COUNT-UP =====
function countUp(el, target, duration, isFloat) {
  let start = 0;
  const step = target / (duration / 16);
  const timer = setInterval(() => {
    start += step;
    if (start >= target) { start = target; clearInterval(timer); }
    el.textContent = isFloat ? start.toFixed(1) : Math.floor(start).toLocaleString('id-ID') + (isFloat ? '' : '+');
  }, 16);
}
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const el = document.getElementById('stat-member');
      const rating = document.getElementById('stat-rating');
      if (el) countUp(el, 284750, 2000, false);
      if (rating) countUp(rating, 4.9, 1500, true);
      observer.disconnect();
    }
  });
}, { threshold: 0.3 });
const statsSection = document.getElementById('statistik');
if (statsSection) observer.observe(statsSection);

// ===== FAQ =====
function toggleFaq(i) {
  const item = document.getElementById('faqItem' + i);
  if (!item) return;
  const wasOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('open'));
  if (!wasOpen) item.classList.add('open');
}

// ===== POPUP =====
function closePopup() {
  const p = document.getElementById('lpPopup');
  if (p) { p.style.animation = 'fadeOut .3s ease forwards'; setTimeout(() => p.remove(), 300); }
  sessionStorage.setItem('nox_popup_seen', '1');
}
window.addEventListener('load', () => {
  if (!sessionStorage.getItem('nox_popup_seen')) {
    setTimeout(() => {
      const p = document.getElementById('lpPopup');
      if (p) p.style.display = 'flex';
    }, 1500);
  }
});
document.getElementById('lpPopup')?.addEventListener('click', function(e) {
  if (e.target === this) closePopup();
});

// ===== AUTO-HIDE FLASH =====
setTimeout(() => { const f = document.getElementById('lpFlash'); if (f) f.remove(); }, 5000);
</script>
</body>
</html>
