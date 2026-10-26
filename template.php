<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/header.php';
$isHome = $pageMode === 'home';
?>
<main id="home" class="page-layout">
    <div class="main-column">
        <?php if ($isHome): ?>
            <section class="hero">
                <div class="hero-copy">
                    <span class="eyebrow">✦ Dapur Nusantara</span>
                    <h1>Jajanan Nusantara,<br><span>lebih mudah!</span></h1>
                    <p>Temukan resep kue tradisional dan jajanan pasar lengkap dengan bahan dan panduan memasak.</p>
                </div>
                <div class="hero-chef" role="img" aria-label="Koki Resepin sedang membuat kue">
                    <div class="chef-bubble">Masak jadi<br>lebih mudah! <span>♥</span></div>
                    <div class="chef-art"><div class="chef-hat">● ● ●</div><div class="chef-face"><i></i><i></i><b></b></div><div class="chef-body">Resepin</div></div>
                    <div class="hero-cake">🍰</div>
                </div>
            </section>
            <section class="ask-panel" aria-label="Tanya asisten Resepin">
                <form id="hero-chat-form" class="ask-form">
                    <span class="bot-icon" aria-hidden="true">☻</span>
                    <input id="hero-chat-input" maxlength="500" placeholder="Tanya resep atau minta panduan membuat kue..." aria-label="Pertanyaan untuk asisten">
                    <button class="send-button" type="submit" aria-label="Kirim pertanyaan">➤</button>
                </form>
                <div class="quick-prompts"><small>Contoh pertanyaan:</small>
                    <button type="button" data-prompt="Cara membuat bolu kukus gula merah">Bolu kukus</button>
                    <button type="button" data-prompt="Resep klepon gula merah">Klepon</button>
                    <button type="button" data-prompt="Kue tradisional dari singkong">Jajanan singkong</button>
                    <button type="button" data-prompt="Tips agar bolu kukus mekar">Tips bolu kukus</button>
                </div>
            </section>
            <section class="feature-strip" aria-label="Fitur Resepin">
                <article><span class="feature-icon">♧</span><div><b>Resep Lengkap</b><small>Resep kue tradisional Nusantara.</small></div></article>
                <article><span class="feature-icon">▣</span><div><b>Panduan Step by Step</b><small>Instruksi memasak mudah diikuti.</small></div></article>
                <article><span class="feature-icon">✦</span><div><b>Asisten Resep</b><small>Cari kue, bahan, atau tips masak.</small></div></article>
                <article><span class="feature-icon">♥</span><div><b>Favorit & Riwayat</b><small>Simpan resep favoritmu.</small></div></article>
            </section>
        <?php else: ?>
            <section class="page-intro">
                <span class="eyebrow"><?= $pageMode === 'search' ? 'CARI RESEP' : ($pageMode === 'recommendations' ? 'PILIHAN DAPUR RESEPIN' : ($pageMode === 'account' ? 'AKUN PENGGUNA' : 'RESEP NUSANTARA')) ?></span>
                <h1><?= $pageMode === 'search' ? 'Temukan resep favoritmu' : ($pageMode === 'recommendations' ? 'Rekomendasi untukmu' : ($pageMode === 'account' ? 'Masuk atau daftar akun' : 'Detail resep')) ?></h1>
                <p><?= $pageMode === 'account' ? 'Daftar sebagai pengguna untuk memiliki akun. Hak tambah, edit, dan hapus resep hanya tersedia untuk admin.' : 'Temukan inspirasi kue Nusantara lengkap dengan takaran bahan dan langkah membuatnya.' ?></p>
            </section>
        <?php endif; ?>

        <section class="recipes-section" id="recipes">
            <div class="section-heading">
                <div><h2>🧁 <span id="recipe-heading"><?= $pageMode === 'recommendations' ? 'Resep Pilihan' : 'Semua Resep' ?></span></h2><p id="result-count" class="result-count"></p></div>
                <div class="section-actions"><button class="text-button" type="button" id="show-history">Riwayat</button><a class="text-button" href="pages/cari-resep.php">Cari resep →</a></div>
            </div>
            <div class="category-list" id="category-list" aria-label="Filter kategori"></div>
            <div id="recipe-grid" class="recipe-grid" aria-live="polite"><div class="loading-state">Memuat resep...</div></div>
        </section>
    </div>

    <aside class="sidebar">
        <section class="recommendations panel">
            <div class="section-heading compact"><h2>✨ Rekomendasi Resep</h2><a class="text-button" href="pages/rekomendasi.php">Lihat semua →</a></div>
            <div id="recommendation-list" class="recommendation-list"></div>
        </section>
        <section id="chat-panel" class="chat-panel panel" aria-label="Asisten Resepin">
            <div class="chat-title"><span class="mini-chef">👨🏻‍🍳</span><div><b>Resepin - Asisten Kue Nusantara</b><small>● Siap membantu</small></div></div>
            <div id="chat-messages" class="chat-messages" aria-live="polite"><div class="chat-message assistant">Halo! Tanya nama kue, bahan yang tersedia, atau tips memasak. Contoh: “resep klepon” atau “kue dari singkong”. 😊</div></div>
            <form id="chat-form" class="chat-form"><input id="chat-input" maxlength="500" placeholder="Tulis pesan..." aria-label="Tulis pesan untuk asisten"><button class="send-button" type="submit" aria-label="Kirim pesan">➤</button></form>
        </section>
    </aside>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
