<?php
// Pastikan db.php sudah di-require sebelum include header
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/db.php';
}
$_kostName = getSetting('nama_kost') ?: 'Kost PW';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'Admin') ?> — <?= h($_kostName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ─── Top Navbar ─────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-dark bg-dark px-3 sticky-top" style="height:56px">
  <!-- Hamburger (mobile only) -->
  <button class="navbar-toggler d-md-none border-0 p-1 me-2"
          type="button"
          data-bs-toggle="offcanvas"
          data-bs-target="#mobileSidebar"
          aria-controls="mobileSidebar">
    <span class="navbar-toggler-icon"></span>
  </button>

  <span class="navbar-brand mb-0 fw-bold"><?= h($_kostName) ?></span>

  <div class="ms-auto d-flex align-items-center gap-2">
    <span class="text-white-50 small d-none d-sm-inline">
      <i class="bi bi-person-circle me-1"></i><?= h($_SESSION['admin_username'] ?? 'admin') ?>
    </span>
    <a href="/auth/logout.php" class="btn btn-sm btn-outline-light">
      <i class="bi bi-box-arrow-right me-1"></i>Keluar
    </a>
  </div>
</nav>

<!-- ─── Layout Wrapper ─────────────────────────────────────────────────────── -->
<div class="d-flex">

  <?php require __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content p-4 flex-grow-1">
    <div class="page-header d-flex align-items-center justify-content-between mb-4">
      <h5 class="mb-0 fw-bold"><?= h($pageTitle ?? '') ?></h5>
    </div>
