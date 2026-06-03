-- Migration 003: Fitur keamanan login — anti brute force + reset password via email
-- Jalankan via phpMyAdmin — hanya sekali

-- 1. Tambah kolom email ke tabel admin
ALTER TABLE admin
  ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER username;

-- 2. Set email admin (sesuaikan jika username berbeda)
UPDATE admin SET email = 'purwandaru.w@gmail.com' WHERE username = 'admin';

-- 3. Tabel pencatatan percobaan login gagal
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_address, attempted_at)
);

-- 4. Tabel token reset password
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT         NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME    NOT NULL,
  used       TINYINT     DEFAULT 0,
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
);
