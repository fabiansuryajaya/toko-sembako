-- Skema database Toko Sembako.
-- Jalankan sekali pada database kosong:  mysql -u root toko_sembako < database/schema.sql
-- Semua uang disimpan dalam rupiah bulat (BIGINT), tanpa desimal.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS purchase_items, purchases, sale_items, sales, products, members, units, suppliers, users, settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin', 'pegawai') NOT NULL DEFAULT 'pegawai',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama     VARCHAR(100) NOT NULL,
  nomor_hp VARCHAR(20)  NOT NULL DEFAULT '',
  aktif    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE units (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama  VARCHAR(50)  NOT NULL,
  aktif TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE members (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama     VARCHAR(100) NOT NULL,
  nomor_hp VARCHAR(20)  NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id INT UNSIGNED NOT NULL,
  unit_id     INT UNSIGNED NOT NULL,
  nama        VARCHAR(100) NOT NULL,
  harga_beli  BIGINT       NOT NULL DEFAULT 0,  -- modal per satuan
  harga_jual  BIGINT       NOT NULL DEFAULT 0,
  stok        INT          NOT NULL DEFAULT 0,  -- boleh minus: barang terjual sebelum restock dicatat
  deskripsi   TEXT         NULL,
  aktif       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_products_nama (nama),
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_products_unit     FOREIGN KEY (unit_id)     REFERENCES units (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penjualan tunai dan hutang ada di satu tabel.
-- jenis = 'tunai'  : selalu lunas = 1
-- jenis = 'hutang' : wajib member, lunas = 0 sampai ditandai lunas
CREATE TABLE sales (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis        ENUM('tunai', 'hutang') NOT NULL DEFAULT 'tunai',
  user_id      INT UNSIGNED NOT NULL,              -- kasir
  member_id    INT UNSIGNED NULL,
  nama_pembeli VARCHAR(100) NOT NULL DEFAULT '',
  subtotal     BIGINT       NOT NULL DEFAULT 0,    -- jumlah (qty × harga jual) semua item
  ongkir       BIGINT       NOT NULL DEFAULT 0,
  dibayar      BIGINT       NOT NULL DEFAULT 0,    -- uang yang diterima saat transaksi
  lunas        TINYINT(1)   NOT NULL DEFAULT 1,
  lunas_at     DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_sales_jenis_created (jenis, created_at),
  KEY idx_sales_lunas_created (lunas, created_at),
  CONSTRAINT fk_sales_user   FOREIGN KEY (user_id)   REFERENCES users (id),
  CONSTRAINT fk_sales_member FOREIGN KEY (member_id) REFERENCES members (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty        INT          NOT NULL,
  harga_jual BIGINT       NOT NULL,  -- harga per satuan saat transaksi
  harga_beli BIGINT       NOT NULL,  -- modal per satuan saat transaksi (untuk laporan untung)
  PRIMARY KEY (id),
  KEY idx_sale_items_product (product_id),
  CONSTRAINT fk_sale_items_sale    FOREIGN KEY (sale_id)    REFERENCES sales (id) ON DELETE CASCADE,
  CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchases (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  total      BIGINT       NOT NULL DEFAULT 0,  -- jumlah (qty × harga beli)
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_purchases_created (created_at),
  CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  qty         INT          NOT NULL,
  harga_beli  BIGINT       NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE,
  CONSTRAINT fk_purchase_items_product  FOREIGN KEY (product_id)  REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  nama       VARCHAR(100) NOT NULL,
  nilai      TEXT         NULL,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
