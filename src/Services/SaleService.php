<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;
use App\Support\Input;

/**
 * Penjualan tunai & hutang (satu tabel `sales`, dibedakan kolom `jenis`).
 *
 * Aturan stok: setiap item mengurangi stok. Edit = kembalikan stok item lama, lalu kurangi dengan item baru.
 * Hapus = kembalikan stok. Semua langkah di dalam satu transaksi dengan baris produk dikunci (FOR UPDATE).
 *
 * Aturan harga: pegawai selalu memakai harga jual barang (atau harga yang sudah tercatat di transaksi itu
 * saat edit); hanya admin yang boleh mengganti harga. Modal (harga_beli) dicatat per item untuk laporan untung.
 */
final class SaleService
{
    public const TUNAI = 'tunai';
    public const HUTANG = 'hutang';

    /** @param array{id:int, role:string} $actor */
    public static function list(string $jenis, ?string $from, ?string $to): array
    {
        self::assertJenis($jenis);
        [$where, $params] = self::dateFilter('s.created_at', $from, $to);
        $rows = Db::all(
            "SELECT s.id, s.jenis, s.created_at, s.subtotal, s.ongkir, s.dibayar, s.lunas, s.lunas_at,
                    s.nama_pembeli, s.user_id, u.username AS kasir, s.member_id, m.nama AS member,
                    (SELECT COALESCE(SUM(qty), 0) FROM sale_items i WHERE i.sale_id = s.id) AS total_qty
             FROM sales s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.jenis = ? $where
             ORDER BY s.created_at DESC, s.id DESC",
            array_merge([$jenis], $params),
        );
        return array_map([self::class, 'shapeHeader'], $rows);
    }

    public static function get(int $id, bool $withCost, ?string $jenis = null): array
    {
        $sale = Db::one(
            'SELECT s.id, s.jenis, s.created_at, s.updated_at, s.subtotal, s.ongkir, s.dibayar, s.lunas, s.lunas_at,
                    s.nama_pembeli, s.user_id, u.username AS kasir, s.member_id, m.nama AS member, m.nomor_hp AS member_hp
             FROM sales s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.id = ?',
            [$id],
        );
        if ($sale === null || ($jenis !== null && $sale['jenis'] !== $jenis)) {
            throw HttpError::notFound('Transaksi tidak ditemukan');
        }
        $sale = self::shapeHeader($sale);
        $sale['items'] = array_map(static function (array $i) use ($withCost) {
            if (!$withCost) {
                unset($i['harga_beli']);
            }
            return $i;
        }, Db::all(
            'SELECT i.id, i.product_id, p.nama, un.nama AS satuan, sp.nama AS supplier, i.qty, i.harga_jual, i.harga_beli,
                    i.qty * i.harga_jual AS total
             FROM sale_items i
             JOIN products p   ON p.id = i.product_id
             JOIN units un     ON un.id = p.unit_id
             JOIN suppliers sp ON sp.id = p.supplier_id
             WHERE i.sale_id = ?
             ORDER BY i.id',
            [$id],
        ));
        return $sale;
    }

