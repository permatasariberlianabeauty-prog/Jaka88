<?php
/**
 * NOXARA - Halaman Referral & Rabat
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/referral.php';

requireLogin();
$user = getCurrentUser();

$stats     = getReferralStats((int)$user['id']);
$downlines = getUserDownlines((int)$user['id']);

// Filter level & type
$filterLevel = (int)($_GET['level'] ?? 0);
$filterType  = clean($_GET['type'] ?? 'all');
$page        = max(1, (int)($_GET['page'] ?? 1));
$commissions = getCommissionHistory((int)$user['id'], $filterType, $page);

$referralLink = rtrim(getSetting('site_url', ''), '/') . '/register?ref=' . htmlspecialchars($user['referral_code'] ?? '');

// Commission settings
$stmtCS = db()->prepare("SELECT * FROM commission_settings ORDER BY type ASC, level ASC");
$stmtCS->execute();
$commSettings = $stmtCS->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCS->close();

// Build tree
$tree = buildReferralTree((int)$user['id'], 3);

// Filter downlines per level jika ada
$filteredDownlines = $filterLevel > 0
    ? array_filter($downlines, fn($d) => (int)$d['level'] === $filterLevel)
    : $downlines;

$pageTitle = 'Referral & Rabat';
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
/* ── Referral Page ── */
.nox-page-header{margin-bottom:24px}.nox-page-header h1{font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px}.nox-breadcrumb{font-size:12px;color:var(--text-secondary);margin-bottom:8px}.nox-breadcrumb a{color:var(--cyan);text-decoration:none}.nox-breadcrumb span{margin:0 6px;opacity:.4}

/* Link Referral */
.nox-ref-link-card{background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(123,47,255,0.08));border:1px solid rgba(0,212,255,0.3);border-radius:var(--radius-lg);padding:24px;margin-bottom:24px}
.nox-ref-link-card h3{font-size:14px;font-weight:700;color:var(--cyan);margin:0 0 12px;text-transform:uppercase;letter-spacing:.05em}
.nox-ref-link-box{display:flex;align-items:center;gap:10px;background:rgba(0,0,0,0.3);border:1px solid rgba(0,212,255,0.2);border-radius:10px;padding:12px 14px;margin-bottom:12px}
.nox-ref-link-text{flex:1;font-size:13px;color:var(--text-primary);word-break:break-all;font-family:monospace}
.nox-ref-share-btns{display:flex;gap:8px;flex-wrap:wrap}

/* Stat Grid */
.nox-ref-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
@media(max-width:640px){.nox-ref-stat-grid{grid-template-columns:repeat(2,1fr)}}
.nox-ref-stat-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px;text-align:center;transition:var(--transition)}
.nox-ref-stat-card:hover{transform:translateY(-2px)}
.nox-ref-stat-card__icon{font-size:22px;margin-bottom:8px}
.nox-ref-stat-card__val{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;margin-bottom:4px}
.nox-ref-stat-card__lbl{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.04em}

/* Commission Table */
.nox-comm-table-wrap{overflow-x:auto;border-radius:10px;border:1px solid var(--border-light);margin-bottom:16px}
.nox-comm-table-wrap table{width:100%;border-collapse:collapse;font-size:13px}
.nox-comm-table-wrap th{background:rgba(255,255,255,0.03);padding:10px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);white-space:nowrap}
.nox-comm-table-wrap td{padding:12px 14px;border-top:1px solid rgba(255,255,255,0.04);vertical-align:middle}

/* Tree View */
.nox-tree{overflow-x:auto;padding:20px 0}
.nox-tree-root{display:flex;flex-direction:column;align-items:center;gap:0}
.nox-tree-node{display:flex;flex-direction:column;align-items:center}
.nox-tree-badge{position:relative;background:var(--bg-card);border:2px solid var(--border-light);border-radius:12px;padding:10px 14px;text-align:center;min-width:90px;cursor:pointer;transition:var(--transition)}
.nox-tree-badge:hover{border-color:var(--cyan);box-shadow:0 4px 20px rgba(0,212,255,0.2)}
.nox-tree-badge.active-node{border-color:var(--cyan);background:rgba(0,212,255,0.07)}
.nox-tree-badge.inactive-node{border-color:rgba(255,68,68,0.3);opacity:.7}
.nox-tree-badge__name{font-size:12px;font-weight:600;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:80px}
.nox-tree-badge__status{font-size:10px;margin-top:2px}
.nox-tree-children{display:flex;gap:16px;margin-top:0;position:relative;padding-top:24px}
.nox-tree-children::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:1px;height:24px;background:var(--border-light)}
.nox-tree-children>div::before{content:'';position:absolute;top:-24px;left:50%;transform:translateX(-50%);width:1px;height:24px;background:var(--border-light)}
.nox-tree-line{width:100%;height:1px;background:var(--border-light);position:absolute;top:0}
.nox-tree-connector{position:relative;display:flex;flex-direction:column;align-items:center}
.nox-tree-connector::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:1px;height:24px;background:var(--border-light)}

