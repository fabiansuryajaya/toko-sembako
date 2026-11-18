<?php
declare(strict_types=1);

use App\Support\Auth;

require __DIR__ . '/../src/bootstrap.php';
Auth::start();
if (Auth::user() !== null) {
    header('Location: app.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken()) ?>">
    <title>Login — Toko Sembako</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">
    <main class="login-card" aria-labelledby="login-title">
        <h1 id="login-title">Toko Sembako</h1>
        <p class="muted">Masuk untuk melanjutkan</p>
        <form id="login-form" novalidate>
            <label for="username">Username</label>
            <input id="username" name="username" autocomplete="username" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
            <p class="form-feedback" role="alert" aria-live="polite"></p>
        </form>
    </main>
    <script type="module" src="assets/js/login.js"></script>
</body>
</html>
