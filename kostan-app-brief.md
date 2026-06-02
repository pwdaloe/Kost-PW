# Kostan Management App — Project Brief

> Dokumen ini adalah konteks lengkap untuk melanjutkan development di Claude Code.
> Dibuat dari sesi perencanaan di Claude.ai.

---

## 1. Overview Proyek

Aplikasi manajemen kostan sederhana berbasis web untuk **6 kamar**, dijalankan di:
- **Server**: Shared VPS + cPanel
- **Stack yang disepakati**: Standalone PHP app (bukan WordPress) di subdomain, e.g. `kostan.domainmu.com`
- **Database**: MySQL (tersedia via cPanel / phpMyAdmin)
- **Approach development**: Vibe coding (AI-assisted)

### Tujuan Utama
Mencatat dan mengelola:
1. Data penghuni aktif
2. Kandidat / calon penghuni
3. Tagihan bulanan per kamar
4. Biaya operasional kost

---

## 2. Fitur yang Harus Dibangun

### Sprint 1 — MVP (Target: ~4 hari)
- [ ] CRUD penghuni aktif (nama, no WA, kamar, tanggal masuk, status)
- [ ] CRUD kamar (nomor kamar, harga sewa, lantai, status: tersedia/terisi)
- [ ] Form input kandidat penghuni + status pipeline (Waitlist → Survey → Aktif → Ditolak)
- [ ] Generate tagihan bulanan per penghuni (sewa + listrik + air + lainnya)
- [ ] Tombol **"Kirim WA"** — buka `wa.me` link dengan pesan tagihan terisi otomatis
- [ ] Tombol **"Tandai Lunas"** + histori pembayaran
- [ ] Login admin sederhana (single user, session PHP)

### Sprint 2 — Otomasi (Target: minggu ke-2)
- [ ] Cron job harian (08:00) — deteksi tagihan H-3 jatuh tempo, generate antrian WA
- [ ] Dashboard antrian WA: status Pending / Sudah Kirim / Sudah Bayar
- [ ] Cron H+1 — generate reminder otomatis untuk yang belum bayar
- [ ] Template pesan WA bisa diedit admin via UI
- [ ] Laporan biaya operasional + ringkasan arus kas bulanan

### Post-MVP (Nice to have)
- [ ] Export tagihan ke PDF
- [ ] Notifikasi WA blast via Fonnte API (upgrade dari semi-otomatis)
- [ ] Upload foto KTP penghuni

---

## 3. Mekanisme Penagihan WA (Semi-Otomatis)

### Alur Kerja
```
Cron H-3 jalan tiap pagi 08:00
  └─> Cek penghuni dengan jatuh tempo dalam 3 hari
  └─> Generate tagihan jika belum ada
  └─> Masukkan ke tabel wa_queue (status: pending)

Admin buka dashboard
  └─> Lihat list tagihan dengan status badge
  └─> Klik tombol "Kirim WA" per penghuni
  └─> Browser buka: https://wa.me/628xxx?text=...
  └─> Admin pencet Send di WA Web/Desktop
  └─> Sistem update status → "sent"

Cron H+1 (belum bayar)
  └─> Generate pesan reminder
  └─> Masuk antrian wa_queue lagi (tipe: reminder)
```

### Format Teks Pesan (Template Default)
```
Halo Kak *{{nama}}* 👋

Berikut tagihan kost bulan *{{bulan}}* untuk Kamar *{{kamar}}*:

━━━━━━━━━━━━━━━
🏠 Sewa Kamar      : Rp {{sewa}}
💡 Listrik ({{kwh}} kWh): Rp {{listrik}}
💧 Air              : Rp {{air}}
{{baris_lainnya}}
━━━━━━━━━━━━━━━
*TOTAL              : Rp {{total}}*

📅 Jatuh Tempo: *{{jatuh_tempo}}*
🏦 Transfer ke:
   {{nama_bank}} {{no_rekening}}
   a.n. {{nama_pemilik}}

Mohon konfirmasi setelah transfer ya Kak 🙏
Terima kasih!
```

### Format Teks Reminder (H+1)
```
Halo Kak *{{nama}}* 😊

Mengingatkan kembali, tagihan kost bulan *{{bulan}}* sebesar *Rp {{total}}* sudah jatuh tempo kemarin ({{jatuh_tempo}}).

Jika sudah transfer, mohon abaikan pesan ini ya Kak 🙏

Jika belum, ditunggu konfirmasinya.
Terima kasih 🙏
```

### Kenapa Semi-Otomatis (bukan full-auto)?
- Tidak pakai WA API gateway berbayar → Rp 0/bulan
- Tidak risiko banned (wa.me adalah link resmi Meta)
- 6 kamar = 6 klik/bulan — sangat manageable
- **Upgrade path tersedia**: bisa swap ke Fonnte API kapan saja tanpa ubah DB schema

