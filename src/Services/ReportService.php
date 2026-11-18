<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;

/**
 * Laporan penjualan: hanya transaksi yang sudah lunas (tunai + hutang lunas),
 * dikelompokkan per barang, harga jual, dan modal.
 */
final class ReportService
{
    public static function sales(?string $from, ?string $to, array $productIds): array
    {
        [$where, $params] = SaleService::dateFilter('s.created_at', $from, $to);
        if ($productIds !== []) {
            $where .= ' AND i.product_id IN (' . Db::placeholders($productIds) . ')';
            $params = array_merge($params, $productIds);
        }
        $rows = Db::all(
            "SELECT p.id AS product_id, p.nama, un.nama AS satuan, sp.nama AS supplier,
                    i.harga_jual, i.harga_beli, SUM(i.qty) AS qty,
                    SUM(i.qty * i.harga_beli) AS total_modal,
                    SUM(i.qty * i.harga_jual) AS total_penjualan,
                    SUM(i.qty * (i.harga_jual - i.harga_beli)) AS untung
             FROM sales s
             JOIN sale_items i ON i.sale_id = s.id
             JOIN products p   ON p.id = i.product_id
             JOIN units un     ON un.id = p.unit_id
             JOIN suppliers sp ON sp.id = p.supplier_id
             WHERE s.lunas = 1 $where
             GROUP BY p.id, p.nama, un.nama, sp.nama, i.harga_jual, i.harga_beli
             ORDER BY p.nama, i.harga_jual",
            $params,
        );
        $rows = array_map(static function (array $r): array {
            foreach (['qty', 'total_modal', 'total_penjualan', 'untung'] as $k) {
                $r[$k] = (int) $r[$k];
            }
            return $r;
        }, $rows);

        return [
            'rows'   => $rows,
            'totals' => [
                'qty'             => array_sum(array_column($rows, 'qty')),
                'total_modal'     => array_sum(array_column($rows, 'total_modal')),
                'total_penjualan' => array_sum(array_column($rows, 'total_penjualan')),
                'untung'          => array_sum(array_column($rows, 'untung')),
            ],
        ];
    }

    /**
     * Hapus transaksi lunas dalam rentang tanggal (arsip laporan lama).
     * Stok tidak dikembalikan: barangnya memang sudah terjual. Hutang yang belum lunas tidak ikut terhapus.
     */
    public static function purge(?string $from, ?string $to): int
    {
        if ($from === null || $to === null) {
            throw HttpError::badRequest('Pilih rentang tanggal yang akan dihapus');
        }
        [$where, $params] = SaleService::dateFilter('created_at', $from, $to);
        return Db::run("DELETE FROM sales WHERE lunas = 1 $where", $params)->rowCount();
    }
}
