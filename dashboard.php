<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';
session_start();

$userId = filter_var($_SESSION['user']['id'] ?? null, FILTER_VALIDATE_INT);
$user = null;
$databaseUnavailable = false;
if ($userId) {
    try {
        $statement = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        error_log('Resepin admin page database error: ' . $exception->getMessage());
        http_response_code(503);
        $databaseUnavailable = true;
    }
}

if ($databaseUnavailable || !$user || $user['role'] !== 'admin') {
    if (!$databaseUnavailable) {
        http_response_code(isset($user['id']) ? 403 : 401);
    }
    $message = $databaseUnavailable
        ? 'Database sedang tidak tersedia. Pastikan MySQL aktif dan konfigurasi database sudah benar.'
        : (isset($user['id'])
        ? 'Akun kamu belum memiliki akses admin.'
        : 'Silakan masuk dengan akun admin untuk membuka halaman ini.');
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Akses Admin — Resepin</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/admin.css">
    </head>
    <body class="admin-page">
        <main class="admin-access panel">
            <span class="admin-mark">✿</span>
            <p class="eyebrow">DAPUR RESEPIN</p>
            <h1>Akses admin diperlukan</h1>
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!isset($user['id'])): ?>
                <a class="primary-button" href="login.php">Masuk admin</a>
            <?php endif; ?>
            <a class="secondary-button" href="../pages/index.php">Kembali ke beranda</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Kelola Resep — Resepin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin.js" defer></script>
