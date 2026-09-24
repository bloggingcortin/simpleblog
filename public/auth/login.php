<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/layout.php';

migrate();
sessionBoot();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        flash('Halo lagi, ' . $user['username'] . '! 👋');
        header('Location: /');
        exit;
    }
    $errors[] = 'Username atau password salah.';
}

ob_start();
?>
<h1>Masuk</h1>
<?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" class="card form-narrow">
    <label>Username
        <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Masuk</button>
    <p class="muted">Belum punya akun? <a href="/auth/register.php">Daftar</a></p>
</form>
<?php
echo layout('Masuk', ob_get_clean());