/* Tooltip */
.nox-tree-tooltip{position:absolute;bottom:calc(100% + 10px);left:50%;transform:translateX(-50%);background:rgba(15,22,41,0.97);border:1px solid rgba(0,212,255,0.3);border-radius:10px;padding:10px 14px;min-width:160px;z-index:100;font-size:12px;display:none;pointer-events:none}
.nox-tree-badge:hover .nox-tree-tooltip{display:block}
.nox-tree-badge{position:relative}

/* Tab */
.nox-tab-bar{display:flex;gap:8px;margin-bottom:16px;overflow-x:auto;padding-bottom:4px}
.nox-tab-bar::-webkit-scrollbar{height:0}
.nox-tab{padding:7px 18px;border-radius:99px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-light);background:var(--bg-card);color:var(--text-secondary);white-space:nowrap;transition:var(--transition)}
.nox-tab.active,.nox-tab:hover{background:var(--cyan);color:#000;border-color:var(--cyan)}

/* Badge Level */
.lvl-badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700}
.lvl-1{background:rgba(0,212,255,0.15);color:var(--cyan)}
.lvl-2{background:rgba(123,47,255,0.15);color:var(--purple)}
.lvl-3{background:rgba(255,179,0,0.15);color:var(--amber)}

/* Table */
.nox-table-wrap{overflow-x:auto;border-radius:var(--radius-lg);border:1px solid var(--border-light)}
.nox-table{width:100%;border-collapse:collapse;font-size:13px}
.nox-table th{background:rgba(255,255,255,0.04);padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);white-space:nowrap}
.nox-table td{padding:12px 16px;border-top:1px solid rgba(255,255,255,0.04);vertical-align:middle}
.nox-table tr:hover td{background:rgba(255,255,255,0.02)}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">
<?= renderFlash() ?>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="nox-page-header">
  <div class="nox-breadcrumb"><a href="<?= BASE_URL ?>/dashboard">Dashboard</a><span>›</span>Referral</div>
  <h1>👥 Referral & Rabat</h1>
  <p style="color:var(--text-secondary);font-size:14px">Ajak teman bergabung dan dapatkan rabat otomatis setiap kali mereka bertransaksi.</p>
</div>


<!-- ── Link Referral Card ───────────────────────────────────── -->
<div class="nox-ref-link-card">
  <h3>🔗 Link Referral Kamu</h3>
  <div class="nox-ref-link-box">
    <div class="nox-ref-link-text" id="refLinkText"><?= htmlspecialchars($referralLink) ?></div>
    <button class="nox-btn nox-btn--sm nox-btn--outline" onclick="copyRefLink()" id="btnCopy">📋 Copy</button>
  </div>
  <div class="nox-ref-share-btns">
    <a href="https://wa.me/?text=<?= urlencode('Yuk daftar NOXARA mining, pakai link referralku: ' . $referralLink) ?>" target="_blank" rel="noopener" class="nox-btn nox-btn--sm" style="background:rgba(37,211,102,0.15);color:#25D366;border:1px solid rgba(37,211,102,0.3)">
      📱 Share WA
    </a>
    <a href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=<?= urlencode('Daftar NOXARA Mining, pakai referral code saya!') ?>" target="_blank" rel="noopener" class="nox-btn nox-btn--sm" style="background:rgba(0,136,204,0.15);color:#0088cc;border:1px solid rgba(0,136,204,0.3)">
      📲 Share TG
    </a>
    <span style="font-size:12px;color:var(--text-secondary);align-self:center;margin-left:6px">
      Kode: <strong style="color:var(--cyan)"><?= htmlspecialchars($user['referral_code'] ?? '-') ?></strong>
    </span>
  </div>
