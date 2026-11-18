<?php
declare(strict_types=1);

namespace App;

/** Daftar halaman: slug => [judul, grup menu, role yang boleh membuka ('user' = semua yang login)]. */
final class Menu
{
    public const PAGES = [
        'barang'     => ['Barang', 'Master', 'user'],
        'supplier'   => ['Supplier', 'Master', 'admin'],
        'satuan'     => ['Satuan', 'Master', 'admin'],
        'member'     => ['Member', 'Master', 'admin'],
        'user'       => ['User', 'Master', 'admin'],
        'penjualan'  => ['Penjualan', 'Transaksi', 'user'],
        'hutang'     => ['Hutang', 'Transaksi', 'user'],
        'restock'    => ['Restock', 'Transaksi', 'admin'],
        'laporan'    => ['Laporan', null, 'admin'],
        'backup'     => ['Backup Database', null, 'admin'],
        'pengaturan' => ['Pengaturan', null, 'admin'],
        'akun'       => ['Akun Saya', null, 'user'],
    ];

    public static function allowed(string $page, string $role): bool
    {
        $access = self::PAGES[$page][2] ?? null;
        return $access !== null && ($access === 'user' || $access === $role);
    }

    /** @return array<string|int, array> grup => [slug => judul]; halaman tanpa grup berdiri sendiri */
    public static function forRole(string $role): array
    {
        $out = [];
        foreach (self::PAGES as $slug => [$label, $group]) {
            if (!self::allowed($slug, $role)) {
                continue;
            }
            if ($group === null) {
                $out[] = [$slug => $label];
            } else {
                $out[$group][$slug] = $label;
            }
        }
        return $out;
    }
}
