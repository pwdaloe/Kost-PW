<?php
$_current = basename($_SERVER['PHP_SELF']);

$_navItems = [
    ['file' => 'dashboard.php',  'icon' => 'bi-speedometer2',    'label' => 'Dashboard'],
    ['file' => 'tenants.php',    'icon' => 'bi-people-fill',     'label' => 'Penghuni'],
    ['file' => 'candidates.php', 'icon' => 'bi-person-plus-fill','label' => 'Kandidat'],
    ['file' => 'rooms.php',      'icon' => 'bi-door-open-fill',  'label' => 'Kamar'],
    ['file' => 'bills.php',      'icon' => 'bi-receipt',         'label' => 'Tagihan'],
    ['file' => 'expenses.php',   'icon' => 'bi-wallet2',         'label' => 'Biaya Ops'],
    ['file' => 'settings.php',   'icon' => 'bi-gear-fill',       'label' => 'Pengaturan'],
];
?>

<!-- ─── Desktop Sidebar ────────────────────────────────────────────────────── -->
<nav class="sidebar d-none d-md-flex flex-column flex-shrink-0">
  <ul class="nav flex-column px-2 py-3 gap-1">
    <?php foreach ($_navItems as $_item): ?>
    <li class="nav-item">
      <a href="/pages/<?= $_item['file'] ?>"
         class="nav-link <?= $_current === $_item['file'] ? 'active' : '' ?>">
        <i class="bi <?= $_item['icon'] ?> me-2"></i><?= $_item['label'] ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</nav>

<!-- ─── Mobile Offcanvas Sidebar ──────────────────────────────────────────── -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="mobileSidebar" style="width:220px">
  <div class="offcanvas-header border-bottom border-secondary py-3">
    <span class="fw-bold"><?= h(getSetting('nama_kost') ?: 'Kost PW') ?></span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-2">
    <ul class="nav flex-column gap-1">
      <?php foreach ($_navItems as $_item): ?>
      <li class="nav-item">
        <a href="/pages/<?= $_item['file'] ?>"
           class="nav-link text-white <?= $_current === $_item['file'] ? 'active' : '' ?>">
          <i class="bi <?= $_item['icon'] ?> me-2"></i><?= $_item['label'] ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
