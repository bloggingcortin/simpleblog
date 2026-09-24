<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/layout.php';

migrate();
sessionBoot();

$slug = $_GET['slug'] ?? '';
$st = db()->prepare(
    'SELECT p.*, u.username FROM posts p JOIN users u ON u.id = p.user_id
     WHERE p.slug = ?'
);
$st->execute([$slug]);
$post = $st->fetch();

if (!$post || (!$post['published'] && (!currentUser() || currentUser()['id'] != $post['user_id']))) {
    http_response_code(404);
    echo layout('Tidak Ditemukan', '<h1>404</h1><p class="muted">Post tidak ditemukan atau belum dipublikasikan.</p>');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $user = requireLogin();
    $commentBody = trim($_POST['comment_body'] ?? '');
    if ($commentBody !== '' && mb_strlen($commentBody) <= 2000) {
        db()->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)')
            ->execute([$post['id'], $user['id'], $commentBody]);
        flash('Komentar terkirim. 💬');
    }
    header('Location: /post/view.php?slug=' . urlencode($post['slug']) . '#komentar');
    exit;
}

$tags = postTags((int) $post['id']);

$cst = db()->prepare(
    'SELECT c.body, c.created_at, u.username FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.post_id = ? ORDER BY c.created_at ASC'
);
$cst->execute([$post['id']]);
$comments = $cst->fetchAll();

$mine = currentUser() && currentUser()['id'] == $post['user_id'];

ob_start();
?>
<article class="post-full">
    <h1><?= e($post['title']) ?></h1>
    <p class="meta">oleh <strong><?= e($post['username']) ?></strong> · <?= e($post['created_at']) ?><?= $post['published'] ? '' : ' · <span class="badge">draft</span>' ?></p>
    <?php if ($tags): ?>
        <p class="tag-list">
            <?php foreach ($tags as $t): ?>
                <a class="tag" href="/tag.php?slug=<?= urlencode($t['slug']) ?>">#<?= e($t['name']) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <div class="post-body"><?= bodyMarkdown($post['body']) ?></div>
    <?php if ($mine): ?>
        <p class="owner-actions">
            <a href="/post/edit.php?id=<?= (int) $post['id'] ?>">✏️ Edit</a>
        </p>
    <?php endif; ?>
</article>

<section id="komentar" class="comments">
    <h2>Komentar (<?= count($comments) ?>)</h2>
    <?php if (!$comments): ?>
        <p class="muted">Belum ada komentar. Jadilah yang pertama!</p>
    <?php else: ?>
        <?php foreach ($comments as $cm): ?>
            <div class="comment">
                <p class="meta"><strong><?= e($cm['username']) ?></strong> · <?= e($cm['created_at']) ?></p>
                <p><?= nl2br(e($cm['body'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (currentUser()): ?>
        <form method="post" class="card comment-form">
            <input type="hidden" name="comment" value="1">
            <label>Tulis Komentar
                <textarea name="comment_body" rows="3" required maxlength="2000" placeholder="Bagikan pendapatmu..."></textarea>
            </label>
            <button type="submit">Kirim Komentar</button>
        </form>
    <?php else: ?>
        <p class="muted"><a href="/auth/login.php">Masuk</a> untuk berkomentar.</p>
    <?php endif; ?>
</section>
<?php
echo layout($post['title'], ob_get_clean(), takeFlashes());
