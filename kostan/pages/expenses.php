<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Biaya Operasional';

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
        $kategori  = in_array($_POST['kategori'] ?? '', ['listrik','air','perawatan','kebersihan','lainnya'])
                     ? $_POST['kategori'] : 'lainnya';
        $ket       = trim($_POST['keterangan'] ?? '');
        $nominal   = (int) str_replace(['.', ','], '', $_POST['nominal'] ?? '0');

        if ($nominal <= 0) {
            $flash = ['type' => 'danger', 'msg' => 'Nominal harus lebih dari 0.'];
        } elseif ($action === 'add') {
            dbExecute('INSERT INTO expenses (tanggal, kategori, keterangan, nominal) VALUES (?,?,?,?)',
                      [$tanggal, $kategori, $ket ?: null, $nominal]);
            $flash = ['type' => 'success', 'msg' => 'Biaya berhasil ditambahkan.'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            dbExecute('UPDATE expenses SET tanggal=?, kategori=?, keterangan=?, nominal=? WHERE id=?',
                      [$tanggal, $kategori, $ket ?: null, $nominal, $id]);
            $flash = ['type' => 'success', 'msg' => 'Biaya berhasil diperbarui.'];
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute('DELETE FROM expenses WHERE id=?', [$id]);
        $flash = ['type' => 'success', 'msg' => 'Biaya dihapus.'];
    }
}

// ─── Filter & Data ────────────────────────────────────────────────────────────

$bulanFilter = $_GET['bulan'] ?? date('Y-m');
$bulanSql    = $bulanFilter . '-01';
$bulanAkhir  = date('Y-m-t', strtotime($bulanSql));

