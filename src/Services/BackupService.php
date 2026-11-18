<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;

/**
 * Backup & restore database lengkap (semua tabel aplikasi, struktur + isi).
 * Sebelum import, isi database saat ini otomatis disimpan ke storage/backups/.
 */
final class BackupService
{
    /** Urutan tabel: induk dulu, supaya file juga bisa dibaca manusia dengan urutan masuk akal. */
    public const TABLES = ['users', 'suppliers', 'units', 'members', 'products', 'sales', 'sale_items', 'purchases', 'purchase_items', 'settings'];
    public const BACKUP_DIR = APP_ROOT . '/storage/backups';
    private const HEADER = '-- toko-sembako backup';

    /** @param resource $out */
    public static function export($out): void
    {
        $pdo = Db::pdo();
        fwrite($out, self::HEADER . ' ' . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach (self::TABLES as $table) {
            $create = Db::one("SHOW CREATE TABLE `$table`");
            fwrite($out, "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n");

            $stmt = $pdo->query("SELECT * FROM `$table`");
            $batch = [];
            $columns = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns ??= '`' . implode('`, `', array_keys($row)) . '`';
                $batch[] = '(' . implode(', ', array_map(static fn ($v) => self::literal($pdo, $v), $row)) . ')';
                if (count($batch) === 200) {
                    fwrite($out, "INSERT INTO `$table` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                fwrite($out, "INSERT INTO `$table` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($out, "\n");
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
    }

    /** Simpan backup ke storage/backups/ dan kembalikan path-nya. */
    public static function saveToDisk(string $prefix): string
    {
        if (!is_dir(self::BACKUP_DIR) && !mkdir(self::BACKUP_DIR, 0750, true)) {
            throw new HttpError(500, 'Folder storage/backups tidak bisa dibuat');
        }
        $path = self::BACKUP_DIR . '/' . $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
        $fh = fopen($path, 'wb');
        try {
            self::export($fh);
        } finally {
            fclose($fh);
        }
        return $path;
    }

    /**
     * Pulihkan database dari file backup buatan aplikasi ini.
     * @return array{statements:int, safety_backup:string}
     */
    public static function import(string $sql): array
    {
        if (strncmp(ltrim($sql), self::HEADER, strlen(self::HEADER)) !== 0) {
            throw HttpError::badRequest('File ini bukan backup dari aplikasi Toko Sembako');
        }
        $statements = self::split($sql);
        foreach ($statements as $st) {
            if (!self::allowed($st)) {
                throw HttpError::badRequest('File backup berisi perintah yang tidak diizinkan: ' . mb_substr($st, 0, 60));
            }
        }

        $safety = self::saveToDisk('sebelum-import');
        $pdo = Db::pdo();
        try {
            foreach ($statements as $st) {
                $pdo->exec($st);
            }
        } catch (\PDOException $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            throw new HttpError(500, 'Import gagal di tengah jalan: ' . $e->getMessage() . '. Salinan database sebelum import ada di ' . basename($safety));
        }
        return ['statements' => count($statements), 'safety_backup' => basename($safety)];
    }

    /** Hanya perintah yang memang dibuat oleh export(), dan hanya untuk tabel aplikasi. */
    private static function allowed(string $st): bool
    {
        $tables = implode('|', self::TABLES);
        return (bool) preg_match('/^(SET (NAMES utf8mb4|FOREIGN_KEY_CHECKS = [01])'
            . "|DROP TABLE IF EXISTS `($tables)`"
            . "|CREATE TABLE `($tables)` \\("
            . "|INSERT INTO `($tables)` \\()/", $st);
    }

    /** Pecah script SQL per pernyataan, dengan memperhatikan string berkutip dan komentar. */
    public static function split(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                } elseif ($c === $quote) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $buf .= $sql[++$i]; // kutip ganda di dalam string ('')
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($c === '-' && substr($sql, $i, 3) === '-- ' || $c === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            }
            if ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    private static function literal(\PDO $pdo, mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        return $pdo->quote((string) $v);
    }
}
