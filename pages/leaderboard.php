<?php
/**
 * NOXARA - Leaderboard
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

$activeTab = in_array($_GET['tab'] ?? 'deposit', ['deposit','referral','profit']) ? ($_GET['tab'] ?? 'deposit') : 'deposit';

// ── Query leaderboard ────────────────────────────────────
function getLeaderboard(string $type): array {
    $db = db();
    if ($type === 'deposit') {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.avatar, u.vip_level,
                   SUM(d.amount) as score
            FROM deposits d
            JOIN users u ON u.id = d.user_id
            WHERE d.status='approved' AND MONTH(d.created_at)=MONTH(CURDATE()) AND YEAR(d.created_at)=YEAR(CURDATE())
            GROUP BY u.id ORDER BY score DESC LIMIT 10
        ");
    } elseif ($type === 'referral') {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.avatar, u.vip_level,
                   COUNT(r.id) as score
            FROM referrals r
            JOIN users u ON u.id = r.referrer_id
            WHERE MONTH(r.created_at)=MONTH(CURDATE()) AND YEAR(r.created_at)=YEAR(CURDATE())
            GROUP BY u.id ORDER BY score DESC LIMIT 10
        ");
    } else {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.avatar, u.vip_level,
                   SUM(t.amount) as score
            FROM transactions t
            JOIN users u ON u.id = t.user_id
            WHERE t.type='profit' AND MONTH(t.created_at)=MONTH(CURDATE()) AND YEAR(t.created_at)=YEAR(CURDATE())
            GROUP BY u.id ORDER BY score DESC LIMIT 10
        ");
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ── Posisi user sendiri ──────────────────────────────────
function getUserRank(string $type, int $userId): array {
    $db = db();
    if ($type === 'deposit') {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as score FROM deposits WHERE user_id=? AND status='approved' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
    } elseif ($type === 'referral') {
        $stmt = $db->prepare("SELECT COUNT(*) as score FROM referrals WHERE referrer_id=? AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
    } else {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as score FROM transactions WHERE user_id=? AND type='profit' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $score = (int)$stmt->get_result()->fetch_assoc()['score'];
    $stmt->close();
    // Hitung rank
    if ($type === 'deposit') {
        $stmt2 = $db->prepare("SELECT COUNT(DISTINCT user_id)+1 as rank FROM deposits WHERE status='approved' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) GROUP BY user_id HAVING SUM(amount)>?");
    } elseif ($type === 'referral') {
        $stmt2 = $db->prepare("SELECT COUNT(DISTINCT referrer_id)+1 as rank FROM referrals WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) GROUP BY referrer_id HAVING COUNT(*)>?");
    } else {
        $stmt2 = $db->prepare("SELECT COUNT(DISTINCT user_id)+1 as rank FROM transactions WHERE type='profit' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) GROUP BY user_id HAVING SUM(amount)>?");
    }
    $stmt2->bind_param('i', $score);
    $stmt2->execute();
    $rankRow = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    $rank = $rankRow ? (int)$rankRow['rank'] : 1;
    return ['rank'=>$rank, 'score'=>$score];
}

$leaderboard = getLeaderboard($activeTab);
$userRank    = getUserRank($activeTab, $userId);

$tabLabels = ['deposit'=>'💳 Top Deposit','referral'=>'👥 Top Referral','profit'=>'💎 Top Profit'];
$scoreLabel = ['deposit'=>'Total Deposit','referral'=>'Jumlah Referral','profit'=>'Total Profit'];
$isMoney = ($activeTab !== 'referral');

$pageTitle = 'Leaderboard';
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
.lb-tabs{display:flex;gap:4px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:4px;margin-bottom:24px}
.lb-tab{flex:1;padding:10px;text-align:center;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;color:var(--text-secondary);transition:.2s}
.lb-tab.active{background:var(--cyan);color:#000}
.podium{display:flex;align-items:flex-end;justify-content:center;gap:16px;margin-bottom:32px;padding:24px 0}
.podium-item{text-align:center;display:flex;flex-direction:column;align-items:center}
.podium-item--1{order:2}.podium-item--2{order:1}.podium-item--3{order:3}
.podium-avatar{width:64px;height:64px;border-radius:50%;background:var(--bg-input,#151d30);border:3px solid;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:8px;position:relative}
.podium-item--1 .podium-avatar{width:80px;height:80px;border-color:#FFB300}
.podium-item--2 .podium-avatar{border-color:#9E9E9E}
.podium-item--3 .podium-avatar{border-color:#CD7F32}
.podium-medal{position:absolute;top:-10px;right:-5px;font-size:20px}
.podium-name{font-size:13px;font-weight:700;max-width:80px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.podium-score{font-size:11px;font-weight:700;color:var(--cyan)}
.podium-block{border-radius:8px 8px 0 0;width:70px;margin-top:10px}
.podium-item--1 .podium-block{height:80px;background:linear-gradient(180deg,rgba(255,179,0,.3),rgba(255,179,0,.1))}
.podium-item--2 .podium-block{height:55px;background:linear-gradient(180deg,rgba(158,158,158,.3),rgba(158,158,158,.1))}
.podium-item--3 .podium-block{height:40px;background:linear-gradient(180deg,rgba(205,127,50,.3),rgba(205,127,50,.1))}
.lb-row{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid rgba(30,42,69,.4)}
.lb-row:last-child{border-bottom:none}
.lb-rank-badge{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;background:rgba(107,122,153,.15)}
.lb-avatar{width:40px;height:40px;border-radius:50%;background:var(--bg-input,#151d30);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.lb-name{flex:1;font-weight:600;font-size:13px}
.lb-score{font-family:'Space Grotesk',sans-serif;font-weight:700;color:var(--cyan)}
.user-rank-card{background:linear-gradient(135deg,rgba(0,212,255,.08),rgba(123,47,255,.08));border:1px solid rgba(0,212,255,.3);border-radius:14px;padding:20px;text-align:center;margin-top:24px}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter">

<div style="margin-bottom:24px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">🏆 Leaderboard</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Top performer bulan <?= date('F Y') ?></p>
</div>

<!-- TABS -->
<div class="lb-tabs">
  <?php foreach ($tabLabels as $k=>$v): ?>
    <a href="?tab=<?= $k ?>" class="lb-tab <?= $activeTab===$k?'active':'' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($leaderboard)): ?>
<div style="text-align:center;padding:48px;color:var(--text-secondary)">
  <div style="font-size:48px;margin-bottom:12px">🏆</div>
  <div style="font-weight:600">Belum ada data leaderboard bulan ini</div>
</div>
<?php else: ?>

<!-- PODIUM TOP 3 -->
<div class="podium">
  <?php foreach ([0,1,2] as $idx):
    if (!isset($leaderboard[$idx])) continue;
    $p = $leaderboard[$idx];
    $pos = $idx + 1;
    $medals = ['🥇','🥈','🥉'];
    $score  = $isMoney ? formatRupiah((int)$p['score']) : number_format((int)$p['score']) . ' orang';
    $name   = maskName($p['full_name'] ?: $p['username']);
  ?>
  <div class="podium-item podium-item--<?= $pos ?>">
    <div class="podium-avatar">
      <?php if (!empty($p['avatar'])): ?>
        <img src="<?= UPLOADS_URL ?>/avatars/<?= htmlspecialchars($p['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      <?php else: ?>
        <?= strtoupper(substr($p['full_name']?:$p['username'],0,1)) ?>
      <?php endif; ?>
      <span class="podium-medal"><?= $medals[$idx] ?></span>
    </div>
    <div class="podium-name"><?= htmlspecialchars($name) ?></div>
    <div class="podium-score"><?= $score ?></div>
    <div class="podium-block"></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- RANK 4-10 -->
<?php if (count($leaderboard) > 3): ?>
<div class="nox-card" style="padding:0;overflow:hidden;margin-bottom:16px">
  <?php foreach (array_slice($leaderboard, 3) as $i => $p):
    $pos   = $i + 4;
    $score = $isMoney ? formatRupiah((int)$p['score']) : number_format((int)$p['score']);
    $name  = maskName($p['full_name'] ?: $p['username']);
    $isMe  = ((int)$p['id'] === $userId);
  ?>
  <div class="lb-row" style="<?= $isMe?'background:rgba(0,212,255,.05)':'' ?>">
    <div class="lb-rank-badge"><?= $pos ?></div>
    <div class="lb-avatar">
      <?php if (!empty($p['avatar'])): ?>
        <img src="<?= UPLOADS_URL ?>/avatars/<?= htmlspecialchars($p['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      <?php else: ?>
        <?= strtoupper(substr($p['full_name']?:$p['username'],0,1)) ?>
      <?php endif; ?>
    </div>
    <div class="lb-name">
      <?= htmlspecialchars($name) ?>
      <?php if ($isMe): ?><span style="font-size:10px;background:rgba(0,212,255,.15);color:var(--cyan);padding:2px 8px;border-radius:99px;margin-left:6px">KAMU</span><?php endif; ?>
    </div>
    <div class="lb-score"><?= $score ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- POSISI KAMU -->
<div class="user-rank-card">
  <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">Posisi Kamu Bulan Ini</div>
  <div style="font-family:'Orbitron',sans-serif;font-size:36px;font-weight:700;color:var(--cyan)">#<?= number_format($userRank['rank']) ?></div>
  <div style="font-size:14px;font-weight:700;margin-top:4px"><?= $isMoney ? formatRupiah($userRank['score']) : number_format($userRank['score']) . ' orang' ?></div>
  <div style="font-size:12px;color:var(--text-secondary);margin-top:4px"><?= $scoreLabel[$activeTab] ?></div>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body></html>