$expenses = dbFetchAll('
    SELECT * FROM expenses
    WHERE tanggal BETWEEN ? AND ?
    ORDER BY tanggal DESC, id DESC
', [$bulanSql, $bulanAkhir]);

$totalBulan = array_sum(array_column($expenses, 'nominal'));

// Ringkasan per kategori
$perKat = dbFetchAll('
    SELECT kategori, SUM(nominal) AS total
    FROM expenses WHERE tanggal BETWEEN ? AND ?
    GROUP BY kategori ORDER BY total DESC
', [$bulanSql, $bulanAkhir]);

// Perbandingan: pendapatan sewa bulan ini vs pengeluaran
$pendapatan = dbFetchOne('
    SELECT COALESCE(SUM(total),0) AS nominal
    FROM bills WHERE bulan=? AND status_bayar="paid"
', [$bulanSql]);

$kategoriLabel = [
    'listrik'    => ['label'=>'Listrik',    'icon'=>'bi-lightning-charge', 'color'=>'text-warning'],
    'air'        => ['label'=>'Air',        'icon'=>'bi-droplet',          'color'=>'text-info'],
    'perawatan'  => ['label'=>'Perawatan',  'icon'=>'bi-tools',            'color'=>'text-secondary'],
    'kebersihan' => ['label'=>'Kebersihan', 'icon'=>'bi-stars',            'color'=>'text-success'],
    'lainnya'    => ['label'=>'Lainnya',    'icon'=>'bi-three-dots',       'color'=>'text-muted'],
];

require __DIR__ . '/../includes/header.php';
?>

<?php if ($flash['msg']): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-3" role="alert">
  <?= $flash['msg'] ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <form method="GET">
    <input type="month" name="bulan" value="<?= h($bulanFilter) ?>"
           class="form-control form-control-sm" style="width:150px" onchange="this.form.submit()">
  </form>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalExp" onclick="resetForm()">
    <i class="bi bi-plus-lg me-1"></i>Tambah Biaya
  </button>
</div>

<!-- ─── Ringkasan ─────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card h-100" style="background:#b91c1c">
      <div>
        <div class="stat-value fs-5"><?= formatRupiah($totalBulan) ?></div>
        <div class="stat-label">Total Pengeluaran <?= formatBulan($bulanSql) ?></div>
      </div>
      <i class="bi bi-wallet2 stat-icon ms-auto"></i>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card h-100" style="background:#065f46">
      <div>
        <div class="stat-value fs-5"><?= formatRupiah((int)$pendapatan['nominal']) ?></div>
        <div class="stat-label">Pendapatan Sewa Masuk</div>
      </div>
      <i class="bi bi-cash-coin stat-icon ms-auto"></i>
    </div>
  </div>
  <div class="col-md-4">
    <?php $selisih = (int)$pendapatan['nominal'] - $totalBulan; ?>
    <div class="stat-card h-100" style="background:<?= $selisih >= 0 ? '#1a56db' : '#92400e' ?>">
      <div>
        <div class="stat-value fs-5"><?= formatRupiah(abs($selisih)) ?></div>
        <div class="stat-label"><?= $selisih >= 0 ? 'Surplus' : 'Defisit' ?></div>
      </div>
      <i class="bi bi-graph-up stat-icon ms-auto"></i>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Tabel pengeluaran -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Daftar Pengeluaran — <?= formatBulan($bulanSql) ?></div>
      <div class="card-body p-0">
        <?php if ($expenses): ?>
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th class="ps-3">Tanggal</th>
              <th>Kategori</th>
              <th>Keterangan</th>
              <th class="text-end">Nominal</th>
              <th class="pe-3 text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $e): ?>
            <tr>
              <td class="ps-3 fs-7"><?= formatTanggal($e['tanggal']) ?></td>
              <td>
                <span class="<?= $kategoriLabel[$e['kategori']]['color'] ?>">
                  <i class="bi <?= $kategoriLabel[$e['kategori']]['icon'] ?> me-1"></i>
                  <?= $kategoriLabel[$e['kategori']]['label'] ?>
                </span>
              </td>
              <td class="fs-7 text-muted"><?= $e['keterangan'] ? h($e['keterangan']) : '—' ?></td>
              <td class="text-end fw-semibold fs-7"><?= formatRupiah((int)$e['nominal']) ?></td>
              <td class="pe-3 text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="editExp(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Hapus biaya ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $e['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="3" class="ps-3 fw-bold text-end">Total</td>
              <td class="text-end fw-bold"><?= formatRupiah($totalBulan) ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <?php else: ?>
          <p class="text-muted text-center py-5 mb-0">Belum ada pengeluaran bulan ini.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Ringkasan per kategori -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Per Kategori</div>
      <div class="card-body">
        <?php if ($perKat): ?>
          <?php foreach ($perKat as $pk): ?>
          <?php
            $pct = $totalBulan > 0 ? round((int)$pk['total'] / $totalBulan * 100) : 0;
            $kat = $kategoriLabel[$pk['kategori']];
          ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between fs-7 mb-1">
              <span class="<?= $kat['color'] ?>">
                <i class="bi <?= $kat['icon'] ?> me-1"></i><?= $kat['label'] ?>
              </span>
              <span class="fw-semibold"><?= formatRupiah((int)$pk['total']) ?></span>
            </div>
            <div class="progress" style="height:6px">
              <div class="progress-bar" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted fs-7 text-center">Belum ada data.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalExp" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="fAction" value="add">
        <input type="hidden" name="id"     id="fId"     value="">
        <div class="modal-header">
          <h6 class="modal-title fw-bold" id="modalTitle">Tambah Biaya</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tanggal</label>
              <input type="date" name="tanggal" id="fTgl" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Kategori</label>
              <select name="kategori" id="fKat" class="form-select">
                <?php foreach ($kategoriLabel as $val => $info): ?>
                <option value="<?= $val ?>"><?= $info['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Keterangan</label>
              <input type="text" name="keterangan" id="fKet" class="form-control" placeholder="Opsional">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Nominal <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text text-muted">Rp</span>
                <input type="text" name="nominal" id="fNominal" class="form-control"
                       placeholder="0" inputmode="numeric" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
function resetForm() {
  document.getElementById('fAction').value = 'add';
  document.getElementById('fId').value     = '';
  document.getElementById('fTgl').value    = new Date().toISOString().split('T')[0];
  document.getElementById('fKat').value    = 'lainnya';
  document.getElementById('fKet').value    = '';
  document.getElementById('fNominal').value = '';
  document.getElementById('modalTitle').textContent = 'Tambah Biaya';
  document.getElementById('btnSubmit').textContent  = 'Simpan';
}
function editExp(e) {
  document.getElementById('fAction').value  = 'edit';
  document.getElementById('fId').value      = e.id;
  document.getElementById('fTgl').value     = e.tanggal;
  document.getElementById('fKat').value     = e.kategori;
  document.getElementById('fKet').value     = e.keterangan ?? '';
  document.getElementById('fNominal').value = e.nominal;
  document.getElementById('modalTitle').textContent = 'Edit Biaya';
  document.getElementById('btnSubmit').textContent  = 'Perbarui';
  new bootstrap.Modal(document.getElementById('modalExp')).show();
}
</script>
