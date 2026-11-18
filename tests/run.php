<?php
declare(strict_types=1);

/**
 * Test integrasi tanpa dependensi: php tests/run.php
 * Memakai database terpisah (default toko_sembako_test) yang DIKOSONGKAN setiap kali tes jalan.
 * Atur lewat env: TEST_DB_HOST, TEST_DB_PORT, TEST_DB_NAME, TEST_DB_USER, TEST_DB_PASS.
 */

if (PHP_SAPI !== 'cli') {
    exit('Jalankan dari terminal: php tests/run.php');
}

require __DIR__ . '/../src/bootstrap.php';

use App\Support\Db;

$name = getenv('TEST_DB_NAME') ?: 'toko_sembako_test';
if (!str_contains($name, 'test')) {
    fwrite(STDERR, "TEST_DB_NAME harus mengandung kata 'test' supaya database asli tidak terhapus.\n");
    exit(2);
}
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', getenv('TEST_DB_HOST') ?: '127.0.0.1', (int) (getenv('TEST_DB_PORT') ?: 3306), $name);
$pdo = new PDO($dsn, getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
]);
Db::use($pdo);

/** Kosongkan & isi ulang database tes. */
function reset_db(): void
{
    foreach (['schema.sql', 'seed.sql'] as $file) {
        foreach (App\Services\BackupService::split((string) file_get_contents(__DIR__ . '/../database/' . $file)) as $st) {
            Db::pdo()->exec($st);
        }
    }
}

$tests = [];
function test(string $name, callable $fn): void
{
    global $tests;
    $tests[$name] = $fn;
}

function eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg ? "$msg: " : '') . 'diharapkan ' . var_export($expected, true) . ', didapat ' . var_export($actual, true));
    }
}

function throws(int $status, callable $fn, string $msg = ''): App\Support\HttpError
{
    try {
        $fn();
    } catch (App\Support\HttpError $e) {
        eq($status, $e->status, $msg ?: 'kode HTTP');
        return $e;
    }
    throw new RuntimeException(($msg ? "$msg: " : '') . "seharusnya gagal dengan HTTP $status");
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$failed = 0;
foreach ($tests as $name => $fn) {
    reset_db();
    try {
        $fn();
        echo "  ✔ $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✘ $name\n      " . $e->getMessage() . "\n      di " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    }
}
echo "\n" . (count($tests) - $failed) . ' lulus, ' . $failed . " gagal\n";
exit($failed ? 1 : 0);
