<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;
use App\Support\Input;

final class UserService
{
    public const MIN_PASSWORD = 6;

    public static function list(): array
    {
        return Db::all('SELECT id, username, role, created_at FROM users ORDER BY role, username');
    }

    public static function get(int $id): array
    {
        return Db::one('SELECT id, username, role, created_at FROM users WHERE id = ?', [$id]) ?? throw HttpError::notFound('User tidak ditemukan');
    }

    public static function save(Input $in, ?int $id = null): array
    {
        $username = $in->str('username', 'Username', 50);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            throw HttpError::badRequest('Username hanya boleh huruf, angka, titik, garis bawah, dan strip');
        }
        $role = $in->str('role', 'Role', 10, false, 'pegawai');
        if (!in_array($role, ['admin', 'pegawai'], true)) {
            throw HttpError::badRequest('Role harus admin atau pegawai');
        }
        $password = $in->str('password', 'Password', 200, $id === null);

        if (Db::value('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$username, $id ?? 0]) > 0) {
            throw HttpError::conflict('Username sudah dipakai');
        }

        if ($id === null) {
            self::checkPassword($password);
            $id = Db::insert('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)', [$username, password_hash($password, PASSWORD_DEFAULT), $role]);
        } else {
            $old = self::get($id);
            if ($old['role'] === 'admin' && $role !== 'admin') {
                self::assertAnotherAdmin($id);
            }
            Db::run('UPDATE users SET username = ?, role = ? WHERE id = ?', [$username, $role, $id]);
            if ($password !== '') {
                self::setPassword($id, $password);
            }
        }
        return self::get($id);
    }

    public static function setPassword(int $id, string $password): void
    {
        self::checkPassword($password);
        Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /** Ganti password sendiri: wajib tahu password lama. */
    public static function changeOwnPassword(int $id, Input $in): void
    {
        $old = $in->str('password_lama', 'Password lama', 200);
        $new = $in->str('password_baru', 'Password baru', 200);
        $hash = (string) Db::value('SELECT password_hash FROM users WHERE id = ?', [$id]);
        if (!password_verify($old, $hash)) {
            throw HttpError::badRequest('Password lama salah');
        }
        self::setPassword($id, $new);
    }

    public static function delete(int $id, int $currentUserId): void
    {
        $user = self::get($id);
        if ($id === $currentUserId) {
            throw HttpError::conflict('Tidak bisa menghapus akun yang sedang dipakai');
        }
        if ($user['role'] === 'admin') {
            self::assertAnotherAdmin($id);
        }
        $used = Db::value('SELECT (SELECT COUNT(*) FROM sales WHERE user_id = ?) + (SELECT COUNT(*) FROM purchases WHERE user_id = ?)', [$id, $id]);
        if ($used > 0) {
            throw HttpError::conflict('User ini sudah punya transaksi, jadi tidak bisa dihapus');
        }
        Db::run('DELETE FROM users WHERE id = ?', [$id]);
    }

    private static function checkPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw HttpError::badRequest('Password minimal ' . self::MIN_PASSWORD . ' karakter');
        }
    }

    private static function assertAnotherAdmin(int $exceptId): void
    {
        if (Db::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?", [$exceptId]) < 1) {
            throw HttpError::conflict('Harus ada minimal satu admin');
        }
    }
}
