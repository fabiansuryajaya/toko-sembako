<?php
declare(strict_types=1);

use App\Services\BackupService;
use App\Services\MasterService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\SettingService;
use App\Services\UserService;
use App\Support\Db;
use App\Support\Input;

test('restock menambah stok dan memperbarui modal', function () {
    $p = PurchaseService::create(new Input(['items' => [['product_id' => 1, 'qty' => 20, 'harga_beli' => 12500], ['product_id' => 3, 'qty' => 5]]]), 1);
    eq(20 * 12500 + 5 * 15000, $p['total']);
    eq(120, stok(1));
    eq(12500, (int) Db::value('SELECT harga_beli FROM products WHERE id = 1'));
    eq(15000, (int) Db::value('SELECT harga_beli FROM products WHERE id = 3'), 'tanpa harga baru, modal tetap');
    eq(1, count(PurchaseService::list(date('Y-m-d'), date('Y-m-d'))));
});

test('produk: validasi, sembunyikan modal untuk pegawai, ubah harga massal', function () {
    throws(400, fn () => ProductService::save(new Input(['nama' => '', 'supplier_id' => 1, 'unit_id' => 1, 'harga_beli' => 1, 'harga_jual' => 1])));
    throws(400, fn () => ProductService::save(new Input(['nama' => 'X', 'supplier_id' => 1, 'unit_id' => 1, 'harga_beli' => -1, 'harga_jual' => 1])));
    throws(404, fn () => ProductService::save(new Input(['nama' => 'X', 'supplier_id' => 99, 'unit_id' => 1, 'harga_beli' => 1, 'harga_jual' => 1])));
    $p = ProductService::save(new Input(['nama' => 'Kecap', 'supplier_id' => 3, 'unit_id' => 3, 'harga_beli' => 8000, 'harga_jual' => 9500, 'stok' => 12, 'deskripsi' => "baris 1\nbaris 2"]));
    eq('Kecap', $p['nama']);
    eq(false, array_key_exists('harga_beli', ProductService::list(true, false)[0]));

    ProductService::updatePrices([new Input(['id' => $p['id'], 'harga_jual' => 10000]), new Input(['id' => 1, 'harga_beli' => 11000])]);
    eq(10000, ProductService::get($p['id'], true)['harga_jual']);
    eq(8000, ProductService::get($p['id'], true)['harga_beli']);
    eq(11000, ProductService::get(1, true)['harga_beli']);

    ProductService::deactivate($p['id']);
    eq(5, count(ProductService::list(true, true)));
    eq(6, count(ProductService::list(false, true)));
});

test('member dengan riwayat transaksi tidak bisa dihapus', function () {
    $m = MasterService::saveMember(new Input(['nama' => 'Bu Ani', 'nomor_hp' => '0812-1111']));
    throws(400, fn () => MasterService::saveMember(new Input(['nama' => 'Bu Ani', 'nomor_hp' => 'abc'])));
    SaleService::create('hutang', new Input(['items' => [['product_id' => 1, 'qty' => 1]], 'member_id' => $m['id']]), ['id' => 1, 'role' => 'admin']);
    throws(409, fn () => MasterService::deleteMember($m['id']));
    $kosong = MasterService::saveMember(new Input(['nama' => 'Baru', 'nomor_hp' => '0813']));
    MasterService::deleteMember($kosong['id']);
    eq(1, count(MasterService::members()));
});

test('user: password di-hash, username unik, admin terakhir & diri sendiri terlindungi', function () {
    $u = UserService::save(new Input(['username' => 'kasir2', 'password' => 'rahasia1']));
    $hash = (string) Db::value('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    eq(true, password_verify('rahasia1', $hash));
    eq(false, str_contains($hash, 'rahasia1'));
    throws(409, fn () => UserService::save(new Input(['username' => 'kasir2', 'password' => 'rahasia1'])));
    throws(400, fn () => UserService::save(new Input(['username' => 'k3', 'password' => '123'])), 'password terlalu pendek');
    throws(409, fn () => UserService::delete(1, 1), 'hapus diri sendiri');
    throws(409, fn () => UserService::save(new Input(['username' => 'admin', 'role' => 'pegawai']), 1), 'admin terakhir');
    UserService::delete($u['id'], 1);

    UserService::changeOwnPassword(2, new Input(['password_lama' => 'pegawai', 'password_baru' => 'baru-123']));
    throws(400, fn () => UserService::changeOwnPassword(2, new Input(['password_lama' => 'salah', 'password_baru' => 'baru-456'])));
});

test('pengaturan hanya menerima nama yang dikenal', function () {
    $s = SettingService::update(['nama_toko' => 'TK. Maju', 'tampilkan_ttd_nota_hutang' => true]);
    eq('TK. Maju', $s['nama_toko']);
    eq(true, $s['tampilkan_ttd_nota_hutang']);
    throws(400, fn () => SettingService::update(['gambar_ttd' => '../../config/config.php']));
    throws(400, fn () => SettingService::update(['tidak_ada' => 'x']));
});

test('backup: export lalu import mengembalikan data persis', function () {
    SaleService::create('tunai', new Input(['items' => [['product_id' => 1, 'qty' => 2]], 'nama_pembeli' => "O'Brien; \"kutip\"\nbaris"]), ['id' => 1, 'role' => 'admin']);
    $fh = fopen('php://memory', 'w+b');
    BackupService::export($fh);
    rewind($fh);
    $sql = stream_get_contents($fh);

    Db::run('DELETE FROM sales');
    Db::run('UPDATE products SET stok = 0');
    $r = BackupService::import($sql);
    eq(true, $r['statements'] > 10);
    eq("O'Brien; \"kutip\"\nbaris", Db::value('SELECT nama_pembeli FROM sales'));
    eq(98, stok(1));
    @unlink(BackupService::BACKUP_DIR . '/' . $r['safety_backup']);
});

test('backup: import menolak file asing dan perintah berbahaya', function () {
    throws(400, fn () => BackupService::import("DROP DATABASE toko;\n"));
    throws(400, fn () => BackupService::import("-- toko-sembako backup\nDROP TABLE IF EXISTS `users`;\nGRANT ALL ON *.* TO x;\n"));
    eq(2, (int) Db::value('SELECT COUNT(*) FROM users'), 'tidak ada yang dijalankan');
});

test('pemecah SQL memperhatikan kutip dan komentar', function () {
    eq(["INSERT INTO t VALUES ('a;b', 'c\\'d;')", 'SELECT 1'], BackupService::split("-- komentar; abc\nINSERT INTO t VALUES ('a;b', 'c\\'d;');\nSELECT 1;"));
});
