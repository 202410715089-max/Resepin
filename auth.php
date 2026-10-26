<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function refresh_current_user(): ?array
{
    $user = current_user();
    $userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $statement = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $freshUser = $statement->fetch();
    if (!$freshUser) {
        unset($_SESSION['user']);
        return null;
    }

    $_SESSION['user'] = [
        'id' => (int) $freshUser['id'],
        'name' => (string) $freshUser['name'],
        'email' => (string) $freshUser['email'],
        'role' => (string) $freshUser['role'],
    ];
    return $_SESSION['user'];
}

function require_admin_page(): array
{
    $user = refresh_current_user();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code($user ? 403 : 401);
        exit($user ? 'Akun ini tidak memiliki akses admin.' : 'Silakan masuk sebagai admin.');
    }
    return $user;
}
