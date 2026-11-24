-- schema.sql — SPK LAKAPRIOR (MySQL / MariaDB, XAMPP)
-- Import: mysql -u root -e "CREATE DATABASE spk_lakaprior CHARACTER SET utf8mb4"
--         mysql -u root spk_lakaprior < schema.sql && mysql -u root spk_lakaprior < seed.sql

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS hasil, penilaian, kriteria, kecamatan, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash CHAR(64)     NOT NULL,          -- SHA2-256 (ganti ke password_hash() PHP sebelum produksi)
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE kecamatan (
  id                 INT PRIMARY KEY,
  nama               VARCHAR(50) NOT NULL UNIQUE,
  luas_km2           DECIMAL(6,2) NOT NULL,
  penduduk_jiwa      INT NOT NULL,              -- DKB Dukcapil, via Satu Data Sukoharjo
  kepadatan_penduduk DECIMAL(9,2) NOT NULL      -- jiwa/km2 = penduduk_jiwa / luas_km2
) ENGINE=InnoDB;

CREATE TABLE kriteria (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  kode   VARCHAR(5)  NOT NULL UNIQUE,
  nama   VARCHAR(80) NOT NULL,
  satuan VARCHAR(20) NOT NULL,
  tipe   ENUM('benefit','cost') NOT NULL,
  bobot  DECIMAL(5,4) NOT NULL                  -- total = 1.0000
) ENGINE=InnoDB;

CREATE TABLE penilaian (
  kecamatan_id INT NOT NULL,
  kriteria_id  INT NOT NULL,
  nilai        DECIMAL(14,4) NOT NULL,
  PRIMARY KEY (kecamatan_id, kriteria_id),
  FOREIGN KEY (kecamatan_id) REFERENCES kecamatan(id) ON DELETE CASCADE,
  FOREIGN KEY (kriteria_id)  REFERENCES kriteria(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Hasil hitung TOPSIS & SAW (diisi ulang setiap kali hitung ulang)
CREATE TABLE hasil (
  kecamatan_id  INT PRIMARY KEY,
  skor_topsis   DECIMAL(9,6) NOT NULL,
  peringkat_topsis TINYINT UNSIGNED NOT NULL,
  skor_saw      DECIMAL(9,6) NOT NULL,
  peringkat_saw TINYINT UNSIGNED NOT NULL,
  dihitung_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kecamatan_id) REFERENCES kecamatan(id) ON DELETE CASCADE
) ENGINE=InnoDB;
