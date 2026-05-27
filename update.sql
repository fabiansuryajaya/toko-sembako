alter table detail_penjualan add column harga_pembelian int default 0 after harga_penjualan;
alter table penjualan add column nama_pembeli varchar(100) default '' after id_member;

-- Create settings table for system configuration
CREATE TABLE IF NOT EXISTS `settings` (
  `id_setting` int(11) NOT NULL AUTO_INCREMENT,
  `nama_setting` varchar(100) NOT NULL UNIQUE,
  `nilai_setting` text,
  `tipe_setting` varchar(20) DEFAULT 'text',
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_setting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default settings
INSERT IGNORE INTO `settings` (`nama_setting`, `nilai_setting`, `tipe_setting`) VALUES
('show_signature_on_nota_hutang', '0', 'boolean');