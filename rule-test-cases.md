# Skenario pengujian rule engine

Asisten menggunakan resep yang tersimpan dalam database lokal dan tidak mengirim pertanyaan ke layanan pihak ketiga.

| Pertanyaan | Hasil yang diharapkan |
|---|---|
| `Resep klepon gula merah` | Jawaban menyarankan resep Klepon Gula Merah. |
| `Kue tradisional dari singkong` | Jawaban menampilkan resep yang memakai singkong, jika tersedia pada dataset. |
| `Tips agar bolu kukus mekar` | Jawaban menampilkan tips mengukus dan mencegah bolu bantat. |
| `Halo` | Asisten menyapa dan memberi contoh pertanyaan. |
| Pesan lebih dari 500 karakter | API mengembalikan validasi HTTP 422. |
