<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/layout.php';

migrate();
sessionBoot();
$user = requireLogin();

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
        $slug = uniqueSlug($title);
        $st = db()->prepare(
            'INSERT INTO posts (user_id, title, slug, body, published) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$user['id'], $title, $slug, $body, $published ? 1 : 0]);
        syncTags((int) db()->lastInsertId(), $tagsInput);
        flash($published ? 'Post dipublikasikan! 🎉' : 'Draft disimpan.');
        header('Location: /post/view.php?slug=' . urlencode($slug));
        exit;
    }
}

ob_start();
?>
<h1>Tulis Post Baru</h1>
<?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card">
    <label>Judul
        <input type="text" name="title" required maxlength="200" value="<?= e($_POST['title'] ?? '') ?>">
    </label>
    <label>Isi
        <textarea name="body" rows="14" required placeholder="Tulis di sini... Format: # Judul besar, ## Subjudul, **tebal**, *miring*, `kode`, ![alt](url-gambar)"><?= e($_POST['body'] ?? '') ?></textarea>
    </label>
    <label>Tag <span class="muted">(pisahkan dengan koma, mis: php, tutorial, web)</span>
        <input type="text" name="tags" value="<?= e($_POST['tags'] ?? '') ?>" placeholder="php, tutorial">
    </label>
    <label>Tambah Gambar <span class="muted">(jpg/png/gif/webp, maks 5MB per file; otomatis jadi WebP kalau ekstensi GD tersedia)</span>
        <input type="file" name="images[]" multiple accept="image/*">
    </label>
    <p class="muted">Gambar yang diunggah akan ditambahkan di akhir isi post. Hapus baris <code>![gambar](...)</code> jika tidak mau dipakai.</p>
    <label class="checkbox">
        <input type="checkbox" name="published" checked>
        Publikasikan sekarang (uncheck = simpan draft)
    </label>
    <button type="submit">Simpan</button>
</form>
<?php
echo layout('Tulis Post', ob_get_clean());
