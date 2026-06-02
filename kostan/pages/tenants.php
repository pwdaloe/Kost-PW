<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Penghuni';

// ─── POST Handler ─────────────────────────────────────────────────────────────

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nama      = trim($_POST['nama'] ?? '');
        $noWa      = trim($_POST['no_wa'] ?? '');
        $noKtp     = trim($_POST['no_ktp'] ?? '');
        $kamarId   = (int)($_POST['kamar_id'] ?? 0) ?: null;
        $tglMasuk  = $_POST['tgl_masuk'] ?? '';
        $tglKeluar = $_POST['tgl_keluar'] ?? '';
        $status    = in_array($_POST['status'] ?? '', ['aktif', 'keluar']) ? $_POST['status'] : 'aktif';
        $catatan   = trim($_POST['catatan'] ?? '');

        if ($nama === '' || $noWa === '') {
            $flash = ['type' => 'danger', 'msg' => 'Nama dan No. WA wajib diisi.'];
        } else {
            if ($action === 'add') {
                dbExecute('
                    INSERT INTO tenants (nama, no_wa, no_ktp, kamar_id, tgl_masuk, tgl_keluar, status, catatan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ', [$nama, $noWa, $noKtp ?: null, $kamarId, $tglMasuk ?: null, $tglKeluar ?: null, $status, $catatan ?: null]);

                // Tandai kamar sebagai terisi
                if ($kamarId && $status === 'aktif') {
                    dbExecute('UPDATE rooms SET status = "terisi" WHERE id = ?', [$kamarId]);
                }
                $flash = ['type' => 'success', 'msg' => "Penghuni <strong>{$nama}</strong> berhasil ditambahkan."];
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $lama = dbFetchOne('SELECT kamar_id, status FROM tenants WHERE id = ?', [$id]);

                dbExecute('
                    UPDATE tenants SET nama=?, no_wa=?, no_ktp=?, kamar_id=?, tgl_masuk=?,
                                       tgl_keluar=?, status=?, catatan=?
                    WHERE id = ?
                ', [$nama, $noWa, $noKtp ?: null, $kamarId, $tglMasuk ?: null,
                    $tglKeluar ?: null, $status, $catatan ?: null, $id]);

                // Sinkron status kamar lama & baru
                if ($lama['kamar_id'] && $lama['kamar_id'] != $kamarId) {
                    dbExecute('UPDATE rooms SET status = "tersedia" WHERE id = ?', [$lama['kamar_id']]);
                }
                if ($kamarId) {
                    $newStatus = $status === 'aktif' ? 'terisi' : 'tersedia';
                    dbExecute('UPDATE rooms SET status = ? WHERE id = ?', [$newStatus, $kamarId]);
                }
                $flash = ['type' => 'success', 'msg' => "Data <strong>{$nama}</strong> berhasil diperbarui."];
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $t  = dbFetchOne('SELECT nama, kamar_id FROM tenants WHERE id = ?', [$id]);
        if ($t) {
            if ($t['kamar_id']) {
                dbExecute('UPDATE rooms SET status = "tersedia" WHERE id = ?', [$t['kamar_id']]);
            }
            dbExecute('DELETE FROM tenants WHERE id = ?', [$id]);
            $flash = ['type' => 'success', 'msg' => "Penghuni <strong>{$t['nama']}</strong> berhasil dihapus."];
        }
    }
}

// ─── Data ─────────────────────────────────────────────────────────────────────

$filterStatus = $_GET['status'] ?? 'aktif';
$validStatus  = ['aktif', 'keluar', 'semua'];
if (!in_array($filterStatus, $validStatus)) $filterStatus = 'aktif';

$where    = $filterStatus !== 'semua' ? 'WHERE t.status = ?' : 'WHERE 1';
$params   = $filterStatus !== 'semua' ? [$filterStatus] : [];

$tenants = dbFetchAll("
    SELECT t.*, r.nomor_kamar, r.harga_sewa
    FROM tenants t
    LEFT JOIN rooms r ON t.kamar_id = r.id
    {$where}
    ORDER BY t.status ASC, t.nama ASC
", $params);

$kamarTersedia = dbFetchAll('
    SELECT id, nomor_kamar, harga_sewa FROM rooms
    WHERE status = "tersedia" ORDER BY nomor_kamar
');
$semuaKamar = dbFetchAll('SELECT id, nomor_kamar, status FROM rooms ORDER BY nomor_kamar');

require __DIR__ . '/../includes/header.php';
?>

<!-- Flash -->
<?php if ($flash['msg']): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-4" role="alert">
  <?= $flash['msg'] ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <!-- Filter tab -->
  <div class="btn-group btn-group-sm">
    <?php foreach (['aktif' => 'Aktif', 'keluar' => 'Keluar', 'semua' => 'Semua'] as $val => $label): ?>
    <a href="?status=<?= $val ?>"
       class="btn btn-outline-secondary <?= $filterStatus === $val ? 'active' : '' ?>">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTenant" onclick="resetForm()">
    <i class="bi bi-plus-lg me-1"></i>Tambah Penghuni
  </button>
</div>

<!-- ─── Tabel Penghuni ────────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-body p-0">
    <?php if ($tenants): ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Nama</th>
            <th>Kamar</th>
            <th>No. WA</th>
            <th>Masuk</th>
            <th>Sewa / bln</th>
            <th>Status</th>
            <th class="pe-3 text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tenants as $t): ?>
          <?php
            $noWaNorm = '62' . ltrim(preg_replace('/[^0-9]/', '', $t['no_wa']), '0');
          ?>
          <tr id="row-<?= $t['id'] ?>">
            <td class="ps-3">
              <div class="fw-semibold"><?= h($t['nama']) ?></div>
              <?php if ($t['no_ktp']): ?>
              <div class="text-muted fs-7">KTP: <?= h($t['no_ktp']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?= $t['nomor_kamar']
                  ? '<span class="fw-semibold">'.h($t['nomor_kamar']).'</span>'
                  : '<span class="text-muted">—</span>' ?>
            </td>
            <td>
              <a href="https://wa.me/<?= $noWaNorm ?>" target="_blank"
                 class="text-success text-decoration-none fs-7">
                <i class="bi bi-whatsapp me-1"></i><?= h($t['no_wa']) ?>
              </a>
            </td>
            <td class="fs-7 text-muted">
              <?= $t['tgl_masuk'] ? formatTanggal($t['tgl_masuk']) : '—' ?>
            </td>
            <td class="fs-7">
              <?= $t['harga_sewa'] ? formatRupiah((int)$t['harga_sewa']) : '<span class="text-muted">—</span>' ?>
            </td>
            <td>
              <span class="badge-status badge-status-<?= $t['status'] ?>">
                <?= $t['status'] === 'aktif' ? 'Aktif' : 'Keluar' ?>
              </span>
            </td>
            <td class="pe-3 text-end">
              <div class="d-flex gap-1 justify-content-end">
                <button class="btn btn-sm btn-outline-primary"
                        onclick="editTenant(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <a href="/pages/bills.php?tenant_id=<?= $t['id'] ?>"
                   class="btn btn-sm btn-outline-secondary" title="Lihat tagihan">
                  <i class="bi bi-receipt"></i>
                </a>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus penghuni <?= h($t['nama']) ?>? Semua tagihan terkait juga akan terhapus.')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted text-center py-5 mb-0">
        <?= $filterStatus === 'aktif' ? 'Belum ada penghuni aktif.' : 'Tidak ada data.' ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Modal Tambah / Edit ───────────────────────────────────────────────── -->
<div class="modal fade" id="modalTenant" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="formTenant">
        <input type="hidden" name="action" id="fAction" value="add">
        <input type="hidden" name="id"     id="fId"     value="">

        <div class="modal-header">
          <h6 class="modal-title fw-bold" id="modalTitle">Tambah Penghuni</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama" id="fNama" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">No. WhatsApp <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text text-muted fs-7">+62</span>
                <input type="text" name="no_wa" id="fNoWa" class="form-control"
                       placeholder="08xxxxxxxxxx" inputmode="tel" required>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">No. KTP</label>
              <input type="text" name="no_ktp" id="fNoKtp" class="form-control"
                     placeholder="16 digit NIK" maxlength="20" inputmode="numeric">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Kamar</label>
              <select name="kamar_id" id="fKamarId" class="form-select">
                <option value="">— Pilih Kamar —</option>
                <?php foreach ($semuaKamar as $k): ?>
                <option value="<?= $k['id'] ?>"
                        data-status="<?= $k['status'] ?>">
                  <?= h($k['nomor_kamar']) ?>
                  <?= $k['status'] === 'terisi' ? '(Terisi)' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tanggal Masuk</label>
              <input type="date" name="tgl_masuk" id="fTglMasuk" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tanggal Keluar</label>
              <input type="date" name="tgl_keluar" id="fTglKeluar" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" id="fStatus" class="form-select">
                <option value="aktif">Aktif</option>
                <option value="keluar">Keluar</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Catatan</label>
              <textarea name="catatan" id="fCatatan" class="form-control" rows="2"
                        placeholder="Opsional..."></textarea>
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
  ['fAction','fId','fNama','fNoWa','fNoKtp','fTglMasuk','fTglKeluar','fCatatan'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('fAction').value  = 'add';
  document.getElementById('fKamarId').value = '';
  document.getElementById('fStatus').value  = 'aktif';
  document.getElementById('modalTitle').textContent = 'Tambah Penghuni';
  document.getElementById('btnSubmit').textContent  = 'Simpan';
}

function editTenant(t) {
  document.getElementById('modalTitle').textContent = 'Edit Penghuni';
  document.getElementById('btnSubmit').textContent  = 'Perbarui';
  document.getElementById('fAction').value    = 'edit';
  document.getElementById('fId').value        = t.id;
  document.getElementById('fNama').value      = t.nama;
  document.getElementById('fNoWa').value      = t.no_wa;
  document.getElementById('fNoKtp').value     = t.no_ktp   ?? '';
  document.getElementById('fKamarId').value   = t.kamar_id ?? '';
  document.getElementById('fTglMasuk').value  = t.tgl_masuk  ?? '';
  document.getElementById('fTglKeluar').value = t.tgl_keluar ?? '';
  document.getElementById('fStatus').value    = t.status;
  document.getElementById('fCatatan').value   = t.catatan ?? '';
  new bootstrap.Modal(document.getElementById('modalTenant')).show();
}
</script>
