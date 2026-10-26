<?php
declare(strict_types=1);

$pageMode = 'detail';
$pageTitle = 'Detail Resep — Resepin';
$initialRecipeId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
require __DIR__ . '/template.php';