---

## 4. Database Schema

### Tabel: `rooms`
```sql
CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor_kamar VARCHAR(10) NOT NULL,
  lantai TINYINT DEFAULT 1,
  harga_sewa INT NOT NULL,
  fasilitas TEXT,
  status ENUM('tersedia', 'terisi') DEFAULT 'tersedia',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel: `tenants`
```sql
CREATE TABLE tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  no_wa VARCHAR(20) NOT NULL,
  no_ktp VARCHAR(20),
  kamar_id INT,
  tgl_masuk DATE,
  tgl_keluar DATE,
  status ENUM('aktif', 'keluar') DEFAULT 'aktif',
  catatan TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kamar_id) REFERENCES rooms(id)
);
```

### Tabel: `candidates`
```sql
CREATE TABLE candidates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  no_wa VARCHAR(20) NOT NULL,
  kamar_diminati VARCHAR(10),
  tgl_masuk_rencana DATE,
  status ENUM('waitlist', 'survey', 'approved', 'rejected') DEFAULT 'waitlist',
  catatan TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel: `bills`
```sql
CREATE TABLE bills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  bulan DATE NOT NULL COMMENT 'Format: YYYY-MM-01',
  sewa INT NOT NULL,
  listrik INT DEFAULT 0,
  kwh DECIMAL(8,2) DEFAULT 0,
  air INT DEFAULT 0,
  lainnya INT DEFAULT 0,
  keterangan_lainnya VARCHAR(255),
  total INT GENERATED ALWAYS AS (sewa + listrik + air + lainnya) STORED,
  tgl_jatuh_tempo DATE NOT NULL,
  status_bayar ENUM('unpaid', 'paid') DEFAULT 'unpaid',
  tgl_bayar DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### Tabel: `payments`
```sql
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_id INT NOT NULL,
  tgl_bayar DATE NOT NULL,
  nominal INT NOT NULL,
  metode ENUM('transfer', 'tunai', 'qris') DEFAULT 'transfer',
  catatan TEXT,
  confirmed_by VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(id)
);
```

### Tabel: `wa_queue`
```sql
CREATE TABLE wa_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_id INT NOT NULL,
  tipe ENUM('tagihan', 'reminder') DEFAULT 'tagihan',
  pesan_text TEXT NOT NULL,
  status ENUM('pending', 'sent') DEFAULT 'pending',
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(id)
);
```

### Tabel: `wa_templates`
```sql
CREATE TABLE wa_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipe VARCHAR(50) NOT NULL UNIQUE,
  template_text TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabel: `expenses`
```sql
CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  kategori ENUM('listrik', 'air', 'perawatan', 'kebersihan', 'lainnya') NOT NULL,
  keterangan VARCHAR(255),
  nominal INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel: `settings`
```sql
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(50) NOT NULL UNIQUE,
  value TEXT NOT NULL
);

-- Seed data
INSERT INTO settings (key_name, value) VALUES
  ('nama_kost', 'Kostan Pak Daru'),
  ('nama_pemilik', 'Daru Purnomo'),
  ('nama_bank', 'BCA'),
  ('no_rekening', '1234567890'),
  ('tgl_jatuh_tempo', '5') -- default tanggal jatuh tempo tiap bulan
;
```

---

## 5. Struktur Folder Proyek

```
/kostan/
├── index.php              # Redirect ke /dashboard
├── config/
│   └── db.php             # Koneksi MySQL + konstanta
├── auth/
│   ├── login.php
│   └── logout.php
├── pages/
│   ├── dashboard.php      # Ringkasan: kamar terisi, tagihan pending, dll
│   ├── tenants.php        # List + CRUD penghuni aktif
│   ├── candidates.php     # List + CRUD kandidat
│   ├── rooms.php          # List + CRUD kamar
│   ├── bills.php          # List tagihan + tombol kirim WA + tandai lunas
│   ├── expenses.php       # Pencatatan biaya operasional
│   └── settings.php       # Template WA + info rekening
├── api/
│   ├── generate_wa.php    # Return wa.me URL + update status sent
│   ├── mark_paid.php      # Tandai tagihan lunas
│   └── generate_bill.php  # Generate tagihan manual 1 penghuni
├── cron/
│   ├── check_billing.php  # Jalankan setiap hari: deteksi H-3, buat wa_queue
│   └── send_reminders.php # Jalankan setiap hari: deteksi H+1 belum bayar
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
└── assets/
    ├── css/style.css
    └── js/app.js
