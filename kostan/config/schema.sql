-- ============================================================
-- Kost PW — Database Schema
-- Jalankan via phpMyAdmin atau: mysql -u user -p kostan_pw < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ─── Tabel: admin ─────────────────────────────────────────────────────────────
-- Seed password: jalankan di terminal server →
--   php -r "echo password_hash('password_anda', PASSWORD_DEFAULT);"
-- Lalu UPDATE admin SET password='HASIL_HASH' WHERE username='admin';

CREATE TABLE IF NOT EXISTS admin (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50)  NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
);

INSERT INTO admin (username, password) VALUES
  ('admin', '$2y$10$placeholder.ganti.dengan.hash.asli.dari.php');

-- ─── Tabel: rooms ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS rooms (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  nomor_kamar          VARCHAR(20)  NOT NULL,
  ukuran               VARCHAR(20)  DEFAULT NULL COMMENT 'Contoh: 3x3.3m',
  lantai               TINYINT      DEFAULT 1,
  harga_sewa           INT          NOT NULL DEFAULT 0,
  no_pelanggan_listrik VARCHAR(20)  DEFAULT NULL COMMENT 'No. ID Pelanggan PLN untuk token listrik',
  youtube_url          VARCHAR(255) DEFAULT NULL COMMENT 'URL video YouTube/Shorts kamar ini',
  fasilitas            TEXT,
  status               ENUM('tersedia','terisi') DEFAULT 'tersedia',
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO rooms (nomor_kamar, ukuran, lantai, harga_sewa, no_pelanggan_listrik, fasilitas, status) VALUES
  ('Aurelia',  '3x3.3m',   1, 0, NULL, 'AC Sharp 0.5PK, kasur+sprei, lemari+meja rias, kamar mandi dalam, water heater Ariston, closet TOTO', 'terisi'),
  ('Bella',    '3x3.3m',   1, 0, NULL, 'AC Sharp 0.5PK, kasur+sprei, lemari+meja rias, kamar mandi dalam, water heater Ariston, closet TOTO', 'terisi'),
  ('Celestia', '3x3.8m',   1, 0, NULL, 'AC Sharp 0.5PK, kasur+sprei, lemari+meja rias, kamar mandi dalam, water heater Ariston, closet TOTO', 'terisi'),
  ('Dravena',  '3x3m',     1, 0, NULL, 'AC Sharp 0.5PK, kasur+sprei, lemari+meja rias, kamar mandi dalam, water heater Ariston, closet TOTO', 'terisi'),
  ('Elara',    '3x3.8m',   1, 0, NULL, 'AC Sharp 0.5PK, kasur+sprei, lemari+meja rias, kamar mandi dalam, water heater Ariston, closet TOTO', 'terisi'),
  ('Florence', '3.7x3.8m', 1, 0, NULL, 'AC Sharp 0.5PK, kasur+sprei, lemari+meja rias, kamar mandi dalam, water heater Ariston, closet TOTO', 'terisi');

-- ─── Tabel: tenants ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS tenants (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama       VARCHAR(100) NOT NULL,
  no_wa      VARCHAR(20)  NOT NULL,
  no_ktp     VARCHAR(20)  DEFAULT NULL,
  kamar_id   INT          DEFAULT NULL,
  tgl_masuk  DATE         DEFAULT NULL,
  tgl_keluar DATE         DEFAULT NULL,
  status     ENUM('aktif','keluar') DEFAULT 'aktif',
  catatan    TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kamar_id) REFERENCES rooms(id) ON DELETE SET NULL
);

