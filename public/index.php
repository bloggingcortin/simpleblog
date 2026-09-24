<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';

migrate();
sessionBoot();

$st = db()->prepare(
    'SELECT p.id, p.title, p.slug, p.body, p.created_at, u.username
     FROM posts p JOIN users u ON u.id = p.user_id
     WHERE p.published = 1
     ORDER BY p.created_at DESC'
);
$st->execute();
$posts = $st->fetchAll();

$tst = db()->query(
    'SELECT t.name, t.slug, COUNT(pt.post_id) AS n
     FROM tags t JOIN post_tags pt ON pt.tag_id = t.id
     JOIN posts p ON p.id = pt.post_id AND p.published = 1
     GROUP BY t.id ORDER BY n DESC, t.name LIMIT 20'
);
$allTags = $tst->fetchAll();

ob_start();
?>
<h1>Post Terbaru</h1>
<?php if (!$posts): ?>
    <p class="muted">Belum ada post. Jadilah yang pertama menulis! ✍️</p>
<?php else: ?>
    <?php foreach ($posts as $p): ?>
        <?php
        $tg = postTags((int) $p['id']);
        ?>
        <article class="post-card">
            <h2><a href="/post/view.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a></h2>
            <p class="meta">oleh <strong><?= e($p['username']) ?></strong> · <?= e($p['created_at']) ?></p>
            <?php if ($tg): ?>
                <p class="tag-list">
                    <?php foreach ($tg as $t): ?>
                        <a class="tag" href="/tag.php?slug=<?= urlencode($t['slug']) ?>">#<?= e($t['name']) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <p><?= e(excerpt($p['body'])) ?></p>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($allTags): ?>
    <section class="tag-cloud">
        <h2>Tag Populer</h2>
        <p class="tag-list">
            <?php foreach ($allTags as $t): ?>
                <a class="tag" href="/tag.php?slug=<?= urlencode($t['slug']) ?>">#<?= e($t['name']) ?> <span class="tag-n"><?= (int) $t['n'] ?></span></a>
            <?php endforeach; ?>
        </p>
    </section>
<?php endif; ?>
<?php
echo layout('Beranda', ob_get_clean(), takeFlashes());
