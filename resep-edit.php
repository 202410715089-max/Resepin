<?php
declare(strict_types=1);

if (isset($_GET['id'])) {
    $_GET['edit'] = (string) $_GET['id'];
}
require __DIR__ . '/dashboard.php';
