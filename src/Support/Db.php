<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOStatement;
use Throwable;

/** Pembungkus tipis PDO: semua query memakai parameter, tidak ada string SQL yang disambung dengan input. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = app_config()['db'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        }
        return self::$pdo;
    }

    /** Untuk tes: pakai koneksi lain. */
    public static function use(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Jalankan $fn di dalam transaksi. Error apa pun → rollback, lalu error diteruskan.
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn(); // transaksi bersarang ikut transaksi luar
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Placeholder "?, ?, ?" untuk klausa IN. */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