    /** @param array{id:int, role:string} $actor */
    public static function create(string $jenis, Input $in, array $actor): array
    {
        self::assertJenis($jenis);
        $id = Db::transaction(static function () use ($jenis, $in, $actor): int {
            $header = self::readHeader($jenis, $in, $actor, null);
            $items = self::readItems($in, $actor, []);
            $subtotal = self::sum($items);

            $id = Db::insert(
                'INSERT INTO sales (jenis, user_id, member_id, nama_pembeli, subtotal, ongkir, dibayar, lunas, lunas_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$jenis, $header['user_id'], $header['member_id'], $header['nama_pembeli'], $subtotal, $header['ongkir'],
                 $header['dibayar'], $jenis === self::TUNAI ? 1 : 0, $jenis === self::TUNAI ? date('Y-m-d H:i:s') : null],
            );
            self::writeItems($id, $items);
            return $id;
        });
        return self::get($id, $actor['role'] === 'admin');
    }

    /** @param array{id:int, role:string} $actor */
    public static function update(int $id, string $jenis, Input $in, array $actor): array
    {
        Db::transaction(static function () use ($id, $jenis, $in, $actor): void {
            $old = Db::one('SELECT id, jenis, user_id FROM sales WHERE id = ? FOR UPDATE', [$id]);
            if ($old === null || $old['jenis'] !== $jenis) {
                throw HttpError::notFound('Transaksi tidak ditemukan');
            }
            $oldItems = Db::all('SELECT product_id, qty, harga_jual, harga_beli FROM sale_items WHERE sale_id = ?', [$id]);

            // kunci produk lama juga, lalu kembalikan stoknya
            self::lockProducts(array_column($oldItems, 'product_id'));
            foreach ($oldItems as $item) {
                Db::run('UPDATE products SET stok = stok + ? WHERE id = ?', [$item['qty'], $item['product_id']]);
            }
            Db::run('DELETE FROM sale_items WHERE sale_id = ?', [$id]);

            $header = self::readHeader($jenis, $in, $actor, (int) $old['user_id']);
            $items = self::readItems($in, $actor, $oldItems);
            Db::run(
                'UPDATE sales SET user_id = ?, member_id = ?, nama_pembeli = ?, subtotal = ?, ongkir = ?, dibayar = ?, updated_at = NOW()
                 WHERE id = ?',
                [$header['user_id'], $header['member_id'], $header['nama_pembeli'], self::sum($items), $header['ongkir'], $header['dibayar'], $id],
            );
            self::writeItems($id, $items);
        });
        return self::get($id, $actor['role'] === 'admin');
    }

    /** Hapus transaksi dan kembalikan stok. Hutang yang sudah lunas harus dibatalkan lunasnya dulu. */
    public static function delete(int $id, string $jenis): void
    {
        Db::transaction(static function () use ($id, $jenis): void {
            $sale = Db::one('SELECT id, jenis, lunas FROM sales WHERE id = ? FOR UPDATE', [$id]);
            if ($sale === null || $sale['jenis'] !== $jenis) {
                throw HttpError::notFound('Transaksi tidak ditemukan');
            }
            if ($jenis === self::HUTANG && $sale['lunas']) {
                throw HttpError::conflict('Hutang yang sudah lunas tidak bisa dihapus. Batalkan status lunas terlebih dahulu.');
            }
            $items = Db::all('SELECT product_id, qty FROM sale_items WHERE sale_id = ?', [$id]);
            self::lockProducts(array_column($items, 'product_id'));
            foreach ($items as $item) {
                Db::run('UPDATE products SET stok = stok + ? WHERE id = ?', [$item['qty'], $item['product_id']]);
            }
            Db::run('DELETE FROM sales WHERE id = ?', [$id]); // sale_items ikut terhapus (ON DELETE CASCADE)
        });
    }

    /** Tandai hutang lunas / batalkan lunas. */
    public static function setPaid(int $id, bool $lunas): array
    {
        $sale = Db::one('SELECT id, jenis FROM sales WHERE id = ?', [$id]);
        if ($sale === null || $sale['jenis'] !== self::HUTANG) {
            throw HttpError::notFound('Hutang tidak ditemukan');
        }
        Db::run('UPDATE sales SET lunas = ?, lunas_at = ? WHERE id = ?', [$lunas ? 1 : 0, $lunas ? date('Y-m-d H:i:s') : null, $id]);
        return self::get($id, true);
    }

    // ------------------------------------------------------------------

    /** @param array{id:int, role:string} $actor */
    private static function readHeader(string $jenis, Input $in, array $actor, ?int $currentKasir): array
    {
        // Kasir: admin boleh memilih; pegawai selalu dirinya sendiri (atau kasir asli saat edit)
        if ($actor['role'] === 'admin' && $in->has('user_id')) {
            $userId = $in->id('user_id', 'Kasir');
            UserService::get($userId);
        } else {
            $userId = $currentKasir ?? $actor['id'];
        }

        $memberId = null;
        if ($jenis === self::HUTANG) {
            $memberId = $in->id('member_id', 'Member');
            MasterService::member($memberId);
        }

        return [
            'user_id'      => $userId,
            'member_id'    => $memberId,
            'nama_pembeli' => $jenis === self::TUNAI ? $in->str('nama_pembeli', 'Nama pembeli', 100, false) : '',
            'ongkir'       => $jenis === self::HUTANG ? $in->money('ongkir', 'Ongkir', false) : 0,
            'dibayar'      => $in->money('dibayar', 'Total bayar', false),
        ];
    }

    /**
     * @param array{id:int, role:string} $actor
     * @param array<int, array{product_id:int, harga_jual:int, harga_beli:int}> $previous item lama (saat edit)
     * @return list<array{product_id:int, qty:int, harga_jual:int, harga_beli:int}>
     */
    private static function readItems(Input $in, array $actor, array $previous): array
    {
        $rows = $in->list('items', 'Daftar barang');
        $ids = array_map(static fn (Input $r) => $r->id('product_id', 'Barang'), $rows);
        $products = self::lockProducts($ids);

        $prevPrice = [];
        $prevCost = [];
        foreach ($previous as $p) {
            $prevPrice[(int) $p['product_id']][] = (int) $p['harga_jual'];
            $prevCost[(int) $p['product_id']] = (int) $p['harga_beli'];
        }

        $items = [];
        foreach ($rows as $n => $row) {
            $pid = $ids[$n];
            $product = $products[$pid] ?? throw HttpError::badRequest("Barang #$pid tidak ditemukan");
            $qty = (int) $row->int('qty', 'Jumlah ' . $product['nama'], 1, 1_000_000);

            $price = (int) $product['harga_jual'];
            if ($row->has('harga_jual')) {
                $asked = $row->money('harga_jual', 'Harga ' . $product['nama']);
                $allowed = $actor['role'] === 'admin' || $asked === $price || in_array($asked, $prevPrice[$pid] ?? [], true);
                if (!$allowed) {
                    throw HttpError::forbidden('Hanya admin yang boleh mengganti harga jual');
                }
                $price = $asked;
            }

            $items[] = [
                'product_id' => $pid,
                'qty'        => $qty,
                'harga_jual' => $price,
                // modal: pertahankan modal yang tercatat saat transaksi asli, supaya edit tidak mengubah laporan untung
                'harga_beli' => $prevCost[$pid] ?? (int) $product['harga_beli'],
            ];
        }
        return $items;
    }

    private static function writeItems(int $saleId, array $items): void
    {
        foreach ($items as $item) {
            Db::run(
                'INSERT INTO sale_items (sale_id, product_id, qty, harga_jual, harga_beli) VALUES (?, ?, ?, ?, ?)',
                [$saleId, $item['product_id'], $item['qty'], $item['harga_jual'], $item['harga_beli']],
            );
            Db::run('UPDATE products SET stok = stok - ? WHERE id = ?', [$item['qty'], $item['product_id']]);
        }
    }

    /** @return array<int, array> produk terkunci, diindeks id */
    public static function lockProducts(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        sort($ids); // urutan kunci tetap → mencegah deadlock antar transaksi
        $rows = Db::all('SELECT id, nama, harga_beli, harga_jual, stok FROM products WHERE id IN (' . Db::placeholders($ids) . ') ORDER BY id FOR UPDATE', $ids);
        return array_column($rows, null, 'id');
    }

    private static function sum(array $items): int
    {
        return array_sum(array_map(static fn (array $i) => $i['qty'] * $i['harga_jual'], $items));
    }

    private static function shapeHeader(array $s): array
    {
        $s['lunas'] = (bool) $s['lunas'];
        $s['tagihan'] = (int) $s['subtotal'] + (int) $s['ongkir'];
        $s['kembalian'] = (int) $s['dibayar'] - $s['tagihan'];
        return $s;
    }

    private static function assertJenis(string $jenis): void
    {
        if ($jenis !== self::TUNAI && $jenis !== self::HUTANG) {
            throw HttpError::badRequest('Jenis transaksi tidak dikenal');
        }
    }

    /** @return array{0:string, 1:array} potongan WHERE untuk rentang tanggal (inklusif) */
    public static function dateFilter(string $column, ?string $from, ?string $to): array
    {
        $where = '';
        $params = [];
        if ($from !== null) {
            $where .= " AND $column >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= " AND $column < ? + INTERVAL 1 DAY";
            $params[] = $to;
        }
        return [$where, $params];
    }
}
