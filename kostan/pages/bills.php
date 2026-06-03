<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Tagihan';

// ─── POST Handler ─────────────────────────────────────────────────────────────

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Generate tagihan bulan ini untuk semua penghuni aktif
    if ($action === 'generate') {
        $bulan       = $_POST['bulan'] ?? date('Y-m-01');
        $bulan       = date('Y-m-01', strtotime($bulan)); // pastikan format benar
        $tglHariJt   = (int) getSetting('tgl_jatuh_tempo') ?: 5;
        $tglJatuhTempo = date('Y-m-' . str_pad($tglHariJt, 2, '0', STR_PAD_LEFT),
                              strtotime($bulan));

        $aktif = dbFetchAll('
            SELECT t.id AS tenant_id, r.harga_sewa
            FROM tenants t
            JOIN rooms r ON t.kamar_id = r.id
            WHERE t.status = "aktif" AND r.harga_sewa > 0
        ');

        $dibuat = 0;
        $skip   = 0;
        foreach ($aktif as $t) {
            $exists = dbFetchOne('SELECT id FROM bills WHERE tenant_id=? AND bulan=?',
                                 [$t['tenant_id'], $bulan]);
            if ($exists) { $skip++; continue; }

            dbExecute('
                INSERT INTO bills (tenant_id, bulan, sewa, tgl_jatuh_tempo)
                VALUES (?, ?, ?, ?)
            ', [$t['tenant_id'], $bulan, $t['harga_sewa'], $tglJatuhTempo]);
            $dibuat++;
        }

        $msg = "{$dibuat} tagihan berhasil digenerate untuk " . formatBulan($bulan) . ".";
        if ($skip) $msg .= " {$skip} sudah ada, dilewati.";
        $flash = ['type' => $dibuat > 0 ? 'success' : 'info', 'msg' => $msg];
    }

    // Edit tagihan (tambah biaya lainnya)
    if ($action === 'edit_bill') {
        $id      = (int)($_POST['id'] ?? 0);
        $lainnya = (int) str_replace(['.', ','], '', $_POST['lainnya'] ?? '0');
        $ket     = trim($_POST['keterangan_lainnya'] ?? '');
        $jt      = $_POST['tgl_jatuh_tempo'] ?? '';

        dbExecute('
            UPDATE bills SET lainnya=?, keterangan_lainnya=?, tgl_jatuh_tempo=?
            WHERE id=?
        ', [$lainnya, $ket ?: null, $jt, $id]);
        $flash = ['type' => 'success', 'msg' => 'Tagihan berhasil diperbarui.'];
    }

    // Hapus tagihan (hanya jika unpaid dan belum ada pembayaran)
    if ($action === 'delete_bill') {
        $id   = (int)($_POST['id'] ?? 0);
        $bill = dbFetchOne('SELECT status_bayar, paid_amount FROM bills WHERE id=?', [$id]);
        if ($bill && $bill['status_bayar'] === 'unpaid' && (int)$bill['paid_amount'] === 0) {
            dbExecute('DELETE FROM bills WHERE id=?', [$id]);
            $flash = ['type' => 'success', 'msg' => 'Tagihan berhasil dihapus.'];
        } else {
            $flash = ['type' => 'danger', 'msg' => 'Tagihan yang sudah ada pembayaran tidak bisa dihapus.'];
        }
    }
}

// ─── Filter & Data ────────────────────────────────────────────────────────────

$tab        = $_GET['tab']       ?? 'tagihan';
$bulanFilter = $_GET['bulan']    ?? date('Y-m');
$statusFilter = $_GET['status']  ?? 'semua';
$tenantFilter = (int)($_GET['tenant_id'] ?? 0);
$highlight   = (int)($_GET['highlight'] ?? 0);

$bulanSql = $bulanFilter . '-01';

// Bills
$where  = ['b.bulan = ?'];
$params = [$bulanSql];
// "unpaid" filter mencakup partial juga (belum lunas penuh)
if ($statusFilter === 'unpaid')  { $where[] = 'b.status_bayar IN ("unpaid","partial")'; }
if ($statusFilter === 'paid')    { $where[] = 'b.status_bayar = "paid"'; }
if ($tenantFilter)               { $where[] = 'b.tenant_id = ?'; $params[] = $tenantFilter; }

$bills = dbFetchAll('
    SELECT b.*, t.nama, t.no_wa, r.nomor_kamar, r.no_pelanggan_listrik,
           wq.status AS wa_status, wq.sent_at
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    LEFT JOIN wa_queue wq ON wq.bill_id = b.id AND wq.tipe = "tagihan"
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY FIELD(b.status_bayar,"partial","unpaid","paid"), b.tgl_jatuh_tempo ASC, t.nama ASC
', $params);

// Payment history untuk semua bill yang ditampilkan
$billIds = array_column($bills, 'id');
$allPayments = [];
if ($billIds) {
    $placeholders = implode(',', array_fill(0, count($billIds), '?'));
    $payRows = dbFetchAll("
        SELECT * FROM payments WHERE bill_id IN ($placeholders)
        ORDER BY tgl_bayar ASC, id ASC
    ", $billIds);
    foreach ($payRows as $p) {
        $allPayments[$p['bill_id']][] = $p;
    }
}

// WA Queue
$waQueue = dbFetchAll('
    SELECT wq.*, b.bulan, b.total, t.nama, t.no_wa, r.nomor_kamar
    FROM wa_queue wq
    JOIN bills   b ON wq.bill_id  = b.id
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    ORDER BY wq.status ASC, wq.created_at DESC
');

// Ringkasan bulan ini — partial masuk hitungan belum bayar
$summary = dbFetchOne('
    SELECT COUNT(*) AS total,
           SUM(status_bayar IN ("unpaid","partial")) AS unpaid,
           SUM(status_bayar="partial")               AS partial,
           SUM(status_bayar="paid")                  AS paid,
           COALESCE(SUM(total), 0)                   AS total_nominal,
           COALESCE(SUM(CASE WHEN status_bayar="paid" THEN total END), 0)        AS nominal_masuk,
           COALESCE(SUM(CASE WHEN status_bayar="partial" THEN paid_amount END),0) AS nominal_partial
    FROM bills WHERE bulan = ?
', [$bulanSql]);

require __DIR__ . '/../includes/header.php';
?>

<!-- Flash -->
<?php if ($flash['msg']): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-3" role="alert">
  <?= $flash['msg'] ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ─── Toolbar ───────────────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
  <!-- Filter bulan -->
  <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <input type="month" name="bulan" value="<?= h($bulanFilter) ?>"
           class="form-control form-control-sm" style="width:150px"
           onchange="this.form.submit()">
    <select name="status" class="form-select form-select-sm" style="width:150px"
            onchange="this.form.submit()">
      <option value="semua"  <?= $statusFilter==='semua'  ? 'selected':'' ?>>Semua</option>
      <option value="unpaid" <?= $statusFilter==='unpaid' ? 'selected':'' ?>>Belum Bayar + Parsial</option>
      <option value="paid"   <?= $statusFilter==='paid'   ? 'selected':'' ?>>Lunas</option>
    </select>
  </form>

  <!-- Generate -->
  <form method="POST" onsubmit="return confirm('Generate tagihan untuk semua penghuni aktif bulan ini?')">
    <input type="hidden" name="action" value="generate">
    <input type="hidden" name="bulan"  value="<?= h($bulanSql) ?>">
    <button class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle me-1"></i>Generate Tagihan <?= formatBulan($bulanSql) ?>
    </button>
  </form>
</div>

<!-- ─── Ringkasan Bulan ───────────────────────────────────────────────────── -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="fw-bold fs-5"><?= (int)$summary['total'] ?></div>
      <div class="text-muted fs-7">Total Tagihan</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="fw-bold fs-5 text-danger"><?= (int)$summary['unpaid'] ?></div>
      <div class="text-muted fs-7">
        Belum Lunas
        <?php if ((int)$summary['partial'] > 0): ?>
          <span class="badge-status badge-status-partial ms-1"><?= (int)$summary['partial'] ?> parsial</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="fw-bold fs-5 text-success"><?= (int)$summary['paid'] ?></div>
      <div class="text-muted fs-7">Lunas</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="fw-bold fs-5 text-primary">
        <?= formatRupiah((int)$summary['nominal_masuk'] + (int)$summary['nominal_partial']) ?>
      </div>
      <div class="text-muted fs-7">
        Total Masuk
        <?php if ((int)$summary['nominal_partial'] > 0): ?>
          <div class="fs-7 text-warning">(+<?= formatRupiah((int)$summary['nominal_partial']) ?> parsial)</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ─── Tab Navigation ────────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab !== 'wa-queue' ? 'active' : '' ?>"
       href="?bulan=<?= h($bulanFilter) ?>&status=<?= h($statusFilter) ?>&tab=tagihan">
      <i class="bi bi-receipt me-1"></i>Tagihan
      <?php if ((int)$summary['unpaid'] > 0): ?>
        <span class="badge bg-danger ms-1"><?= (int)$summary['unpaid'] ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <?php $pendingWa = count(array_filter($waQueue, fn($w) => $w['status'] === 'pending')); ?>
    <a class="nav-link <?= $tab === 'wa-queue' ? 'active' : '' ?>"
       href="?tab=wa-queue">
      <i class="bi bi-whatsapp me-1"></i>Antrian WA
      <?php if ($pendingWa > 0): ?>
        <span class="badge bg-warning text-dark ms-1"><?= $pendingWa ?></span>
      <?php endif; ?>
    </a>
  </li>
</ul>

<?php if ($tab === 'wa-queue'): ?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: ANTRIAN WA
════════════════════════════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-body p-0">
    <?php if ($waQueue): ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Penghuni</th>
            <th>Kamar</th>
            <th>Bulan</th>
            <th>Total</th>
            <th>Tipe</th>
            <th>Status WA</th>
            <th>Terkirim</th>
            <th class="pe-3 text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($waQueue as $wq): ?>
          <tr>
            <td class="ps-3 fw-semibold"><?= h($wq['nama']) ?></td>
            <td><?= h($wq['nomor_kamar']) ?></td>
            <td class="fs-7"><?= formatBulan($wq['bulan']) ?></td>
            <td class="fs-7"><?= formatRupiah((int)$wq['total']) ?></td>
            <td>
              <span class="badge-status <?= $wq['tipe']==='tagihan' ? 'badge-status-aktif' : 'badge-status-waitlist' ?>">
                <?= $wq['tipe'] ?>
              </span>
            </td>
            <td>
              <span class="badge-status badge-status-<?= $wq['status'] ?>">
                <?= $wq['status'] === 'pending' ? 'Pending' : 'Terkirim' ?>
              </span>
            </td>
            <td class="fs-7 text-muted">
              <?= $wq['sent_at'] ? formatTanggal(substr($wq['sent_at'],0,10)) : '—' ?>
            </td>
            <td class="pe-3 text-end">
              <button class="btn btn-sm btn-success"
                      onclick="kirimWA(<?= $wq['bill_id'] ?>, this)">
                <i class="bi bi-whatsapp me-1"></i>
                <?= $wq['status'] === 'sent' ? 'Kirim Ulang' : 'Kirim WA' ?>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted text-center py-5 mb-0">Belum ada antrian WA.</p>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: TAGIHAN
════════════════════════════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-body p-0">
    <?php if ($bills): ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Penghuni</th>
            <th>Kamar</th>
            <th>Sewa</th>
            <th>Lainnya</th>
            <th>Total</th>
            <th>Jatuh Tempo</th>
            <th>Bayar</th>
            <th>WA</th>
            <th class="pe-3 text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bills as $b): ?>
          <?php
            $isHighlight = $b['id'] === $highlight;
            $paidAmt     = (int)$b['paid_amount'];
            $totalAmt    = (int)$b['total'];
            $sisaAmt     = $totalAmt - $paidAmt;
            $pct         = $totalAmt > 0 ? round($paidAmt / $totalAmt * 100) : 0;
            $hariTelat   = '';
            if (in_array($b['status_bayar'], ['unpaid','partial']) && $b['tgl_jatuh_tempo'] < date('Y-m-d')) {
                $hariTelat = (int) floor((time() - strtotime($b['tgl_jatuh_tempo'])) / 86400);
            }
            $bPayments   = $allPayments[$b['id']] ?? [];
          ?>
          <tr id="bill-<?= $b['id'] ?>" <?= $isHighlight ? 'class="table-warning"' : '' ?>>
            <td class="ps-3">
              <div class="fw-semibold"><?= h($b['nama']) ?></div>
            </td>
            <td class="fs-7"><?= h($b['nomor_kamar']) ?></td>
            <td class="fs-7"><?= formatRupiah((int)$b['sewa']) ?></td>
            <td class="fs-7">
              <?php if ((int)$b['lainnya'] > 0): ?>
                <span title="<?= h($b['keterangan_lainnya'] ?? '') ?>">
                  <?= formatRupiah((int)$b['lainnya']) ?>
                </span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="fw-semibold fs-7"><?= formatRupiah($totalAmt) ?></td>
            <td class="fs-7">
              <div><?= formatTanggal($b['tgl_jatuh_tempo']) ?></div>
              <?php if ($hariTelat !== ''): ?>
                <span class="badge-status badge-status-unpaid"><?= $hariTelat ?>h telat</span>
              <?php endif; ?>
            </td>

            <!-- Kolom Bayar: status + progress partial -->
            <td style="min-width:130px">
              <?php if ($b['status_bayar'] === 'paid'): ?>
                <span class="badge-status badge-status-paid">Lunas</span>
                <?php if ($b['tgl_bayar']): ?>
                  <div class="text-muted fs-7"><?= formatTanggal($b['tgl_bayar']) ?></div>
                <?php endif; ?>

              <?php elseif ($b['status_bayar'] === 'partial'): ?>
                <span class="badge-status badge-status-partial">Parsial</span>
                <div class="progress mt-1 mb-1" style="height:5px" title="<?= $pct ?>% terbayar">
                  <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="fs-7 text-muted">
                  <?= formatRupiah($paidAmt) ?> / <?= formatRupiah($totalAmt) ?>
                </div>
                <div class="fs-7 text-danger">Sisa <?= formatRupiah($sisaAmt) ?></div>

              <?php else: ?>
                <span class="badge-status badge-status-unpaid">Belum Bayar</span>
              <?php endif; ?>
            </td>

            <td>
              <?php if ($b['wa_status'] === 'sent'): ?>
                <span class="badge-status badge-status-sent">Terkirim</span>
              <?php elseif ($b['wa_status'] === 'pending'): ?>
                <span class="badge-status badge-status-pending">Pending</span>
              <?php else: ?>
                <span class="text-muted fs-7">—</span>
              <?php endif; ?>
            </td>

            <td class="pe-3 text-end">
              <div class="d-flex gap-1 justify-content-end flex-wrap">
                <!-- Kirim WA -->
                <button class="btn btn-sm btn-success" id="wa-btn-<?= $b['id'] ?>"
                        onclick="kirimWA(<?= $b['id'] ?>, this)" title="Kirim WA Tagihan">
                  <i class="bi bi-whatsapp"></i>
                </button>

                <?php if ($b['status_bayar'] !== 'paid'): ?>
                <!-- Catat Pembayaran -->
                <button class="btn btn-sm btn-primary"
                        onclick="showBayar(<?= $b['id'] ?>, <?= $totalAmt ?>, <?= $paidAmt ?>)"
                        title="Catat Pembayaran">
                  <i class="bi bi-cash-coin"></i>
                </button>
                <?php endif; ?>

                <!-- Histori pembayaran -->
                <?php if ($bPayments): ?>
                <button class="btn btn-sm btn-outline-info"
                        onclick="showHistori(<?= $b['id'] ?>, '<?= h($b['nama']) ?>')"
                        title="Histori Pembayaran">
                  <i class="bi bi-clock-history"></i>
                </button>
                <?php endif; ?>

                <?php if ($b['status_bayar'] === 'unpaid' && !$bPayments): ?>
                <!-- Edit (hanya jika belum ada pembayaran) -->
                <button class="btn btn-sm btn-outline-secondary"
                        onclick="editBill(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)"
                        title="Edit Tagihan">
                  <i class="bi bi-pencil"></i>
                </button>
                <!-- Hapus -->
                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus tagihan ini?')">
                  <input type="hidden" name="action" value="delete_bill">
                  <input type="hidden" name="id"     value="<?= $b['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Hapus">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="text-center py-5">
        <p class="text-muted mb-3">Belum ada tagihan untuk <?= formatBulan($bulanSql) ?>.</p>
        <form method="POST">
          <input type="hidden" name="action" value="generate">
          <input type="hidden" name="bulan"  value="<?= h($bulanSql) ?>">
          <button class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Generate Tagihan Sekarang
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ─── Modal: Catat Pembayaran ───────────────────────────────────────────── -->
<div class="modal fade" id="modalBayar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Catat Pembayaran</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Ringkasan tagihan -->
        <div class="bg-light rounded p-3 mb-3 fs-7">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Total Tagihan</span>
            <span class="fw-semibold" id="bTotal">—</span>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Sudah Dibayar</span>
            <span class="text-success fw-semibold" id="bSudahBayar">—</span>
          </div>
          <div class="d-flex justify-content-between border-top pt-1 mt-1">
            <span class="fw-bold">Sisa</span>
            <span class="fw-bold text-danger" id="bSisa">—</span>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label fw-semibold mb-0">Jumlah Dibayar Sekarang</label>
              <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-7"
                      onclick="bayarPenuh()">Bayar Penuh</button>
            </div>
            <div class="input-group">
              <span class="input-group-text text-muted">Rp</span>
              <input type="text" id="bNominal" class="form-control" inputmode="numeric"
                     oninput="updatePreview()" placeholder="0">
            </div>
            <!-- Preview status setelah bayar -->
            <div id="bPreview" class="mt-2 fs-7 d-none"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Tanggal Bayar</label>
            <input type="date" id="bTglBayar" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Metode</label>
            <select id="bMetode" class="form-select">
              <option value="transfer">Transfer Bank</option>
              <option value="qris">QRIS</option>
              <option value="tunai">Tunai</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Catatan</label>
            <input type="text" id="bCatatan" class="form-control" placeholder="Opsional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnBayar" onclick="submitBayar()">
          <i class="bi bi-check-circle me-1"></i>Simpan Pembayaran
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ─── Modal: Histori Pembayaran ─────────────────────────────────────────── -->
<div class="modal fade" id="modalHistori" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">
          <i class="bi bi-clock-history me-2"></i>Histori Pembayaran — <span id="hNama"></span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th class="ps-3">Tanggal</th>
              <th>Nominal</th>
              <th>Metode</th>
              <th class="pe-3">Catatan</th>
            </tr>
          </thead>
          <tbody id="hBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Data histori semua payments (embed di halaman untuk akses JS) -->
<script>
const _allPayments = <?= json_encode($allPayments) ?>;
</script>

<!-- ─── Modal: Edit Tagihan ───────────────────────────────────────────────── -->
<div class="modal fade" id="modalEditBill" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit_bill">
        <input type="hidden" name="id"     id="eBillId" value="">
        <div class="modal-header">
          <h6 class="modal-title fw-bold">Edit Tagihan</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Jatuh Tempo</label>
              <input type="date" name="tgl_jatuh_tempo" id="eTglJt" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Biaya Lainnya</label>
              <div class="input-group">
                <span class="input-group-text text-muted">Rp</span>
                <input type="text" name="lainnya" id="eLainnya" class="form-control"
                       placeholder="0" inputmode="numeric">
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Keterangan Biaya Lainnya</label>
              <input type="text" name="keterangan_lainnya" id="eKet" class="form-control"
                     placeholder="Contoh: biaya kebersihan">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
let _activeBillId = null;

// ── Kirim WA ────────────────────────────────────────────────────────────────
async function kirimWA(billId, btn) {
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  try {
    const res  = await fetch(`/api/generate_wa.php?bill_id=${billId}`);
    const data = await res.json();
    if (data.url) {
      window.open(data.url, '_blank');
      btn.innerHTML = '<i class="bi bi-check-lg"></i>';
      btn.classList.replace('btn-success', 'btn-outline-success');
      // Update badge WA di baris
      const row = document.getElementById('bill-' + billId);
      if (row) {
        const waCell = row.querySelectorAll('td')[7];
        if (waCell) waCell.innerHTML = '<span class="badge-status badge-status-sent">Terkirim</span>';
      }
    } else {
      alert('Error: ' + (data.error ?? 'Gagal generate WA'));
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-whatsapp"></i>';
    }
  } catch(e) {
    alert('Gagal menghubungi server.');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-whatsapp"></i>';
  }
}

// ── Catat Pembayaran ─────────────────────────────────────────────────────────
let _bTotal = 0, _bPaid = 0, _bSisa = 0;

function formatRp(n) {
  return 'Rp ' + parseInt(n).toLocaleString('id-ID');
}

function showBayar(billId, total, paidAmt) {
  _activeBillId = billId;
  _bTotal = total;
  _bPaid  = paidAmt;
  _bSisa  = total - paidAmt;

  document.getElementById('bTotal').textContent      = formatRp(total);
  document.getElementById('bSudahBayar').textContent = formatRp(paidAmt);
  document.getElementById('bSisa').textContent       = formatRp(_bSisa);
  document.getElementById('bNominal').value          = _bSisa.toLocaleString('id-ID');
  document.getElementById('bTglBayar').value         = new Date().toISOString().split('T')[0];
  document.getElementById('bCatatan').value          = '';
  document.getElementById('bPreview').classList.add('d-none');
  updatePreview();
  new bootstrap.Modal(document.getElementById('modalBayar')).show();
}

function bayarPenuh() {
  document.getElementById('bNominal').value = _bSisa.toLocaleString('id-ID');
  updatePreview();
}

function updatePreview() {
  const raw = parseInt(document.getElementById('bNominal').value.replace(/\D/g,'')) || 0;
  const prev = document.getElementById('bPreview');

  if (raw <= 0) { prev.classList.add('d-none'); return; }

  if (raw > _bSisa) {
    prev.className = 'mt-2 fs-7 text-danger';
    prev.textContent = '⚠️ Melebihi sisa tagihan (' + formatRp(_bSisa) + '). Overpayment tidak diizinkan.';
  } else if (raw === _bSisa) {
    prev.className = 'mt-2 fs-7 text-success fw-semibold';
    prev.textContent = '✅ Tagihan akan menjadi LUNAS';
  } else {
    const sisaBaru = _bSisa - raw;
    prev.className = 'mt-2 fs-7 text-warning fw-semibold';
    prev.textContent = '⚠️ Tagihan akan menjadi PARSIAL · Sisa ' + formatRp(sisaBaru);
  }
  prev.classList.remove('d-none');
}

async function submitBayar() {
  const nominal = parseInt(document.getElementById('bNominal').value.replace(/\D/g,'')) || 0;
  if (nominal <= 0)      { alert('Masukkan jumlah pembayaran.'); return; }
  if (nominal > _bSisa)  { alert('Jumlah melebihi sisa tagihan.'); return; }

  const btn = document.getElementById('btnBayar');
  btn.disabled = true;
  const body = new FormData();
  body.append('bill_id',   _activeBillId);
  body.append('tgl_bayar', document.getElementById('bTglBayar').value);
  body.append('nominal',   nominal);
  body.append('metode',    document.getElementById('bMetode').value);
  body.append('catatan',   document.getElementById('bCatatan').value);

  try {
    const res  = await fetch('/api/mark_paid.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error ?? 'Gagal menyimpan');
      btn.disabled = false;
    }
  } catch(e) {
    alert('Gagal menghubungi server.');
    btn.disabled = false;
  }
}

// ── Histori Pembayaran ────────────────────────────────────────────────────────
const _metodeLabel = { transfer: 'Transfer', qris: 'QRIS', tunai: 'Tunai' };

function showHistori(billId, nama) {
  document.getElementById('hNama').textContent = nama;
  const payments = _allPayments[billId] || [];
  const body = document.getElementById('hBody');
  if (!payments.length) {
    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 ps-3">Belum ada pembayaran.</td></tr>';
  } else {
    body.innerHTML = payments.map(p => `
      <tr>
        <td class="ps-3 fs-7">${p.tgl_bayar}</td>
        <td class="fw-semibold fs-7">${formatRp(p.nominal)}</td>
        <td class="fs-7">${_metodeLabel[p.metode] ?? p.metode}</td>
        <td class="pe-3 fs-7 text-muted">${p.catatan ?? '—'}</td>
      </tr>`).join('');
  }
  new bootstrap.Modal(document.getElementById('modalHistori')).show();
}

// ── Edit Tagihan ─────────────────────────────────────────────────────────────
function editBill(b) {
  document.getElementById('eBillId').value = b.id;
  document.getElementById('eTglJt').value  = b.tgl_jatuh_tempo;
  document.getElementById('eLainnya').value = b.lainnya ?? 0;
  document.getElementById('eKet').value    = b.keterangan_lainnya ?? '';
  new bootstrap.Modal(document.getElementById('modalEditBill')).show();
}

// ── Scroll ke highlighted row ─────────────────────────────────────────────────
<?php if ($highlight): ?>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('bill-<?= $highlight ?>');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
<?php endif; ?>
</script>
