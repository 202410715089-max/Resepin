<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
$user = current_user();
if ($user && $user['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}
$adminAccountExists = (bool) db()->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h((string) $_SESSION['csrf_token']) ?>">
    <title>Masuk Admin — Resepin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
    <main class="admin-access panel">
        <span class="admin-mark">✿</span>
        <p class="eyebrow">DAPUR RESEPIN</p>
        <h1>Masuk sebagai admin</h1>
        <form id="admin-login-form" class="auth-form">
            <label>Email<input name="email" type="email" autocomplete="username" required></label>
            <label>Kata sandi<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="primary-button" type="submit">Masuk</button>
        </form>
        <p id="login-error" class="form-error" role="alert"></p>
        <p class="login-separation">Login admin khusus pengelola. Akun pengguna biasa tidak dapat menambah, mengedit, atau menghapus resep.</p>
        <a class="admin-user-link" href="daftar.php">Belum punya akun admin? Daftar admin</a>
        <a class="admin-user-link" href="../pages/akun.php?mode=register">Pengguna baru? Daftar akun pengguna</a>
        <a class="admin-back" href="../pages/akun.php?mode=login">Sudah punya akun pengguna? Masuk</a>
    </main>
    <script>
        const loginForm = document.getElementById('admin-login-form');
        loginForm.addEventListener('submit', async event => {
            event.preventDefault();
            const button = loginForm.querySelector('button');
            button.disabled = true;
            try {
                const form = new FormData(loginForm);
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ action: 'auth', type: 'admin_login', ...Object.fromEntries(form.entries()) })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Gagal masuk.');
                window.location.href = 'dashboard.php';
            } catch (error) {
                document.getElementById('login-error').textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
