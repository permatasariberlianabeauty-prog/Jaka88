<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Boleh via GET dengan token atau POST CSRF
$validRequest = false;

// Via POST (form submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validateCsrf()) {
        $validRequest = true;
    }
}

// Via GET dengan token (link logout)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    if (!empty($token) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
        $validRequest = true;
    }
}

if ($validRequest && isLoggedIn()) {
    $name = $_SESSION['full_name'] ?? 'Member';
    logoutUser();
    setFlash('info', 'Sampai jumpa, ' . htmlspecialchars($name) . '! Kamu telah keluar dari NOXARA. 👋');
    redirect('/auth/login.php');
}

// Jika tidak valid / belum login, redirect saja
redirect('/auth/login.php');
