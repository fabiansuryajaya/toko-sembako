<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Membaca & memvalidasi input (query string atau body JSON).
 * Setiap getter melempar HttpError 400 dengan pesan yang bisa langsung ditampilkan.
 */
final class Input
{
    public function __construct(private array $data)
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null && $this->data[$key] !== '';
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function str(string $key, string $label, int $max = 255, bool $required = true, string $default = ''): string
    {
        if (!$this->has($key)) {
            if ($required) {
                throw HttpError::badRequest("$label wajib diisi");
            }
            return $default;
        }
        $v = $this->data[$key];
        if (!is_scalar($v)) {
            throw HttpError::badRequest("$label tidak valid");
        }
        $v = trim((string) $v);
        if ($required && $v === '') {
            throw HttpError::badRequest("$label wajib diisi");
        }
        if (mb_strlen($v) > $max) {
            throw HttpError::badRequest("$label maksimal $max karakter");
        }
        return $v;
    }

    public function int(string $key, string $label, ?int $min = null, ?int $max = null, bool $required = true, ?int $default = null): ?int
    {
        if (!$this->has($key)) {
            if ($required) {
                throw HttpError::badRequest("$label wajib diisi");
            }
            return $default;
        }
        return self::toInt($this->data[$key], $label, $min, $max);
    }

    /** Uang dalam rupiah bulat, tidak boleh negatif. */
    public function money(string $key, string $label, bool $required = true, int $default = 0): int
    {
        return (int) $this->int($key, $label, 0, null, $required, $default);
    }

    public function id(string $key, string $label = 'ID'): int
    {
        return (int) $this->int($key, $label, 1);
    }

    public function bool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }
        $v = $this->data[$key];
        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'Y' || $v === 'on';
    }

    /** Tanggal YYYY-MM-DD, atau null bila kosong. */
    public function date(string $key, string $label): ?string
    {
        if (!$this->has($key)) {
            return null;
        }
        $v = (string) $this->data[$key];
        $d = \DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            throw HttpError::badRequest("$label harus berformat YYYY-MM-DD");
        }
        return $v;
    }

    /** Daftar ID dari array [1,2] atau string "1,2". */
    public function ids(string $key, string $label): array
    {
        if (!$this->has($key)) {
            return [];
        }
        $v = $this->data[$key];
        $list = is_array($v) ? $v : explode(',', (string) $v);
        $out = [];
        foreach ($list as $item) {
            if ($item === '' || $item === null) {
                continue;
            }
            $out[] = self::toInt($item, $label, 1, null);
        }
        return array_values(array_unique($out));
    }

    /** Array of objek (mis. item keranjang). */
    public function list(string $key, string $label): array
    {
        $v = $this->data[$key] ?? null;
        if (!is_array($v) || $v === [] || !array_is_list($v)) {
            throw HttpError::badRequest("$label kosong atau tidak valid");
        }
        return array_map(static function ($row) use ($label): Input {
            if (!is_array($row)) {
                throw HttpError::badRequest("$label tidak valid");
            }
            return new Input($row);
        }, $v);
    }

    private static function toInt(mixed $v, string $label, ?int $min, ?int $max): int
    {
        if (is_int($v)) {
            $n = $v;
        } elseif (is_float($v) && floor($v) === $v) {
            $n = (int) $v;
        } elseif (is_string($v) && preg_match('/^-?\d+$/', trim($v))) {
            $n = (int) trim($v);
        } else {
            throw HttpError::badRequest("$label harus berupa angka bulat");
        }
        if ($min !== null && $n < $min) {
            throw HttpError::badRequest("$label minimal $min");
        }
        if ($max !== null && $n > $max) {
            throw HttpError::badRequest("$label maksimal $max");
        }
        return $n;
    }
}
