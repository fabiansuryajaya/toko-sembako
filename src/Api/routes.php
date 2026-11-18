<?php
declare(strict_types=1);

use App\Services\BackupService;
use App\Services\MasterService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\SettingService;
use App\Services\UserService;
use App\Support\Auth;
use App\Support\HttpError;
use App\Support\Input;

/**
 * Tabel rute API: "METODE resource" => [akses, handler].
 * akses: 'public' (tanpa login), 'user' (admin & pegawai), 'admin'.
 * handler menerima (Input $query, Input $body, array|null $user) dan mengembalikan data untuk dikirim sebagai JSON.
 */

$id = static fn (Input $q): int => $q->id('id');

return [
    // ---------- auth ----------
    'POST auth/login' => ['public', static function (Input $q, Input $b): array {
        $user = Auth::login($b->str('username', 'Username', 50), $b->str('password', 'Password', 200));
        return ['user' => $user, 'csrf' => Auth::csrfToken()];
    }],
    'POST auth/logout' => ['public', static function (): ?array {
        Auth::logout();
        return null;
    }],
    'GET auth/me' => ['user', static fn (Input $q, Input $b, array $u): array => [
        'user' => $u,
        'default_password' => Auth::usesDefaultPassword(),
    ]],
    'PUT auth/password' => ['user', static function (Input $q, Input $b, array $u): ?array {
        UserService::changeOwnPassword($u['id'], $b);
        Auth::clearDefaultPasswordFlag();
        return null;
    }],

    // ---------- barang ----------
    'GET products' => ['user', static function (Input $q) use ($id): array {
        $admin = Auth::isAdmin();
        if ($q->has('id')) {
            return ProductService::get($id($q), $admin);
        }
        return ProductService::list($q->raw('status', 'aktif') !== 'semua', $admin);
    }],
    'POST products'        => ['admin', static fn (Input $q, Input $b): array => ProductService::save($b)],
    'PUT products'         => ['admin', static fn (Input $q, Input $b): array => ProductService::save($b, $id($q))],
    'DELETE products'      => ['admin', static fn (Input $q): ?array => ProductService::deactivate($id($q))],
    'PUT products/prices'  => ['admin', static fn (Input $q, Input $b): array => ['updated' => ProductService::updatePrices($b->list('items', 'Perubahan harga'))]],

    // ---------- supplier, satuan, member ----------
    'GET suppliers'    => ['user', static fn (Input $q): array => $q->has('id') ? MasterService::supplier($id($q)) : MasterService::suppliers()],
    'POST suppliers'   => ['admin', static fn (Input $q, Input $b): array => MasterService::saveSupplier($b)],
    'PUT suppliers'    => ['admin', static fn (Input $q, Input $b): array => MasterService::saveSupplier($b, $id($q))],
    'DELETE suppliers' => ['admin', static fn (Input $q): ?array => MasterService::deactivateSupplier($id($q))],

    'GET units'    => ['user', static fn (Input $q): array => $q->has('id') ? MasterService::unit($id($q)) : MasterService::units()],
    'POST units'   => ['admin', static fn (Input $q, Input $b): array => MasterService::saveUnit($b)],
    'PUT units'    => ['admin', static fn (Input $q, Input $b): array => MasterService::saveUnit($b, $id($q))],
    'DELETE units' => ['admin', static fn (Input $q): ?array => MasterService::deactivateUnit($id($q))],

    // pegawai perlu daftar member untuk mencatat hutang
    'GET members'    => ['user', static fn (Input $q): array => $q->has('id') ? MasterService::member($id($q)) : MasterService::members()],
    'POST members'   => ['admin', static fn (Input $q, Input $b): array => MasterService::saveMember($b)],
    'PUT members'    => ['admin', static fn (Input $q, Input $b): array => MasterService::saveMember($b, $id($q))],
    'DELETE members' => ['admin', static fn (Input $q): ?array => MasterService::deleteMember($id($q))],

    // ---------- user ----------
    'GET users'    => ['admin', static fn (Input $q): array => $q->has('id') ? UserService::get($id($q)) : UserService::list()],
    'POST users'   => ['admin', static fn (Input $q, Input $b): array => UserService::save($b)],
    'PUT users'    => ['admin', static fn (Input $q, Input $b): array => UserService::save($b, $id($q))],
    'DELETE users' => ['admin', static fn (Input $q, Input $b, array $u): ?array => UserService::delete($id($q), $u['id'])],

    // ---------- penjualan tunai ----------
    'GET sales' => ['user', static fn (Input $q): array => $q->has('id')
        ? SaleService::get($id($q), Auth::isAdmin(), SaleService::TUNAI)
        : SaleService::list(SaleService::TUNAI, $q->date('from', 'Tanggal awal'), $q->date('to', 'Tanggal akhir'))],
    'POST sales'   => ['user', static fn (Input $q, Input $b, array $u): array => SaleService::create(SaleService::TUNAI, $b, $u)],
    'PUT sales'    => ['user', static fn (Input $q, Input $b, array $u): array => SaleService::update($id($q), SaleService::TUNAI, $b, $u)],
    'DELETE sales' => ['admin', static fn (Input $q): ?array => SaleService::delete($id($q), SaleService::TUNAI)],

    // ---------- hutang ----------
    'GET debts' => ['user', static fn (Input $q): array => $q->has('id')
        ? SaleService::get($id($q), Auth::isAdmin(), SaleService::HUTANG)
        : SaleService::list(SaleService::HUTANG, $q->date('from', 'Tanggal awal'), $q->date('to', 'Tanggal akhir'))],
    'POST debts'     => ['user', static fn (Input $q, Input $b, array $u): array => SaleService::create(SaleService::HUTANG, $b, $u)],
    'PUT debts'      => ['user', static fn (Input $q, Input $b, array $u): array => SaleService::update($id($q), SaleService::HUTANG, $b, $u)],
    'DELETE debts'   => ['admin', static fn (Input $q): ?array => SaleService::delete($id($q), SaleService::HUTANG)],
    'PUT debts/paid' => ['admin', static fn (Input $q, Input $b): array => SaleService::setPaid($id($q), $b->bool('lunas'))],

    // ---------- restock ----------
    'GET purchases' => ['admin', static fn (Input $q): array => $q->has('id')
        ? PurchaseService::get($id($q))
        : PurchaseService::list($q->date('from', 'Tanggal awal'), $q->date('to', 'Tanggal akhir'))],
    'POST purchases' => ['admin', static fn (Input $q, Input $b, array $u): array => PurchaseService::create($b, $u['id'])],

    // ---------- laporan ----------
    'GET report' => ['admin', static fn (Input $q): array => ReportService::sales(
        $q->date('from', 'Tanggal awal'),
        $q->date('to', 'Tanggal akhir'),
        $q->ids('product_ids', 'Barang'),
    )],
    'DELETE report' => ['admin', static function (Input $q, Input $b): array {
        if ($b->raw('konfirmasi') !== 'HAPUS') {
            throw HttpError::badRequest('Ketik HAPUS untuk konfirmasi');
        }
        return ['deleted' => ReportService::purge($q->date('from', 'Tanggal awal'), $q->date('to', 'Tanggal akhir'))];
    }],

    // ---------- pengaturan ----------
    'GET settings'            => ['user', static fn (): array => SettingService::all()],
    'PUT settings'            => ['admin', static fn (Input $q, Input $b): array => SettingService::update((array) $b->raw('values', []))],
    'POST settings/signature' => ['admin', static fn (): array => SettingService::saveSignature($_FILES['signature'] ?? [])],
    'DELETE settings/signature' => ['admin', static fn (): array => SettingService::deleteSignature()],

    // ---------- backup ----------
    'GET backup/export' => ['admin', static function (): never {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="toko-sembako-' . date('Ymd-His') . '.sql"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'wb');
        BackupService::export($out);
        fclose($out);
        exit;
    }],
    'POST backup/import' => ['admin', static function (): array {
        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw HttpError::badRequest('Pilih file backup (.sql) terlebih dahulu');
        }
        $maxMb = (int) (app_config()['import_max_mb'] ?? 20);
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw HttpError::badRequest("Ukuran file maksimal $maxMb MB");
        }
        return BackupService::import((string) file_get_contents($file['tmp_name']));
    }],
];
