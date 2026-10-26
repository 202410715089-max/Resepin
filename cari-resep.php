<?php
declare(strict_types=1);

$pageMode = 'search';
$pageTitle = 'Cari Resep — Resepin';
$initialSearch = trim((string) ($_GET['q'] ?? ''));
require __DIR__ . '/template.php';
