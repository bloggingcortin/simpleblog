<?php

declare(strict_types=1);

const DB_PATH = __DIR__ . '/../data/blog.sqlite';
const APP_NAME = 'BlogKita';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function migrate(): void
{
    db()->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            body TEXT NOT NULL,
            published INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS post_tags (
            post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
            PRIMARY KEY (post_id, tag_id)
        );
    SQL);

    db()->exec("CREATE INDEX IF NOT EXISTS idx_comments_post ON comments(post_id)");
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-') ?: bin2hex(random_bytes(4));
}

function uniqueSlug(string $title): string
{
    $slug = slugify($title);
    $base = $slug;
    $n = 2;
    $st = db()->prepare('SELECT 1 FROM posts WHERE slug = ?');
    $st->execute([$slug]);
    while ($st->fetch()) {
        $slug = $base . '-' . $n;
        $n++;
        $st->execute([$slug]);
    }
    return $slug;
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function sessionBoot(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function takeFlashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function currentUser(): ?array
{
    sessionBoot();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $st = db()->prepare('SELECT id, username, created_at FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: /auth/login.php');
        exit;
    }
    return $user;
}

function bodyMarkdown(string $body): string
{
    $body = e($body);
    $body = preg_replace('/`(.+?)`/s', '<code>$1</code>', $body);
    $body = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $body);
    $body = preg_replace('/!\[([^\]]*)\]\(([^\s)]+)\)/s', '<img src="$2" alt="$1" loading="lazy">', $body);
    $body = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/s', '<a href="$2" rel="noopener">$1</a>', $body);
    $body = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $body);
    $body = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $body);
    $body = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $body);
    $body = preg_replace("/\n{2,}/", "</p><p>", $body);
    $body = preg_replace("/\n/", '<br>', $body);
    return '<p>' . $body . '</p>';
}

function parseTags(string $input): array
{
    $tags = [];
    foreach (explode(',', $input) as $t) {
        $t = trim(mb_substr($t, 0, 30, 'UTF-8'));
        if ($t !== '') {
            $tags[mb_strtolower($t, 'UTF-8')] = $t;
        }
    }
    return array_values($tags);
}

function syncTags(int $postId, string $input): void
{
    $db = db();
    $db->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
    foreach (parseTags($input) as $name) {
        $slug = slugify($name);
        if ($slug === '') {
            continue;
        }
        $st = $db->prepare('SELECT id FROM tags WHERE slug = ?');
        $st->execute([$slug]);
        $tag = $st->fetch();
        if ($tag) {
            $tagId = (int) $tag['id'];
        } else {
            $db->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            $tagId = (int) $db->lastInsertId();
        }
        $db->prepare('INSERT OR IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)')->execute([$postId, $tagId]);
    }
}

function postTags(int $postId): array
{
    $st = db()->prepare(
        'SELECT t.name, t.slug FROM tags t
         JOIN post_tags pt ON pt.tag_id = t.id
         WHERE pt.post_id = ? ORDER BY t.name'
    );
    $st->execute([$postId]);
    return $st->fetchAll();
}

function handleImageUploads(array $files, string &$error): array
{
    $markdown = [];
    $dir = __DIR__ . '/../public/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        return [];
    }
    $hasGd = function_exists('imagecreatefromstring') && function_exists('imagewebp');
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $error = 'Upload gambar gagal (kode ' . (int) $files['error'][$i] . ').';
            continue;
        }
        if ($files['size'][$i] > 5 * 1024 * 1024) {
            $error = 'Gambar maksimal 5MB.';
            continue;
        }
        $tmp = $files['tmp_name'][$i];
        $data = @file_get_contents($tmp);
        if ($data === false) {
            $error = 'Gagal membaca file upload.';
            continue;
        }

        if ($hasGd) {
            $img = @imagecreatefromstring($data);
            if (!$img) {
                $error = 'File bukan gambar yang valid.';
                continue;
            }
            $name = bin2hex(random_bytes(8)) . '.webp';
            if (!imagewebp($img, $dir . '/' . $name, 82)) {
                $error = 'Konversi WebP gagal.';
                imagedestroy($img);
                continue;
            }
            imagedestroy($img);
            $markdown[] = PHP_EOL . PHP_EOL . '![gambar](/uploads/' . $name . ')';
        } else {
            $mime = (finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data) ?: '');
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $error = 'File bukan gambar yang valid.';
                continue;
            }
            $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (@file_put_contents($dir . '/' . $name, $data) === false) {
                $error = 'Gagal menyimpan gambar.';
                continue;
            }
            $markdown[] = PHP_EOL . PHP_EOL . '![gambar](/uploads/' . $name . ')';
        }
    }
    return $markdown;
}

function excerpt(string $body, int $len = 200): string
{
    $plain = strip_tags(bodyMarkdown($body));
    return mb_strlen($plain) > $len ? mb_substr($plain, 0, $len) . '…' : $plain;
}
