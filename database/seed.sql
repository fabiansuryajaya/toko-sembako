-- Data awal / contoh. Jalankan setelah schema.sql:  mysql -u root toko_sembako < database/seed.sql
-- Akun awal: admin / admin dan pegawai / pegawai. SEGERA ganti password-nya di menu Master → User.

SET NAMES utf8mb4;

INSERT INTO users (username, password_hash, role) VALUES
  ('admin',   '$2y$12$ofEowM1Oi1UU8bDgFZPTcen2dFI/yfi5Q3wBjHM9Iwh85gEdk6zXm', 'admin'),
  ('pegawai', '$2y$12$i0bLgeERzVVE4aynXIwCzOeaoQjv9yTQJAZFt2r7ToT1mGjvE8l/O', 'pegawai');

INSERT INTO units (nama) VALUES ('Kg'), ('Liter'), ('Pcs'), ('Sak');

INSERT INTO suppliers (nama, nomor_hp) VALUES
  ('Supplier Beras', ''),
  ('Supplier Minyak', ''),
  ('Supplier Umum', '');

INSERT INTO products (supplier_id, unit_id, nama, harga_beli, harga_jual, stok, deskripsi) VALUES
  (1, 1, 'Beras Medium',   12000, 13500, 100, NULL),
  (1, 4, 'Beras 25 Kg',   290000, 320000, 10, 'Karung 25 kg'),
  (2, 2, 'Minyak Goreng',  15000, 17000, 50, NULL),
  (3, 1, 'Gula Pasir',     15500, 17500, 40, NULL),
  (3, 3, 'Telur (butir)',   1800,  2200, 300, NULL);

INSERT INTO settings (nama, nilai) VALUES
  ('nama_toko', 'Toko Sembako'),
  ('alamat_toko', ''),
  ('telepon_toko', ''),
  ('catatan_struk', 'Barang yang dibeli tidak dapat dikembalikan'),
  ('penutup_struk', 'TERIMA KASIH ATAS KUNJUNGAN ANDA'),
  ('penutup_nota_hutang', 'TERIMA KASIH ATAS PESANANNYA! DITUNGGU ORDER BERIKUTNYA'),
  ('tampilkan_ttd_nota_hutang', '0'),
  ('gambar_ttd', '');
