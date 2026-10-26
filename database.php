<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'resepin';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . (getenv('RESEPIN_DB_PORT') ?: '3306') . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $_POST;
}

function is_local_request(): bool
{
    return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
}

function current_favorite_key(): string
{
    if (empty($_SESSION['favorite_key'])) {
        $_SESSION['favorite_key'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['favorite_key'];
}

function decode_recipe(array $recipe): array
{
    $recipe['ingredients'] = json_decode((string) $recipe['ingredients'], true) ?: [];
    $recipe['instructions'] = json_decode((string) $recipe['instructions'], true) ?: [];
    $recipe['tags'] = json_decode((string) $recipe['tags'], true) ?: [];
    $recipe['is_favorite'] = (bool) ($recipe['is_favorite'] ?? false);
    return $recipe;
}

function recipe_user_role(PDO $pdo): ?string
{
    $userId = filter_var($_SESSION['user']['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $statement = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $role = $statement->fetchColumn();
    if (!is_string($role)) {
        unset($_SESSION['user']);
        return null;
    }

    $_SESSION['user']['role'] = $role;
    return $role;
}

function uploaded_recipe_image(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Gambar gagal diunggah. Coba pilih file lain.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 5 MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
        throw new InvalidArgumentException('File bukan gambar yang valid atau dimensinya terlalu besar.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime]) || $imageInfo['mime'] !== $mime) {
        throw new InvalidArgumentException('Format gambar yang didukung: JPG, PNG, atau WEBP.');
    }

    $relativeDirectory = 'uploads/recipes';
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recipes';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder gambar tidak dapat dibuat. Periksa izin folder proyek.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Gambar tidak dapat disimpan. Periksa izin folder uploads.');
    }

    return $relativeDirectory . '/' . $filename;
}
