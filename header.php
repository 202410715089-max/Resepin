<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$headerUser = current_user();
$pageTitle = $pageTitle ?? 'Resepin — Resep Lezat, Kue Sempurna';
$initialSearch = $initialSearch ?? '';
$pageMode = $pageMode ?? 'home';
$initialRecipeId = $initialRecipeId ?? 0;
$initialAuthMode = $initialAuthMode ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <meta name="csrf-token" content="<?= h((string) $_SESSION['csrf_token']) ?>">
    <meta name="description" content="Temukan resep kue Nusantara, panduan memasak, dan inspirasi baking bersama Resepin.">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body data-page-mode="<?= h($pageMode) ?>" data-recipe-id="<?= (int) $initialRecipeId ?>" data-auth-mode="<?= h($initialAuthMode) ?>">
<header class="site-header">
    <a class="brand" href="pages/index.php" aria-label="Resepin beranda">
        <span class="brand-icon" aria-hidden="true">👨🏻‍🍳</span>
        <span><strong>Resepin</strong><small>Resep Lezat, Kue Sempurna</small></span>
    </a>
    <nav class="main-nav" aria-label="Navigasi utama">
        <a class="nav-link<?= $pageMode === 'home' ? ' active' : '' ?>" href="pages/index.php#home">⌂ <span>Beranda</span></a>
        <a class="nav-link<?= $pageMode === 'search' || $pageMode === 'detail' ? ' active' : '' ?>" href="pages/cari-resep.php">▧ <span>Resep</span></a>
        <a class="nav-link<?= $pageMode === 'recommendations' ? ' active' : '' ?>" href="pages/rekomendasi.php">✦ <span>Rekomendasi</span></a>
        <button class="nav-link" type="button" data-open-guide>▣ <span>Panduan</span></button>
        <button class="nav-link" type="button" data-show-favorites>♡ <span>Favorit</span></button>
        <?php if (($headerUser['role'] ?? '') === 'admin'): ?>
            <a class="nav-link admin-link" href="admin/dashboard.php">⚙ <span>Kelola</span></a>
        <?php endif; ?>
    </nav>
    <form class="header-search" id="search-form" action="pages/cari-resep.php" method="get">
        <span aria-hidden="true">⌕</span>
        <input id="header-search" name="q" type="search" value="<?= h($initialSearch) ?>" placeholder="Cari resep atau bahan..." aria-label="Cari resep atau bahan">
    </form>
    <?php if (!$headerUser): ?>
        <a class="header-auth-link" href="pages/akun.php?mode=login">Masuk</a>
        <a class="header-register-link" href="pages/akun.php?mode=register">Daftar</a>
    <?php endif; ?>
    <button class="profile-button" type="button" id="profile-button" aria-label="<?= $headerUser ? 'Akun pengguna' : 'Masuk ke akun' ?>">
        <?= $headerUser ? h(mb_substr((string) $headerUser['name'], 0, 1)) : '♙' ?>
    </button>
</header>
