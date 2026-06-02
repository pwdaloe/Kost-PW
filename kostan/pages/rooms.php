<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Kamar';

// ─── POST Handler ─────────────────────────────────────────────────────────────

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nomor      = trim($_POST['nomor_kamar'] ?? '');
        $ukuran     = trim($_POST['ukuran'] ?? '');
        $lantai     = (int)($_POST['lantai'] ?? 1);
        $harga      = (int) str_replace(['.', ','], '', $_POST['harga_sewa'] ?? '0');
        $noPel      = trim($_POST['no_pelanggan_listrik'] ?? '');
        $ytUrl      = trim($_POST['youtube_url'] ?? '');
        $fasilitas  = trim($_POST['fasilitas'] ?? '');
        $status     = in_array($_POST['status'] ?? '', ['tersedia', 'terisi']) ? $_POST['status'] : 'tersedia';

        if ($nomor === '') {
            $flash = ['type' => 'danger', 'msg' => 'Nama kamar wajib diisi.'];
        } elseif ($action === 'add') {
            dbExecute('
                INSERT INTO rooms (nomor_kamar, ukuran, lantai, harga_sewa, no_pelanggan_listrik, youtube_url, fasilitas, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ', [$nomor, $ukuran ?: null, $lantai, $harga, $noPel ?: null, $ytUrl ?: null, $fasilitas ?: null, $status]);
            $flash = ['type' => 'success', 'msg' => "Kamar <strong>{$nomor}</strong> berhasil ditambahkan."];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            dbExecute('
                UPDATE rooms SET nomor_kamar=?, ukuran=?, lantai=?, harga_sewa=?,
                                 no_pelanggan_listrik=?, youtube_url=?, fasilitas=?, status=?
                WHERE id=?
            ', [$nomor, $ukuran ?: null, $lantai, $harga, $noPel ?: null, $ytUrl ?: null, $fasilitas ?: null, $status, $id]);
            $flash = ['type' => 'success', 'msg' => "Kamar <strong>{$nomor}</strong> berhasil diperbarui."];
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $room = dbFetchOne('SELECT nomor_kamar FROM rooms WHERE id = ?', [$id]);
        $aktif = dbFetchOne('SELECT id FROM tenants WHERE kamar_id = ? AND status = "aktif"', [$id]);
        if ($aktif) {
            $flash = ['type' => 'danger', 'msg' => 'Kamar tidak bisa dihapus karena masih ada penghuni aktif.'];
        } elseif ($room) {
            dbExecute('DELETE FROM rooms WHERE id = ?', [$id]);
            $flash = ['type' => 'success', 'msg' => "Kamar <strong>{$room['nomor_kamar']}</strong> berhasil dihapus."];
        }
    }
}

// ─── Data ─────────────────────────────────────────────────────────────────────

$rooms = dbFetchAll('
    SELECT r.*, t.nama AS penghuni, t.id AS tenant_id
    FROM rooms r
    LEFT JOIN tenants t ON t.kamar_id = r.id AND t.status = "aktif"
    ORDER BY r.id ASC
');

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
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0 fs-7"><?= count($rooms) ?> kamar terdaftar</p>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRoom" onclick="resetForm()">
    <i class="bi bi-plus-lg me-1"></i>Tambah Kamar
  </button>
</div>

<!-- ─── Grid Kartu Kamar ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <?php foreach ($rooms as $r): ?>
  <div class="col-12 col-sm-6 col-xl-4">
    <div class="card h-100">
      <!-- Thumbnail / Video preview -->
      <?php
        $slug      = strtolower($r['nomor_kamar']);
        $thumbFile = __DIR__ . '/../assets/kamar/' . $slug . '.jpg';
        $thumbUrl  = file_exists($thumbFile) ? '/assets/kamar/' . $slug . '.jpg' : null;
        $ytId      = extractYoutubeId($r['youtube_url'] ?? '');
        $autoThumb = $ytId ? 'https://img.youtube.com/vi/' . $ytId . '/hqdefault.jpg' : null;
        $imgSrc    = $thumbUrl ?? $autoThumb;
      ?>
      <?php if ($imgSrc): ?>
      <div class="room-thumb-wrap position-relative">
        <img src="<?= h($imgSrc) ?>" alt="<?= h($r['nomor_kamar']) ?>"
             class="w-100 room-thumb-img">
        <?php if ($ytId): ?>
        <a href="#" class="room-play-btn" onclick="playVideo('<?= $ytId ?>','<?= h($r['nomor_kamar']) ?>');return false;">
          <i class="bi bi-play-circle-fill"></i>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Header kartu -->
      <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-bold"><?= h($r['nomor_kamar']) ?></span>
        <span class="badge-status badge-status-<?= $r['status'] ?>">
          <?= $r['status'] === 'terisi' ? 'Terisi' : 'Tersedia' ?>
        </span>
      </div>

      <div class="card-body py-3">
        <!-- Harga -->
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-cash-coin text-success"></i>
          <span class="fw-semibold">
            <?= $r['harga_sewa'] ? formatRupiah((int)$r['harga_sewa']) : '<span class="text-muted fst-italic">Harga belum diset</span>' ?>
          </span>
          <span class="text-muted fs-7">/ bulan</span>
        </div>

        <!-- Ukuran & Lantai -->
        <div class="d-flex gap-3 mb-2 fs-7 text-muted">
          <?php if ($r['ukuran']): ?>
          <span><i class="bi bi-aspect-ratio me-1"></i><?= h($r['ukuran']) ?></span>
          <?php endif; ?>
          <span><i class="bi bi-layers me-1"></i>Lantai <?= (int)$r['lantai'] ?></span>
        </div>

        <!-- Penghuni -->
        <div class="mb-2 fs-7">
          <i class="bi bi-person me-1 text-muted"></i>
          <?php if ($r['penghuni']): ?>
            <a href="/pages/tenants.php?id=<?= $r['tenant_id'] ?>" class="text-decoration-none fw-semibold">
              <?= h($r['penghuni']) ?>
            </a>
          <?php else: ?>
            <span class="text-muted fst-italic">Belum ada penghuni</span>
          <?php endif; ?>
        </div>

        <!-- No. Token Listrik -->
        <div class="fs-7 mb-2">
          <i class="bi bi-lightning-charge me-1 text-warning"></i>
          <?php if ($r['no_pelanggan_listrik']): ?>
            <span class="font-monospace"><?= h($r['no_pelanggan_listrik']) ?></span>
            <span class="text-muted ms-1">(ID PLN)</span>
          <?php else: ?>
            <span class="text-muted fst-italic">No. PLN belum diset</span>
          <?php endif; ?>
        </div>

        <!-- Status video -->
        <div class="fs-7">
          <i class="bi bi-youtube me-1 text-danger"></i>
          <?php if ($ytId): ?>
            <a href="#" onclick="playVideo('<?= $ytId ?>','<?= h($r['nomor_kamar']) ?>');return false;"
               class="text-decoration-none text-danger fw-semibold">▶ Lihat Video</a>
            <span class="text-muted ms-1 fs-7">
              | <a href="/kamar.php?nama=<?= urlencode($r['nomor_kamar']) ?>" target="_blank"
                   class="text-muted">🔗 Halaman publik</a>
            </span>
          <?php else: ?>
            <span class="text-muted fst-italic">Video belum diset</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Footer aksi -->
      <div class="card-footer bg-white border-top d-flex gap-2 py-2">
        <button class="btn btn-sm btn-outline-primary flex-fill"
                onclick="editRoom(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
          <i class="bi bi-pencil me-1"></i>Edit
        </button>
        <form method="POST" onsubmit="return confirm('Hapus kamar <?= h($r['nomor_kamar']) ?>?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"
                  <?= $r['penghuni'] ? 'disabled title="Ada penghuni aktif"' : '' ?>>
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── Modal Tambah / Edit ───────────────────────────────────────────────── -->
<div class="modal fade" id="modalRoom" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="formRoom">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="id"     id="formId"     value="">

        <div class="modal-header">
          <h6 class="modal-title fw-bold" id="modalTitle">Tambah Kamar</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Nama Kamar <span class="text-danger">*</span></label>
              <input type="text" name="nomor_kamar" id="fNamaKamar" class="form-control"
                     placeholder="Contoh: Aurelia" required>
            </div>

            <div class="col-6">
              <label class="form-label fw-semibold">Ukuran</label>
              <input type="text" name="ukuran" id="fUkuran" class="form-control" placeholder="3x3.3m">
            </div>

            <div class="col-6">
              <label class="form-label fw-semibold">Lantai</label>
              <input type="number" name="lantai" id="fLantai" class="form-control" value="1" min="1" max="10">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Harga Sewa / Bulan</label>
              <div class="input-group">
                <span class="input-group-text text-muted">Rp</span>
                <input type="text" name="harga_sewa" id="fHarga" class="form-control"
                       placeholder="0" inputmode="numeric">
              </div>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">
                <i class="bi bi-lightning-charge text-warning me-1"></i>No. ID Pelanggan PLN (Token Listrik)
              </label>
              <input type="text" name="no_pelanggan_listrik" id="fNoPLN" class="form-control font-monospace"
                     placeholder="Contoh: 5217xxxxxxxxxx" maxlength="20">
              <div class="form-text">Nomor ini akan ditampilkan di pesan WA tagihan.</div>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">
                <i class="bi bi-youtube text-danger me-1"></i>URL Video YouTube / Shorts
              </label>
              <input type="url" name="youtube_url" id="fYoutubeUrl" class="form-control"
                     placeholder="https://youtube.com/shorts/VIDEO_ID atau https://youtu.be/VIDEO_ID">
              <div class="form-text">Mendukung format: youtube.com/watch, youtu.be, youtube.com/shorts</div>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" id="fStatus" class="form-select">
                <option value="tersedia">Tersedia</option>
                <option value="terisi">Terisi</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Fasilitas</label>
              <textarea name="fasilitas" id="fFasilitas" class="form-control" rows="3"
                        placeholder="AC Sharp 0.5PK, kasur+sprei, ..."></textarea>
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

<!-- Modal Video -->
<div class="modal fade" id="modalVideo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content bg-black border-0 rounded-4 overflow-hidden">
      <div class="modal-header border-0 pb-0 px-3 pt-3">
        <span class="text-white fw-bold" id="videoModalTitle"></span>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="ratio" style="--bs-aspect-ratio: 177.78%">
          <iframe id="videoIframe" src="" allowfullscreen
                  allow="autoplay; encrypted-media; picture-in-picture"></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
function resetForm() {
  document.getElementById('modalTitle').textContent  = 'Tambah Kamar';
  document.getElementById('btnSubmit').textContent   = 'Simpan';
  document.getElementById('formAction').value        = 'add';
  document.getElementById('formId').value            = '';
  document.getElementById('fNamaKamar').value        = '';
  document.getElementById('fUkuran').value           = '';
  document.getElementById('fLantai').value           = '1';
  document.getElementById('fHarga').value            = '';
  document.getElementById('fNoPLN').value            = '';
  document.getElementById('fYoutubeUrl').value       = '';
  document.getElementById('fStatus').value           = 'tersedia';
  document.getElementById('fFasilitas').value        = '';
}

function editRoom(r) {
  document.getElementById('modalTitle').textContent  = 'Edit Kamar ' + r.nomor_kamar;
  document.getElementById('btnSubmit').textContent   = 'Perbarui';
  document.getElementById('formAction').value        = 'edit';
  document.getElementById('formId').value            = r.id;
  document.getElementById('fNamaKamar').value        = r.nomor_kamar;
  document.getElementById('fUkuran').value           = r.ukuran        ?? '';
  document.getElementById('fLantai').value           = r.lantai        ?? 1;
  document.getElementById('fHarga').value            = r.harga_sewa    ?? 0;
  document.getElementById('fNoPLN').value            = r.no_pelanggan_listrik ?? '';
  document.getElementById('fYoutubeUrl').value       = r.youtube_url   ?? '';
  document.getElementById('fStatus').value           = r.status;
  document.getElementById('fFasilitas').value        = r.fasilitas     ?? '';
  new bootstrap.Modal(document.getElementById('modalRoom')).show();
}

function playVideo(ytId, nama) {
  document.getElementById('videoModalTitle').textContent = 'Kamar ' + nama;
  document.getElementById('videoIframe').src =
    'https://www.youtube.com/embed/' + ytId + '?autoplay=1&rel=0';
  new bootstrap.Modal(document.getElementById('modalVideo')).show();
}

// Stop video saat modal ditutup
document.getElementById('modalVideo').addEventListener('hidden.bs.modal', () => {
  document.getElementById('videoIframe').src = '';
});
</script>

<style>
.room-thumb-wrap { position: relative; overflow: hidden; max-height: 180px; }
.room-thumb-img  { width: 100%; height: 180px; object-fit: cover; display: block; }
.room-play-btn   {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,.35); color: #fff; font-size: 3rem; text-decoration: none;
  transition: background .2s;
}
.room-play-btn:hover { background: rgba(0,0,0,.55); color: #fff; }
</style>
