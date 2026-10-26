<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';
require __DIR__ . '/rule-engine.php';
session_start();

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $data = $method === 'POST' ? request_data() : [];
    $action = (string) ($_GET['action'] ?? $data['action'] ?? '');
    $favoriteKey = current_favorite_key();

    if ($method === 'GET' && $action === 'admin_list') {
        if (recipe_user_role($pdo) !== 'admin') {
            json_response(['error' => 'Halaman ini hanya dapat diakses admin.'], 403);
        }
        $recipes = array_map('decode_recipe', $pdo->query('SELECT * FROM recipes ORDER BY updated_at DESC, name')->fetchAll());
        json_response(['recipes' => $recipes]);
    }

    if ($method === 'GET' && $action === 'admin_users') {
        if (recipe_user_role($pdo) !== 'admin') {
            json_response(['error' => 'Halaman ini hanya dapat diakses admin.'], 403);
        }
        $users = $pdo->query("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY id")->fetchAll();
        json_response(['users' => $users]);
    }

    if ($method === 'GET' && $action === 'list') {
        $search = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $favoritesOnly = ($_GET['favorites'] ?? '') === '1';
        if (mb_strlen($search) > 100) {
            json_response(['error' => 'Kata pencarian maksimal 100 karakter.'], 422);
        }

        $where = ['JSON_CONTAINS(r.tags, ?)'];
        $params = [$favoriteKey, '"Lokal Indonesia"'];
        if ($search !== '') {
            $ignoredWords = ['apa', 'bagaimana', 'bikin', 'buat', 'cara', 'dan', 'dari', 'dengan', 'dong', 'gimana', 'ingin', 'kue', 'mau', 'membuat', 'resep', 'resepnya', 'tolong', 'yang'];
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tokens = array_values(array_filter($tokens, static fn (string $token): bool => !in_array($token, $ignoredWords, true)));
            $aliases = ['kelepon' => 'klepon', 'nogosari' => 'nagasari', 'surabi' => 'serabi'];
            $tokenConditions = [];
            foreach (array_slice(array_unique($tokens), 0, 6) as $token) {
                $alternatives = [];
                foreach (array_unique([$token, $aliases[$token] ?? $token]) as $term) {
                    $alternatives[] = '(r.name LIKE ? OR r.description LIKE ? OR r.category LIKE ? OR CAST(r.tags AS CHAR) LIKE ? OR CAST(r.ingredients AS CHAR) LIKE ?)';
                    $like = '%' . $term . '%';
                    array_push($params, $like, $like, $like, $like, $like);
                }
                $tokenConditions[] = '(' . implode(' OR ', $alternatives) . ')';
            }
            if ($tokenConditions) {
                $where[] = '(' . implode(' AND ', $tokenConditions) . ')';
            } else {
                $where[] = '(r.name LIKE ? OR r.description LIKE ? OR r.category LIKE ? OR CAST(r.tags AS CHAR) LIKE ? OR CAST(r.ingredients AS CHAR) LIKE ?)';
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }
        if ($category !== '' && $category !== 'Semua') {
            $where[] = 'r.category = ?';
            $params[] = $category;
        }
        if ($favoritesOnly) {
            $where[] = 'EXISTS (SELECT 1 FROM favorites f WHERE f.recipe_id = r.id AND f.session_key = ?)';
            $params[] = $favoriteKey;
        }

        $sql = 'SELECT r.*, EXISTS (SELECT 1 FROM favorites f WHERE f.recipe_id = r.id AND f.session_key = ?) AS is_favorite FROM recipes r';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= $search !== ''
            ? ' ORDER BY CASE WHEN r.name LIKE ? THEN 0 ELSE 1 END, r.featured DESC, r.name ASC'
            : ' ORDER BY r.featured DESC, r.name ASC';
        if ($search !== '') {
            $params[] = '%' . $search . '%';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $recipes = array_map('decode_recipe', $statement->fetchAll());
        json_response(['recipes' => $recipes]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            json_response(['error' => 'Resep tidak ditemukan.'], 404);
        }
        $statement = $pdo->prepare(
            'SELECT r.*, EXISTS (SELECT 1 FROM favorites f WHERE f.recipe_id = r.id AND f.session_key = ?) AS is_favorite
             FROM recipes r WHERE r.id = ?'
        );
        $statement->execute([$favoriteKey, $id]);
        $recipe = $statement->fetch();
        if (!$recipe) {
            json_response(['error' => 'Resep tidak ditemukan.'], 404);
        }
        json_response(['recipe' => decode_recipe($recipe)]);
    }

    if ($method === 'GET' && $action === 'categories') {
        $categories = $pdo->query(
            'SELECT c.name, c.icon FROM categories c
             WHERE EXISTS (
                 SELECT 1 FROM recipes r
                 WHERE r.category = c.name AND JSON_CONTAINS(r.tags, \'"Lokal Indonesia"\')
             )
             ORDER BY c.sort_order, c.name'
        )->fetchAll();
        json_response(['categories' => $categories]);
    }

    if ($method === 'GET' && $action === 'session') {
        recipe_user_role($pdo);
        json_response([
            'user' => $_SESSION['user'] ?? null,
            'csrf' => $_SESSION['csrf_token'] ?? null,
        ]);
    }

    if ($method !== 'POST') {
        json_response(['error' => 'Metode permintaan tidak didukung.'], 405);
    }
    $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        json_response(['error' => 'Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 403);
    }

    if ($action === 'admin_user_create') {
        if (recipe_user_role($pdo) !== 'admin') {
            json_response(['error' => 'Hanya admin yang boleh membuat akun admin.'], 403);
        }
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 80) {
            json_response(['error' => 'Nama wajib diisi dan maksimal 80 karakter.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Masukkan alamat email yang valid.'], 422);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            json_response(['error' => 'Kata sandi harus 8–72 karakter.'], 422);
        }

        $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        try {
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                json_response(['error' => 'Email tersebut sudah digunakan akun lain. Gunakan email berbeda.'], 409);
            }
            throw $exception;
        }
        json_response(['message' => 'Akun admin berhasil dibuat untuk ' . $name . '.']);
    }

    if (in_array($action, ['admin_create', 'admin_update', 'admin_delete'], true)) {
        if ($method !== 'POST') {
            json_response(['error' => 'Metode permintaan tidak didukung.'], 405);
        }
        if (recipe_user_role($pdo) !== 'admin') {
            json_response(['error' => 'Hanya admin yang boleh mengelola resep.'], 403);
        }
        if ($action === 'admin_delete') {
            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                json_response(['error' => 'ID resep tidak valid.'], 422);
            }
            $find = $pdo->prepare('SELECT image FROM recipes WHERE id = ?');
            $find->execute([$id]);
            $recipe = $find->fetch();
            if (!$recipe) {
                json_response(['error' => 'Resep tidak ditemukan.'], 404);
            }
            $delete = $pdo->prepare('DELETE FROM recipes WHERE id = ?');
            $delete->execute([$id]);
            if (preg_match('#^uploads/recipes/[a-f0-9]{40}\.(jpg|png|webp)$#', (string) $recipe['image'])) {
                $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $recipe['image']);
                if (is_file($path) && !unlink($path)) {
                    error_log('Resepin could not remove image: ' . $path);
                }
            }
            json_response(['message' => 'Resep berhasil dihapus.']);
        }

        $id = $action === 'admin_update' ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) : null;
        if ($action === 'admin_update' && (!$id || $id < 1)) {
            json_response(['error' => 'ID resep tidak valid.'], 422);
        }
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $category = trim((string) ($data['category'] ?? 'Jajanan Pasar'));
        $duration = filter_var($data['duration'] ?? null, FILTER_VALIDATE_INT);
        $servings = filter_var($data['servings'] ?? null, FILTER_VALIDATE_INT);
        $difficulty = (string) ($data['difficulty'] ?? 'Mudah');
        if ($name === '' || mb_strlen($name) > 150 || $description === '' || mb_strlen($description) > 2000
            || $category === '' || mb_strlen($category) > 50
            || !$duration || $duration < 1 || $duration > 1440
            || !$servings || $servings < 1 || $servings > 100
            || !in_array($difficulty, ['Mudah', 'Sedang', 'Sulit'], true)) {
            json_response(['error' => 'Periksa nama, deskripsi, kategori, waktu (1–1440 menit), porsi (1–100), dan tingkat kesulitan.'], 422);
        }

        $ingredientLines = preg_split('/\r\n|\r|\n/', trim((string) ($data['ingredients'] ?? ''))) ?: [];
        $ingredients = [];
        foreach ($ingredientLines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || mb_strlen($parts[0]) > 50 || mb_strlen($parts[1]) > 150) {
                json_response(['error' => 'Format bahan per baris: takaran | nama bahan.'], 422);
            }
            $ingredients[] = ['amount' => $parts[0], 'name' => $parts[1]];
        }
        $instructions = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim((string) ($data['instructions'] ?? ''))) ?: [])));
        if (!$ingredients || count($ingredients) > 100 || !$instructions || count($instructions) > 100) {
            json_response(['error' => 'Resep harus memiliki bahan dan langkah (maksimal 100 baris masing-masing).'], 422);
        }
        foreach ($instructions as $step) {
            if (mb_strlen($step) > 1000) {
                json_response(['error' => 'Setiap langkah maksimal 1.000 karakter.'], 422);
            }
        }
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? ''))))));
        $tags = array_values(array_unique(array_merge(['Lokal Indonesia'], array_slice($tags, 0, 15))));
        foreach ($tags as $tag) {
            if (mb_strlen($tag) > 40) {
                json_response(['error' => 'Setiap tag maksimal 40 karakter.'], 422);
            }
        }

        $oldImage = 'assets/images/recipe-art.svg';
        if ($id !== null) {
            $find = $pdo->prepare('SELECT image FROM recipes WHERE id = ?');
            $find->execute([$id]);
            $existing = $find->fetch();
            if (!$existing) {
                json_response(['error' => 'Resep tidak ditemukan.'], 404);
            }
            $oldImage = (string) $existing['image'];
        }
        try {
            $newImage = uploaded_recipe_image($_FILES['image'] ?? null);
        } catch (InvalidArgumentException $exception) {
            json_response(['error' => $exception->getMessage()], 422);
        }
        $image = $newImage ?? $oldImage;
        $pdo->beginTransaction();
        try {
            $categoryStatement = $pdo->prepare(
                'INSERT INTO categories (name, icon, sort_order)
                 VALUES (?, ?, 99)
                 ON DUPLICATE KEY UPDATE icon = VALUES(icon)'
            );
            $categoryStatement->execute([$category, '🧁']);
            $fields = [
                $name,
                $description,
                $category,
                $duration,
                $difficulty,
                $servings,
                $image,
                json_encode($ingredients, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                json_encode($instructions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                !empty($data['featured']) ? 1 : 0,
            ];
            if ($id === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO recipes (name, description, category, duration, difficulty, servings, image, ingredients, instructions, tags, featured)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute($fields);
                $id = (int) $pdo->lastInsertId();
            } else {
                $fields[] = $id;
                $update = $pdo->prepare(
                    'UPDATE recipes SET name = ?, description = ?, category = ?, duration = ?, difficulty = ?, servings = ?,
                     image = ?, ingredients = ?, instructions = ?, tags = ?, featured = ? WHERE id = ?'
                );
                $update->execute($fields);
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newImage !== null) {
                $newPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newImage);
                if (is_file($newPath)) {
                    unlink($newPath);
                }
            }
            if ($exception->getCode() === '23000') {
                json_response(['error' => 'Nama resep tersebut sudah ada.'], 409);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newImage !== null) {
                $newPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newImage);
                if (is_file($newPath)) {
                    unlink($newPath);
                }
            }
            throw $exception;
        }

        if ($newImage !== null && preg_match('#^uploads/recipes/[a-f0-9]{40}\.(jpg|png|webp)$#', $oldImage)) {
            $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldImage);
            if (is_file($oldPath) && !unlink($oldPath)) {
                error_log('Resepin could not replace image: ' . $oldPath);
            }
        }
        json_response(['message' => $action === 'admin_create' ? 'Resep baru berhasil ditambahkan.' : 'Perubahan resep berhasil disimpan.', 'id' => $id]);
    }

    if ($action === 'favorite') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            json_response(['error' => 'ID resep tidak valid.'], 422);
        }
        $exists = $pdo->prepare('SELECT id FROM recipes WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            json_response(['error' => 'Resep tidak ditemukan.'], 404);
        }
        $check = $pdo->prepare('SELECT 1 FROM favorites WHERE session_key = ? AND recipe_id = ?');
        $check->execute([$favoriteKey, $id]);
        if ($check->fetchColumn()) {
            $remove = $pdo->prepare('DELETE FROM favorites WHERE session_key = ? AND recipe_id = ?');
            $remove->execute([$favoriteKey, $id]);
            json_response(['is_favorite' => false]);
        }
        $add = $pdo->prepare('INSERT INTO favorites (session_key, recipe_id) VALUES (?, ?)');
        $add->execute([$favoriteKey, $id]);
        json_response(['is_favorite' => true]);
    }

    if ($action === 'auth') {
        $authAction = (string) ($data['type'] ?? '');
        if ($authAction === 'logout') {
            unset($_SESSION['user']);
            json_response(['user' => null, 'message' => 'Kamu sudah keluar.']);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || strlen($password) > 72) {
            json_response(['error' => 'Masukkan email yang benar dan kata sandi 8–72 karakter.'], 422);
        }

        if ($authAction === 'admin_register') {
            if (!is_local_request()) {
                json_response(['error' => 'Pendaftaran admin langsung hanya dapat dilakukan dari komputer server.'], 403);
            }
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                json_response(['error' => 'Nama wajib diisi dan maksimal 80 karakter.'], 422);
            }

            $existing = $pdo->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ?');
            $existing->execute([$email]);
            $existingUser = $existing->fetch();
            if ($existingUser) {
                if ($existingUser['role'] === 'admin') {
                    json_response(['error' => 'Email tersebut sudah terdaftar sebagai admin.'], 409);
                }
                if (!password_verify($password, $existingUser['password_hash'])) {
                    json_response(['error' => 'Email sudah terdaftar sebagai pengguna. Masukkan kata sandi akun pengguna tersebut untuk menjadi admin.'], 401);
                }
                $promote = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                $promote->execute([$existingUser['id']]);
                $userId = (int) $existingUser['id'];
                $name = (string) $existingUser['name'];
            } else {
                $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
                try {
                    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        json_response(['error' => 'Email tersebut sudah terdaftar.'], 409);
                    }
                    throw $exception;
                }
                $userId = (int) $pdo->lastInsertId();
            }

            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'admin'];
        } elseif ($authAction === 'register') {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                json_response(['error' => 'Nama wajib diisi dan maksimal 80 karakter.'], 422);
            }
            $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
            try {
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    json_response(['error' => 'Email tersebut sudah terdaftar.'], 409);
                }
                throw $exception;
            }
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'user'];
        } elseif ($authAction === 'login' || $authAction === 'admin_login') {
            $query = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
            $query->execute([$email]);
            $user = $query->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                json_response(['error' => 'Email atau kata sandi tidak cocok.'], 401);
            }
            if ($authAction === 'admin_login' && $user['role'] !== 'admin') {
                json_response(['error' => 'Akun ini adalah akun pengguna, bukan admin. Masuk sebagai pengguna atau gunakan akun admin.'], 403);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        } else {
            json_response(['error' => 'Jenis autentikasi tidak dikenal.'], 422);
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json_response(['user' => $_SESSION['user'], 'csrf' => $_SESSION['csrf_token'], 'message' => 'Berhasil masuk.']);
    }

    if ($action === 'assistant') {
        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '' || mb_strlen($message) > 500) {
            json_response(['error' => 'Tulis pertanyaan maksimal 500 karakter.'], 422);
        }
        json_response(rule_engine_answer($pdo, $message));
    }

    json_response(['error' => 'Fitur tidak dikenal.'], 404);
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Resepin API error: ' . $exception->getMessage());
    json_response(['error' => 'Terjadi masalah pada server. Pastikan database Resepin sudah diimpor dan konfigurasi koneksi benar.'], 500);
}
