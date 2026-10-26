<?php
declare(strict_types=1);

function rule_engine_answer(PDO $pdo, string $message): array
{
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ignoredWords = ['apa', 'bagaimana', 'bikin', 'buat', 'cara', 'dan', 'dari', 'dengan', 'dong', 'gimana', 'ingin', 'kue', 'mau', 'membuat', 'resep', 'resepnya', 'tolong', 'yang'];
    $tokens = array_values(array_filter(
        $tokens,
        static fn (string $token): bool => mb_strlen($token) > 2 && !in_array($token, $ignoredWords, true)
    ));
    $recipes = $pdo->query(
        'SELECT id, name, description, category, duration, difficulty, image, ingredients, instructions
         FROM recipes WHERE JSON_CONTAINS(tags, \'"Lokal Indonesia"\')'
    )->fetchAll();
    $scored = [];
    foreach ($recipes as $recipe) {
        $ingredients = json_decode((string) $recipe['ingredients'], true) ?: [];
        $instructions = json_decode((string) $recipe['instructions'], true) ?: [];
        $ingredientNames = implode(' ', array_column($ingredients, 'name'));
        $haystack = mb_strtolower(
            $recipe['name'] . ' ' . $recipe['description'] . ' ' . $recipe['category'] . ' ' .
            $ingredientNames . ' ' . implode(' ', $instructions)
        );
        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += mb_strlen($token) > 4 ? 3 : 1;
            }
        }
        if (mb_stripos($message, $recipe['name']) !== false) {
            $score += 10;
        }
        if ($score > 0) {
            $recipe['ingredients'] = $ingredients;
            $recipe['instructions'] = $instructions;
            $recipe['score'] = $score;
            $scored[] = $recipe;
        }
    }

    usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
    $suggestions = array_slice($scored, 0, 3);
    if (preg_match('/(tips|bantat|tidak mekar|kurang matang|terlalu keras)/iu', $message)) {
        $answer = 'Supaya bolu kukus mekar: panaskan kukusan sampai uapnya banyak sebelum adonan masuk, gunakan api besar yang stabil, isi cetakan hampir penuh, bungkus tutup kukusan dengan kain, dan jangan membuka tutup selama 15 menit pertama. Pastikan bahan pengembang masih aktif.';
    } elseif ($suggestions) {
        $first = $suggestions[0];
        $ingredientSummary = implode(', ', array_slice(array_map(
            static fn (array $item): string => $item['amount'] . ' ' . $item['name'],
            $first['ingredients']
        ), 0, 5));
        $firstStep = $first['instructions'][0] ?? 'Buka resep untuk melihat panduan lengkap.';
        $answer = 'Ini yang paling cocok: ' . $first['name'] . ' (' . $first['duration'] . ' menit, ' . $first['difficulty'] . '). Bahan utamanya: ' . $ingredientSummary . '. Langkah pertama: ' . $firstStep . ' Tekan resep di bawah untuk melihat semua takaran dan langkah.';
    } elseif (preg_match('/(simpan|awet|gagal|bantat|oven|kukus|tips|suhu|adonan|mengembang|matang)/iu', $message)) {
        $answer = 'Tips Resepin: timbang bahan dengan akurat, gunakan bahan bersuhu ruang kecuali resep menyebutkan lain, dan ikuti urutan pencampuran. Untuk bolu kukus, panaskan kukusan sampai beruap banyak, bungkus tutupnya dengan kain agar air tidak menetes, isi cetakan hampir penuh, lalu jangan buka tutup selama proses mengukus.';
    } elseif (preg_match('/(halo|hai|selamat pagi|selamat siang|selamat sore|selamat malam)/iu', $message)) {
        $answer = 'Halo! Aku siap bantu cari jajanan pasar dan kue Nusantara. Sebutkan nama kue, bahan yang tersedia, atau tanyakan tips memasaknya. Contoh: “resep klepon” atau “kue dari pisang”.';
    } else {
        $answer = 'Aku belum menemukan resep yang cocok. Coba sebut nama kue atau bahan lokal, misalnya bolu kukus, klepon, nagasari, tepung ketan, pisang, atau gula merah.';
    }

    return ['answer' => $answer, 'recipes' => $suggestions];
}