-- ─── Tabel: candidates ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS candidates (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  nama               VARCHAR(100) NOT NULL,
  no_wa              VARCHAR(20)  NOT NULL,
  kamar_diminati     VARCHAR(20)  DEFAULT NULL,
  tgl_masuk_rencana  DATE         DEFAULT NULL,
  status             ENUM('waitlist','survey','approved','rejected') DEFAULT 'waitlist',
  catatan            TEXT,
  sumber             ENUM('form_web','manual') DEFAULT 'form_web',
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Tabel: bills ─────────────────────────────────────────────────────────────

-- Listrik & air TIDAK ditagihkan — listrik pakai token mandiri per kamar,
-- air termasuk dalam sewa. Kolom lainnya untuk biaya tambahan insidental.
CREATE TABLE IF NOT EXISTS bills (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id            INT          NOT NULL,
  bulan                DATE         NOT NULL COMMENT 'Format: YYYY-MM-01',
  sewa                 INT          NOT NULL,
  lainnya              INT          DEFAULT 0,
  keterangan_lainnya   VARCHAR(255) DEFAULT NULL,
  total                INT GENERATED ALWAYS AS (sewa + lainnya) STORED,
  tgl_jatuh_tempo      DATE         NOT NULL,
  status_bayar         ENUM('unpaid','paid') DEFAULT 'unpaid',
  tgl_bayar            DATE         DEFAULT NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_bulan (tenant_id, bulan),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- ─── Tabel: payments ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS payments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  bill_id      INT         NOT NULL,
  tgl_bayar    DATE        NOT NULL,
  nominal      INT         NOT NULL,
  metode       ENUM('transfer','tunai','qris') DEFAULT 'transfer',
  catatan      TEXT,
  confirmed_by VARCHAR(50) DEFAULT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
);

-- ─── Tabel: wa_queue ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS wa_queue (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  bill_id    INT  NOT NULL,
  tipe       ENUM('tagihan','reminder') DEFAULT 'tagihan',
  pesan_text TEXT NOT NULL,
  status     ENUM('pending','sent') DEFAULT 'pending',
  sent_at    TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
);

-- ─── Tabel: wa_templates ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS wa_templates (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tipe          VARCHAR(50) NOT NULL UNIQUE,
  template_text TEXT        NOT NULL,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO wa_templates (tipe, template_text) VALUES
('tagihan',
'Halo Kak *{{nama}}* 👋

Berikut tagihan kost bulan *{{bulan}}* untuk Kamar *{{kamar}}*:

━━━━━━━━━━━━━━━
🏠 Sewa Kamar  : {{sewa}}{{baris_lainnya}}
━━━━━━━━━━━━━━━
*TOTAL         : {{total}}*

📅 Jatuh Tempo: *{{jatuh_tempo}}*
🏦 Transfer ke:
   {{nama_bank}} {{no_rekening}}
   a.n. {{nama_pemilik}}

🔌 No. Token Listrik: {{no_pelanggan}}

Mohon konfirmasi setelah transfer ya Kak 🙏
Terima kasih!'),

('reminder',
'Halo Kak *{{nama}}* 😊

Mengingatkan kembali, tagihan kost bulan *{{bulan}}* sebesar *{{total}}* sudah jatuh tempo kemarin ({{jatuh_tempo}}).

Jika sudah transfer, mohon abaikan pesan ini ya Kak 🙏

Jika belum, ditunggu konfirmasinya.
Terima kasih 🙏'),

('kamar_kosong',
'Halo Kak *{{nama}}* 👋

Kabar gembira! Ada kamar yang tersedia di *Kost PW Balai Pustaka*.

🏠 Kamar   : *{{kamar}}*
📐 Ukuran  : {{ukuran}}
💰 Harga   : {{harga}} / bulan

Fasilitas:
✅ AC Sharp 0.5 PK
✅ Kasur, bantal & sprei
✅ Lemari + meja rias
✅ Kamar mandi dalam
✅ Water heater Ariston
✅ WiFi Biznet 300 Mbps
✅ Listrik token mandiri

Jika tertarik, segera balas pesan ini ya Kak 🙏
Tempat terbatas!');

-- ─── Tabel: expenses ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS expenses (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  tanggal    DATE        NOT NULL,
  kategori   ENUM('listrik','air','perawatan','kebersihan','lainnya') NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  nominal    INT         NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── Tabel: settings ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS settings (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(50) NOT NULL UNIQUE,
  value    TEXT        NOT NULL
);

INSERT INTO settings (key_name, value) VALUES
  ('nama_kost',       'Kost PW'),
  ('nama_pemilik',    'Purwandaru'),
  ('nama_bank',       'BCA'),
  ('no_rekening',     ''),
  ('tgl_jatuh_tempo', '5'),
  ('alamat',          'Balai Pustaka-Rawamangun, Jakarta Timur'),
  ('fasilitas_umum',  'WiFi Biznet 300Mbps, dapur bersama, mesin cuci 7kg, parkir motor, balkon, taman minimalis');
