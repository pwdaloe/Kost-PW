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

$rooms       = dbFetchAll('SELECT id, nomor_kamar, ukuran, harga_sewa, status FROM rooms ORDER BY nomor_kamar');
$roomsTersedia = array_filter($rooms, fn($r) => $r['status'] === 'tersedia');

// Kamar dipilih untuk panel notifikasi
$notifRoomId = (int)($_GET['notif_room'] ?? 0);
$notifRoom   = $notifRoomId
    ? dbFetchOne('SELECT id, nomor_kamar, ukuran, harga_sewa FROM rooms WHERE id=?', [$notifRoomId])
    : null;

// Kandidat waitlist yang cocok: diminati = kamar ini ATAU belum ada preferensi
$kandidatNotif = [];
if ($notifRoom) {
    $kandidatNotif = dbFetchAll("
        SELECT id, nama, no_wa, kamar_diminati
        FROM candidates
        WHERE status = 'waitlist'
          AND (kamar_diminati = ? OR kamar_diminati IS NULL OR kamar_diminati = '')
        ORDER BY
          CASE WHEN kamar_diminati = ? THEN 0 ELSE 1 END,
          created_at ASC
    ", [$notifRoom['nomor_kamar'], $notifRoom['nomor_kamar']]);
}

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
  <div class="d-flex gap-2">
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNotifKamar">
      <i class="bi bi-door-open-fill me-1"></i>Notifikasi Kamar Kosong
    </button>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCand" onclick="resetForm()">
      <i class="bi bi-plus-lg me-1"></i>Tambah Kandidat
    </button>
  </div>
</div>

<!-- Panel notifikasi aktif (jika kamar dipilih) -->
<?php if ($notifRoom): ?>
<div class="card mb-3 border-success">
  <div class="card-header bg-success bg-opacity-10 d-flex align-items-center justify-content-between">
    <div>
      <i class="bi bi-door-open-fill text-success me-2"></i>
      <strong>Mode Notifikasi:</strong> Kamar <?= h($notifRoom['nomor_kamar']) ?>
      <?php if ($notifRoom['ukuran']): ?>
        <span class="text-muted fs-7 ms-1">(<?= h($notifRoom['ukuran']) ?>)</span>
      <?php endif; ?>
      <?php if ($notifRoom['harga_sewa']): ?>
        <span class="text-success fw-semibold ms-2"><?= formatRupiah((int)$notifRoom['harga_sewa']) ?>/bln</span>
      <?php endif; ?>
    </div>
    <a href="?status=<?= h($filterStatus) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-x me-1"></i>Tutup Mode Ini
    </a>
  </div>
  <div class="card-body p-0">
    <?php if ($kandidatNotif): ?>
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th class="ps-3">Nama</th>
          <th>No. WA</th>
          <th>Kamar Diminati</th>
          <th>Daftar Sejak</th>
          <th class="pe-3 text-end">Kirim WA</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($kandidatNotif as $k): ?>
        <tr>
          <td class="ps-3 fw-semibold"><?= h($k['nama']) ?></td>
          <td class="fs-7">
            <?= '62' . ltrim(preg_replace('/[^0-9]/', '', $k['no_wa']), '0') ?>
          </td>
          <td>
            <?php if ($k['kamar_diminati'] === $notifRoom['nomor_kamar']): ?>
              <span class="badge-status badge-status-aktif">
                <i class="bi bi-star-fill me-1"></i><?= h($k['kamar_diminati']) ?>
              </span>
            <?php else: ?>
              <span class="text-muted fs-7">Tidak spesifik</span>
            <?php endif; ?>
          </td>
          <td class="fs-7 text-muted"><?= formatTanggal(substr($k['created_at'] ?? '', 0, 10)) ?></td>
          <td class="pe-3 text-end">
            <button class="btn btn-sm btn-success"
                    id="notif-btn-<?= $k['id'] ?>"
                    onclick="kirimNotifKamar(<?= $k['id'] ?>, <?= $notifRoom['id'] ?>, this)">
              <i class="bi bi-whatsapp me-1"></i>Kirim WA
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="px-3 py-2 fs-7 text-muted border-top">
      <i class="bi bi-info-circle me-1"></i>
      <?= count($kandidatNotif) ?> kandidat — yang spesifik minta kamar ini ditampilkan pertama.
      Klik "Kirim WA" satu per satu.
    </div>
    <?php else: ?>
      <p class="text-muted text-center py-4 mb-0 fs-7">
        Tidak ada kandidat waitlist yang cocok untuk kamar ini.
      </p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

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

<!-- ─── Modal Pilih Kamar untuk Notifikasi ────────────────────────────────── -->
<div class="modal fade" id="modalNotifKamar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">
          <i class="bi bi-door-open-fill text-success me-2"></i>Notifikasi Kamar Kosong
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted fs-7 mb-3">
          Pilih kamar yang tersedia. Sistem akan menampilkan kandidat waitlist
          yang cocok untuk dikirimi pesan WA.
        </p>
        <label class="form-label fw-semibold">Pilih Kamar</label>
        <div class="row g-2">
          <?php foreach ($rooms as $r): ?>
          <div class="col-6">
            <a href="?notif_room=<?= $r['id'] ?>&status=<?= h($filterStatus) ?>"
               class="card text-decoration-none h-100 <?= $notifRoomId===$r['id'] ? 'border-success' : '' ?>"
               data-bs-dismiss="modal"
               style="border-radius:8px; transition: border-color .15s">
              <div class="card-body py-2 px-3">
                <div class="fw-semibold"><?= h($r['nomor_kamar']) ?></div>
                <?php if ($r['ukuran']): ?>
                  <div class="text-muted fs-7"><?= h($r['ukuran']) ?></div>
                <?php endif; ?>
                <span class="badge <?= $r['status']==='tersedia' ? 'bg-success' : 'bg-secondary' ?> rounded-pill mt-1"
                      style="font-size:.65rem">
                  <?= $r['status']==='tersedia' ? 'Tersedia' : 'Terisi' ?>
                </span>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$roomsTersedia): ?>
        <div class="alert alert-warning mt-3 py-2 mb-0 fs-7">
          <i class="bi bi-exclamation-triangle me-1"></i>
          Semua kamar sedang terisi. Notifikasi tetap bisa dikirim untuk kamar manapun.
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
      </div>
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

async function kirimNotifKamar(candidateId, roomId, btn) {
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  try {
    const res  = await fetch(`/api/generate_wa_kamar.php?candidate_id=${candidateId}&room_id=${roomId}`);
    const data = await res.json();
    if (data.url) {
      window.open(data.url, '_blank');
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Terkirim';
      btn.classList.replace('btn-success', 'btn-outline-success');
    } else {
      alert('Error: ' + (data.error ?? 'Gagal generate WA'));
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-whatsapp me-1"></i>Kirim WA';
    }
  } catch(e) {
    alert('Gagal menghubungi server.');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-whatsapp me-1"></i>Kirim WA';
  }
}
</script>
