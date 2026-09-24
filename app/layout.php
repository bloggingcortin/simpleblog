<?php

declare(strict_types=1);

function layout(string $title, string $content, array $flashes = []): string
{
    $flashHtml = '';
    foreach ($flashes as $f) {
        $cls = $f['type'] === 'error' ? 'error' : 'success';
        $flashHtml .= '<div class="flash ' . $cls . '">' . e($f['msg']) . '</div>';
    }
    $user = currentUser();
    $nav = '<a href="/">Beranda</a><a href="/search.php">Cari</a>';
    if ($user) {
        $nav .= '<a href="/post/create.php">Tulis Post</a>'
            . '<a href="/post/mine.php">Post Saya</a>'
            . '<span class="nav-user">' . e($user['username']) . '</span>'
            . '<a href="/auth/logout.php">Keluar</a>';
    } else {
        $nav .= '<a href="/auth/login.php">Masuk</a>'
            . '<a href="/auth/register.php">Daftar</a>';
    }

    $appName = APP_NAME;
    return <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$title} · {$appName}</title>
        <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body>
        <nav>
            <a class="brand" href="/">📝 {$appName}</a>
            <div class="nav-links">{$nav}</div>
        </nav>
        <div class="flash-area">{$flashHtml}</div>
        <main>
            {$content}
        </main>
        <footer>
            <p>Dibuat dengan PHP + SQLite · {$appName}</p>
        </footer>
    </body>
    </html>
    HTML;
}
