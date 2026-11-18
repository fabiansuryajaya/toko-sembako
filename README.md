# Toko Sembako

Aplikasi kasir & stok untuk toko sembako: penjualan tunai, hutang member, restock, laporan untung, cetak struk 80 mm, dan backup database. PHP native (tanpa framework/composer) + MySQL/MariaDB, jalan di XAMPP biasa.

## Instalasi (XAMPP di Windows)

1. Salin folder ini ke `C:\xampp\htdocs\toko-sembako`.
2. Buka phpMyAdmin → buat database `toko_sembako` (collation `utf8mb4_unicode_ci`).
3. Import `database/schema.sql`, lalu `database/seed.sql` (tab **Import**).
   Atau lewat terminal:
   ```
   mysql -u root toko_sembako < database/schema.sql
   mysql -u root toko_sembako < database/seed.sql
   ```
4. Buka <http://localhost/toko-sembako/>.
5. Login `admin` / `admin`, lalu **segera ganti password** (menu *Akun Saya*). Akun `pegawai` / `pegawai` juga ada di seed.
6. Isi identitas toko untuk struk di menu *Pengaturan*.

Koneksi database default: `root` tanpa password di `127.0.0.1`. Kalau berbeda, salin `config/config.example.php` menjadi `config/config.php` lalu ubah isinya.

Syarat: PHP ≥ 8.1 dengan ekstensi `pdo_mysql`, MySQL ≥ 8 atau MariaDB ≥ 10.4, Apache dengan `AllowOverride All` (bawaan XAMPP).

> **Server selain Apache (nginx, `php -S`)**: arahkan *document root* ke folder `public/`.
> File `.htaccess` yang melindungi `config/`, `storage/` (berisi backup), dan folder lain hanya dibaca Apache.

## Fitur & hak akses

| Menu | Admin | Pegawai |
|---|---|---|
| Barang | lihat, tambah, edit, nonaktifkan, ubah harga massal, ekspor Excel | lihat (tanpa modal & supplier), ekspor |
| Supplier, Satuan, Member, User | ✔ | – |
| Penjualan | buat, edit, hapus, ganti harga, pilih kasir, struk | buat, edit, struk (harga sesuai daftar barang) |
| Hutang | buat, edit, tandai/batalkan lunas, hapus yang belum lunas, nota | buat, edit, nota |
| Restock | ✔ (stok bertambah, modal ikut harga beli terakhir) | – |
| Laporan | penjualan lunas per barang + untung, ekspor Excel, hapus arsip per periode | – |
| Backup Database, Pengaturan | ✔ | – |
| Akun Saya (ganti password) | ✔ | ✔ |

Hak akses dicek di server untuk setiap request. Menu yang disembunyikan di browser hanya untuk kenyamanan.

### Aturan stok & uang
- Setiap penjualan/hutang mengurangi stok. **Edit** mengembalikan stok item lama dulu, lalu mengurangi dengan item baru. **Hapus** mengembalikan stok. Semua langkah berjalan dalam satu transaksi database.
- Stok boleh minus (barang terjual sebelum restock dicatat); angka minus tampil merah.
- Modal (harga beli) dicatat per item saat transaksi, jadi laporan untung tidak berubah walau modal barang naik belakangan.
- Laporan hanya memuat transaksi lunas (tunai + hutang lunas). "Hapus Data Periode Ini" hanya menghapus transaksi lunas pada rentang tanggal yang dipilih. Hutang yang belum dibayar tidak ikut terhapus.
- Uang disimpan sebagai rupiah bulat.

## Struktur folder

```
public/              satu-satunya folder yang dibuka browser
  index.php          halaman login
  app.php            kerangka aplikasi (menu sesuai role)
  page.php           potongan HTML tiap halaman (cek hak akses)
  api.php            satu pintu API: api.php?r=<resource>
  assets/js/core/    modul bersama: api, keranjang, struk, modal, dropdown, ekspor Excel
  assets/js/pages/   satu modul per halaman
  assets/vendor/     jQuery, Select2, SheetJS (disimpan lokal: kasir tetap jalan tanpa internet)
  uploads/           gambar tanda tangan (eksekusi skrip dimatikan)
src/
  bootstrap.php      autoload, konfigurasi, zona waktu
  Api/routes.php     daftar endpoint + hak akses masing-masing
  Services/          logika bisnis (penjualan/hutang, restock, laporan, backup, …)
  Support/           database (PDO), auth/CSRF, validasi input, error HTTP
  Views/             markup halaman
database/            schema.sql, seed.sql
storage/backups/     salinan otomatis sebelum import database
tests/               test integrasi PHP + test browser
```

## Keamanan
- Login berbasis session (cookie `HttpOnly`, `SameSite=Lax`), password di-hash (`password_hash`), dikunci 5 menit setelah 5 kali salah.
- Token CSRF wajib untuk semua request yang mengubah data.
- Semua query memakai parameter (prepared statement). Output di browser di-escape.
- Harga modal tidak pernah dikirim ke akun pegawai.
- Upload tanda tangan hanya menerima gambar PNG/JPG/WEBP asli. Nama file dibuat server.
- Import database hanya menerima file backup dari aplikasi ini (perintah `SET`/`DROP TABLE`/`CREATE TABLE`/`INSERT` untuk tabel aplikasi). Sebelum import, database disalin ke `storage/backups/`.

## Test

```bash
# Test integrasi (memakai database terpisah yang DIKOSONGKAN tiap kali jalan; nama wajib mengandung "test")
mysql -u root -e "CREATE DATABASE toko_sembako_test"
php tests/run.php
# env opsional: TEST_DB_HOST, TEST_DB_PORT, TEST_DB_NAME, TEST_DB_USER, TEST_DB_PASS

# Test browser (Playwright). Database harus berisi seed awal; tes ini mengubah data.
php -S 127.0.0.1:8765 -t .
BASE_URL=http://127.0.0.1:8765/public node tests/e2e.mjs
```

## Pindah dari versi lama

Versi ini memakai skema database baru, jadi data versi lama tidak terbaca langsung. Perubahan utama dari versi lama:
- **Keamanan**:
  - API dulu bisa dipanggil tanpa login. Contohnya `api/report.php` menghapus semua penjualan pada request GET, upload tanda tangan menerima file `.php`, dan import menjalankan SQL apa pun.
  - Password dulu tersimpan polos, dan role hanya disimpan di `localStorage` browser.
- **Stok**:
  - Edit penjualan/hutang dulu mengurangi stok dua kali.
  - Hapus penjualan dulu tidak mengembalikan stok dan meninggalkan baris detail.
  - Penulisan dulu tidak memakai transaksi database.
- **Laporan**: hutang dulu tidak mencatat modal, sehingga untungnya salah.
- **Kode**:
  - Logika keranjang dan struk yang dulu disalin di tiga tempat sekarang jadi satu modul.
  - Identitas toko yang dulu ditulis di kode sekarang ada di Pengaturan.
  - Format respons API dulu berbeda-beda, sekarang seragam.
  - File `select2.min.css` yang dulu kosong sudah diganti.
