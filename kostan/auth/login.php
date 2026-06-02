<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sudah login → langsung ke dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $admin = dbFetchOne('SELECT id, username, password FROM admin WHERE username = ?', [$username]);

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: /pages/dashboard.php');
            exit;
        } else {
            // Pesan generik — jangan ungkap apakah username atau password yang salah
            $error = 'Username atau password salah.';
        }
    }
}
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
      width: 90px;
      height: 90px;
      object-fit: contain;
      margin-bottom: .75rem;
      filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
    }
    .login-header .logo-placeholder {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: #1a56db;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto .75rem;
      font-size: 2rem;
    }
    .login-header h4 {
      margin: 0;
      font-weight: 800;
      letter-spacing: .5px;
      font-size: 1.2rem;
    }
    .login-header p {
      margin: .3rem 0 0;
      opacity: .75;
      font-size: .85rem;
      letter-spacing: .3px;
    }
  </style>
</head>
<body>

<div class="login-card card">
  <div class="login-header">
    <?php
    $logoPath = __DIR__ . '/../assets/logo.png';
    if (file_exists($logoPath)): ?>
      <img src="/assets/logo.png" alt="Logo Kost PW">
    <?php else: ?>
      <div class="logo-placeholder">🏠</div>
    <?php endif; ?>
    <h4>Kost PW</h4>
    <p>Panel Admin &mdash; Balai Pustaka</p>
  </div>

  <div class="card-body p-4">

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="mb-3">
        <label for="username" class="form-label fw-semibold">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          class="form-control"
          value="<?= h($_POST['username'] ?? '') ?>"
          autocomplete="username"
          autofocus
          required
        >
      </div>

      <div class="mb-4">
        <label for="password" class="form-label fw-semibold">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          autocomplete="current-password"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        Masuk
      </button>
    </form>

  </div>
</div>

</body>
</html>