</div>

<!-- ── Statistik Referral ──────────────────────────────────── -->
<div class="nox-ref-stat-grid">
  <div class="nox-ref-stat-card" style="border-color:rgba(0,212,255,0.2)">
    <div class="nox-ref-stat-card__icon">👥</div>
    <div class="nox-ref-stat-card__val" style="color:var(--cyan)"><?= (int)$stats['total_downlines'] ?></div>
    <div class="nox-ref-stat-card__lbl">Total Downline</div>
  </div>
  <div class="nox-ref-stat-card" style="border-color:rgba(0,230,118,0.2)">
    <div class="nox-ref-stat-card__icon">✅</div>
    <div class="nox-ref-stat-card__val" style="color:var(--green)"><?= (int)($stats['by_level'][1]['active'] ?? 0) ?></div>
    <div class="nox-ref-stat-card__lbl">Aktif Level 1</div>
  </div>
  <div class="nox-ref-stat-card" style="border-color:rgba(123,47,255,0.2)">
    <div class="nox-ref-stat-card__icon">✅</div>
    <div class="nox-ref-stat-card__val" style="color:var(--purple)"><?= (int)($stats['by_level'][2]['active'] ?? 0) ?></div>
    <div class="nox-ref-stat-card__lbl">Aktif Level 2</div>
  </div>
  <div class="nox-ref-stat-card" style="border-color:rgba(255,179,0,0.2)">
    <div class="nox-ref-stat-card__icon">✅</div>
    <div class="nox-ref-stat-card__val" style="color:var(--amber)"><?= (int)($stats['by_level'][3]['active'] ?? 0) ?></div>
    <div class="nox-ref-stat-card__lbl">Aktif Level 3</div>
  </div>
  <div class="nox-ref-stat-card" style="border-color:rgba(0,230,118,0.2)">
    <div class="nox-ref-stat-card__icon">💰</div>
    <div class="nox-ref-stat-card__val" style="color:var(--green);font-size:16px"><?= formatRupiah($stats['today_commission'], false) ?></div>
    <div class="nox-ref-stat-card__lbl">Rabat Hari Ini</div>
  </div>
  <div class="nox-ref-stat-card" style="border-color:rgba(0,212,255,0.2)">
    <div class="nox-ref-stat-card__icon">🏆</div>
    <div class="nox-ref-stat-card__val" style="color:var(--cyan);font-size:16px"><?= formatRupiah($stats['total_commission'], false) ?></div>
    <div class="nox-ref-stat-card__lbl">Total Rabat</div>
  </div>
</div>

<!-- ── Tabel % Rabat ───────────────────────────────────────── -->
<div class="nox-card" style="padding:20px;margin-bottom:24px">
  <h3 style="font-size:15px;font-weight:700;margin:0 0 14px">📊 Transparansi Persentase Rabat</h3>
  <div class="nox-comm-table-wrap">
    <table>
      <thead>
        <tr>
          <th>Jenis Rabat</th>
          <th><span class="lvl-badge lvl-1">L1</span></th>
          <th><span class="lvl-badge lvl-2">L2</span></th>
          <th><span class="lvl-badge lvl-3">L3</span></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $commByType = [];
        foreach ($commSettings as $cs) {
            $commByType[$cs['type']][(int)$cs['level']] = $cs['percent'];
        }
        $typeLabels = ['deposit' => '🏦 Rabat Deposit', 'transaction' => '🛒 Rabat Transaksi'];
        foreach ($typeLabels as $type => $label): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($label) ?></td>
          <?php for ($l = 1; $l <= 3; $l++): ?>
            <td style="font-weight:700;color:<?= $l===1?'var(--cyan)':($l===2?'var(--purple)':'var(--amber)') ?>">
              <?= isset($commByType[$type][$l]) ? $commByType[$type][$l] . '%' : '-' ?>
            </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- ── Pohon Downline Visual ───────────────────────────────── -->
