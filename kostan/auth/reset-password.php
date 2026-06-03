<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['admin_id'])) { header('Location: /pages/dashboard.php'); exit; }

$token  = trim($_GET['token'] ?? '');
$error  = '';
$done   = false;

// Validasi token
$reset = null;
if ($token !== '') {
    $reset = dbFetchOne('
        SELECT pr.id, pr.admin_id, pr.expires_at, pr.used
        FROM password_resets pr
        WHERE pr.token = ?
    ', [$token]);
}

$tokenValid = $reset
    && $reset['used'] == 0
    && strtotime($reset['expires_at']) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        dbExecute('UPDATE admin SET password = ? WHERE id = ?',
                  [password_hash($pass1, PASSWORD_DEFAULT), $reset['admin_id']]);

        // Tandai token sudah terpakai
        dbExecute('UPDATE password_resets SET used = 1 WHERE id = ?', [$reset['id']]);

        // Hapus semua sesi aktif
        session_destroy();

        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — Kost PW</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #0a1628 0%, #003087 50%, #1a2332 100%);
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
    }
    .card {
      width: 100%; max-width: 400px; border: none; border-radius: 14px;
      box-shadow: 0 8px 40px rgba(0,0,0,.35);
    }
    .card-header {
      background: #003087; color: #fff; border-radius: 14px 14px 0 0 !important;
      padding: 1.75rem 2rem; text-align: center; border-bottom: 3px solid #1a56db;
    }
    .card-header h5 { margin: 0; font-weight: 800; }
    .card-header p  { margin: .3rem 0 0; opacity: .75; font-size: .85rem; }
  </style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <h5>🔐 Reset Password</h5>
    <p>Kost PW Admin Panel</p>
  </div>

  <div class="card-body p-4">

    <?php if ($done): ?>
      <div class="text-center py-2">
        <div class="text-success fs-1 mb-3">✅</div>
        <h6 class="fw-bold">Password Berhasil Diubah!</h6>
        <p class="text-muted small">Silakan login dengan password baru Anda.</p>
        <a href="/auth/login.php" class="btn btn-primary mt-2">Login Sekarang</a>
      </div>

    <?php elseif (!$tokenValid): ?>
      <div class="text-center py-2">
        <div class="fs-1 mb-3">⚠️</div>
        <h6 class="fw-bold text-danger">Link Tidak Valid</h6>
        <p class="text-muted small">
          Link reset password sudah kadaluarsa (1 jam) atau sudah pernah digunakan.
        </p>
        <a href="/auth/forgot-password.php" class="btn btn-primary mt-2">
          Minta Link Baru
        </a>
      </div>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= h($error) ?></div>
      <?php endif; ?>

      <p class="text-muted small mb-3">Masukkan password baru untuk akun admin Kost PW.</p>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold">Password Baru</label>
          <input type="password" name="password" class="form-control"
                 minlength="6" placeholder="Min. 6 karakter" autofocus required>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
          <input type="password" name="password2" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          Simpan Password Baru
        </button>
      </form>

      <div class="text-center mt-3">
        <span class="text-muted small">
          <i class="bi bi-clock me-1"></i>
          Link berlaku hingga <?= date('H:i', strtotime($reset['expires_at'])) ?>
        </span>
      </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>
