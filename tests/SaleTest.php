<?php
declare(strict_types=1);

use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\Db;
use App\Support\Input;

const ADMIN = ['id' => 1, 'username' => 'admin', 'role' => 'admin'];
const PEGAWAI = ['id' => 2, 'username' => 'pegawai', 'role' => 'pegawai'];

function stok(int $productId): int
{
    return (int) Db::value('SELECT stok FROM products WHERE id = ?', [$productId]);
}

function cart(array $items, array $extra = []): Input
{
    return new Input(['items' => $items] + $extra);
}

test('penjualan tunai mengurangi stok dan mencatat modal & kasir', function () {
    $sale = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 3], ['product_id' => 3, 'qty' => 2]], ['dibayar' => 100000, 'nama_pembeli' => 'Budi']), PEGAWAI);
    eq(97, stok(1));
    eq(48, stok(3));
    eq(3 * 13500 + 2 * 17000, $sale['subtotal']);
    eq(100000 - $sale['subtotal'], $sale['kembalian']);
    eq('pegawai', $sale['kasir']);
    eq(true, $sale['lunas']);
    eq(false, array_key_exists('harga_beli', $sale['items'][0]), 'pegawai tidak boleh menerima modal');
    eq(12000, (int) Db::value('SELECT harga_beli FROM sale_items WHERE sale_id = ? AND product_id = 1', [$sale['id']]));
});

test('edit penjualan mengembalikan stok lama sebelum mengurangi stok baru', function () {
    $sale = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 5]]), ADMIN);
    eq(95, stok(1));
    SaleService::update($sale['id'], 'tunai', cart([['product_id' => 1, 'qty' => 2], ['product_id' => 4, 'qty' => 1]]), ADMIN);
    eq(98, stok(1), 'stok beras = 100 - 2');
    eq(39, stok(4));
    // edit kedua kali dengan isi sama: stok tidak boleh berkurang lagi (bug lama)
    SaleService::update($sale['id'], 'tunai', cart([['product_id' => 1, 'qty' => 2], ['product_id' => 4, 'qty' => 1]]), ADMIN);
    eq(98, stok(1));
    eq(39, stok(4));
});

test('hapus penjualan mengembalikan stok dan menghapus item', function () {
    $sale = SaleService::create('tunai', cart([['product_id' => 2, 'qty' => 4]]), ADMIN);
    eq(6, stok(2));
    SaleService::delete($sale['id'], 'tunai');
    eq(10, stok(2));
    eq(0, (int) Db::value('SELECT COUNT(*) FROM sale_items'));
});

test('pegawai tidak boleh mengganti harga, admin boleh', function () {
    throws(403, fn () => SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 1, 'harga_jual' => 1000]]), PEGAWAI));
    eq(100, stok(1), 'transaksi yang ditolak tidak boleh mengubah stok');
    $ok = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 1, 'harga_jual' => 13500]]), PEGAWAI);
    eq(13500, $ok['subtotal'], 'harga normal boleh dikirim pegawai');
    $diskon = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 2, 'harga_jual' => 13000]]), ADMIN);
    eq(26000, $diskon['subtotal']);
});

test('pegawai boleh mengedit transaksi diskon admin tanpa mengubah harganya', function () {
    $sale = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 2, 'harga_jual' => 13000]]), ADMIN);
    $edited = SaleService::update($sale['id'], 'tunai', cart([['product_id' => 1, 'qty' => 3, 'harga_jual' => 13000]]), PEGAWAI);
    eq(39000, $edited['subtotal']);
    eq('admin', $edited['kasir'], 'pegawai tidak mengambil alih kasir');
});

test('pegawai tidak bisa memilih kasir lain', function () {
    $sale = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 1]], ['user_id' => 1]), PEGAWAI);
    eq('pegawai', $sale['kasir']);
    $byAdmin = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 1]], ['user_id' => 2]), ADMIN);
    eq('pegawai', $byAdmin['kasir']);
});