<div class="nox-card" style="padding:20px;margin-bottom:24px;overflow:hidden">
  <h3 style="font-size:15px;font-weight:700;margin:0 0 16px">🌳 Pohon Referral</h3>
  <div class="nox-tree" id="referralTree">
    <div style="display:flex;flex-direction:column;align-items:center">
      <!-- Root: KAMU -->
      <div class="nox-tree-badge active-node" style="border-color:var(--cyan);background:rgba(0,212,255,0.1);min-width:100px">
        <div style="font-size:20px">👑</div>
        <div class="nox-tree-badge__name" style="color:var(--cyan)"><?= htmlspecialchars(mb_substr($user['username'] ?? 'KAMU', 0, 8)) ?></div>
        <div class="nox-tree-badge__status" style="color:var(--cyan)">KAMU</div>
      </div>

      <!-- Level 1 Children -->
      <?php if (!empty($tree)): ?>
        <div style="width:1px;height:24px;background:var(--border-light)"></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;position:relative">
          <!-- Horizontal connector line -->
          <?php if (count($tree) > 1): ?>
          <div style="position:absolute;top:0;left:50%;transform:translateX(-50%);height:1px;background:var(--border-light)" id="hline1"></div>
          <?php endif; ?>
          <?php foreach ($tree as $l1Node): ?>
            <div style="display:flex;flex-direction:column;align-items:center">
              <div class="nox-tree-badge <?= $l1Node['status'] === 'active' ? 'active-node' : 'inactive-node' ?>">
                <div style="font-size:16px"><?= $l1Node['status'] === 'active' ? '✅' : '❌' ?></div>
                <div class="nox-tree-badge__name"><?= htmlspecialchars(mb_substr($l1Node['full_name'] ?? $l1Node['username'], 0, 9)) ?></div>
                <div class="nox-tree-badge__status" style="color:<?= $l1Node['status'] === 'active' ? 'var(--green)' : 'var(--red)' ?>;font-size:10px">L1 · VIP <?= (int)$l1Node['vip_level'] ?></div>
                <div class="nox-tree-tooltip">
                  <div style="font-weight:700;margin-bottom:4px"><?= htmlspecialchars($l1Node['full_name'] ?? $l1Node['username']) ?></div>
                  <div style="color:var(--text-secondary)">@<?= htmlspecialchars($l1Node['username']) ?></div>
                  <div style="margin-top:4px">VIP <?= (int)$l1Node['vip_level'] ?> · <?= $l1Node['status'] === 'active' ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--red)">Belum Deposit</span>' ?></div>
                  <div style="margin-top:2px;color:var(--text-secondary)">Dep: <?= formatRupiah((int)$l1Node['total_deposit_cumulative']) ?></div>
                </div>
              </div>
              <!-- L2 Children -->
              <?php if (!empty($l1Node['children'])): ?>
                <div style="width:1px;height:20px;background:var(--border-light)"></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
                  <?php foreach ($l1Node['children'] as $l2Node): ?>
                    <div style="display:flex;flex-direction:column;align-items:center">
                      <div class="nox-tree-badge <?= $l2Node['status'] === 'active' ? 'active-node' : 'inactive-node' ?>" style="min-width:74px;padding:7px 10px">
                        <div style="font-size:13px"><?= $l2Node['status'] === 'active' ? '✅' : '❌' ?></div>
                        <div class="nox-tree-badge__name" style="font-size:11px"><?= htmlspecialchars(mb_substr($l2Node['full_name'] ?? $l2Node['username'], 0, 8)) ?></div>
                        <div class="nox-tree-badge__status" style="color:var(--text-secondary);font-size:9px">L2</div>
                        <div class="nox-tree-tooltip">
                          <div style="font-weight:700;margin-bottom:4px"><?= htmlspecialchars($l2Node['full_name'] ?? $l2Node['username']) ?></div>
                          <div style="color:var(--text-secondary)">@<?= htmlspecialchars($l2Node['username']) ?></div>
                          <div style="margin-top:4px">VIP <?= (int)$l2Node['vip_level'] ?> · <?= $l2Node['status'] === 'active' ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--red)">Belum Deposit</span>' ?></div>
                        </div>
                      </div>
                      <!-- L3 Children -->
                      <?php if (!empty($l2Node['children'])): ?>
                        <div style="width:1px;height:16px;background:var(--border-light)"></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center">
                          <?php foreach (array_slice($l2Node['children'], 0, 3) as $l3Node): ?>
                            <div class="nox-tree-badge <?= $l3Node['status'] === 'active' ? 'active-node' : 'inactive-node' ?>" style="min-width:60px;padding:6px 8px">
                              <div style="font-size:11px"><?= $l3Node['status'] === 'active' ? '✅' : '❌' ?></div>
                              <div class="nox-tree-badge__name" style="font-size:10px"><?= htmlspecialchars(mb_substr($l3Node['full_name'] ?? $l3Node['username'], 0, 7)) ?></div>
                              <div class="nox-tree-badge__status" style="color:var(--text-secondary);font-size:9px">L3</div>
                              <div class="nox-tree-tooltip">
                                <div style="font-weight:700;margin-bottom:4px"><?= htmlspecialchars($l3Node['full_name'] ?? $l3Node['username']) ?></div>
                                <div style="margin-top:4px">VIP <?= (int)$l3Node['vip_level'] ?> · <?= $l3Node['status'] === 'active' ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--red)">Belum Deposit</span>' ?></div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                          <?php if (count($l2Node['children']) > 3): ?>
                            <div style="font-size:10px;color:var(--text-secondary);align-self:center">+<?= count($l2Node['children']) - 3 ?> lagi</div>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:var(--text-secondary);font-size:13px;margin-top:12px">
          <div style="font-size:32px;margin-bottom:8px">🌱</div>
          Belum ada downline. Bagikan link referral kamu!
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- ── Tab: Daftar Downline ────────────────────────────────── -->
<div class="nox-card" style="padding:20px;margin-bottom:24px">
  <h3 style="font-size:15px;font-weight:700;margin:0 0 14px">👥 Daftar Downline</h3>
  <div class="nox-tab-bar" id="downlineTabs">
    <?php
    $lvlTabs = [0 => 'Semua', 1 => 'Level 1', 2 => 'Level 2', 3 => 'Level 3'];
    foreach ($lvlTabs as $lv => $label): ?>
      <button class="nox-tab <?= $filterLevel === $lv ? 'active' : '' ?>" data-lvl="<?= $lv ?>" onclick="filterDownlines(<?= $lv ?>)"><?= htmlspecialchars($label) ?></button>
    <?php endforeach; ?>
  </div>

  <div id="downlineTableWrap">
    <?php if (empty($filteredDownlines)): ?>
      <div style="text-align:center;padding:28px;color:var(--text-secondary);font-size:13px">Tidak ada downline<?= $filterLevel > 0 ? ' di level ' . $filterLevel : '' ?>.</div>
    <?php else: ?>
      <div class="nox-table-wrap">
        <table class="nox-table">
          <thead>
            <tr>
              <th>Level</th>
              <th>Nama</th>
              <th>Username</th>
              <th>VIP</th>
              <th>Status</th>
              <th>Total Deposit</th>
              <th>Tgl Gabung</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filteredDownlines as $dl): ?>
            <tr style="<?= $dl['status'] === 'active' ? 'background:rgba(0,230,118,0.02)' : '' ?>">
              <td><span class="lvl-badge lvl-<?= (int)$dl['level'] ?>">L<?= (int)$dl['level'] ?></span></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:32px;height:32px;border-radius:50%;background:rgba(0,212,255,0.1);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">
                    <?= !empty($dl['avatar']) ? '<img src="' . UPLOADS_URL . '/avatars/' . htmlspecialchars($dl['avatar']) . '" style="width:32px;height:32px;border-radius:50%;object-fit:cover">' : '👤' ?>
                  </div>
                  <span style="font-weight:600"><?= htmlspecialchars($dl['full_name'] ?? '-') ?></span>
                </div>
              </td>
              <td style="color:var(--text-secondary)">@<?= htmlspecialchars($dl['username']) ?></td>
              <td>
                <?php $vipColors = [0=>'#6B7A99',1=>'#00D4FF',2=>'#00E676',3=>'#7B2FFF',4=>'#FFB300',5=>'#FF6B6B']; $vc = $vipColors[(int)$dl['vip_level']] ?? '#6B7A99'; ?>
                <span style="background:<?= $vc ?>22;color:<?= $vc ?>;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700">VIP <?= (int)$dl['vip_level'] ?></span>
              </td>
              <td>
                <?php if ($dl['status'] === 'active'): ?>
                  <span style="background:rgba(0,230,118,0.1);color:var(--green);padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700">✅ Aktif</span>
                <?php else: ?>
                  <span style="background:rgba(107,122,153,0.1);color:#6B7A99;padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700">❌ Belum Deposit</span>
                <?php endif; ?>
              </td>
              <td style="font-family:'Space Grotesk',sans-serif;font-weight:600"><?= formatRupiah((int)$dl['total_deposit_cumulative']) ?></td>
              <td style="color:var(--text-secondary)"><?= formatDate($dl['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Tab: Riwayat Rabat ──────────────────────────────────── -->
<div class="nox-card" style="padding:20px;margin-bottom:32px">
  <h3 style="font-size:15px;font-weight:700;margin:0 0 14px">💰 Riwayat Rabat</h3>
  <div class="nox-tab-bar" id="commTabs">
    <?php
    $typeTabs = ['all' => 'Semua', 'deposit' => 'Rabat Deposit', 'transaction' => 'Rabat Transaksi'];
    foreach ($typeTabs as $t => $tLabel): ?>
      <a class="nox-tab <?= $filterType === $t ? 'active' : '' ?>" href="?type=<?= htmlspecialchars($t) ?>&page=1"><?= htmlspecialchars($tLabel) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($commissions['data'])): ?>
    <div style="text-align:center;padding:28px;color:var(--text-secondary);font-size:13px">
      <div style="font-size:32px;margin-bottom:8px">📭</div>
      Belum ada riwayat rabat<?= $filterType !== 'all' ? ' ' . $typeTabs[$filterType] : '' ?>.
    </div>
  <?php else: ?>
    <div class="nox-table-wrap">
      <table class="nox-table">
        <thead>
          <tr>
            <th>Level</th>
            <th>Dari Member</th>
            <th>Nominal Transaksi</th>
            <th>% Rabat</th>
            <th>Rabat Diterima</th>
            <th>Tanggal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($commissions['data'] as $cm): ?>
          <tr>
            <td><span class="lvl-badge lvl-<?= (int)$cm['level'] ?>">L<?= (int)$cm['level'] ?></span></td>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($cm['full_name'] ?? $cm['username']) ?></div>
              <div style="font-size:11px;color:var(--text-secondary)">@<?= htmlspecialchars($cm['username']) ?> · <?= $cm['type'] === 'deposit' ? '🏦 Deposit' : '🛒 Transaksi' ?></div>
            </td>
            <td style="font-family:'Space Grotesk',sans-serif"><?= formatRupiah((int)$cm['source_amount']) ?></td>
            <td style="color:var(--cyan);font-weight:700"><?= number_format((float)$cm['commission_percent'], 2) ?>%</td>
            <td style="color:var(--green);font-weight:700;font-family:'Space Grotesk',sans-serif">+<?= formatRupiah((int)$cm['commission_amount']) ?></td>
            <td style="color:var(--text-secondary)"><?= formatDateTime($cm['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php echo renderPagination(paginate($commissions['total'], defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10, $page, BASE_URL . '/referral?type=' . $filterType)); ?>
  <?php endif; ?>
</div>
</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
/* ── Referral Page JS ─────────────────────────────────────── */
function copyRefLink() {
  const txt = document.getElementById('refLinkText')?.textContent;
  if (!txt) return;
  navigator.clipboard.writeText(txt).then(() => {
    const btn = document.getElementById('btnCopy');
    if (btn) { btn.textContent = '✅ Tersalin!'; setTimeout(() => { btn.textContent = '📋 Copy'; }, 2000); }
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    const btn = document.getElementById('btnCopy');
    if (btn) { btn.textContent = '✅ Tersalin!'; setTimeout(() => { btn.textContent = '📋 Copy'; }, 2000); }
  });
}

function filterDownlines(level) {
  document.querySelectorAll('#downlineTabs .nox-tab').forEach(t => {
    t.classList.toggle('active', parseInt(t.dataset.lvl) === level);
  });
  document.querySelectorAll('[data-dl-level]').forEach(row => {
    row.style.display = (level === 0 || parseInt(row.dataset.dlLevel) === level) ? '' : 'none';
  });
}
</script>
</body>
</html>
