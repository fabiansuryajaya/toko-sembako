<?php
declare(strict_types=1);

/** Mengirim potongan HTML halaman (pages/<slug>.php) hanya kalau user boleh membukanya. */

use App\Menu;
use App\Support\Auth;

require __DIR__ . '/../src/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$user = Auth::user();
$page = (string) ($_GET['p'] ?? '');

if ($user === null) {
    http_response_code(401);
    exit('<p>Sesi berakhir. <a href="index.php">Login lagi</a>.</p>');
}
if (!array_key_exists($page, Menu::PAGES)) {
    http_response_code(404);
    exit('<p>Halaman tidak ditemukan.</p>');
}
if (!Menu::allowed($page, $user['role'])) {
    http_response_code(403);
    exit('<p>Anda tidak punya akses ke halaman ini.</p>');
}

$isAdmin = $user['role'] === 'admin';
require __DIR__ . '/../src/Views/' . $page . '.php';
