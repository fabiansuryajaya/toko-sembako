<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;
use App\Support\Input;

/** Data master sederhana: supplier, satuan, member. */
final class MasterService
{
    // ---------- supplier (hapus = nonaktifkan, karena produk lama masih merujuk) ----------

    public static function suppliers(): array
    {
        return Db::all('SELECT id, nama, nomor_hp FROM suppliers WHERE aktif = 1 ORDER BY nama');
    }

    public static function supplier(int $id): array
    {
        return Db::one('SELECT id, nama, nomor_hp FROM suppliers WHERE id = ? AND aktif = 1', [$id]) ?? throw HttpError::notFound('Supplier tidak ditemukan');
    }

    public static function saveSupplier(Input $in, ?int $id = null): array
    {
        $nama = $in->str('nama', 'Nama supplier', 100);
        $hp = self::phone($in);
        if ($id === null) {
            $id = Db::insert('INSERT INTO suppliers (nama, nomor_hp) VALUES (?, ?)', [$nama, $hp]);
        } else {
            self::supplier($id);
            Db::run('UPDATE suppliers SET nama = ?, nomor_hp = ? WHERE id = ?', [$nama, $hp, $id]);
        }
        return self::supplier($id);
    }

    public static function deactivateSupplier(int $id): void
    {
        self::supplier($id);
        Db::run('UPDATE suppliers SET aktif = 0 WHERE id = ?', [$id]);
    }

    // ---------- satuan ----------

    public static function units(): array
    {
        return Db::all('SELECT id, nama FROM units WHERE aktif = 1 ORDER BY nama');
    }

    public static function unit(int $id): array
    {
        return Db::one('SELECT id, nama FROM units WHERE id = ? AND aktif = 1', [$id]) ?? throw HttpError::notFound('Satuan tidak ditemukan');
    }

    public static function saveUnit(Input $in, ?int $id = null): array
    {
        $nama = $in->str('nama', 'Nama satuan', 50);
        if ($id === null) {
            $id = Db::insert('INSERT INTO units (nama) VALUES (?)', [$nama]);
        } else {
            self::unit($id);
            Db::run('UPDATE units SET nama = ? WHERE id = ?', [$nama, $id]);
        }
        return self::unit($id);
    }

    public static function deactivateUnit(int $id): void
    {
        self::unit($id);
        Db::run('UPDATE units SET aktif = 0 WHERE id = ?', [$id]);
    }

    // ---------- member (pelanggan yang boleh berhutang) ----------

    public static function members(): array
    {
        return Db::all('SELECT id, nama, nomor_hp FROM members ORDER BY nama');
    }

    public static function member(int $id): array
    {
        return Db::one('SELECT id, nama, nomor_hp FROM members WHERE id = ?', [$id]) ?? throw HttpError::notFound('Member tidak ditemukan');
    }

    public static function saveMember(Input $in, ?int $id = null): array
    {
        $nama = $in->str('nama', 'Nama member', 100);
        $hp = self::phone($in, true);
        if ($id === null) {
            $id = Db::insert('INSERT INTO members (nama, nomor_hp) VALUES (?, ?)', [$nama, $hp]);
        } else {
            self::member($id);
            Db::run('UPDATE members SET nama = ?, nomor_hp = ? WHERE id = ?', [$nama, $hp, $id]);
        }
        return self::member($id);
    }

    public static function deleteMember(int $id): void
    {
        self::member($id);
        if (Db::value('SELECT COUNT(*) FROM sales WHERE member_id = ?', [$id]) > 0) {
            throw HttpError::conflict('Member ini punya riwayat transaksi/hutang, jadi tidak bisa dihapus');
        }
        Db::run('DELETE FROM members WHERE id = ?', [$id]);
    }

    private static function phone(Input $in, bool $required = false): string
    {
        $hp = $in->str('nomor_hp', 'Nomor HP', 20, $required);
        if ($hp !== '' && !preg_match('/^[0-9+\-\s()]+$/', $hp)) {
            throw HttpError::badRequest('Nomor HP hanya boleh berisi angka, spasi, +, -, dan tanda kurung');
        }
        return $hp;
    }
}
