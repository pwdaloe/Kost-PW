# Kost PW — Aplikasi Manajemen Kost

Aplikasi web manajemen kost berbasis PHP + MySQL untuk **Kost PW Balai Pustaka**, Jakarta Timur. Dibangun tanpa framework, berjalan di shared hosting / cPanel.

---

## Daftar Isi

- [Overview](#overview)
- [Fitur](#fitur)
- [Tech Stack](#tech-stack)
- [Struktur Folder](#struktur-folder)
- [Konfigurasi & Instalasi](#konfigurasi--instalasi)
- [Layar & Halaman](#layar--halaman)
- [API Endpoints](#api-endpoints)
- [Cron Jobs](#cron-jobs)
- [Skema Database](#skema-database)
- [Alur Kerja WA Semi-Otomatis](#alur-kerja-wa-semi-otomatis)
- [Keamanan](#keamanan)

---

## Overview

Kost PW mengelola **6 kamar** bernama Aurelia, Bella, Celestia, Dravena, Elara, dan Florence. Aplikasi ini menangani seluruh siklus operasional kost:

- Pendaftaran calon penghuni via **frontpage publik**
- Manajemen penghuni aktif dan pipeline kandidat
- Generate & kirim tagihan bulanan via **WhatsApp semi-otomatis** (wa.me — gratis, tanpa API berbayar)
- Pencatatan biaya operasional + laporan arus kas bulanan
- Notifikasi otomatis H-3 jatuh tempo dan H+1 reminder via cron job

> **Catatan listrik & air:** Listrik menggunakan token mandiri per kamar (masing-masing penghuni isi sendiri). Air sudah termasuk dalam harga sewa. Tagihan bulanan hanya terdiri dari **sewa + biaya lainnya** (insidental).

---

## Fitur

### Sprint 1 — MVP
| Fitur | Status |
|-------|--------|
| Frontpage publik + form pendaftaran calon penghuni | ✅ |
| Login admin (PHP session) | ✅ |
| Dashboard ringkasan (kamar, tagihan, antrian WA, kandidat) | ✅ |
| CRUD kamar (termasuk No. ID Pelanggan PLN) | ✅ |
| CRUD penghuni aktif + sinkron status kamar | ✅ |
| Pipeline kandidat (Waitlist → Survey → Disetujui → Ditolak) | ✅ |
| Promosikan kandidat → penghuni aktif | ✅ |
| Generate tagihan bulanan (semua penghuni aktif sekaligus) | ✅ |
| Tombol **Kirim WA** → buka wa.me di tab baru | ✅ |
| Tombol **Tandai Lunas** + histori pembayaran | ✅ |
| Edit tagihan (tambah biaya lainnya + keterangan) | ✅ |
| Pencatatan biaya operasional + laporan surplus/defisit | ✅ |
| Edit template WA tagihan & reminder via UI | ✅ |
| Pengaturan: nama kost, bank, rekening, tgl jatuh tempo | ✅ |
| Ganti password admin | ✅ |

### Sprint 2 — Otomasi
| Fitur | Status |
|-------|--------|
| Cron H-3: deteksi tagihan mendekati jatuh tempo → antrian WA | ✅ |
| Cron H+1: reminder tagihan belum bayar | ✅ |
| Dashboard antrian WA (Pending / Terkirim) | ✅ |

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend | PHP 7.4+ (standalone, tanpa framework) |
| Database | MySQL 5.7+ / MariaDB |
| Frontend | Bootstrap 5.3 (CDN) + Bootstrap Icons 1.11 (CDN) |
| Hosting | Shared VPS + cPanel |
| WA Gateway | wa.me link (gratis, semi-otomatis) |

**Tidak ada build step** — tidak perlu Node.js, Composer, atau npm. Upload langsung jalan.

---

## Struktur Folder

```
kostan/
├── index.php                    ← Frontpage PUBLIK (form pendaftaran calon penghuni)
│
├── config/
│   ├── db.php                   ← Koneksi PDO + helper functions
│   ├── schema.sql               ← SQL lengkap: CREATE TABLE + seed data
│   └── .htaccess                ← Block akses langsung via browser
│
├── auth/
│   ├── login.php                ← Login admin
│   └── logout.php               ← Destroy session + redirect
│
├── pages/                       ← Halaman admin (semua require login)
│   ├── dashboard.php            ← Ringkasan + peringatan jatuh tempo
│   ├── rooms.php                ← CRUD kamar
│   ├── tenants.php              ← CRUD penghuni aktif
│   ├── candidates.php           ← Pipeline kandidat
│   ├── bills.php                ← Tagihan + kirim WA + tandai lunas
│   ├── expenses.php             ← Biaya operasional + laporan
│   └── settings.php            ← Template WA + pengaturan kost
│
├── api/
│   ├── generate_wa.php          ← Render pesan WA + return wa.me URL (AJAX)
│   ├── mark_paid.php            ← Tandai tagihan lunas (AJAX)
│   ├── generate_bill.php        ← Generate tagihan 1 penghuni (AJAX)
│   └── register_candidate.php   ← Submit form calon penghuni (AJAX alternatif)
│
├── cron/
│   ├── check_billing.php        ← Harian 08:00: deteksi H-3, buat wa_queue
│   ├── send_reminders.php       ← Harian 08:00: reminder H+1 belum bayar
│   └── .htaccess                ← Block akses langsung via browser
│
├── includes/
│   ├── header.php               ← HTML head + top navbar + buka sidebar
│   ├── sidebar.php              ← Navigasi admin (desktop + mobile offcanvas)
│   └── footer.php               ← Tutup layout + load Bootstrap JS
│
└── assets/
    ├── css/style.css            ← Custom CSS (sidebar, badge status, stat cards)
    ├── js/app.js                ← Format rupiah + auto-dismiss alert
    └── logo.png                 ← Logo Kost PW (letakkan manual)
```

---

## Konfigurasi & Instalasi

### 1. Upload File

Upload seluruh isi folder `kostan/` ke:
```
/public_html/kostan/
```
atau ke root subdomain `kostan.domainmu.com` → `/public_html/`.

### 2. Buat Database

Di cPanel → **MySQL Databases**:
1. Buat database baru, misal: `user_kostanpw`
2. Buat user MySQL baru dengan password kuat
3. Assign user ke database dengan **All Privileges**

### 3. Jalankan Schema SQL

Di cPanel → **phpMyAdmin** → pilih database → tab **SQL** → paste isi `config/schema.sql` → **Go**.

Schema otomatis membuat:
- 9 tabel (admin, rooms, tenants, candidates, bills, payments, wa_queue, wa_templates, settings)
- Seed 6 kamar (Aurelia s/d Florence)
- Seed 2 template WA (tagihan + reminder)
- Seed pengaturan dasar Kost PW

### 4. Konfigurasi Database

Edit `config/db.php`, isi 4 baris ini:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'user_kostanpw');   // nama database di cPanel
define('DB_USER', 'user_dbanda');     // username MySQL
define('DB_PASS', 'password_anda');   // password MySQL
```

### 5. Set Password Admin

Jalankan perintah ini di terminal server (via SSH atau Terminal cPanel):

```bash
php -r "echo password_hash('password_pilihan_anda', PASSWORD_DEFAULT);"
```

Salin hasilnya, lalu jalankan di phpMyAdmin:

```sql
UPDATE admin SET password = 'HASIL_HASH_DI_ATAS' WHERE username = 'admin';
```

### 6. Setup Cron Job

Di cPanel → **Cron Jobs**, tambahkan 2 entri:

```
0 8 * * *   php /home/USERNAME/public_html/kostan/cron/check_billing.php
0 8 * * *   php /home/USERNAME/public_html/kostan/cron/send_reminders.php
```

Ganti `USERNAME` dengan username cPanel Anda.

### 7. Upload Logo

Letakkan file logo di:
```
kostan/assets/logo.png
```
Jika belum ada, aplikasi otomatis menampilkan emoji 🏠 sebagai fallback.

### 8. Konfigurasi Awal via UI

Setelah login admin, buka **Pengaturan** dan lengkapi:
- Nama pemilik
- Nomor rekening bank
- Tanggal jatuh tempo default (contoh: `5` = setiap tgl 5)

Lalu buka **Kamar** dan isi:
- Harga sewa per kamar
- No. ID Pelanggan PLN per kamar

---

## Layar & Halaman

### Frontpage Publik — `index.php`

Halaman yang diakses calon penghuni tanpa login.

**Konten:**
- Hero section: logo, nama kost, alamat
- Grid 8 fasilitas kamar (AC, kasur, kamar mandi dalam, WiFi, dll)
- Grid 6 kamar dengan status Tersedia / Sedang Terisi
- **Form pendaftaran**: nama, no WA, kamar diminati, rencana masuk, pesan/pertanyaan
- Footer dengan link tersembunyi ke admin panel

**Keamanan form:** Honeypot field (`name="website"`) untuk filter bot. Submit yang mengisi field ini diterima diam-diam tanpa disimpan.

---

### Login Admin — `auth/login.php`

- Background gradient navy sesuai warna logo
- Tampilkan logo jika `assets/logo.png` ada
- Verifikasi via `password_verify()` + `session_regenerate_id()`
- Pesan error generik (tidak membocorkan apakah username atau password yang salah)

---

### Dashboard — `pages/dashboard.php`

**4 Stat Cards:**
| Card | Warna |
|------|-------|
| Kamar Terisi (X/6) | Navy |
| Tagihan Belum Bayar | Merah jika > 0, hijau jika 0 |
| Antrian WA Pending | Oranye jika > 0, biru jika 0 |
| Kandidat Waitlist | Teal |

**Shortcut Buttons:** Generate Tagihan, Antrian WA (dengan badge), Data Penghuni, Kandidat.

**Panel Peringatan:**
- Jatuh tempo dalam 3 hari → dengan tombol lihat detail tagihan
- Tagihan terlambat → hitung hari keterlambatan + tombol WA langsung

**Tabel Status Kamar:** Semua 6 kamar, nama penghuni aktif, harga sewa, badge status.

---

### Manajemen Kamar — `pages/rooms.php`

Tampilan **grid kartu** (bukan tabel), satu card per kamar.

**Tiap card menampilkan:**
- Nama kamar + badge status (Terisi / Tersedia)
- Harga sewa per bulan
- Ukuran kamar + lantai
- Nama penghuni aktif (link ke data penghuni)
- No. ID Pelanggan PLN untuk token listrik

**Aksi:** Tambah kamar, edit via modal Bootstrap, hapus (diblock jika ada penghuni aktif).

---

### Manajemen Penghuni — `pages/tenants.php`

**Filter tab:** Aktif / Keluar / Semua

**Kolom tabel:** Nama + KTP, Kamar, No WA (klik → buka WA), Tanggal Masuk, Harga Sewa, Status.

**Fitur penting:**
- Saat penghuni ditambah/pindah kamar/keluar → status kamar otomatis sinkron (tersedia/terisi)
- Tombol shortcut ke halaman tagihan penghuni tersebut
- Modal add/edit dengan semua field termasuk tanggal masuk & keluar

---

### Pipeline Kandidat — `pages/candidates.php`

**Pipeline status:** Waitlist → Survey → Disetujui → Ditolak

**Filter:** Per status dengan badge jumlah.

**Tombol Promosikan** (muncul saat status = Disetujui):
- Otomatis buat penghuni baru di tabel `tenants`
- Assign ke kamar yang diminati (jika tersedia)
- Update status kamar menjadi terisi
- Update status kandidat menjadi `approved`

**Sumber data:** `form_web` (dari frontpage) atau `manual` (input admin).

---

### Tagihan — `pages/bills.php`

**Toolbar:** Filter bulan (month picker) + filter status + tombol Generate Tagihan.

**4 Stat Cards:** Total tagihan, belum bayar, lunas, total nominal masuk.

**Tab Tagihan:**

| Kolom | Keterangan |
|-------|-----------|
| Penghuni | Nama |
| Kamar | Nama kamar |
| Sewa | Harga sewa bulanan |
| Lainnya | Biaya tambahan (jika ada) |
| Total | Sewa + Lainnya |
| Jatuh Tempo | Tanggal + badge hari keterlambatan |
| Bayar | Status + tanggal bayar |
| WA | Status pengiriman WA |
| Aksi | Kirim WA / Tandai Lunas / Edit / Hapus |

**Tombol Kirim WA:**
1. AJAX ke `api/generate_wa.php`
2. Render pesan dari template + variabel dinamis
3. Buka `wa.me/...` di tab baru
4. Update badge baris menjadi "Terkirim" tanpa reload halaman

**Tombol Tandai Lunas:**
- Modal: tanggal bayar, nominal, metode (Transfer/QRIS/Tunai), catatan
- AJAX ke `api/mark_paid.php` → reload halaman

**Tab Antrian WA:** Semua entri `wa_queue` dengan status Pending/Terkirim + tombol Kirim WA.

**Generate Tagihan:** Sekali klik buat tagihan untuk semua penghuni aktif bulan yang dipilih. Skip yang sudah ada (idempotent).

---

### Biaya Operasional — `pages/expenses.php`

**3 Stat Cards:** Total Pengeluaran, Pendapatan Sewa Masuk, **Surplus/Defisit** bulan ini.

**Kategori:** Listrik (area umum), Air, Perawatan, Kebersihan, Lainnya.

**Panel kanan:** Breakdown per kategori dengan progress bar persentase.

---

### Pengaturan — `pages/settings.php`

- **Pengaturan umum:** Nama kost, alamat, pemilik, bank, no rekening, tgl jatuh tempo
- **Ganti password admin** dengan verifikasi password lama
- **Edit template WA** tagihan & reminder langsung di textarea
- Referensi variabel template tersedia (`{{nama}}`, `{{kamar}}`, `{{total}}`, dst)

---

## API Endpoints

Semua endpoint memerlukan login session kecuali `register_candidate.php`.

### `GET /api/generate_wa.php?bill_id=X`

Render pesan WA dari template + return URL wa.me.

**Response:**
```json
{ "url": "https://wa.me/628xxx?text=..." }
```

**Proses:**
1. Fetch bill + tenant + room + settings
2. Render template (isi `{{variabel}}`)
3. `{{baris_lainnya}}` muncul otomatis hanya jika `lainnya > 0`
4. `{{no_pelanggan}}` diisi dari `rooms.no_pelanggan_listrik`
5. Upsert `wa_queue` → status `sent`
6. Return URL wa.me

---

### `POST /api/mark_paid.php`

Tandai tagihan sebagai lunas.

**Body:** `bill_id`, `tgl_bayar`, `nominal`, `metode` (transfer/tunai/qris), `catatan`

**Response:**
```json
{ "success": true }
```

**Proses:** Update `bills.status_bayar = paid` + insert record ke tabel `payments`.

---

### `POST /api/generate_bill.php`

Generate tagihan manual untuk 1 penghuni.

**Body:** `tenant_id`, `bulan` (YYYY-MM-DD)

**Response:**
```json
{ "success": true, "bill_id": 42, "msg": "Tagihan Budi berhasil dibuat." }
```

---

### `POST /api/register_candidate.php`

Submit pendaftaran calon penghuni (versi AJAX dari form di `index.php`).

**Body:** `nama`, `no_wa`, `kamar_diminati`, `tgl_masuk_rencana`, `catatan`, `website` (honeypot)

**Response:**
```json
{ "success": true }
```

---

## Cron Jobs

### `cron/check_billing.php` — H-3 Jatuh Tempo

**Jadwal:** Setiap hari pukul 08:00

```
0 8 * * *   php /home/USERNAME/public_html/kostan/cron/check_billing.php
```

**Logika:**
1. Cari tagihan dengan `tgl_jatuh_tempo = CURDATE() + 3 hari`
2. Filter: `status_bayar = unpaid` dan belum ada di `wa_queue` (tipe: tagihan)
3. Render pesan dari template `tagihan`
4. Insert ke `wa_queue` dengan status `pending`

---

### `cron/send_reminders.php` — H+1 Reminder

**Jadwal:** Setiap hari pukul 08:00

```
0 8 * * *   php /home/USERNAME/public_html/kostan/cron/send_reminders.php
```

**Logika:**
1. Cari tagihan dengan `tgl_jatuh_tempo = CURDATE() - 1 hari`
2. Filter: `status_bayar = unpaid` dan belum ada di `wa_queue` (tipe: reminder)
3. Render pesan dari template `reminder`
4. Insert ke `wa_queue` dengan status `pending`

**Admin kemudian** buka halaman Tagihan → Tab Antrian WA → klik Kirim WA per penghuni.

---

## Skema Database

### `rooms` — Data Kamar
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT PK | |
| nomor_kamar | VARCHAR(20) | Nama kamar (Aurelia, Bella, dst) |
| ukuran | VARCHAR(20) | Contoh: 3x3.3m |
| lantai | TINYINT | Default 1 |
| harga_sewa | INT | Per bulan |
| no_pelanggan_listrik | VARCHAR(20) | ID Pelanggan PLN untuk token |
| fasilitas | TEXT | Deskripsi fasilitas |
| status | ENUM | tersedia / terisi |

### `tenants` — Penghuni
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT PK | |
| nama | VARCHAR(100) | |
| no_wa | VARCHAR(20) | Format bebas, dinormalisasi saat generate WA |
| no_ktp | VARCHAR(20) | NIK KTP (opsional) |
| kamar_id | INT FK | → rooms.id |
| tgl_masuk | DATE | |
| tgl_keluar | DATE | |
| status | ENUM | aktif / keluar |

### `candidates` — Calon Penghuni
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT PK | |
| nama | VARCHAR(100) | |
| no_wa | VARCHAR(20) | |
| kamar_diminati | VARCHAR(20) | |
| tgl_masuk_rencana | DATE | |
| status | ENUM | waitlist / survey / approved / rejected |
| sumber | ENUM | form_web / manual |

### `bills` — Tagihan Bulanan
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT PK | |
| tenant_id | INT FK | → tenants.id |
| bulan | DATE | Format: YYYY-MM-01 |
| sewa | INT | Harga sewa bulan ini |
| lainnya | INT | Biaya tambahan insidental |
| keterangan_lainnya | VARCHAR(255) | |
| total | INT GENERATED | `sewa + lainnya` (auto) |
| tgl_jatuh_tempo | DATE | |
| status_bayar | ENUM | unpaid / paid |
| tgl_bayar | DATE | Diisi saat tandai lunas |

> Unique constraint: `(tenant_id, bulan)` — tidak bisa double tagihan per bulan.

### `payments` — Histori Pembayaran
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT PK | |
| bill_id | INT FK | → bills.id |
| tgl_bayar | DATE | |
| nominal | INT | |
| metode | ENUM | transfer / tunai / qris |
| confirmed_by | VARCHAR(50) | Username admin yang konfirmasi |

### `wa_queue` — Antrian WA
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT PK | |
| bill_id | INT FK | → bills.id |
| tipe | ENUM | tagihan / reminder |
| pesan_text | TEXT | Teks WA yang sudah di-render |
| status | ENUM | pending / sent |
| sent_at | TIMESTAMP | Waktu kirim |

### `wa_templates` — Template Pesan WA
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| tipe | VARCHAR(50) UNIQUE | tagihan / reminder |
| template_text | TEXT | Teks dengan variabel `{{...}}` |

**Variabel template:** `{{nama}}`, `{{kamar}}`, `{{bulan}}`, `{{sewa}}`, `{{baris_lainnya}}`, `{{total}}`, `{{jatuh_tempo}}`, `{{nama_bank}}`, `{{no_rekening}}`, `{{nama_pemilik}}`, `{{no_pelanggan}}`

### `expenses` — Biaya Operasional
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| tanggal | DATE | |
| kategori | ENUM | listrik / air / perawatan / kebersihan / lainnya |
| keterangan | VARCHAR(255) | |
| nominal | INT | |

### `settings` — Konfigurasi Aplikasi
| key_name | Keterangan |
|----------|-----------|
| nama_kost | Nama kost |
| nama_pemilik | Nama pemilik untuk tanda tangan WA |
| nama_bank | Nama bank tujuan transfer |
| no_rekening | Nomor rekening |
| tgl_jatuh_tempo | Tanggal jatuh tempo tiap bulan (default: 5) |
| alamat | Alamat kost |
| fasilitas_umum | Deskripsi fasilitas bersama |

---

## Alur Kerja WA Semi-Otomatis

```
Cron H-3 jalan tiap pagi 08:00
  └─> Deteksi tagihan jatuh tempo 3 hari ke depan
  └─> Render pesan dari template
  └─> Insert ke wa_queue (status: pending)

Admin buka Tagihan → Tab Antrian WA
  └─> Lihat list dengan badge status
  └─> Klik tombol "Kirim WA"
  └─> Browser buka: https://wa.me/628xxx?text=...
  └─> Admin klik Send di WA Web/Desktop
  └─> Sistem update status → "sent" (via AJAX sebelum tab WA terbuka)

Cron H+1 (belum bayar)
  └─> Deteksi tagihan lewat jatuh tempo & belum bayar
  └─> Render pesan reminder
  └─> Insert ke wa_queue (tipe: reminder, status: pending)
```

**Kenapa semi-otomatis?**
- Tidak pakai WA API gateway berbayar → Rp 0/bulan
- Tidak risiko banned (wa.me adalah link resmi Meta)
- 6 kamar = maksimal 6 klik/bulan — sangat manageable

**Upgrade path:** Jika ingin full-otomatis kelak, swap fungsi di `api/generate_wa.php` ke Fonnte API — tanpa ubah skema database.

---

## Keamanan

- Semua query menggunakan **PDO prepared statements** — aman dari SQL injection
- Semua output di-escape dengan `htmlspecialchars()` via helper `h()`
- Session check `requireLogin()` di setiap halaman admin
- `session_regenerate_id(true)` setelah login berhasil — cegah session fixation
- Password admin disimpan dengan `password_hash()` + verifikasi `password_verify()`
- Pesan error login generik — tidak membocorkan apakah username atau password yang salah
- `.htaccess` di `/config/` dan `/cron/` memblokir akses langsung via browser
- Honeypot field di form pendaftaran publik — filter bot sederhana
- Validasi format No. WA dengan regex sebelum disimpan

---

*Dibuat: Juni 2026 | Stack: PHP + MySQL + Bootstrap 5 | Hosting: cPanel*
