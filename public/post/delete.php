<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

migrate();
sessionBoot();
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $st = db()->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
    $st->execute([$id, $user['id']]);
    flash('Post dihapus.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $st = db()->prepare('SELECT title FROM posts WHERE id = ? AND user_id = ?');
    $st->execute([$id, $user['id']]);
    $post = $st->fetch();
    if ($post) {
        require_once __DIR__ . '/../../app/layout.php';
        $content = '<h1>Hapus Post</h1>
            <p>Yakin hapus <strong>' . e($post['title']) . '</strong>? Tindakan ini tidak bisa dibatalkan.</p>
            <form method="post" class="card form-narrow">
                <input type="hidden" name="id" value="' . (int) $id . '">
                <button type="submit" class="danger-btn">Ya, Hapus</button>
                <a href="/post/mine.php">Batal</a>
            </form>';
        echo layout('Hapus Post', $content);
        exit;
    }
}

header('Location: /post/mine.php');
