<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['admin_id'])) { header('Location: /pages/dashboard.php'); exit; }

// PRG pattern
$success = isset($_GET['sent']);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Email wajib diisi.';
    } else {
        $admin = dbFetchOne('SELECT id, email FROM admin WHERE email = ?', [$email]);

        // Selalu tampilkan pesan sama (jangan ungkap apakah email terdaftar)
        if ($admin) {
            // Hapus token lama yang belum terpakai
            dbExecute('DELETE FROM password_resets WHERE admin_id = ?', [$admin['id']]);

            // Generate token baru
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            dbExecute('INSERT INTO password_resets (admin_id, token, expires_at) VALUES (?, ?, ?)',
                      [$admin['id'], $token, $expiresAt]);

            $resetUrl = 'https://kost.purwandaru.com/auth/reset-password.php?token=' . $token;

            $body = mailTemplate('Reset Password', '
                <p>Halo Admin,</p>
                <p>Kami menerima permintaan reset password untuk akun admin <strong>Kost PW</strong>.</p>
                <p>Klik tombol di bawah untuk membuat password baru. Link berlaku selama <strong>1 jam</strong>.</p>
                <p style="text-align:center">
                    <a href="' . $resetUrl . '" class="btn">Reset Password Sekarang</a>
                </p>
                <div class="alert-box">
                    Jika Anda tidak meminta reset password, abaikan email ini.
                    Password Anda tidak akan berubah.
                </div>
                <p style="color:#9ca3af;font-size:.8rem;margin-top:16px">
                    Atau salin link ini ke browser:<br>
                    <a href="' . $resetUrl . '" style="color:#003087;word-break:break-all">' . $resetUrl . '</a>
                </p>
            ');

            sendMail($admin['email'], '[Kost PW] Reset Password Admin', $body);
        }

        header('Location: /auth/forgot-password.php?sent=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lupa Password — Kost PW</title>
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
    <h5>🔑 Lupa Password</h5>
    <p>Kost PW Admin Panel</p>
  </div>

  <div class="card-body p-4">
    <?php if ($success): ?>
      <div class="text-center py-2">
        <div class="text-success fs-1 mb-3">✉️</div>
        <h6 class="fw-bold">Email Terkirim!</h6>
        <p class="text-muted small">
          Jika email terdaftar, link reset password sudah dikirim ke
          <strong><?= h($_GET['email'] ?? 'email Anda') ?></strong>.
          Cek inbox (dan folder spam) Anda.
        </p>
        <p class="text-muted small">Link berlaku selama <strong>1 jam</strong>.</p>
        <a href="/auth/login.php" class="btn btn-primary mt-2">Kembali ke Login</a>
      </div>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= h($error) ?></div>
      <?php endif; ?>

      <p class="text-muted small mb-3">
        Masukkan email yang terdaftar sebagai admin. Kami akan kirim link reset password ke email tersebut.
      </p>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold">Email Admin</label>
          <input type="email" name="email" class="form-control"
                 value="<?= h($_POST['email'] ?? '') ?>"
                 placeholder="contoh@gmail.com" autofocus required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
          Kirim Link Reset Password
        </button>
      </form>

      <div class="text-center">
        <a href="/auth/login.php" class="text-muted small text-decoration-none">
          ← Kembali ke Login
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
