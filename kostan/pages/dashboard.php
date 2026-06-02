<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Dashboard';
$bulanIni  = date('Y-m-01');
$bulanLabel = formatBulan($bulanIni);

// ─── Stats ────────────────────────────────────────────────────────────────────

$rooms = dbFetchOne('
    SELECT
        COUNT(*) AS total,
        SUM(status = "terisi")   AS terisi,
        SUM(status = "tersedia") AS tersedia
    FROM rooms
');

$tagihanUnpaid = dbFetchOne('
    SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS nominal
    FROM bills
    WHERE bulan = ? AND status_bayar = "unpaid"
', [$bulanIni]);

$waQueuePending = dbFetchOne('
    SELECT COUNT(*) AS total FROM wa_queue WHERE status = "pending"
');

$kandidatBaru = dbFetchOne('
    SELECT COUNT(*) AS total FROM candidates WHERE status = "waitlist"
');

// ─── Tagihan jatuh tempo dalam 3 hari ke depan ───────────────────────────────

$jatuhTempoDeket = dbFetchAll('
    SELECT b.id, b.tgl_jatuh_tempo, b.total,
           t.nama, r.nomor_kamar
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    WHERE b.status_bayar = "unpaid"
      AND b.tgl_jatuh_tempo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY b.tgl_jatuh_tempo ASC
');

// ─── Tagihan sudah lewat jatuh tempo (belum bayar) ───────────────────────────

$tagihanTelat = dbFetchAll('
    SELECT b.id, b.tgl_jatuh_tempo, b.total,
           t.nama, t.no_wa, r.nomor_kamar
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    WHERE b.status_bayar = "unpaid"
      AND b.tgl_jatuh_tempo < CURDATE()
    ORDER BY b.tgl_jatuh_tempo ASC
');

// ─── Kandidat terbaru ─────────────────────────────────────────────────────────

$kandidatTerbaru = dbFetchAll('
    SELECT id, nama, no_wa, kamar_diminati, tgl_masuk_rencana, created_at
    FROM candidates
    WHERE status = "waitlist"
    ORDER BY created_at DESC
    LIMIT 5
');

require __DIR__ . '/../includes/header.php';
?>

<!-- ─── Stat Cards ────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

  <div class="col-6 col-md-3">
    <div class="stat-card h-100" style="background:#003087">
      <div>
        <div class="stat-value"><?= (int)$rooms['terisi'] ?> / <?= (int)$rooms['total'] ?></div>
        <div class="stat-label">Kamar Terisi</div>
      </div>
      <i class="bi bi-door-open-fill stat-icon ms-auto"></i>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="stat-card h-100" style="background:<?= (int)$tagihanUnpaid['total'] > 0 ? '#b91c1c' : '#065f46' ?>">
      <div>
        <div class="stat-value"><?= (int)$tagihanUnpaid['total'] ?></div>
        <div class="stat-label">Tagihan Belum Bayar</div>
      </div>
      <i class="bi bi-receipt stat-icon ms-auto"></i>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="stat-card h-100" style="background:<?= (int)$waQueuePending['total'] > 0 ? '#b45309' : '#1a56db' ?>">
      <div>
        <div class="stat-value"><?= (int)$waQueuePending['total'] ?></div>
        <div class="stat-label">Antrian WA Pending</div>
      </div>
      <i class="bi bi-whatsapp stat-icon ms-auto"></i>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="stat-card h-100" style="background:#0e7490">
      <div>
        <div class="stat-value"><?= (int)$kandidatBaru['total'] ?></div>
        <div class="stat-label">Kandidat Waitlist</div>
      </div>
      <i class="bi bi-person-plus-fill stat-icon ms-auto"></i>
    </div>
  </div>

</div>

<!-- ─── Shortcut Buttons ───────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap gap-2 mb-4">
  <a href="/pages/bills.php?action=generate&bulan=<?= $bulanIni ?>"
     class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Generate Tagihan <?= $bulanLabel ?>
  </a>
  <a href="/pages/bills.php?tab=wa-queue" class="btn btn-success">
    <i class="bi bi-whatsapp me-1"></i>Antrian WA
    <?php if ((int)$waQueuePending['total'] > 0): ?>
      <span class="badge bg-danger ms-1"><?= (int)$waQueuePending['total'] ?></span>
    <?php endif; ?>
  </a>
  <a href="/pages/tenants.php" class="btn btn-outline-secondary">
    <i class="bi bi-people-fill me-1"></i>Data Penghuni
  </a>
  <a href="/pages/candidates.php" class="btn btn-outline-secondary">
    <i class="bi bi-person-plus me-1"></i>Kandidat
  </a>
</div>

<!-- ─── Row: Jatuh Tempo & Telat ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">

  <!-- Jatuh Tempo Dekat -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-alarm text-warning"></i>
        Jatuh Tempo dalam 3 Hari
        <?php if ($jatuhTempoDeket): ?>
          <span class="badge bg-warning text-dark ms-auto"><?= count($jatuhTempoDeket) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if ($jatuhTempoDeket): ?>
          <table class="table table-sm mb-0">
            <tbody>
              <?php foreach ($jatuhTempoDeket as $row): ?>
              <tr>
                <td class="ps-3 py-2">
                  <div class="fw-semibold fs-7"><?= h($row['nama']) ?></div>
                  <div class="text-muted fs-7">Kamar <?= h($row['nomor_kamar']) ?></div>
                </td>
                <td class="py-2 text-end">
                  <div class="fs-7"><?= formatRupiah((int)$row['total']) ?></div>
                  <div class="text-danger fs-7"><?= formatTanggal($row['tgl_jatuh_tempo']) ?></div>
                </td>
                <td class="pe-3 py-2 text-end">
                  <a href="/pages/bills.php?highlight=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary py-0">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="text-muted text-center py-4 mb-0 fs-7">Tidak ada tagihan jatuh tempo dekat.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Tagihan Telat -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle text-danger"></i>
        Tagihan Terlambat
        <?php if ($tagihanTelat): ?>
          <span class="badge bg-danger ms-auto"><?= count($tagihanTelat) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if ($tagihanTelat): ?>
          <table class="table table-sm mb-0">
            <tbody>
              <?php foreach ($tagihanTelat as $row): ?>
              <?php
                $hariTelat = (int) floor((strtotime('today') - strtotime($row['tgl_jatuh_tempo'])) / 86400);
                $noWa = '62' . ltrim(preg_replace('/[^0-9]/', '', $row['no_wa']), '0');
              ?>
              <tr>
                <td class="ps-3 py-2">
                  <div class="fw-semibold fs-7"><?= h($row['nama']) ?></div>
                  <div class="text-muted fs-7">Kamar <?= h($row['nomor_kamar']) ?></div>
                </td>
                <td class="py-2">
                  <div class="fs-7"><?= formatRupiah((int)$row['total']) ?></div>
                  <span class="badge-status badge-status-unpaid"><?= $hariTelat ?> hari telat</span>
                </td>
                <td class="pe-3 py-2 text-end">
                  <a href="https://wa.me/<?= $noWa ?>" target="_blank"
                     class="btn btn-sm btn-success py-0">
                    <i class="bi bi-whatsapp"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="text-muted text-center py-4 mb-0 fs-7">Semua tagihan on-time.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ─── Kandidat Terbaru ───────────────────────────────────────────────────── -->
<?php if ($kandidatTerbaru): ?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-person-plus-fill me-2 text-info"></i>Kandidat Baru (Waitlist)</span>
    <a href="/pages/candidates.php" class="btn btn-sm btn-outline-secondary">Lihat Semua</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead>
        <tr>
          <th class="ps-3">Nama</th>
          <th>No. WA</th>
          <th>Kamar Diminati</th>
          <th>Rencana Masuk</th>
          <th class="pe-3">Daftar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($kandidatTerbaru as $k): ?>
        <tr>
          <td class="ps-3 fw-semibold"><?= h($k['nama']) ?></td>
          <td>
            <a href="https://wa.me/<?= '62' . ltrim(preg_replace('/[^0-9]/', '', $k['no_wa']), '0') ?>"
               target="_blank" class="text-success text-decoration-none">
              <i class="bi bi-whatsapp me-1"></i><?= h($k['no_wa']) ?>
            </a>
          </td>
          <td><?= $k['kamar_diminati'] ? h($k['kamar_diminati']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $k['tgl_masuk_rencana'] ? formatTanggal($k['tgl_masuk_rencana']) : '<span class="text-muted">—</span>' ?></td>
          <td class="pe-3 text-muted fs-7"><?= formatTanggal(substr($k['created_at'], 0, 10)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ─── Status Kamar ───────────────────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-door-open-fill me-2 text-primary"></i>Status Kamar</span>
    <a href="/pages/rooms.php" class="btn btn-sm btn-outline-secondary">Kelola Kamar</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead>
        <tr>
          <th class="ps-3">Kamar</th>
          <th>Ukuran</th>
          <th>Harga Sewa</th>
          <th>Penghuni</th>
          <th class="pe-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $allRooms = dbFetchAll('
            SELECT r.id, r.nomor_kamar, r.ukuran, r.harga_sewa, r.status,
                   t.nama AS penghuni
            FROM rooms r
            LEFT JOIN tenants t ON t.kamar_id = r.id AND t.status = "aktif"
            ORDER BY r.id ASC
        ');
        foreach ($allRooms as $room): ?>
        <tr>
          <td class="ps-3 fw-semibold"><?= h($room['nomor_kamar']) ?></td>
          <td class="text-muted fs-7"><?= h($room['ukuran'] ?? '—') ?></td>
          <td><?= $room['harga_sewa'] ? formatRupiah((int)$room['harga_sewa']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $room['penghuni'] ? h($room['penghuni']) : '<span class="text-muted">Kosong</span>' ?></td>
          <td class="pe-3">
            <span class="badge-status badge-status-<?= $room['status'] ?>">
              <?= $room['status'] === 'terisi' ? 'Terisi' : 'Tersedia' ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
