# Kost PW — Aplikasi Manajemen Kost

Aplikasi web manajemen kost berbasis **PHP + MySQL** untuk [Kost PW Balai Pustaka](https://purwandaru.com/video-kamar-di-kost-pw/), Jakarta Timur. Dibangun tanpa framework, berjalan di shared hosting / cPanel.

---

## Fitur Utama

- **Frontpage publik** — landing page dengan info kamar, video per kamar, dan form pendaftaran calon penghuni
- **Halaman detail kamar** — URL shareable per kamar (`/kamar.php?nama=Celestia`) lengkap dengan video YouTube Shorts, fasilitas, dan tombol Share WA
- **Pipeline kandidat** — Waitlist → Survey → Disetujui → aktif jadi penghuni
- **Generate tagihan bulanan** — sewa + biaya lainnya (listrik token mandiri, air sudah termasuk sewa)
- **Kirim tagihan via WhatsApp** — semi-otomatis via wa.me, gratis, tanpa API berbayar
- **Cron otomasi** — pengingat H-3 jatuh tempo & reminder H+1 belum bayar
- **Laporan arus kas** — pendapatan sewa vs biaya operasional per bulan
- **Edit template WA** — teks pesan tagihan & reminder bisa diedit via UI admin

---

## Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend  | PHP 7.4+ (standalone) |
| Database | MySQL 8.0 |
| Frontend | Bootstrap 5.3 + Bootstrap Icons (CDN) |
| Hosting  | cPanel / Shared VPS |
| WA Gateway | wa.me (gratis, semi-otomatis) |

---

## Kamar

| Nama | Ukuran |
|------|--------|
| Aurelia | 3 × 3.3 m |
| Bella | 3 × 3.3 m |
| Celestia | 3 × 3.8 m |
| Dravena | 3 × 3 m |
| Elara | 3 × 3.8 m |
| Florence | 3.7 × 3.8 m |

Fasilitas per kamar: AC Sharp 0.5PK, kasur + sprei, lemari + meja rias (Informa), kamar mandi dalam, water heater Ariston, closet TOTO, WiFi Biznet 300Mbps, listrik token mandiri.

---

## Struktur Folder

```
Kost-PW/
├── Dockerfile                    ← PHP 8.1 Apache + pdo_mysql
├── docker-compose.yml            ← Web + MySQL + phpMyAdmin
├── docker/
│   └── apache.conf               ← AllowOverride All
│
└── kostan/                       ← Web root aplikasi
    ├── index.php                 ← Frontpage publik (landing page + form daftar)
    ├── kamar.php                 ← Halaman detail kamar (shareable URL)
    ├── setup.php                 ← Setup password admin pertama kali (hapus setelah deploy)
    │
    ├── config/
    │   ├── db.php                ← Koneksi PDO + helper functions
    │   ├── db.example.php        ← Template konfigurasi DB
    │   ├── schema.sql            ← CREATE TABLE + seed data lengkap
    │   └── migration_001_youtube.sql  ← Tambah kolom youtube_url ke rooms
    │
    ├── auth/
    │   ├── login.php             ← Login admin
    │   └── logout.php
    │
    ├── pages/                    ← Halaman admin (require login)
    │   ├── dashboard.php         ← Ringkasan + peringatan
    │   ├── rooms.php             ← CRUD kamar + input YouTube URL
    │   ├── tenants.php           ← CRUD penghuni
    │   ├── candidates.php        ← Pipeline kandidat
    │   ├── bills.php             ← Tagihan + kirim WA + tandai lunas
    │   ├── expenses.php          ← Biaya operasional + arus kas
    │   └── settings.php         ← Template WA + pengaturan kost
    │
    ├── api/
    │   ├── generate_wa.php       ← Render + return wa.me URL (AJAX)
    │   ├── mark_paid.php         ← Tandai tagihan lunas (AJAX)
    │   ├── generate_bill.php     ← Generate tagihan 1 penghuni (AJAX)
    │   └── register_candidate.php ← Submit form calon penghuni
    │
    ├── cron/
    │   ├── check_billing.php     ← H-3: buat antrian WA tagihan
    │   └── send_reminders.php    ← H+1: buat antrian WA reminder
    │
    ├── includes/
    │   ├── header.php            ← HTML head + navbar + sidebar
    │   ├── sidebar.php           ← Navigasi admin (desktop + mobile offcanvas)
    │   └── footer.php            ← Bootstrap JS + app.js
    │
    └── assets/
        ├── css/style.css         ← Custom CSS
        ├── js/app.js             ← Format rupiah + auto-dismiss alert
        ├── logo.png              ← Logo Kost PW (tidak di-commit, letakkan manual)
        └── kamar/                ← Thumbnail foto per kamar (tidak di-commit)
            ├── aurelia.jpg
            ├── bella.jpg
            └── ...
```

---

## Instalasi Lokal (Docker)

### Prasyarat
[Docker Desktop](https://www.docker.com/products/docker-desktop/) sudah terinstall.

### Jalankan

```bash
git clone https://github.com/pwdaloe/Kost-PW.git
cd Kost-PW
docker-compose up -d
```

### Setup pertama kali

1. Buka **`http://localhost:8080/setup.php`** → set password admin
2. **Hapus `setup.php`** setelah password berhasil diset
3. Login di **`http://localhost:8080/auth/login.php`**

| URL | Keterangan |
|-----|-----------|
| `http://localhost:8080` | Frontpage publik |
| `http://localhost:8080/auth/login.php` | Login admin |
| `http://localhost:8081` | phpMyAdmin (user: `kostan_user` / pass: `kostan_pass`) |

> Schema SQL (`config/schema.sql`) otomatis dijalankan saat container pertama kali dibuat.

---

## Deploy ke cPanel

### 1. Upload

Upload isi folder `kostan/` ke:
```
/public_html/kostan/
```
atau root subdomain `kostan.domainmu.com`.

### 2. Database

Di cPanel → **MySQL Databases**:
1. Buat database baru
2. Buat user MySQL + assign ke database (All Privileges)
3. phpMyAdmin → jalankan `config/schema.sql`

### 3. Konfigurasi

```bash
cp kostan/config/db.example.php kostan/config/db.php
```

Edit `config/db.php`:
```php
define('DB_USER', 'cpanel_user_db');
define('DB_PASS', 'password_kuat');
define('DB_NAME', 'cpanel_nama_db');
```

### 4. Set Password Admin

```bash
php -r "echo password_hash('password_pilihan', PASSWORD_DEFAULT);"
```

Jalankan di phpMyAdmin:
```sql
UPDATE admin SET password = 'HASIL_HASH' WHERE username = 'admin';
```

### 5. Cron Jobs

Di cPanel → **Cron Jobs**:
```
0 8 * * *   php /home/USERNAME/public_html/kostan/cron/check_billing.php
0 8 * * *   php /home/USERNAME/public_html/kostan/cron/send_reminders.php
```

### 6. Aset

Upload manual (tidak di-commit ke git):
- `kostan/assets/logo.png` — logo Kost PW
- `kostan/assets/kamar/aurelia.jpg` dst — foto per kamar

### 7. Setup awal via UI

Login admin → **Pengaturan**:
- Isi nama pemilik, nomor rekening bank
- Isi harga sewa per kamar di halaman **Kamar**
- Isi URL video YouTube per kamar di halaman **Kamar**

---

## Layar & Halaman

### Frontpage Publik — `index.php`
- Hero: logo + nama kost + alamat
- Grid fasilitas kamar (8 item)
- Grid 6 kamar: thumbnail otomatis dari YouTube, play button → modal video 9:16
- Tombol "Lihat Detail" → halaman kamar shareable
- Form pendaftaran (PRG pattern — aman dari resubmit)
- Harga ditampilkan sebagai "Mulai dari Rp 2.000.000/bulan"

### Halaman Detail Kamar — `kamar.php?nama=Celestia`
- URL shareable per kamar
- Video full dengan play-on-click (load iframe hanya saat diklik)
- Thumbnail custom (`assets/kamar/celestia.jpg`) atau auto dari YouTube
- Open Graph tags untuk preview saat di-share ke WA / Instagram
- Tombol Copy Link + Share WA
- Grid kamar lainnya di bagian bawah

### Dashboard Admin — `pages/dashboard.php`
- 4 stat card: kamar terisi, tagihan belum bayar, antrian WA pending, kandidat waitlist
- Panel peringatan: jatuh tempo 3 hari ke depan + tagihan terlambat
- Tabel status semua kamar

### Tagihan — `pages/bills.php`
- Generate tagihan semua penghuni aktif sekaligus (idempotent)
- Kirim WA: AJAX → buka wa.me di tab baru → update status row tanpa reload
- Tandai Lunas: modal pilih metode + tanggal + nominal → histori tersimpan di `payments`
- Tab Antrian WA terpisah

### Pipeline Kandidat — `pages/candidates.php`
- Status: Waitlist → Survey → Disetujui → Ditolak
- Tombol "Promosikan" → otomatis jadi penghuni aktif + assign kamar

---

## Skema Database (Ringkas)

| Tabel | Keterangan |
|-------|-----------|
| `rooms` | 6 kamar + `youtube_url` + `no_pelanggan_listrik` |
| `tenants` | Penghuni aktif / keluar |
| `candidates` | Pipeline calon penghuni (dari form web + manual) |
| `bills` | Tagihan bulanan: `sewa + lainnya` (listrik & air tidak ditagih) |
| `payments` | Histori pembayaran per tagihan |
| `wa_queue` | Antrian pesan WA (tagihan + reminder) |
| `wa_templates` | Template teks WA yang bisa diedit admin |
| `expenses` | Biaya operasional kost |
| `settings` | Konfigurasi: nama kost, rekening, tgl jatuh tempo, dll |

---

## Alur WA Semi-Otomatis

```
Cron 08:00 H-3  →  deteksi tagihan mendekati jatuh tempo
                →  render pesan dari template
                →  insert wa_queue (status: pending)

Admin buka Tagihan → Tab Antrian WA
                →  klik "Kirim WA"
                →  wa.me terbuka di tab baru (admin klik Send)
                →  status otomatis update → "sent"

Cron 08:00 H+1  →  deteksi tagihan terlambat belum bayar
                →  insert wa_queue (tipe: reminder, status: pending)
```

Tidak pakai WA API berbayar. Upgrade path tersedia ke Fonnte API tanpa ubah skema DB.

---

## Keamanan

- PDO prepared statements di semua query
- `htmlspecialchars()` via `h()` di semua output
- `session_regenerate_id()` setelah login
- Password di-hash dengan `password_hash()`
- `.htaccess` block akses ke `/config/` dan `/cron/`
- Honeypot anti-spam di form publik
- PRG pattern pada form pendaftaran (mencegah resubmit)
- `setup.php` hanya bisa diakses dari localhost

---

*Kost PW · Balai Pustaka-Rawamangun, Jakarta Timur*
