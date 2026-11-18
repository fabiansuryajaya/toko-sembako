<?php
declare(strict_types=1);

use App\Menu;
use App\Support\Auth;

require __DIR__ . '/../src/bootstrap.php';
$user = Auth::user();
if ($user === null) {
    header('Location: index.php');
    exit;
}
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
$v = static fn (string $path): string => $path . '?v=' . filemtime(__DIR__ . '/' . $path); // cache-busting otomatis
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $e(Auth::csrfToken()) ?>">
    <title>Toko Sembako</title>
    <link rel="stylesheet" href="<?= $v('assets/vendor/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= $v('assets/css/app.css') ?>">
</head>
<body data-role="<?= $e($user['role']) ?>" data-username="<?= $e($user['username']) ?>" data-user-id="<?= (int) $user['id'] ?>">
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-head">
            <strong>Toko Sembako</strong>
            <span class="muted"><?= $e($user['username']) ?> · <?= $e($user['role']) ?></span>
        </div>
        <nav>
            <ul class="menu">
                <?php foreach (Menu::forRole($user['role']) as $group => $items): ?>
                    <?php if (is_string($group)): ?>
                        <li class="menu-group">
                            <span class="menu-group-label"><?= $e($group) ?></span>
                            <ul>
                                <?php foreach ($items as $slug => $label): ?>
                                    <li><a href="#<?= $e($slug) ?>" data-page="<?= $e($slug) ?>"><?= $e($label) ?></a></li>
                                <?php endforeach ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <?php foreach ($items as $slug => $label): ?>
                            <li><a href="#<?= $e($slug) ?>" data-page="<?= $e($slug) ?>"><?= $e($label) ?></a></li>
                        <?php endforeach ?>
                    <?php endif ?>
                <?php endforeach ?>
                <li><a href="#" id="logout-link">Keluar</a></li>
            </ul>
        </nav>
    </aside>

    <main class="screen">
        <div id="default-password-warning" class="alert alert-warning" hidden>
            Akun ini masih memakai password awal. <a href="#akun">Ganti password sekarang</a>.
        </div>
        <div id="page"></div>
    </main>
</div>
<div id="toasts" aria-live="polite"></div>

<script src="<?= $v('assets/vendor/jquery.min.js') ?>"></script>
<script src="<?= $v('assets/vendor/select2.min.js') ?>"></script>
<script type="module" src="<?= $v('assets/js/app.js') ?>"></script>
</body>
</html>
