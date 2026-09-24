<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/layout.php';

migrate();
sessionBoot();
$user = requireLogin();

$st = db()->prepare(
    'SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC'
);
$st->execute([$user['id']]);
$posts = $st->fetchAll();

ob_start();
?>
<h1>Post Saya</h1>
<?php if (!$posts): ?>
    <p class="muted">Kamu belum menulis apa pun. <a href="/post/create.php">Tulis post pertamamu →</a></p>
<?php else: ?>
    <table class="table">
        <tr><th>Judul</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td><a href="/post/view.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a></td>
                <td><?= $p['published'] ? '<span class="badge ok">terbit</span>' : '<span class="badge">draft</span>' ?></td>
                <td><?= e($p['created_at']) ?></td>
                <td>
                    <a href="/post/edit.php?id=<?= (int) $p['id'] ?>">Edit</a> ·
                    <a href="/post/delete.php?id=<?= (int) $p['id'] ?>" class="danger" onclick="return confirm('Yakin hapus post ini?')">hapus</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<?php
echo layout('Post Saya', ob_get_clean());
