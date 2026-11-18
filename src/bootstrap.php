<?php
declare(strict_types=1);

/**
 * Titik awal semua request: autoload class App\*, konfigurasi, zona waktu, dan session.
 */

const APP_ROOT = __DIR__ . '/..';

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $file = APP_ROOT . '/config/config.php';
        $config = require (is_file($file) ? $file : APP_ROOT . '/config/config.example.php');
    }
    return $config;
}

date_default_timezone_set(app_config()['timezone'] ?? 'Asia/Jakarta');
