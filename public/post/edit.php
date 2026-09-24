<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/layout.php';

migrate();
sessionBoot();
$user = requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT * FROM posts WHERE id = ? AND user_id = ?');
$st->execute([$id, $user['id']]);
$post = $st->fetch();

if (!$post) {
    http_response_code(404);
    echo layout('Tidak Ditemukan', '<h1>404</h1><p class="muted">Post tidak ditemukan atau bukan milikmu.</p>');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $published = isset($_POST['published']);
    $tagsInput = trim($_POST['tags'] ?? '');

    if ($title === '') {
        $errors[] = 'Judul wajib diisi.';
    }
    if ($body === '') {
        $errors[] = 'Isi post tidak boleh kosong.';
    }

    $imgError = '';
    $imgMd = handleImageUploads($_FILES['images'] ?? [], $imgError);
    if ($imgError !== '') {
        $errors[] = $imgError;
    }
    $body .= implode('', $imgMd);

    if (!$errors) {
        db()->prepare('UPDATE posts SET title = ?, body = ?, published = ?, updated_at = datetime(\'now\') WHERE id = ?')
            ->execute([$title, $body, $published ? 1 : 0, $post['id']]);
        syncTags((int) $post['id'], $tagsInput);
        flash('Post diperbarui. ✏️');
        header('Location: /post/view.php?slug=' . urlencode($post['slug']));
        exit;
    }
    $post['title'] = $title;
    $post['body'] = $body;
    $post['published'] = $published ? 1 : 0;
    $tagsInput = $tagsInput;
} else {
    $tagsInput = implode(', ', array_column(postTags((int) $post['id']), 'name'));
}

ob_start();
?>
<h1>Edit Post</h1>
<?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card">
    <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
    <label>Judul
        <input type="text" name="title" required maxlength="200" value="<?= e($post['title']) ?>">
    </label>
    <label>Isi
        <textarea name="body" rows="14" required><?= e($post['body']) ?></textarea>
    </label>
    <label>Tag <span class="muted">(pisahkan dengan koma, mis: php, tutorial, web)</span>
        <input type="text" name="tags" value="<?= e($tagsInput) ?>" placeholder="php, tutorial">
    </label>
    <label>Tambah Gambar <span class="muted">(otomatis dikonversi ke WebP, maks 5MB per file)</span>
        <input type="file" name="images[]" multiple accept="image/*">
    </label>
    <p class="muted">Gambar yang diunggah akan ditambahkan di akhir isi post. Hapus baris <code>![gambar](...)</code> jika tidak mau dipakai.</p>
    <label class="checkbox">
        <input type="checkbox" name="published" <?= $post['published'] ? 'checked' : '' ?>>
        Publikasikan (uncheck = draft)
    </label>
    <button type="submit">Simpan Perubahan</button>
</form>
<?php
echo layout('Edit Post', ob_get_clean());
