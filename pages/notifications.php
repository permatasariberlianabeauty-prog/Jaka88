<?php
/**
 * NOXARA - Notifikasi
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Handle POST actions ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>false]); exit; }
        redirect(BASE_URL . '/pages/notifications.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        $stmt = db()->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $nid, $userId);
        $stmt->execute();
        $stmt->close();
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
    }
    if ($action === 'mark_all_read') {
        $stmt = db()->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'delete') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        $stmt = db()->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $nid, $userId);
        $stmt->execute();
        $stmt->close();
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
    }
    redirect(BASE_URL . '/pages/notifications.php');
}

// ── Filter ───────────────────────────────────────────────
$filterRead = in_array($_GET['filter'] ?? 'all', ['all','unread']) ? ($_GET['filter'] ?? 'all') : 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$cond  = "WHERE user_id = ?";
$types = ['i'];
$vals  = [$userId];
if ($filterRead === 'unread') { $cond .= " AND is_read=0"; }

// Count
$stmtC = db()->prepare("SELECT COUNT(*) as c FROM notifications $cond");
$stmtC->bind_param(implode('',$types), ...$vals);
$stmtC->execute();
$total = (int)$stmtC->get_result()->fetch_assoc()['c'];
$stmtC->close();

$totalPages = max(1,(int)ceil($total/$perPage));
$offset = ($page-1)*$perPage;

// Fetch
$stmtN = db()->prepare("SELECT * FROM notifications $cond ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmtN->bind_param(implode('',$types).'ii', ...[...$vals, $perPage, $offset]);
$stmtN->execute();
$notifications = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtN->close();

// Count unread
$stmtU = db()->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0");
$stmtU->bind_param('i', $userId);
$stmtU->execute();
$unreadCount = (int)$stmtU->get_result()->fetch_assoc()['c'];
$stmtU->close();

$pageTitle = 'Notifikasi';

// Icon & color per tipe
$typeConfig = [
    'deposit'     => ['💳','var(--green)'],
    'withdraw'    => ['💸','var(--red)'],
    'profit'      => ['💎','var(--cyan)'],
    'referral'    => ['👥','var(--purple)'],
    'mission'     => ['🎯','var(--amber)'],
    'system'      => ['⚙️','var(--text-secondary)'],
    'promo'       => ['🎁','#FF6B6B'],
    'security'    => ['🔐','var(--amber)'],
    'vip'         => ['👑','#FFB300'],
    'info'        => ['ℹ️','var(--cyan)'],
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
.notif-item{display:flex;align-items:flex-start;gap:14px;padding:16px;border-bottom:1px solid rgba(30,42,69,.4);cursor:pointer;transition:.2s;position:relative}
.notif-item:last-child{border-bottom:none}
.notif-item:hover{background:rgba(0,212,255,.03)}
.notif-item.unread{background:rgba(0,212,255,.04)}
.notif-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.notif-dot{width:8px;height:8px;border-radius:50%;background:var(--cyan);position:absolute;top:20px;right:16px;flex-shrink:0}
.notif-title{font-size:14px;font-weight:700;margin-bottom:3px}
.notif-msg{font-size:12px;color:var(--text-secondary);margin-bottom:4px;line-height:1.5}
.notif-time{font-size:11px;color:var(--text-disabled)}
.notif-actions{display:none;gap:6px;margin-top:6px}
.notif-item:hover .notif-actions{display:flex}
.filter-tabs{display:flex;gap:4px;margin-bottom:20px}
.filter-tab{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;color:var(--text-secondary);border:1px solid var(--border-light);background:var(--bg-card);transition:.2s}
.filter-tab.active{background:var(--cyan);color:#000;border-color:var(--cyan)}
.nox-pagination{display:flex;gap:6px;justify-content:center;margin-top:24px;flex-wrap:wrap}
.nox-page-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:var(--bg-card);border:1px solid var(--border-light);color:var(--text-primary);text-decoration:none;font-size:13px;font-weight:600;transition:.2s}
.nox-page-btn:hover,.nox-page-btn.active{background:var(--cyan);color:#000;border-color:var(--cyan)}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<!-- HEADER -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">
      🔔 Notifikasi
      <?php if ($unreadCount > 0): ?>
        <span style="font-size:14px;background:var(--cyan);color:#000;border-radius:99px;padding:2px 10px;margin-left:6px"><?= $unreadCount ?></span>
      <?php endif; ?>
    </h1>
    <p style="color:var(--text-secondary);font-size:14px;margin:0">Semua aktivitas dan informasi akun</p>
  </div>
  <?php if ($unreadCount > 0): ?>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="mark_all_read">
    <button type="submit" class="nox-btn nox-btn--outline nox-btn--sm">✅ Tandai Semua Dibaca</button>
  </form>
  <?php endif; ?>
</div>

<!-- FILTER TABS -->
<div class="filter-tabs">
  <a href="?filter=all"    class="filter-tab <?= $filterRead==='all'?'active':'' ?>">Semua (<?= $total ?>)</a>
  <a href="?filter=unread" class="filter-tab <?= $filterRead==='unread'?'active':'' ?>">Belum Dibaca (<?= $unreadCount ?>)</a>
</div>

<!-- NOTIF LIST -->
<div class="nox-card" style="padding:0;overflow:hidden">
  <?php if (empty($notifications)): ?>
    <div style="text-align:center;padding:56px;color:var(--text-secondary)">
      <div style="font-size:56px;margin-bottom:16px">🔕</div>
      <div style="font-weight:700;font-size:16px;margin-bottom:6px">Tidak ada notifikasi</div>
      <div style="font-size:13px">Semua notifikasi akan muncul di sini</div>
    </div>
  <?php else: ?>
    <?php foreach ($notifications as $n):
      $type   = $n['type'] ?? 'info';
      $cfg    = $typeConfig[$type] ?? ['🔔','var(--cyan)'];
      $isRead = (bool)$n['is_read'];
    ?>
    <div class="notif-item <?= !$isRead?'unread':'' ?>" id="notif-<?= (int)$n['id'] ?>" onclick="markRead(<?= (int)$n['id'] ?>)">
      <?php if (!$isRead): ?><div class="notif-dot"></div><?php endif; ?>
      <div class="notif-icon" style="background:<?= $cfg[1] ?>22"><?= $cfg[0] ?></div>
      <div style="flex:1;min-width:0">
        <div class="notif-title" style="color:<?= !$isRead?'var(--text-primary)':'var(--text-secondary)' ?>"><?= htmlspecialchars($n['title'] ?? 'Notifikasi') ?></div>
        <div class="notif-msg"><?= htmlspecialchars($n['message'] ?? '') ?></div>
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
          <div class="notif-actions">
            <?php if (!$isRead): ?>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notif_id" value="<?= (int)$n['id'] ?>">
              <button type="submit" style="font-size:11px;background:rgba(0,212,255,.1);color:var(--cyan);border:none;border-radius:6px;padding:3px 8px;cursor:pointer">Baca</button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus notifikasi ini?')">
              <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="notif_id" value="<?= (int)$n['id'] ?>">
              <button type="submit" style="font-size:11px;background:rgba(255,68,68,.1);color:var(--red);border:none;border-radius:6px;padding:3px 8px;cursor:pointer">Hapus</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="nox-pagination">
  <?php if ($page > 1): ?><a href="?filter=<?= $filterRead ?>&page=<?= $page-1 ?>" class="nox-page-btn">‹</a><?php endif; ?>
  <?php for ($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
    <a href="?filter=<?= $filterRead ?>&page=<?= $i ?>" class="nox-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?><a href="?filter=<?= $filterRead ?>&page=<?= $page+1 ?>" class="nox-page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function markRead(id) {
  const el = document.getElementById('notif-'+id);
  if (!el || !el.classList.contains('unread')) return;
  const fd = new FormData();
  fd.append('action','mark_read'); fd.append('notif_id',id);
  fd.append('csrf_token','<?= generateCsrfToken() ?>');
  fd.append('ajax','1');
  fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ el.classList.remove('unread'); const dot=el.querySelector('.notif-dot'); if(dot)dot.remove(); }
  });
}
</script>
</body></html>
