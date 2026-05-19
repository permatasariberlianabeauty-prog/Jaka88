<?php
/**
 * NOXARA - Footer (scripts + popup system)
 */
$currentUser = getCurrentUser();
$userId      = (int)($currentUser['id'] ?? 0);

// Ambil popup settings untuk semua events
$popupStmt = db()->prepare("SELECT event, title, message, style, duration_seconds, position, is_active FROM popup_settings WHERE is_active = 1");
$popupStmt->execute();
$popupSettings = [];
$popupRows = $popupStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$popupStmt->close();
foreach ($popupRows as $row) {
    $popupSettings[$row['event']] = $row;
}
?>

<!-- Toast Container -->
<div class="nox-toast-container" id="toastContainer"></div>

<!-- Confetti Container -->
<div class="nox-coins-container" id="coinsContainer"></div>

<!-- Popup Overlay Global -->
<div class="nox-overlay" id="globalOverlay">
  <div class="nox-modal" id="globalModal" role="dialog">
    <button class="nox-modal__close" onclick="closeGlobalModal()" aria-label="Tutup">&times;</button>
    <div class="nox-modal__icon" id="globalModalIcon"></div>
    <h3 class="nox-modal__title" id="globalModalTitle"></h3>
    <p class="nox-modal__text" id="globalModalText"></p>
    <div id="globalModalBody"></div>
    <div id="globalModalFooter" style="display:flex;gap:12px;justify-content:center;margin-top:8px"></div>
  </div>
</div>

<!-- Scripts -->
<script src="<?= ASSETS_URL ?>/js/main.js" defer></script>
<script src="<?= ASSETS_URL ?>/js/animations.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>

<script>
// ── Popup Settings dari PHP ────────────────────────────────
window.NOXARA_POPUPS = <?= json_encode($popupSettings) ?>;
window.NOXARA_BASE_URL = '<?= BASE_URL ?>';
window.NOXARA_USER_ID = <?= $userId ?>;
window.NOXARA_CSRF = '<?= generateCsrfToken() ?>';

// ── Sidebar Toggle ─────────────────────────────────────────
function openSidebar() {
  document.getElementById('sidebar')?.classList.add('open');
  document.getElementById('sidebarOverlay')?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  const sidebar = document.getElementById('sidebar');
  sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
});

// ── FAB Menu ───────────────────────────────────────────────
function closeFab() {
  document.getElementById('fabMenu')?.classList.remove('open');
  document.getElementById('fabOverlay').style.display = 'none';
}
document.getElementById('fabBtn')?.addEventListener('click', (e) => {
  e.stopPropagation();
  const menu = document.getElementById('fabMenu');
  const isOpen = menu?.classList.contains('open');
  if (isOpen) { closeFab(); }
  else {
    menu?.classList.add('open');
    document.getElementById('fabOverlay').style.display = 'block';
  }
});

// ── Notification Dropdown ──────────────────────────────────
const notifBtn = document.getElementById('notifBtn');
const notifDrop = document.getElementById('notifDropdown');
notifBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  const isVisible = notifDrop.style.display !== 'none';
  notifDrop.style.display = isVisible ? 'none' : 'block';
  userDrop.style.display = 'none';
  if (!isVisible) loadNotifications();
});

// ── User Dropdown ──────────────────────────────────────────
const userMenuBtn = document.getElementById('userMenuBtn');
const userDrop = document.getElementById('userDropdown');
userMenuBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  const isVisible = userDrop.style.display !== 'none';
  userDrop.style.display = isVisible ? 'none' : 'block';
  notifDrop.style.display = 'none';
});

// Close dropdowns on outside click
document.addEventListener('click', () => {
  notifDrop.style.display = 'none';
  userDrop.style.display = 'none';
});

// ── Load Notifications ─────────────────────────────────────
async function loadNotifications() {
  try {
    const res = await fetch('/api/notification?action=list&limit=8', {
      headers: { 'X-CSRF-TOKEN': window.NOXARA_CSRF }
    });
    const data = await res.json();
    const list = document.getElementById('notifList');
    if (!list) return;
    if (!data.data?.length) {
      list.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-secondary);font-size:13px">Belum ada notifikasi</div>';
      return;
    }
    list.innerHTML = data.data.map(n => `
      <div class="nox-notif-item ${n.is_read ? '' : 'nox-notif-item--unread'}" onclick="markRead(${n.id})">
        ${!n.is_read ? '<div class="nox-notif-item__dot"></div>' : '<div style="width:8px"></div>'}
        <div style="flex:1">
          <div class="nox-notif-item__title">${escHtml(n.title)}</div>
          <div class="nox-notif-item__msg">${escHtml(n.message)}</div>
          <div class="nox-notif-item__time">${n.time_ago}</div>
        </div>
      </div>
    `).join('');
  } catch(e) {}
}

