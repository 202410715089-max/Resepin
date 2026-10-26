<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$registrationAvailable = is_local_request();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h((string) $_SESSION['csrf_token']) ?>">
    <title>Daftar Admin — Resepin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
    <main class="admin-access panel">
        <span class="admin-mark">✿</span>
        <p class="eyebrow">PENDAFTARAN ADMIN</p>
        <h1>Daftar akun admin</h1>
        <?php if ($registrationAvailable): ?>
            <p>Isi data untuk membuat akun admin. Pendaftaran ini hanya tersedia dari komputer server.</p>
            <form id="admin-register-form" class="auth-form">
                <label>Nama<input name="name" type="text" maxlength="80" autocomplete="name" required></label>
                <label>Email<input name="email" type="email" maxlength="190" autocomplete="email" required></label>
                <label>Kata sandi<input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
                <button class="primary-button" type="submit">Daftar sebagai admin</button>
            </form>
            <p id="register-error" class="form-error" role="alert"></p>
        <?php else: ?>
            <p>Pendaftaran admin langsung hanya dapat dilakukan dari komputer server melalui alamat <strong>localhost</strong>. Pengguna biasa tetap hanya dapat mendaftar sebagai user.</p>
        <?php endif; ?>
        <a class="admin-back" href="login.php">Kembali ke login admin</a>
    </main>
    <?php if ($registrationAvailable): ?>
    <script>
        const registerForm = document.getElementById('admin-register-form');
        registerForm.addEventListener('submit', async event => {
            event.preventDefault();
            const button = registerForm.querySelector('button');
            button.disabled = true;
            try {
                const form = new FormData(registerForm);
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ action: 'auth', type: 'admin_register', ...Object.fromEntries(form.entries()) })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Pendaftaran admin gagal.');
                window.location.href = 'dashboard.php';
            } catch (error) {
                document.getElementById('register-error').textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
