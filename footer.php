<footer class="site-footer">
    <a class="brand footer-brand" href="pages/index.php#home"><span class="brand-icon">👨🏻‍🍳</span><span><strong>Resepin</strong><small>Resep Lezat, Kue Sempurna</small></span></a>
    <nav aria-label="Navigasi footer"><a href="pages/index.php">Beranda</a><a href="pages/cari-resep.php">Cari resep</a><a href="pages/rekomendasi.php">Rekomendasi</a><a href="pages/akun.php?mode=register">Daftar akun</a><a href="admin/login.php">Login admin</a><button type="button" data-open-guide>Panduan</button></nav>
    <small>© <?= date('Y') ?> Resepin. Semua hak dilindungi.</small>
</footer>
<dialog id="recipe-dialog" class="dialog recipe-dialog"><button class="dialog-close" data-close-dialog aria-label="Tutup">×</button><div id="recipe-detail"></div></dialog>
<dialog id="guide-dialog" class="dialog"><button class="dialog-close" data-close-dialog aria-label="Tutup">×</button><div class="dialog-content">
    <span class="eyebrow">PANDUAN BAKING</span><h2>Mulai membuat kue dengan percaya diri</h2>
    <div class="guide-list">
        <article><b>1. Siapkan alat dan bahan</b><p>Baca resep sampai selesai dan timbang semua bahan terlebih dahulu.</p></article>
        <article><b>2. Ikuti urutan resep</b><p>Gunakan takaran tepat dan hindari mengaduk adonan berlebihan.</p></article>
        <article><b>3. Perhatikan suhu</b><p>Panaskan oven sebelum memanggang. Untuk kue kukus, tunggu kukusan beruap.</p></article>
        <article><b>4. Cek kematangan</b><p>Tusuk bagian tengah kue dengan lidi dan dinginkan sebelum dihias.</p></article>
    </div>
</div></dialog>
<dialog id="auth-dialog" class="dialog"><button class="dialog-close" data-close-dialog aria-label="Tutup">×</button><div id="auth-content" class="dialog-content"></div></dialog>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
</body>
</html>
