<?php
declare(strict_types=1);

$pageMode = 'account';
$pageTitle = 'Masuk atau Daftar — Resepin';
$initialAuthMode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
require __DIR__ . '/template.php';
