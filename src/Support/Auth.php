<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Login berbasis session PHP + token CSRF.
 * Hak akses dicek di server; menu yang disembunyikan di browser hanya kenyamanan tampilan.
 */
final class Auth
{
    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 300;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('toko_sembako');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    /** @return array{id:int, username:string, role:string}|null */
    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    /** @return array{id:int, username:string, role:string} */
    public static function require(?string $role = null): array
    {
        $user = self::user();
        if ($user === null) {
            throw HttpError::unauthorized();
        }
        if ($role !== null && $user['role'] !== $role) {
            throw HttpError::forbidden();
        }
        return $user;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /** @return array{id:int, username:string, role:string} */
    public static function login(string $username, string $password): array
    {
        self::start();
        $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
        if ($lockedUntil > time()) {
            throw new HttpError(429, 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.');
        }

        $row = Db::one('SELECT id, username, password_hash, role FROM users WHERE username = ?', [$username]);
        // password_verify tetap dijalankan walau user tidak ada, supaya waktu respons sama
        $hash = $row['password_hash'] ?? '$2y$12$i0bLgeERzVVE4aynXIwCzOeaoQjv9yTQJAZFt2r7ToT1mGjvE8l/O';
        if (!password_verify($password, $hash) || $row === null) {
            $fails = ($_SESSION['login_fails'] ?? 0) + 1;
            $_SESSION['login_fails'] = $fails;
            if ($fails >= self::MAX_FAILS) {
                $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['login_fails'] = 0;
            }
            throw HttpError::unauthorized('Username atau password salah');
        }

        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }

        session_regenerate_id(true);
        $user = ['id' => (int) $row['id'], 'username' => $row['username'], 'role' => $row['role']];
        $_SESSION = [
            'user'             => $user,
            'csrf'             => bin2hex(random_bytes(32)),
            // password awal dari seed (sama dengan username) → tampilkan peringatan di dashboard
            'default_password' => hash_equals($row['username'], $password),
        ];
        return $user;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Wajib untuk semua request yang mengubah data. */
    public static function checkCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
        if (!is_string($sent) || $sent === '' || !hash_equals(self::csrfToken(), $sent)) {
            throw new HttpError(419, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }
    }

    public static function usesDefaultPassword(): bool
    {
        self::start();
        return !empty($_SESSION['default_password']);
    }

    public static function clearDefaultPasswordFlag(): void
    {
        self::start();
        $_SESSION['default_password'] = false;
    }
}
