<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';

migrate();
sessionBoot();

$slug = $_GET['slug'] ?? '';
$st = db()->prepare('SELECT * FROM tags WHERE slug = ?');
$st->execute([$slug]);
$tag = $st->fetch();

if (!$tag) {
    http_response_code(404);
    echo layout('Tidak Ditemukan', '<h1>404</h1><p class="muted">Tag tidak ditemukan.</p>');
    exit;
}

$pst = db()->prepare(
    'SELECT p.id, p.title, p.slug, p.body, p.created_at, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     JOIN post_tags pt ON pt.post_id = p.id
     WHERE pt.tag_id = ? AND p.published = 1
     ORDER BY p.created_at DESC'
);
$pst->execute([$tag['id']]);
$posts = $pst->fetchAll();

ob_start();
?>
<h1>Tag: #<?= e($tag['name']) ?></h1>
<p class="muted"><?= count($posts) ?> post dengan tag ini</p>
<?php if (!$posts): ?>
    <p class="muted">Belum ada post terbit dengan tag ini.</p>
<?php else: ?>
    <?php foreach ($posts as $p): ?>
        <article class="post-card">
            <h2><a href="/post/view.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a></h2>
            <p class="meta">oleh <strong><?= e($p['username']) ?></strong> · <?= e($p['created_at']) ?></p>
            <p><?= e(excerpt($p['body'])) ?></p>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php
echo layout('Tag: ' . $tag['name'], ob_get_clean());