</head>
<body class="admin-page">
    <header class="admin-header">
        <a class="brand" href="../pages/index.php">
            <span class="brand-icon">👨🏻‍🍳</span>
            <span><strong>Resepin</strong><small>Ruang Admin</small></span>
        </a>
        <a href="../pages/index.php" class="admin-back">← Kembali ke website</a>
    </header>
    <main class="admin-layout">
        <section class="admin-intro">
            <div>
                <span class="eyebrow">✦ PANEL ADMIN</span>
                <h1>Kelola resep Nusantara</h1>
                <p>Halo, <?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>. Tambah resep baru, perbarui bahan dan langkah, atau ganti gambar kue kapan saja.</p>
            </div>
            <button class="primary-button add-recipe-button" type="button" id="new-recipe">＋ Tambah resep</button>
        </section>

        <section class="admin-stats">
            <article class="panel"><span>🧁</span><div><b id="recipe-total">—</b><small>Resep terdaftar</small></div></article>
            <article class="panel"><span>🖼️</span><div><b>JPG · PNG · WEBP</b><small>Gambar hingga 5 MB</small></div></article>
            <article class="panel"><span>🔒</span><div><b>Kelola Admin</b><small>Tambah admin khusus</small></div></article>
        </section>

        <section class="admin-accounts panel">
            <div class="admin-section-title">
                <div><span class="eyebrow">AKSES PENGELOLA</span><h2>Tambah akun admin</h2></div>
            </div>
            <p>Buat akun admin tambahan. Hanya admin yang sudah masuk yang dapat menggunakan form ini.</p>
            <form id="admin-account-form" class="admin-account-form">
                <label>Nama<input name="name" type="text" maxlength="80" autocomplete="name" required></label>
                <label>Email<input name="email" type="email" autocomplete="email" required></label>
                <label>Kata sandi sementara<input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
                <button class="primary-button" type="submit">＋ Buat akun admin</button>
            </form>
            <p class="form-error" id="admin-account-error" role="alert"></p>
            <h3 class="admin-account-heading">Akun admin terdaftar</h3>
            <div id="admin-account-list" class="admin-account-list" aria-live="polite"></div>
        </section>

        <section class="admin-workspace">
            <section class="admin-editor panel" id="editor-panel">
                <div class="admin-section-title">
                    <div><span class="eyebrow">FORM RESEP</span><h2 id="editor-title">Tambah resep baru</h2></div>
                    <button type="button" class="icon-button" id="close-editor" aria-label="Tutup form">×</button>
                </div>
                <form id="recipe-form">
                    <input type="hidden" name="id">
                    <label>Nama kue<input name="name" maxlength="150" required placeholder="Contoh: Bolu Kukus Gula Merah"></label>
                    <label>Deskripsi<textarea name="description" rows="3" maxlength="2000" required placeholder="Ceritakan keistimewaan kue ini..."></textarea></label>
                    <div class="form-row">
                        <label>Kategori<input name="category" maxlength="50" list="category-options" required placeholder="Jajanan Pasar"></label>
                        <datalist id="category-options">
                            <option value="Kue Tradisional"><option value="Kue Kukus Nusantara"><option value="Jajanan Pasar"><option value="Kue Basah Nusantara"><option value="Kue Kering Nusantara"><option value="Cake & Bolu">
                        </datalist>
                        <label>Waktu (menit)<input name="duration" type="number" min="1" max="1440" value="45" required></label>
                        <label>Porsi<input name="servings" type="number" min="1" max="100" value="8" required></label>
                    </div>
                    <div class="form-row form-row-bottom">
                        <label>Tingkat kesulitan<select name="difficulty"><option>Mudah</option><option>Sedang</option><option>Sulit</option></select></label>
                        <label>Tag, pisahkan dengan koma<input name="tags" maxlength="300" placeholder="Kukus, Gula Merah, Jawa"></label>
                    </div>
                    <label class="featured-option"><input type="checkbox" name="featured" value="1"> Tampilkan sebagai resep pilihan</label>
                    <section class="image-editor">
                        <div class="image-preview" id="image-preview"><span>🧁</span><small>Pratinjau gambar kue</small></div>
                        <div class="image-picker-copy">
                            <b>Foto kue Nusantara</b>
                            <small>Pilih foto sendiri untuk resep ini. JPG, PNG, atau WEBP, maksimal 5 MB.</small>
                            <label class="secondary-button image-picker-button" for="recipe-image">📷 <span id="image-picker-label">Pilih / edit gambar</span></label>
                            <input class="visually-hidden" id="recipe-image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </section>
                    <label>Bahan-bahan <small>Isi satu bahan per baris dengan format: takaran | nama bahan</small><textarea name="ingredients" rows="7" required placeholder="250 g | tepung beras&#10;200 ml | santan&#10;2 butir | telur"></textarea></label>
                    <label>Langkah pembuatan <small>Satu langkah per baris, urutkan dari awal sampai selesai</small><textarea name="instructions" rows="7" required placeholder="Campur tepung dan bahan kering.&#10;Tuang santan sedikit demi sedikit sambil diaduk.&#10;Kukus hingga matang."></textarea></label>
                    <div class="editor-actions">
                        <button type="button" class="secondary-button" id="cancel-edit">Batal</button>
                        <button type="submit" class="primary-button" id="save-recipe">Simpan resep</button>
                    </div>
                    <p id="form-error" class="form-error" role="alert"></p>
                </form>
            </section>

            <section class="recipe-manager">
                <div class="admin-section-title">
                    <div><span class="eyebrow">KATALOG KUE</span><h2>Resep kamu</h2></div>
                    <label class="manager-search">⌕ <input id="admin-search" type="search" placeholder="Cari nama kue..."></label>
                </div>
                <div id="admin-recipe-list" class="admin-recipe-list" aria-live="polite"><div class="loading-state">Memuat resep...</div></div>
            </section>
        </section>
    </main>
    <dialog id="delete-dialog" class="dialog delete-dialog">
        <div class="dialog-content">
            <span class="eyebrow">HAPUS RESEP</span>
            <h2>Yakin menghapus resep ini?</h2>
            <p>Resep akan dihapus dari katalog dan tidak bisa dipulihkan.</p>
            <div class="editor-actions"><button class="secondary-button" data-close-delete type="button">Batal</button><button class="danger-button" id="confirm-delete" type="button">Ya, hapus resep</button></div>
        </div>
    </dialog>
    <div id="admin-toast" class="toast" role="status" aria-live="polite"></div>
</body>
</html>
