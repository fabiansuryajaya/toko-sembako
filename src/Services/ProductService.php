<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;
use App\Support\Input;

final class ProductService
{
    private const SELECT = 'SELECT p.id, p.nama, p.supplier_id, s.nama AS supplier, p.unit_id, u.nama AS satuan,
                                   p.harga_beli, p.harga_jual, p.stok, p.deskripsi, p.aktif
                            FROM products p
                            JOIN suppliers s ON s.id = p.supplier_id
                            JOIN units u     ON u.id = p.unit_id';

    /** Harga modal (harga_beli) hanya dikirim ke admin. */
    public static function list(bool $activeOnly, bool $withCost): array
    {
        $sql = self::SELECT . ($activeOnly ? ' WHERE p.aktif = 1' : '') . ' ORDER BY p.nama';
        return array_map(static fn (array $p) => self::shape($p, $withCost), Db::all($sql));
    }

    public static function get(int $id, bool $withCost): array
    {
        $p = Db::one(self::SELECT . ' WHERE p.id = ?', [$id]) ?? throw HttpError::notFound('Barang tidak ditemukan');
        return self::shape($p, $withCost);
    }

    public static function save(Input $in, ?int $id = null): array
    {
        $data = [
            'nama'        => $in->str('nama', 'Nama barang', 100),
            'supplier_id' => $in->id('supplier_id', 'Supplier'),
            'unit_id'     => $in->id('unit_id', 'Satuan'),
            'harga_beli'  => $in->money('harga_beli', 'Modal'),
            'harga_jual'  => $in->money('harga_jual', 'Harga jual'),
            'stok'        => (int) $in->int('stok', 'Stok', null, null, false, 0),
            'deskripsi'   => $in->str('deskripsi', 'Deskripsi', 2000, false),
            'aktif'       => $in->bool('aktif', true) ? 1 : 0,
        ];
        MasterService::supplier($data['supplier_id']);
        MasterService::unit($data['unit_id']);

        if ($id === null) {
            $id = Db::insert(
                'INSERT INTO products (nama, supplier_id, unit_id, harga_beli, harga_jual, stok, deskripsi, aktif)
                 VALUES (:nama, :supplier_id, :unit_id, :harga_beli, :harga_jual, :stok, :deskripsi, :aktif)',
                $data,
            );
        } else {
            self::get($id, true);
            Db::run(
                'UPDATE products SET nama = :nama, supplier_id = :supplier_id, unit_id = :unit_id, harga_beli = :harga_beli,
                        harga_jual = :harga_jual, stok = :stok, deskripsi = :deskripsi, aktif = :aktif
                 WHERE id = :id',
                $data + ['id' => $id],
            );
        }
        return self::get($id, true);
    }

    /**
     * Simpan perubahan harga dari tabel barang sekaligus.
     * @param Input[] $rows  tiap baris: id + harga_beli dan/atau harga_jual
     */
    public static function updatePrices(array $rows): int
    {
        return Db::transaction(static function () use ($rows): int {
            foreach ($rows as $row) {
                $id = $row->id('id', 'ID barang');
                self::get($id, true);
                if ($row->has('harga_beli')) {
                    Db::run('UPDATE products SET harga_beli = ? WHERE id = ?', [$row->money('harga_beli', 'Modal'), $id]);
                }
                if ($row->has('harga_jual')) {
                    Db::run('UPDATE products SET harga_jual = ? WHERE id = ?', [$row->money('harga_jual', 'Harga jual'), $id]);
                }
            }
            return count($rows);
        });
    }

    public static function deactivate(int $id): void
    {
        self::get($id, true);
        Db::run('UPDATE products SET aktif = 0 WHERE id = ?', [$id]);
    }

    private static function shape(array $p, bool $withCost): array
    {
        $p['aktif'] = (bool) $p['aktif'];
        if (!$withCost) {
            unset($p['harga_beli']);
        }
        return $p;
    }
}