```

---

## 6. Fungsi Kunci PHP

### `generate_wa_link($bill_id)` — di `api/generate_wa.php`
```php
function generateWaLink($bill_id) {
    $bill    = getBill($bill_id);
    $tenant  = getTenant($bill['tenant_id']);
    $template = getTemplate('tagihan'); // dari wa_templates

    $pesan = str_replace([
        '{{nama}}', '{{kamar}}', '{{bulan}}',
        '{{sewa}}', '{{listrik}}', '{{kwh}}', '{{air}}',
        '{{total}}', '{{jatuh_tempo}}'
    ], [
        $tenant['nama'], $bill['nomor_kamar'], formatBulan($bill['bulan']),
        formatRupiah($bill['sewa']), formatRupiah($bill['listrik']),
        $bill['kwh'], formatRupiah($bill['air']),
        formatRupiah($bill['total']), formatTanggal($bill['tgl_jatuh_tempo'])
    ], $template);

    // Normalisasi nomor WA → 62xxx
    $nomor = '62' . ltrim(preg_replace('/[^0-9]/', '', $tenant['no_wa']), '0');

    // Update status antrian
    updateWaStatus($bill_id, 'sent');

    return 'https://wa.me/' . $nomor . '?text=' . urlencode($pesan);
}
```

### Cron `check_billing.php` — deteksi H-3
```php
// Jalankan: setiap hari jam 08:00
// cPanel Cron: 0 8 * * * php /home/user/kostan/cron/check_billing.php

$penghuni_h3 = query("
    SELECT b.id, b.tenant_id, t.nama, t.no_wa, r.nomor_kamar
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms r ON t.kamar_id = r.id
    WHERE b.tgl_jatuh_tempo = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND b.status_bayar = 'unpaid'
    AND b.id NOT IN (SELECT bill_id FROM wa_queue WHERE tipe='tagihan')
");

foreach ($penghuni_h3 as $p) {
    $pesan = generatePesanTagihan($p['id']); // render template
    insertWaQueue($p['id'], 'tagihan', $pesan);
}
```

---

## 7. UI / UX Notes

- **Framework CSS**: Gunakan Tailwind CDN atau Bootstrap 5 CDN — tidak perlu build step
- **Warna tema**: Bebas, yang penting bersih dan mobile-friendly
- **Dashboard utama** harus tampilkan:
  - Jumlah kamar terisi vs kosong (dari 6)
  - Jumlah tagihan pending kirim WA (badge merah)
  - Jumlah tagihan belum bayar bulan ini
  - Shortcut tombol: "Generate Tagihan Bulan Ini", "Lihat Antrian WA"
- **Halaman Tagihan**: tabel dengan kolom Nama, Kamar, Total, Jatuh Tempo, Status Bayar, Status WA, Aksi
- **Tombol "Kirim WA"**: buka tab baru (`target="_blank"`), jangan redirect halaman

---

## 8. Security Checklist (Wajib di Setiap File)

- [ ] Semua query pakai **prepared statements** (`PDO` atau `mysqli` dengan bind param)
- [ ] Semua input di-sanitasi dengan `htmlspecialchars()` saat output
- [ ] Session check di setiap halaman: `if (!isset($_SESSION['admin'])) { header('Location: /auth/login.php'); exit; }`
- [ ] `.htaccess` di folder `/config/` dan `/cron/` untuk block direct HTTP access
- [ ] Nomor WA di-strip non-numerik sebelum dimasukkan URL
- [ ] Jangan simpan password plain text — gunakan `password_hash()` / `password_verify()`

---

## 9. Catatan Deployment (cPanel)

1. Upload ke subdomain: `kostan.domainmu.com` → root di `/public_html/kostan/`
2. Buat database MySQL baru via cPanel → phpMyAdmin → jalankan semua `CREATE TABLE` di atas
3. Isi `config/db.php` dengan kredensial DB
4. Set cron job di cPanel:
   - `0 8 * * * php /home/username/public_html/kostan/cron/check_billing.php`
   - `0 8 * * * php /home/username/public_html/kostan/cron/send_reminders.php`
5. Pastikan PHP version ≥ 7.4 di cPanel

---

## 10. Konteks Tambahan

- Jumlah kamar: **6 kamar**
- Admin: **1 orang** (pemilik kost)
- WA gateway: **semi-otomatis via wa.me** (bukan API berbayar)
- Upgrade path: jika suatu saat mau full-otomatis, swap `generateWaLink()` ke Fonnte API tanpa ubah DB
- Prioritas fitur reminder: H-3 jatuh tempo (tagihan pertama) + H+1 (reminder belum bayar)

---

*Brief dibuat: Juni 2026 | Siap dilanjutkan di Claude Code*
