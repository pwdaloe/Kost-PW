<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

// ─── Konstanta brute force ────────────────────────────────────────────────────
const MAX_ATTEMPTS  = 5;
const WINDOW_MINUTES = 15;

// ─── Helper: ambil IP pengunjung ─────────────────────────────────────────────
function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

// ─── Helper: cek apakah IP sedang diblokir ───────────────────────────────────
function checkBruteForce(string $ip): array {
    // Hapus record lama (lebih dari WINDOW_MINUTES)
    dbExecute('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)',
              [WINDOW_MINUTES]);

    $row = dbFetchOne('
        SELECT COUNT(*) AS cnt, MIN(attempted_at) AS first_attempt
        FROM login_attempts
        WHERE ip_address = ?
          AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ', [$ip, WINDOW_MINUTES]);

    $count = (int)($row['cnt'] ?? 0);

    if ($count >= MAX_ATTEMPTS) {
        $unblockAt   = strtotime($row['first_attempt']) + (WINDOW_MINUTES * 60);
        $waitSeconds = max(0, $unblockAt - time());
        $waitMinutes = (int) ceil($waitSeconds / 60);
        return ['blocked' => true, 'wait' => $waitMinutes, 'count' => $count];
    }

    return ['blocked' => false, 'count' => $count];
}

// ─── Helper: catat percobaan gagal ───────────────────────────────────────────
function recordFailedAttempt(string $ip): int {
    dbExecute('INSERT INTO login_attempts (ip_address) VALUES (?)', [$ip]);

    $row = dbFetchOne('
        SELECT COUNT(*) AS cnt FROM login_attempts
        WHERE ip_address = ?
          AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ', [$ip, WINDOW_MINUTES]);

    return (int)($row['cnt'] ?? 0);
}

// ─── Helper: kirim notifikasi brute force ke admin ───────────────────────────
function notifyBruteForce(string $ip, int $count): void {
    $admin = dbFetchOne('SELECT email FROM admin LIMIT 1');
    if (!$admin || !$admin['email']) return;

    $body = mailTemplate('Peringatan Keamanan', '
        <p>Halo Admin,</p>
        <div class="alert-box">
            ⚠️ Terdeteksi percobaan login berulang dari IP <strong>' . htmlspecialchars($ip) . '</strong>
        </div>
        <p><strong>Detail:</strong></p>
        <ul>
            <li>IP Address: <code>' . htmlspecialchars($ip) . '</code></li>
            <li>Jumlah percobaan: <strong>' . $count . ' kali</strong> dalam ' . WINDOW_MINUTES . ' menit terakhir</li>
            <li>Waktu: <strong>' . date('d M Y H:i:s') . ' WIB</strong></li>
            <li>Status: IP <strong>diblokir sementara</strong> selama ' . WINDOW_MINUTES . ' menit</li>
        </ul>
        <p>Jika ini bukan Anda, pastikan password admin Anda aman.</p>
    ');

    sendMail($admin['email'], '[Kost PW] Peringatan: Percobaan Login Berulang dari ' . $ip, $body);
}

// ─── Proses POST ─────────────────────────────────────────────────────────────
$error = '';
$ip    = getClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = checkBruteForce($ip);

    if ($status['blocked']) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $status['wait'] . ' menit.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $admin = dbFetchOne('SELECT id, username, password FROM admin WHERE username = ?', [$username]);

            if ($admin && password_verify($password, $admin['password'])) {
                // Login sukses — hapus catatan percobaan IP ini
                dbExecute('DELETE FROM login_attempts WHERE ip_address = ?', [$ip]);
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: /pages/dashboard.php');
                exit;
            } else {
                $count = recordFailedAttempt($ip);
                $sisa  = MAX_ATTEMPTS - $count;

                if ($count >= MAX_ATTEMPTS) {
                    notifyBruteForce($ip, $count);
                    $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . WINDOW_MINUTES . ' menit.';
                } elseif ($sisa <= 2) {
                    $error = 'Username atau password salah. Tersisa ' . $sisa . ' percobaan sebelum diblokir.';
                } else {
                    $error = 'Username atau password salah.';
                }
            }
        }
    }
}

// Cek status blokir untuk GET (agar form tidak muncul kalau masih kena blokir)
$blockStatus = checkBruteForce($ip);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin — Kost PW</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #0a1628 0%, #003087 50%, #1a2332 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      border: none;
      border-radius: 14px;
      box-shadow: 0 8px 40px rgba(0,0,0,.35);
    }
    .login-header {
      background: #003087;
      color: #fff;
      border-radius: 14px 14px 0 0;
      padding: 2rem 2rem 1.75rem;
      text-align: center;
      border-bottom: 3px solid #1a56db;
    }
    .login-header img {
      width: 90px; height: 90px; object-fit: contain;
      margin-bottom: .75rem;
      filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
    }
    .login-header .logo-placeholder {
      width: 90px; height: 90px; border-radius: 50%;
      background: #1a56db; display: flex; align-items: center;
      justify-content: center; margin: 0 auto .75rem; font-size: 2rem;
    }
    .login-header h4 { margin: 0; font-weight: 800; letter-spacing: .5px; font-size: 1.2rem; }
    .login-header p  { margin: .3rem 0 0; opacity: .75; font-size: .85rem; }
    .blocked-info { background: #fee2e2; border-left: 4px solid #dc2626;
                    border-radius: 8px; padding: 1rem 1.25rem; }
  </style>
</head>
<body>

<div class="login-card card">
  <div class="login-header">
    <?php $logoPath = __DIR__ . '/../assets/logo.png'; ?>
    <?php if (file_exists($logoPath)): ?>
      <img src="/assets/logo.png" alt="Logo Kost PW">
    <?php else: ?>
      <div class="logo-placeholder">🏠</div>
    <?php endif; ?>
    <h4>Kost PW</h4>
    <p>Panel Admin &mdash; Balai Pustaka</p>
  </div>

  <div class="card-body p-4">

    <?php if ($blockStatus['blocked']): ?>
      <div class="blocked-info text-danger">
        <div class="fw-bold mb-1">🔒 Akses Sementara Diblokir</div>
        <div class="small">Terlalu banyak percobaan login gagal dari IP Anda. Coba lagi dalam
          <strong><?= $blockStatus['wait'] ?> menit</strong>.</div>
      </div>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <div class="mb-3">
          <label for="username" class="form-label fw-semibold">Username</label>
          <input type="text" id="username" name="username" class="form-control"
                 value="<?= h($_POST['username'] ?? '') ?>"
                 autocomplete="username" autofocus required>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label fw-semibold">Password</label>
          <input type="password" id="password" name="password" class="form-control"
                 autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
          Masuk
        </button>

        <div class="text-center">
          <a href="/auth/forgot-password.php" class="text-muted small text-decoration-none">
            Lupa password?
          </a>
        </div>
      </form>
    <?php endif; ?>

  </div>
</div>

</body>
</html>
