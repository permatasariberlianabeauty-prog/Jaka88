<?php
/**
 * NOXARA - Mobile Bottom Navigation
 */
$currentUri = strtok($_SERVER['REQUEST_URI'], '?');
function mobileIsActive(string $path): string {
    global $currentUri;
    return ($currentUri === $path || str_starts_with($currentUri, $path)) ? ' active' : '';
}
?>
<nav class="nox-bottom-nav" id="bottomNav">
  <div class="nox-bottom-nav__inner">

    <!-- Home -->
    <a href="/dashboard" class="nox-bottom-nav__item<?= mobileIsActive('/dashboard') ?>">
      <svg><use href="#icon-dashboard"/></svg>
      <span class="nox-bottom-nav__label">Home</span>
    </a>

    <!-- Keuangan -->
    <a href="/deposit" class="nox-bottom-nav__item<?= mobileIsActive('/deposit') . mobileIsActive('/withdraw') . mobileIsActive('/history') ?>">
      <svg><use href="#icon-wallet"/></svg>
      <span class="nox-bottom-nav__label">Keuangan</span>
    </a>

    <!-- FAB — Investasi -->
    <div class="nox-bottom-nav__item" style="flex:0 0 64px">
      <button class="nox-bottom-nav__fab" id="fabBtn" aria-label="Investasi">
        <svg><use href="#icon-mining"/></svg>
      </button>
    </div>

    <!-- Referral -->
    <a href="/referral" class="nox-bottom-nav__item<?= mobileIsActive('/referral') ?>">
      <svg><use href="#icon-referral"/></svg>
      <span class="nox-bottom-nav__label">Referral</span>
    </a>

    <!-- Akun -->
    <a href="/profile" class="nox-bottom-nav__item<?= mobileIsActive('/profile') . mobileIsActive('/security') . mobileIsActive('/vip') . mobileIsActive('/voucher') ?>">
      <svg><use href="#icon-profile"/></svg>
      <span class="nox-bottom-nav__label">Akun</span>
    </a>

  </div>
</nav>

<!-- FAB Menu -->
<div class="nox-bottom-nav__fab-menu" id="fabMenu">
  <a href="/products" class="nox-bottom-nav__fab-item">
    <svg width="18" height="18"><use href="#icon-package"/></svg>
    Produk Mining
  </a>
  <a href="/my-packages" class="nox-bottom-nav__fab-item">
    <svg width="18" height="18"><use href="#icon-mining"/></svg>
    Paket Aktif Saya
  </a>
  <a href="/ads" class="nox-bottom-nav__fab-item">
    <svg width="18" height="18"><use href="#icon-ads"/></svg>
    Nonton Iklan
  </a>
  <a href="/daily-reward" class="nox-bottom-nav__fab-item">
    <svg width="18" height="18"><use href="#icon-gift"/></svg>
    Hadiah Harian
  </a>
</div>
<!-- FAB Overlay -->
<div id="fabOverlay" style="display:none;position:fixed;inset:0;z-index:400" onclick="closeFab()"></div>
