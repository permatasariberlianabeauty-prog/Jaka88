<?php
/**
 * NOXARA - Live Chat CS
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$user['id'];

// ── Handle AJAX ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'send') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Token invalid']); exit; }
        $msg  = trim($_POST['message'] ?? '');
        $roomId = (int)($_POST['room_id'] ?? 0);
        if (empty($msg) && empty($_FILES['attachment']['name'])) { echo json_encode(['success'=>false,'message'=>'Pesan kosong']); exit; }
        if (mb_strlen($msg) > 1000) { echo json_encode(['success'=>false,'message'=>'Pesan terlalu panjang']); exit; }

        // Ambil/buat room
        if (!$roomId) {
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
            } else { $roomId = (int)$room['id']; }
        }

        // Upload attachment
        $attachFile = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../includes/functions.php';
            $attachFile = uploadImage($_FILES['attachment'], 'chat', 'chat_' . $userId);
        }

        $cleanMsg = clean($msg);
        $stmtI = db()->prepare("INSERT INTO chat_messages (room_id, sender_type, sender_id, message, attachment, created_at) VALUES (?,?,?,?,?,NOW())");
        $senderType = 'user';
        $stmtI->bind_param('isiss', $roomId, $senderType, $userId, $cleanMsg, $attachFile);
        $stmtI->execute();
        $msgId = (int)$stmtI->insert_id;
        $stmtI->close();

        // Update room
        $stmtU = db()->prepare("UPDATE chat_rooms SET last_message=?, unread_by_admin=unread_by_admin+1, updated_at=NOW() WHERE id=?");
        $stmtU->bind_param('si', $cleanMsg, $roomId);
        $stmtU->execute();
        $stmtU->close();

        echo json_encode(['success'=>true,'message_id'=>$msgId,'room_id'=>$roomId]);
        exit;
    }

    if ($action === 'poll') {
        $roomId  = (int)($_POST['room_id'] ?? 0);
        $lastId  = (int)($_POST['last_id'] ?? 0);
        if (!$roomId) { echo json_encode(['messages'=[]]); exit; }
        $stmtP = db()->prepare("SELECT cm.*, CASE WHEN cm.sender_type='user' THEN u.full_name ELSE 'CS NOXARA' END as sender_name FROM chat_messages cm LEFT JOIN users u ON u.id=cm.sender_id AND cm.sender_type='user' WHERE cm.room_id=? AND cm.id>? ORDER BY cm.id ASC LIMIT 30");
        $stmtP->bind_param('ii', $roomId, $lastId);
        $stmtP->execute();
        $msgs = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtP->close();
        echo json_encode(['messages'=>$msgs]);
        exit;
    }
    echo json_encode(['success'=>false]); exit;
}

// ── Ambil/buat room ──────────────────────────────────────
$stmtR = db()->prepare("SELECT * FROM chat_rooms WHERE user_id=? LIMIT 1");
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
    $room = ['id'=>$roomId,'status'=>'open','user_id'=>$userId];
} else {
    $roomId = (int)$room['id'];
}

// Mark unread_by_user = 0
$stmtMark = db()->prepare("UPDATE chat_rooms SET unread_by_user=0 WHERE id=?");
$stmtMark->bind_param('i', $roomId);
$stmtMark->execute();
$stmtMark->close();

// Riwayat 50 pesan
$stmtH = db()->prepare("SELECT cm.*, CASE WHEN cm.sender_type='user' THEN u.full_name ELSE 'CS NOXARA' END as sender_name FROM chat_messages cm LEFT JOIN users u ON u.id=cm.sender_id AND cm.sender_type='user' WHERE cm.room_id=? ORDER BY cm.id DESC LIMIT 50");
$stmtH->bind_param('i', $roomId);
$stmtH->execute();
$messages = array_reverse($stmtH->get_result()->fetch_all(MYSQLI_ASSOC));
$stmtH->close();

$lastMsgId  = !empty($messages) ? (int)end($messages)['id'] : 0;
$csStatus   = getSetting('cs_status', 'online'); // online/busy/offline
$csStatusColors = ['online'=>'var(--green)','busy'=>'var(--amber)','offline'=>'var(--red)'];
$csStatusLabels = ['online'=>'Online','busy'=>'Sibuk','offline'=>'Offline'];
$pageTitle = 'Live Chat CS';
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
.chat-wrap{display:flex;flex-direction:column;height:calc(100vh - 180px);min-height:400px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;overflow:hidden}
.chat-header{padding:14px 18px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:12px;flex-shrink:0}
.cs-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--cyan),var(--purple));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.cs-status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:4px}
.chat-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px}
.chat-messages::-webkit-scrollbar{width:4px}.chat-messages::-webkit-scrollbar-track{background:transparent}.chat-messages::-webkit-scrollbar-thumb{background:rgba(0,212,255,.2);border-radius:99px}
.msg-row{display:flex;gap:8px;max-width:75%}
.msg-row--user{align-self:flex-end;flex-direction:row-reverse}
.msg-row--admin{align-self:flex-start}
.msg-bubble{padding:10px 14px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word}
.msg-row--user .msg-bubble{background:linear-gradient(135deg,var(--cyan),#0099BB);color:#000;border-radius:14px 4px 14px 14px}
.msg-row--admin .msg-bubble{background:rgba(255,255,255,.07);color:var(--text-primary);border-radius:4px 14px 14px 14px}
.msg-time{font-size:10px;color:var(--text-disabled);margin-top:3px;text-align:right}
.msg-row--admin .msg-time{text-align:left}
.chat-input-area{padding:12px 16px;border-top:1px solid var(--border-light);display:flex;align-items:flex-end;gap:10px;flex-shrink:0}
.chat-textarea{flex:1;background:rgba(255,255,255,.05);border:1px solid var(--border-light);border-radius:12px;padding:10px 14px;color:var(--text-primary);font-size:13px;resize:none;outline:none;max-height:120px;min-height:42px;font-family:inherit}
.chat-textarea:focus{border-color:var(--cyan)}
.chat-send-btn{width:42px;height:42px;border-radius:12px;background:var(--cyan);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#000;font-size:18px;transition:.2s}
.chat-send-btn:hover{background:#00b8d9}
.attach-btn{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-secondary);font-size:18px;transition:.2s}
.attach-btn:hover{background:rgba(0,212,255,.1);color:var(--cyan)}
.msg-attachment{margin-top:6px;border-radius:8px;max-width:200px;cursor:pointer}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="nox-main">
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="nox-content nox-page-enter" style="padding-bottom:16px">

<div style="margin-bottom:16px">
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;margin:0 0 4px">💬 Live Chat CS</h1>
  <p style="color:var(--text-secondary);font-size:14px;margin:0">Chat langsung dengan tim customer service kami</p>
</div>

<div class="chat-wrap">
  <!-- CHAT HEADER -->
  <div class="chat-header">
    <div class="cs-avatar">🎧</div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:15px">CS NOXARA</div>
      <div style="font-size:12px;color:var(--text-secondary)">
        <span class="cs-status-dot" style="background:<?= $csStatusColors[$csStatus] ?? 'var(--green)' ?>"></span>
        <?= $csStatusLabels[$csStatus] ?? 'Online' ?>
        <?php if ($csStatus === 'online'): ?> · Siap membantu<?php endif; ?>
      </div>
    </div>
    <div style="font-size:11px;color:var(--text-secondary);text-align:right">
      <div>Jam Operasional</div>
      <div style="font-weight:600"><?= getSetting('cs_hours','08:00 - 22:00') ?></div>
    </div>
  </div>

  <!-- MESSAGES -->
  <div class="chat-messages" id="chatMessages">
    <?php if (empty($messages)): ?>
    <div style="text-align:center;padding:32px;color:var(--text-secondary)">
      <div style="font-size:48px;margin-bottom:12px">👋</div>
      <div style="font-weight:600;margin-bottom:4px">Halo! Ada yang bisa kami bantu?</div>
      <div style="font-size:13px">Tim CS kami siap membantu Anda 24/7</div>
    </div>
    <?php else: ?>
    <?php foreach ($messages as $m):
      $isUser = ($m['sender_type'] === 'user');
    ?>
    <div class="msg-row msg-row--<?= $isUser?'user':'admin' ?>" id="msg-<?= (int)$m['id'] ?>">
      <?php if (!$isUser): ?><div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--cyan),var(--purple));display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">🎧</div><?php endif; ?>
      <div>
        <?php if (!$isUser): ?><div style="font-size:11px;font-weight:700;color:var(--cyan);margin-bottom:3px">CS NOXARA</div><?php endif; ?>
        <div class="msg-bubble">
          <?= nl2br(htmlspecialchars($m['message'] ?? '')) ?>
          <?php if (!empty($m['attachment'])): ?>
            <br><img src="<?= UPLOADS_URL ?>/chat/<?= htmlspecialchars($m['attachment']) ?>" class="msg-attachment" onclick="window.open(this.src)">
          <?php endif; ?>
        </div>
        <div class="msg-time"><?= htmlspecialchars(date('H:i', strtotime($m['created_at']))) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- INPUT AREA -->
  <form class="chat-input-area" id="chatForm" onsubmit="return false">
    <input type="file" id="attachInput" name="attachment" accept="image/*" style="display:none" onchange="previewAttach(this)">
    <button type="button" class="attach-btn" onclick="document.getElementById('attachInput').click()" title="Lampirkan foto">📎</button>
    <textarea class="chat-textarea" id="msgInput" placeholder="Ketik pesan..." rows="1" onkeydown="handleKey(event)"></textarea>
    <button type="button" class="chat-send-btn" onclick="sendMessage()" title="Kirim">➤</button>
  </form>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const ROOM_ID = <?= $roomId ?>;
let lastMsgId = <?= $lastMsgId ?>;
const CSRF = '<?= generateCsrfToken() ?>';
const UPLOADS = '<?= UPLOADS_URL ?>';

function scrollBottom() {
  const c = document.getElementById('chatMessages');
  if (c) c.scrollTop = c.scrollHeight;
}
scrollBottom();

function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function renderMsg(m) {
  const isUser = m.sender_type === 'user';
  const div = document.createElement('div');
  div.className = 'msg-row msg-row--' + (isUser?'user':'admin');
  div.id = 'msg-'+m.id;
  let avatarHtml = !isUser ? '<div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--cyan),var(--purple));display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">🎧</div>' : '';
  let attachHtml = m.attachment ? `<br><img src="${UPLOADS}/chat/${m.attachment}" class="msg-attachment" onclick="window.open(this.src)">` : '';
  let text = (m.message||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
  let nameHtml = !isUser ? `<div style="font-size:11px;font-weight:700;color:var(--cyan);margin-bottom:3px">CS NOXARA</div>` : '';
  let time = new Date(m.created_at).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
  div.innerHTML = `${avatarHtml}<div>${nameHtml}<div class="msg-bubble">${text}${attachHtml}</div><div class="msg-time">${time}</div></div>`;
  return div;
}

function sendMessage() {
  const input = document.getElementById('msgInput');
  const msg = input.value.trim();
  const fileInput = document.getElementById('attachInput');
  if (!msg && !fileInput.files.length) return;

  const fd = new FormData();
  fd.append('action','send'); fd.append('message',msg);
  fd.append('room_id',ROOM_ID); fd.append('csrf_token',CSRF);
  if (fileInput.files.length) fd.append('attachment',fileInput.files[0]);

  input.value = ''; fileInput.value = '';
  document.getElementById('attachPreview')?.remove();

  fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) { lastMsgId = d.message_id; pollMessages(); }
  });
}

function pollMessages() {
  const fd = new FormData();
  fd.append('action','poll'); fd.append('room_id',ROOM_ID); fd.append('last_id',lastMsgId);
  fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.messages && d.messages.length) {
      const container = document.getElementById('chatMessages');
      d.messages.forEach(m => {
        if (!document.getElementById('msg-'+m.id)) {
          container.appendChild(renderMsg(m));
          lastMsgId = Math.max(lastMsgId, parseInt(m.id));
        }
      });
      scrollBottom();
    }
  });
}

function previewAttach(input) {
  document.getElementById('attachPreview')?.remove();
  if (!input.files.length) return;
  const prev = document.createElement('div');
  prev.id = 'attachPreview';
  prev.style.cssText = 'padding:6px 16px;font-size:12px;color:var(--cyan);background:rgba(0,212,255,.08)';
  prev.textContent = '📎 ' + input.files[0].name;
  document.getElementById('chatForm').prepend(prev);
}

// Poll setiap 3 detik
setInterval(pollMessages, 3000);
</script>
</body></html>
