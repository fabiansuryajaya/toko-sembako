<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\HttpError;

/** Pengaturan toko (identitas di struk, tanda tangan nota hutang). */
final class SettingService
{
    /** Nama setting yang boleh diubah dari halaman Pengaturan, beserta batas panjangnya. */
    public const EDITABLE = [
        'nama_toko'                 => 100,
        'alamat_toko'               => 200,
        'telepon_toko'              => 50,
        'catatan_struk'             => 300,
        'penutup_struk'             => 200,
        'penutup_nota_hutang'       => 200,
        'tampilkan_ttd_nota_hutang' => 1,
    ];

    public const SIGNATURE_DIR = APP_ROOT . '/public/uploads';
    private const SIGNATURE_TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    public static function all(): array
    {
        $out = array_fill_keys(array_keys(self::EDITABLE), '') + ['gambar_ttd' => ''];
        foreach (Db::all('SELECT nama, nilai FROM settings') as $row) {
            $out[$row['nama']] = (string) $row['nilai'];
        }
        $out['tampilkan_ttd_nota_hutang'] = $out['tampilkan_ttd_nota_hutang'] === '1';
        return $out;
    }

    public static function update(array $values): array
    {
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, self::EDITABLE)) {
                throw HttpError::badRequest("Pengaturan '$name' tidak dikenal");
            }
            if ($name === 'tampilkan_ttd_nota_hutang') {
                $value = ($value === true || $value === 1 || $value === '1') ? '1' : '0';
            } elseif (!is_scalar($value)) {
                throw HttpError::badRequest("Nilai '$name' tidak valid");
            }
            $value = trim((string) $value);
            if (mb_strlen($value) > self::EDITABLE[$name]) {
                throw HttpError::badRequest("Nilai '$name' terlalu panjang");
            }
            self::set($name, $value);
        }
        return self::all();
    }

    /** Simpan gambar tanda tangan. Hanya PNG/JPG/WEBP yang benar-benar gambar; nama file dibuat server. */
    public static function saveSignature(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw HttpError::badRequest('File gambar tidak terunggah');
        }
        $maxKb = (int) (app_config()['signature_max_kb'] ?? 1024);
        if ($file['size'] > $maxKb * 1024) {
            throw HttpError::badRequest("Ukuran gambar maksimal $maxKb KB");
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = $info ? (self::SIGNATURE_TYPES[$info[2]] ?? null) : null;
        if ($ext === null) {
            throw HttpError::badRequest('File harus berupa gambar PNG, JPG, atau WEBP');
        }

        if (!is_dir(self::SIGNATURE_DIR) && !mkdir(self::SIGNATURE_DIR, 0755, true)) {
            throw new HttpError(500, 'Folder upload tidak bisa dibuat');
        }
        $name = 'ttd-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], self::SIGNATURE_DIR . '/' . $name)) {
            throw new HttpError(500, 'Gagal menyimpan gambar');
        }
        self::removeSignatureFile();
        self::set('gambar_ttd', 'uploads/' . $name);
        return self::all();
    }

    public static function deleteSignature(): array
    {
        self::removeSignatureFile();
        self::set('gambar_ttd', '');
        return self::all();
    }

    private static function removeSignatureFile(): void
    {
        $current = (string) Db::value("SELECT nilai FROM settings WHERE nama = 'gambar_ttd'");
        // hanya hapus file buatan kita sendiri di folder uploads
        if (preg_match('#^uploads/ttd-[a-f0-9]{16}\.(png|jpg|webp)$#', $current)) {
            @unlink(APP_ROOT . '/public/' . $current);
        }
    }

    private static function set(string $name, string $value): void
    {
        Db::run('INSERT INTO settings (nama, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)', [$name, $value]);
    }
}