async function markRead(id) {
  await fetch('/api/notification?action=read&id=' + id, {
    method: 'POST', headers: { 'X-CSRF-TOKEN': window.NOXARA_CSRF }
  });
  loadNotifications();
  updateNotifBadge();
}

async function markAllRead() {
  await fetch('/api/notification?action=read_all', {
    method: 'POST', headers: { 'X-CSRF-TOKEN': window.NOXARA_CSRF }
  });
  loadNotifications();
  updateNotifBadge();
}

function updateNotifBadge() {
  const badge = document.getElementById('notifBadge');
  if (badge) badge.remove();
}

// ── Global Modal ───────────────────────────────────────────
function showModal(opts) {
  const { icon = 'info', title = '', text = '', body = '', buttons = [], onClose } = opts;
  const overlay = document.getElementById('globalOverlay');
  const iconEl  = document.getElementById('globalModalIcon');
  const titleEl = document.getElementById('globalModalTitle');
  const textEl  = document.getElementById('globalModalText');
  const bodyEl  = document.getElementById('globalModalBody');
  const footerEl= document.getElementById('globalModalFooter');

  const iconMap = { success:'check-circle', error:'x-circle', warning:'alert-triangle', info:'info' };
  iconEl.className = `nox-modal__icon nox-modal__icon--${icon}`;
  iconEl.innerHTML = `<svg width="32" height="32"><use href="#icon-${iconMap[icon]||'info'}"/></svg>`;
  titleEl.textContent = title;
  textEl.innerHTML = text;
  bodyEl.innerHTML = body;
  footerEl.innerHTML = buttons.map(b =>
    `<button class="nox-btn ${b.class||'nox-btn--primary'}" onclick="${b.action||'closeGlobalModal()'}">${b.label}</button>`
  ).join('');

  overlay.classList.add('active');
  overlay._onClose = onClose;
}

function closeGlobalModal() {
  const overlay = document.getElementById('globalOverlay');
  overlay.classList.remove('active');
  if (typeof overlay._onClose === 'function') overlay._onClose();
}
document.getElementById('globalOverlay')?.addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeGlobalModal();
});

// ── Toast System ───────────────────────────────────────────
function showToast(type, title, msg, duration = 4000) {
  const iconMap = { success:'check-circle', error:'x-circle', warning:'alert-triangle', info:'info' };
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `nox-toast nox-toast--${type}`;
  toast.innerHTML = `
    <svg class="nox-toast__icon" width="20" height="20"><use href="#icon-${iconMap[type]||'info'}"/></svg>
    <div class="nox-toast__content">
      <div class="nox-toast__title">${escHtml(title)}</div>
      ${msg ? `<div class="nox-toast__msg">${escHtml(msg)}</div>` : ''}
    </div>
    <span class="nox-toast__close" onclick="this.parentElement.remove()">&times;</span>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ── Logout Confirm ─────────────────────────────────────────
function confirmLogout() {
  showModal({
    icon: 'info',
    title: 'Keluar dari NOXARA?',
    text: 'Kamu yakin ingin keluar dari akun ini?',
    buttons: [
      { label: 'Batal', class: 'nox-btn nox-btn--ghost', action: 'closeGlobalModal()' },
      { label: 'Ya, Keluar', class: 'nox-btn nox-btn--danger', action: "window.location.href='/logout'" }
    ]
  });
}

// ── PIN Input Auto Focus ───────────────────────────────────
document.querySelectorAll('.nox-pin-input input').forEach((input, i, arr) => {
  input.addEventListener('input', e => {
    if (e.target.value.length === 1 && arr[i+1]) arr[i+1].focus();
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !e.target.value && arr[i-1]) arr[i-1].focus();
  });
});

// ── Utility ────────────────────────────────────────────────
function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(str || ''));
  return d.innerHTML;
}

function copyToClipboard(text, label = 'Disalin!') {
  navigator.clipboard.writeText(text).then(() => showToast('success', label, ''));
}

// ── Flash Message ──────────────────────────────────────────
<?php
$flash = getFlash();
if ($flash): ?>
showToast('<?= $flash['type'] ?>', '', '<?= addslashes(htmlspecialchars($flash['message'])) ?>');
<?php endif; ?>

// ── Popup saat Login (jika ada di session) ─────────────────
<?php if (isset($_SESSION['show_popup'])): ?>
const pp = <?= json_encode($_SESSION['show_popup']) ?>;
<?php unset($_SESSION['show_popup']); ?>
if (pp) showModal({ icon: pp.icon||'success', title: pp.title, text: pp.message,
  buttons:[{label:'OK', class:'nox-btn nox-btn--primary', action:'closeGlobalModal()'}]
});
<?php endif; ?>
</script>
