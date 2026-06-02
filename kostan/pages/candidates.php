<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Kandidat';

// ─── POST Handler ─────────────────────────────────────────────────────────────

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nama      = trim($_POST['nama'] ?? '');
        $noWa      = trim($_POST['no_wa'] ?? '');
        $kamar     = trim($_POST['kamar_diminati'] ?? '');
        $tglRencana = $_POST['tgl_masuk_rencana'] ?? '';
        $status    = in_array($_POST['status'] ?? '', ['waitlist','survey','approved','rejected'])
                     ? $_POST['status'] : 'waitlist';
        $catatan   = trim($_POST['catatan'] ?? '');

        if ($nama === '' || $noWa === '') {
            $flash = ['type' => 'danger', 'msg' => 'Nama dan No. WA wajib diisi.'];
        } elseif ($action === 'add') {
            dbExecute('
                INSERT INTO candidates (nama, no_wa, kamar_diminati, tgl_masuk_rencana, status, catatan, sumber)
                VALUES (?, ?, ?, ?, ?, ?, "manual")
            ', [$nama, $noWa, $kamar ?: null, $tglRencana ?: null, $status, $catatan ?: null]);
            $flash = ['type' => 'success', 'msg' => "Kandidat <strong>{$nama}</strong> ditambahkan."];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            dbExecute('
                UPDATE candidates SET nama=?, no_wa=?, kamar_diminati=?, tgl_masuk_rencana=?,
                                      status=?, catatan=?
                WHERE id=?
            ', [$nama, $noWa, $kamar ?: null, $tglRencana ?: null, $status, $catatan ?: null, $id]);
            $flash = ['type' => 'success', 'msg' => "Data <strong>{$nama}</strong> diperbarui."];
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $c  = dbFetchOne('SELECT nama FROM candidates WHERE id=?', [$id]);
        if ($c) {
            dbExecute('DELETE FROM candidates WHERE id=?', [$id]);
            $flash = ['type' => 'success', 'msg' => "Kandidat <strong>{$c['nama']}</strong> dihapus."];
        }
    }

    // Promosikan kandidat → penghuni aktif
    if ($action === 'promote') {
        $id  = (int)($_POST['id'] ?? 0);
        $c   = dbFetchOne('SELECT * FROM candidates WHERE id=?', [$id]);
        if ($c) {
            // Cari kamar yang diminati (jika ada & tersedia)
            $kamarId = null;
            if ($c['kamar_diminati']) {
                $room = dbFetchOne('SELECT id FROM rooms WHERE nomor_kamar=? AND status="tersedia"',
                                   [$c['kamar_diminati']]);
                $kamarId = $room ? $room['id'] : null;
            }
            $tenantId = dbExecute('
                INSERT INTO tenants (nama, no_wa, kamar_id, tgl_masuk, status)
                VALUES (?, ?, ?, ?, "aktif")
            ', [$c['nama'], $c['no_wa'], $kamarId, date('Y-m-d')]);

            if ($kamarId) {
                dbExecute('UPDATE rooms SET status="terisi" WHERE id=?', [$kamarId]);
            }
            dbExecute('UPDATE candidates SET status="approved" WHERE id=?', [$id]);
            $flash = ['type' => 'success',
                      'msg'  => "<strong>{$c['nama']}</strong> berhasil dipromosikan menjadi penghuni."];
        }
    }
}

// ─── Data ─────────────────────────────────────────────────────────────────────

$filterStatus = $_GET['status'] ?? 'semua';
$where  = $filterStatus !== 'semua' ? 'WHERE status = ?' : 'WHERE 1';
$params = $filterStatus !== 'semua' ? [$filterStatus] : [];

$candidates = dbFetchAll("
    SELECT * FROM candidates {$where} ORDER BY
    FIELD(status,'waitlist','survey','approved','rejected'), created_at DESC
", $params);

$rooms = dbFetchAll('SELECT nomor_kamar FROM rooms ORDER BY nomor_kamar');

// Hitung per status untuk badge
$counts = dbFetchAll('SELECT status, COUNT(*) AS n FROM candidates GROUP BY status');
$cnt    = array_column($counts, 'n', 'status');

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
  <div class="d-flex gap-2 flex-wrap">
    <?php
    $tabs = ['semua'=>'Semua','waitlist'=>'Waitlist','survey'=>'Survey',
             'approved'=>'Disetujui','rejected'=>'Ditolak'];
    foreach ($tabs as $val => $label):
      $badge = $val !== 'semua' && isset($cnt[$val]) ? $cnt[$val] : null;
    ?>
    <a href="?status=<?= $val ?>"
       class="btn btn-sm <?= $filterStatus===$val ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= $label ?>
      <?php if ($badge): ?><span class="badge bg-white text-primary ms-1"><?= $badge ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCand" onclick="resetForm()">
    <i class="bi bi-plus-lg me-1"></i>Tambah Kandidat
  </button>
</div>

<!-- Pipeline info -->
<div class="d-flex gap-2 mb-3 fs-7 flex-wrap">
  <?php
  $pipeline = ['waitlist'=>['bg-warning','Waitlist'], 'survey'=>['bg-info','Survey'],
               'approved'=>['bg-success','Disetujui'], 'rejected'=>['bg-secondary','Ditolak']];
  foreach ($pipeline as $s => [$bg, $label]): ?>
  <span class="badge <?= $bg ?> text-dark"><?= $label ?>: <?= $cnt[$s] ?? 0 ?></span>
  <?php endforeach; ?>
</div>

<!-- Tabel -->
<div class="card">
  <div class="card-body p-0">
    <?php if ($candidates): ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Nama</th>
            <th>No. WA</th>
            <th>Kamar Diminati</th>
            <th>Rencana Masuk</th>
            <th>Status</th>
            <th>Sumber</th>
            <th>Daftar</th>
            <th class="pe-3 text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($candidates as $c): ?>
          <?php $noWaNorm = '62' . ltrim(preg_replace('/[^0-9]/', '', $c['no_wa']), '0'); ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold"><?= h($c['nama']) ?></div>
              <?php if ($c['catatan']): ?>
                <div class="text-muted fs-7"><?= h($c['catatan']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="https://wa.me/<?= $noWaNorm ?>" target="_blank"
                 class="text-success text-decoration-none fs-7">
                <i class="bi bi-whatsapp me-1"></i><?= h($c['no_wa']) ?>
              </a>
            </td>
            <td class="fs-7"><?= $c['kamar_diminati'] ? h($c['kamar_diminati']) : '<span class="text-muted">—</span>' ?></td>
            <td class="fs-7 text-muted"><?= $c['tgl_masuk_rencana'] ? formatTanggal($c['tgl_masuk_rencana']) : '—' ?></td>
            <td>
              <span class="badge-status badge-status-<?= $c['status'] ?>">
                <?= ['waitlist'=>'Waitlist','survey'=>'Survey','approved'=>'Disetujui','rejected'=>'Ditolak'][$c['status']] ?>
              </span>
            </td>
            <td class="fs-7 text-muted"><?= $c['sumber'] === 'form_web' ? 'Form Web' : 'Manual' ?></td>
            <td class="fs-7 text-muted"><?= formatTanggal(substr($c['created_at'],0,10)) ?></td>
            <td class="pe-3 text-end">
              <div class="d-flex gap-1 justify-content-end">
                <?php if ($c['status'] === 'approved'): ?>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('Promosikan <?= h($c['nama']) ?> menjadi penghuni aktif?')">
                    <input type="hidden" name="action" value="promote">
                    <input type="hidden" name="id"     value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-success" title="Jadikan Penghuni">
                      <i class="bi bi-person-check"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-primary"
                        onclick="editCand(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus kandidat ini?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted text-center py-5 mb-0">Belum ada kandidat.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalCand" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="fAction" value="add">
        <input type="hidden" name="id"     id="fId"     value="">
        <div class="modal-header">
          <h6 class="modal-title fw-bold" id="modalTitle">Tambah Kandidat</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nama <span class="text-danger">*</span></label>
              <input type="text" name="nama" id="fNama" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">No. WA <span class="text-danger">*</span></label>
              <input type="text" name="no_wa" id="fNoWa" class="form-control"
                     placeholder="08xxxxxxxxxx" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Kamar Diminati</label>
              <select name="kamar_diminati" id="fKamar" class="form-select">
                <option value="">— Belum tahu —</option>
                <?php foreach ($rooms as $r): ?>
                <option value="<?= h($r['nomor_kamar']) ?>"><?= h($r['nomor_kamar']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Rencana Masuk</label>
              <input type="date" name="tgl_masuk_rencana" id="fTgl" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status Pipeline</label>
              <select name="status" id="fStatus" class="form-select">
                <option value="waitlist">Waitlist</option>
                <option value="survey">Survey</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Catatan</label>
              <textarea name="catatan" id="fCatatan" class="form-control" rows="2"></textarea>
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
  ['fId','fNama','fNoWa','fTgl','fCatatan'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fAction').value  = 'add';
  document.getElementById('fKamar').value   = '';
  document.getElementById('fStatus').value  = 'waitlist';
  document.getElementById('modalTitle').textContent = 'Tambah Kandidat';
  document.getElementById('btnSubmit').textContent  = 'Simpan';
}
function editCand(c) {
  document.getElementById('modalTitle').textContent = 'Edit Kandidat';
  document.getElementById('btnSubmit').textContent  = 'Perbarui';
  document.getElementById('fAction').value   = 'edit';
  document.getElementById('fId').value       = c.id;
  document.getElementById('fNama').value     = c.nama;
  document.getElementById('fNoWa').value     = c.no_wa;
  document.getElementById('fKamar').value    = c.kamar_diminati ?? '';
  document.getElementById('fTgl').value      = c.tgl_masuk_rencana ?? '';
  document.getElementById('fStatus').value   = c.status;
  document.getElementById('fCatatan').value  = c.catatan ?? '';
  new bootstrap.Modal(document.getElementById('modalCand')).show();
}
</script>
