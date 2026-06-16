-- ============================================================
-- BistroFlow ERP - Experience Loyalty & Story Membership
-- Database Schema (MySQL) - konsisten dengan Modul 9 PDM
-- ============================================================

CREATE DATABASE IF NOT EXISTS bistroflow_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bistroflow_erp;

-- ----------------------------------------------------------
-- Tabel: users
-- ----------------------------------------------------------
CREATE TABLE users (
    user_id INT(11) NOT NULL AUTO_INCREMENT,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nomor_telepon VARCHAR(13) NOT NULL,
    role ENUM('member','admin','chef','produsen') NOT NULL DEFAULT 'member',
    tier_member ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze',
    status_akun ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    tanggal_daftar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: konfigurasi_credits
-- ----------------------------------------------------------
CREATE TABLE konfigurasi_credits (
    config_id INT(11) NOT NULL AUTO_INCREMENT,
    aturan_credits_per_nominal INT NOT NULL DEFAULT 10000,
    credits_kunjungan INT NOT NULL DEFAULT 500,
    credits_workshop INT NOT NULL DEFAULT 500,
    PRIMARY KEY (config_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: credits
-- ----------------------------------------------------------
CREATE TABLE credits (
    credit_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    jumlah_credits INT(6) NOT NULL,
    saldo_credits INT(8) NOT NULL DEFAULT 0,
    jenis_transaksi_credit ENUM('masuk','keluar') NOT NULL,
    keterangan_credit VARCHAR(255),
    tanggal_credit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (credit_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: transaksi_pos
-- ----------------------------------------------------------
CREATE TABLE transaksi_pos (
    pos_transaksi_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id_member INT(11) NOT NULL,
    nominal_transaksi DECIMAL(15,2) NOT NULL,
    credits_diberikan INT(6) NOT NULL DEFAULT 0,
    status_sinkronisasi ENUM('berhasil','gagal','proses') NOT NULL DEFAULT 'proses',
    tanggal_transaksi_pos DATETIME NOT NULL,
    PRIMARY KEY (pos_transaksi_id),
    FOREIGN KEY (user_id_member) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: konten
-- ----------------------------------------------------------
CREATE TABLE konten (
    konten_id INT(11) NOT NULL AUTO_INCREMENT,
    judul_konten VARCHAR(200) NOT NULL,
    jenis_konten ENUM('video','foto','artikel') NOT NULL,
    deskripsi_konten TEXT NOT NULL,
    path_file_server VARCHAR(500) NOT NULL,
    pembuat_konten_id INT(11) NOT NULL,
    status_konten ENUM('pending','tayang','ditolak') NOT NULL DEFAULT 'pending',
    catatan_review TEXT,
    tanggal_upload DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (konten_id),
    FOREIGN KEY (pembuat_konten_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: bahan
-- ----------------------------------------------------------
CREATE TABLE bahan (
    bahan_id INT(11) NOT NULL AUTO_INCREMENT,
    nama_bahan VARCHAR(150) NOT NULL UNIQUE,
    asal_daerah VARCHAR(200) NOT NULL,
    narasi_cerita_bahan TEXT NOT NULL,
    nama_petani VARCHAR(100) NOT NULL,
    sertifikasi VARCHAR(200),
    status_ketersediaan ENUM('tersedia','terbatas','habis') NOT NULL DEFAULT 'tersedia',
    is_musiman TINYINT(1) NOT NULL DEFAULT 0,
    kuota_reservasi INT DEFAULT 0,
    estimasi_tersedia_berikutnya DATE,
    path_file_server VARCHAR(500),
    produsen_id INT(11) DEFAULT NULL,
    PRIMARY KEY (bahan_id),
    FOREIGN KEY (produsen_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: reservasi
-- ----------------------------------------------------------
CREATE TABLE reservasi (
    reservasi_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id_member INT(11) NOT NULL,
    jenis_reservasi ENUM('bahan_musiman','kunjungan_petani') NOT NULL,
    bahan_id INT(11) DEFAULT NULL,
    tanggal_reservasi DATE NOT NULL,
    jumlah_peserta INT NOT NULL DEFAULT 1,
    status_reservasi ENUM('menunggu','dikonfirmasi','dibatalkan','ditolak') NOT NULL DEFAULT 'menunggu',
    credits_digunakan INT NOT NULL DEFAULT 0,
    catatan_admin TEXT,
    tanggal_dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reservasi_id),
    FOREIGN KEY (user_id_member) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (bahan_id) REFERENCES bahan(bahan_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: workshops
-- ----------------------------------------------------------
CREATE TABLE workshops (
    workshop_id INT(11) NOT NULL AUTO_INCREMENT,
    judul_workshop VARCHAR(200) NOT NULL,
    deskripsi_workshop TEXT NOT NULL,
    chef_pengampu_id INT(11) NOT NULL,
    tanggal_workshop DATE NOT NULL,
    waktu_mulai TIME NOT NULL,
    kapasitas_peserta INT NOT NULL,
    biaya_credits INT NOT NULL DEFAULT 0,
    harga_uang DECIMAL(12,2) NOT NULL DEFAULT 0,
    metode_bayar_workshop ENUM('uang','credits','keduanya') NOT NULL DEFAULT 'keduanya',
    status_workshop ENUM('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif',
    path_rekaman VARCHAR(500),
    PRIMARY KEY (workshop_id),
    FOREIGN KEY (chef_pengampu_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: pendaftaran_workshop
-- ----------------------------------------------------------
CREATE TABLE pendaftaran_workshop (
    pendaftaran_id INT(11) NOT NULL AUTO_INCREMENT,
    workshop_id INT(11) NOT NULL,
    user_id_member INT(11) NOT NULL,
    metode_bayar_workshop ENUM('uang','credits') NOT NULL,
    status_kehadiran ENUM('belum','hadir','tidak_hadir') NOT NULL DEFAULT 'belum',
    path_sertifikat VARCHAR(500),
    tanggal_daftar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pendaftaran_id),
    UNIQUE KEY uniq_workshop_member (workshop_id, user_id_member),
    FOREIGN KEY (workshop_id) REFERENCES workshops(workshop_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_member) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: notifikasi
-- ----------------------------------------------------------
CREATE TABLE notifikasi (
    notifikasi_id INT(11) NOT NULL AUTO_INCREMENT,
    penerima_id INT(11) NOT NULL,
    jenis_notifikasi ENUM('bahan_musiman','konfirmasi_reservasi','workshop_baru','konten_disetujui','konten_ditolak','sertifikat_tersedia') NOT NULL,
    isi_notifikasi TEXT NOT NULL,
    status_pengiriman ENUM('terkirim','gagal','belum_dibaca') NOT NULL DEFAULT 'belum_dibaca',
    tanggal_kirim DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tier_target ENUM('gold','silver','bronze','semua') DEFAULT NULL,
    PRIMARY KEY (notifikasi_id),
    FOREIGN KEY (penerima_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- DATA AWAL (SEED)
-- ----------------------------------------------------------

INSERT INTO konfigurasi_credits (aturan_credits_per_nominal, credits_kunjungan, credits_workshop)
VALUES (10000, 500, 500);

-- Password default untuk semua akun seed di bawah: "password123"
-- Hash dibuat dengan password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO users (nama_lengkap, email, password, nomor_telepon, role, tier_member, status_akun) VALUES
('Admin BistroFlow', 'admin@bistroflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200000001', 'admin', 'bronze', 'aktif'),
('Chef Marcus Rossi', 'chef@bistroflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200000002', 'chef', 'bronze', 'aktif'),
('Elena Bianchi', 'produsen@bistroflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200000003', 'produsen', 'bronze', 'aktif'),
('Alexander Wijaya', 'member@bistroflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200000004', 'member', 'gold', 'aktif');

-- Saldo credits awal untuk member contoh (Alexander Wijaya, user_id = 4)
INSERT INTO credits (user_id, jumlah_credits, saldo_credits, jenis_transaksi_credit, keterangan_credit) VALUES
(4, 24500, 24500, 'masuk', 'Saldo awal akumulasi loyalty');

-- Bahan musiman contoh
INSERT INTO bahan (nama_bahan, asal_daerah, narasi_cerita_bahan, nama_petani, sertifikasi, status_ketersediaan, is_musiman, kuota_reservasi, estimasi_tersedia_berikutnya, produsen_id) VALUES
('Alba White Truffle', 'Piedmont, Italia', 'Diunggah pagi ini dari perbukitan Piedmont, truffle ini memiliki aroma bawang putih, tanah, dan madu liar yang mendalam.', 'Tartufi Morra', 'Slow Food Presidia', 'terbatas', 1, 12, '2026-07-01', 3),
('Matsutake', 'Cascade, Washington', 'Dipanen dari hutan Cascade dengan aroma khas pinus dan rempah, sangat dicari pada musim gugur.', 'Cascade Foragers', NULL, 'terbatas', 1, 5, '2026-06-30', 3),
('Bafun Uni (Sea Urchin)', 'Hokkaido, Jepang', 'Uni segar dari perairan Hokkaido dengan tekstur creamy dan rasa laut yang bersih.', 'Hokkaido Fisheries', 'MSC Certified', 'tersedia', 1, 20, '2026-08-01', 3);

-- Workshop contoh
INSERT INTO workshops (judul_workshop, deskripsi_workshop, chef_pengampu_id, tanggal_workshop, waktu_mulai, kapasitas_peserta, biaya_credits, harga_uang, metode_bayar_workshop, status_workshop) VALUES
('The Architecture of Flavor: Deconstructing the Modern Sauce', 'Workshop mendalami teknik dasar saus modern bersama Chef Marcus Rossi, alumnus Michelin 3-Star.', 2, '2026-06-24', '19:00:00', 15, 800, 1500000, 'keduanya', 'aktif'),
('Artisanal Sourdough: Cultivating the Perfect Starter', 'Belajar membuat starter sourdough dan teknik fermentasi roti artisan.', 2, '2026-06-28', '10:00:00', 20, 500, 750000, 'keduanya', 'aktif');

-- Konten storytelling contoh
INSERT INTO konten (judul_konten, jenis_konten, deskripsi_konten, path_file_server, pembuat_konten_id, status_konten) VALUES
('The Art of Fermentation', 'video', 'Dokumentasi proses fermentasi bahan eksklusif yang digunakan dalam menu musim ini.', '/uploads/konten/the-art-of-fermentation.mp4', 2, 'tayang'),
('Sourcing the Perfect Truffle: A Journey to Alba', 'artikel', 'Perjalanan tim kuliner ke Piedmont, Italia untuk menjalin hubungan dengan pemburu truffle.', '/uploads/konten/sourcing-truffle-alba.html', 3, 'tayang'),
('The Origin of our Wagyu', 'artikel', 'Cerita asal usul Wagyu A5 yang digunakan pada menu signature.', '/uploads/konten/origin-wagyu.html', 2, 'pending');
