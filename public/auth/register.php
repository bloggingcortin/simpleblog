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

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username 3-20 karakter, hanya huruf/angka/underscore.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $st->execute([$username]);
        if ($st->fetch()) {
            $errors[] = 'Username sudah dipakai.';
        }
    }

    if (!$errors) {
        $st = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        flash('Selamat datang, ' . $username . '! 🎉');
        header('Location: /');
        exit;
    }
}

ob_start();
?>
<h1>Daftar</h1>
<?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" class="card form-narrow">
    <label>Username
        <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
    </label>
    <label>Password
        <input type="password" name="password" required minlength="6">
    </label>
    <button type="submit">Daftar</button>
    <p class="muted">Sudah punya akun? <a href="/auth/login.php">Masuk</a></p>
</form>
<?php
echo layout('Daftar', ob_get_clean());
