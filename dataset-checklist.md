# Checklist dataset resep

- [ ] Setiap resep memiliki nama, deskripsi, kategori, waktu, tingkat kesulitan, dan porsi.
- [ ] Kolom `ingredients`, `instructions`, dan `tags` berisi JSON valid.
- [ ] Resep lokal memiliki tag `Lokal Indonesia`.
- [ ] Foto resep menggunakan path relatif lokal, misalnya `uploads/recipes/nama-kue.jpg`.
- [ ] File foto tersedia pada path yang tersimpan, atau gunakan fallback `assets/images/recipe-art.svg`.
- [ ] Tidak ada kata sandi, alamat email pengguna, atau data pribadi di seed SQL.
- [ ] Jalankan pemeriksaan jumlah resep dan kategori setelah mengimpor `database/resepin.sql`.
