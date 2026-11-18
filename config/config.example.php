<?php
// Salin file ini menjadi config.php lalu sesuaikan. Tanpa config.php, aplikasi memakai nilai di file ini
// (cocok untuk XAMPP standar: user root tanpa password).
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'toko_sembako',
        'user'     => 'root',
        'password' => '',
    ],
    'timezone' => 'Asia/Jakarta',
    // Tampilkan detail error PHP di respons API. Jangan aktifkan di komputer toko.
    'debug' => false,
    // Batas ukuran file import database (MB) dan gambar tanda tangan (KB).
    'import_max_mb'    => 20,
    'signature_max_kb' => 1024,
];
