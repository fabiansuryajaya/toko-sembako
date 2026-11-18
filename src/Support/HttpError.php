<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/** Error yang aman ditampilkan ke pengguna, lengkap dengan kode HTTP. */
final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function unauthorized(string $message = 'Silakan login terlebih dahulu'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'Anda tidak punya akses untuk tindakan ini'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Data tidak ditemukan'): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }
}
