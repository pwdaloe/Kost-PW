<?php

// Salin file ini ke db.php lalu isi dengan kredensial database Anda:
//   cp config/db.example.php config/db.php

// Docker: baca dari environment variable (lihat docker-compose.yml)
// cPanel: isi nilai fallback di bawah
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME') ?: 'kostan_pw');
define('DB_USER',    getenv('DB_USER') ?: 'isi_username_db');
define('DB_PASS',    getenv('DB_PASS') ?: 'isi_password_db');
define('DB_CHARSET', 'utf8mb4');