test('input tidak valid ditolak dan transaksi tidak tersimpan', function () {
    throws(400, fn () => SaleService::create('tunai', cart([]), ADMIN));
    throws(400, fn () => SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 0]]), ADMIN));
    throws(400, fn () => SaleService::create('tunai', cart([['product_id' => 1, 'qty' => '2x']]), ADMIN));
    throws(400, fn () => SaleService::create('tunai', cart([['product_id' => 999, 'qty' => 1]]), ADMIN));
    throws(400, fn () => SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 1], ['product_id' => 999, 'qty' => 1]]), ADMIN));
    eq(100, stok(1), 'rollback: stok tidak berubah');
    eq(0, (int) Db::value('SELECT COUNT(*) FROM sales'));
});

test('hutang wajib member, belum lunas, dan bisa dilunasi', function () {
    throws(400, fn () => SaleService::create('hutang', cart([['product_id' => 1, 'qty' => 1]]), ADMIN));
    Db::run("INSERT INTO members (nama, nomor_hp) VALUES ('Bu Sari', '0812')");
    $debt = SaleService::create('hutang', cart([['product_id' => 1, 'qty' => 2]], ['member_id' => 1, 'ongkir' => 5000]), PEGAWAI);
    eq(false, $debt['lunas']);
    eq(27000 + 5000, $debt['tagihan']);
    eq(98, stok(1));

    eq([], SaleService::list('tunai', null, null), 'hutang tidak muncul di daftar penjualan tunai');
    eq(1, count(SaleService::list('hutang', null, null)));
    throws(404, fn () => SaleService::get($debt['id'], true, 'tunai'), 'hutang tidak bisa dibuka lewat endpoint penjualan');

    eq(true, SaleService::setPaid($debt['id'], true)['lunas']);
    throws(409, fn () => SaleService::delete($debt['id'], 'hutang'), 'hutang lunas tidak boleh dihapus');
    SaleService::setPaid($debt['id'], false);
    SaleService::delete($debt['id'], 'hutang');
    eq(100, stok(1));
});

test('laporan hanya menghitung transaksi lunas, dengan untung dari modal tercatat', function () {
    Db::run("INSERT INTO members (nama) VALUES ('Pak Joko')");
    SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 2]]), ADMIN);
    $debt = SaleService::create('hutang', cart([['product_id' => 1, 'qty' => 10]], ['member_id' => 1]), ADMIN);

    // modal naik setelah transaksi: laporan tetap memakai modal saat transaksi
    Db::run('UPDATE products SET harga_beli = 20000 WHERE id = 1');

    $today = date('Y-m-d');
    $r = ReportService::sales($today, $today, []);
    eq(2, $r['totals']['qty'], 'hutang belum lunas tidak masuk laporan');
    eq(2 * (13500 - 12000), $r['totals']['untung']);

    SaleService::setPaid($debt['id'], true);
    eq(12, ReportService::sales($today, $today, [])['totals']['qty']);
    eq(0, ReportService::sales($today, $today, [3])['totals']['qty'], 'filter barang');

    // hapus arsip: hanya yang lunas
    Db::run("INSERT INTO members (nama) VALUES ('Belum Bayar')");
    SaleService::create('hutang', cart([['product_id' => 1, 'qty' => 1]], ['member_id' => 2]), ADMIN);
    eq(2, ReportService::purge($today, $today));
    eq(1, (int) Db::value('SELECT COUNT(*) FROM sales'), 'hutang belum lunas tetap ada');
});

test('filter tanggal inklusif di kedua ujung', function () {
    $sale = SaleService::create('tunai', cart([['product_id' => 1, 'qty' => 1]]), ADMIN);
    Db::run("UPDATE sales SET created_at = '2026-01-31 23:59:59' WHERE id = ?", [$sale['id']]);
    eq(1, count(SaleService::list('tunai', '2026-01-31', '2026-01-31')));
    eq(0, count(SaleService::list('tunai', '2026-02-01', '2026-02-28')));
    eq(0, count(SaleService::list('tunai', '2026-01-01', '2026-01-30')));
});
