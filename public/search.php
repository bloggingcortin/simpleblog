<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';

migrate();
sessionBoot();

$q = trim($_GET['q'] ?? '');
$posts = [];

if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $st = db()->prepare(
        "SELECT p.id, p.title, p.slug, p.body, p.created_at, u.username
         FROM posts p JOIN users u ON u.id = p.user_id
         WHERE p.published = 1 AND (p.title LIKE ? ESCAPE '\\' OR p.body LIKE ? ESCAPE '\\')
         ORDER BY p.created_at DESC"
    );
    $st->execute([$like, $like]);
    $posts = $st->fetchAll();
}

ob_start();
?>
<h1>Cari Post</h1>
<form method="get" class="card form-narrow search-form">
    <label>Kata kunci
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari judul atau isi post..." autofocus>
    </label>
    <button type="submit">Cari</button>
</form>

<?php if ($q !== ''): ?>
    <h2>Hasil untuk &quot;<?= e($q) ?>&quot; (<?= count($posts) ?>)</h2>
    <?php if (!$posts): ?>
        <p class="muted">Tidak ada post yang cocok. Coba kata kunci lain.</p>
    <?php else: ?>
        <?php foreach ($posts as $p): ?>
            <article class="post-card">
                <h2><a href="/post/view.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a></h2>
                <p class="meta">oleh <strong><?= e($p['username']) ?></strong> · <?= e($p['created_at']) ?></p>
                <p><?= e(excerpt($p['body'])) ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
<?php
echo layout('Cari', ob_get_clean());
