<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;
use App\Support\Input;

/** Restock barang: menambah stok dan memperbarui modal (harga beli) barang ke harga terakhir. */
final class PurchaseService
{
    public static function list(?string $from, ?string $to): array
    {
        [$where, $params] = SaleService::dateFilter('pu.created_at', $from, $to);
        return Db::all(
            "SELECT pu.id, pu.created_at, pu.total, u.username AS user,
                    (SELECT COALESCE(SUM(qty), 0) FROM purchase_items i WHERE i.purchase_id = pu.id) AS total_qty
             FROM purchases pu
             JOIN users u ON u.id = pu.user_id
             WHERE 1 = 1 $where
             ORDER BY pu.created_at DESC, pu.id DESC",
            $params,
        );
    }

    public static function get(int $id): array
    {
        $purchase = Db::one(
            'SELECT pu.id, pu.created_at, pu.total, u.username AS user FROM purchases pu JOIN users u ON u.id = pu.user_id WHERE pu.id = ?',
            [$id],
        ) ?? throw HttpError::notFound('Data restock tidak ditemukan');
        $purchase['items'] = Db::all(
            'SELECT i.product_id, p.nama, un.nama AS satuan, i.qty, i.harga_beli, i.qty * i.harga_beli AS total
             FROM purchase_items i
             JOIN products p ON p.id = i.product_id
             JOIN units un   ON un.id = p.unit_id
             WHERE i.purchase_id = ?
             ORDER BY i.id',
            [$id],
        );
        return $purchase;
    }

    public static function create(Input $in, int $userId): array
    {
        $id = Db::transaction(static function () use ($in, $userId): int {
            $rows = $in->list('items', 'Daftar barang');
            $ids = array_map(static fn (Input $r) => $r->id('product_id', 'Barang'), $rows);
            $products = SaleService::lockProducts($ids);

            $items = [];
            foreach ($rows as $n => $row) {
                $pid = $ids[$n];
                $product = $products[$pid] ?? throw HttpError::badRequest("Barang #$pid tidak ditemukan");
                $items[] = [
                    'product_id' => $pid,
                    'qty'        => (int) $row->int('qty', 'Jumlah ' . $product['nama'], 1, 1_000_000),
                    'harga_beli' => $row->has('harga_beli') ? $row->money('harga_beli', 'Harga beli ' . $product['nama']) : (int) $product['harga_beli'],
                ];
            }
            $total = array_sum(array_map(static fn ($i) => $i['qty'] * $i['harga_beli'], $items));

            $id = Db::insert('INSERT INTO purchases (user_id, total) VALUES (?, ?)', [$userId, $total]);
            foreach ($items as $item) {
                Db::run('INSERT INTO purchase_items (purchase_id, product_id, qty, harga_beli) VALUES (?, ?, ?, ?)', [$id, $item['product_id'], $item['qty'], $item['harga_beli']]);
                Db::run('UPDATE products SET stok = stok + ?, harga_beli = ? WHERE id = ?', [$item['qty'], $item['harga_beli'], $item['product_id']]);
            }
            return $id;
        });
        return self::get($id);
    }
}
