<?php
require_once __DIR__ . '/config/db.php';

// ─── POST: Pendaftaran calon penghuni ─────────────────────────────────────────

// PRG pattern: sukses ditandai via GET param, bukan POST state
$success = isset($_GET['sukses']);
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']    ?? '');
    $noWa     = trim($_POST['no_wa']   ?? '');
    $kamar    = trim($_POST['kamar_diminati'] ?? '');
    $tgl      = $_POST['tgl_masuk_rencana'] ?? '';
    $catatan  = trim($_POST['catatan'] ?? '');
    $honeypot = $_POST['website'] ?? '';

    if ($honeypot !== '') {
        // Bot — redirect diam-diam ke sukses
        header('Location: /index.php?sukses=1#daftar'); exit;
    }

    if ($nama === '')  $errors[] = 'Nama wajib diisi.';
    if ($noWa === '')  $errors[] = 'No. WhatsApp wajib diisi.';
    if (!preg_match('/^[0-9+\s\-]{8,20}$/', $noWa)) $errors[] = 'Format No. WA tidak valid.';

    if (empty($errors)) {
        dbExecute('
            INSERT INTO candidates (nama, no_wa, kamar_diminati, tgl_masuk_rencana, catatan, sumber)
            VALUES (?, ?, ?, ?, ?, "form_web")
        ', [$nama, $noWa, $kamar ?: null, $tgl ?: null, $catatan ?: null]);
        // Redirect GET — mencegah resubmit & "Daftar Lagi" bisa reload bersih
        header('Location: /index.php?sukses=1#daftar'); exit;
    }
}

$kostName  = getSetting('nama_kost')  ?: 'Kost PW';
$alamat    = getSetting('alamat')     ?: 'Balai Pustaka-Rawamangun, Jakarta Timur';
$fasilUmum = getSetting('fasilitas_umum') ?: '';

$rooms = dbFetchAll('SELECT nomor_kamar, ukuran, harga_sewa, youtube_url, status FROM rooms ORDER BY id');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($kostName) ?> — Kamar Kost Eksklusif Balai Pustaka</title>
  <meta name="description" content="Kamar kost eksklusif di <?= h($alamat) ?>. Fasilitas lengkap, AC, kamar mandi dalam, WiFi cepat.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --navy: #003087; --blue: #1a56db; }

    body { font-family: 'Segoe UI', system-ui, sans-serif; }

    /* Hero */
    .hero {
      background: linear-gradient(135deg, #0a1628 0%, var(--navy) 60%, #1a2e5a 100%);
      color: #fff;
      padding: 4rem 0 3rem;
      text-align: center;
    }
    .hero img.logo { width: 110px; height: 110px; object-fit: contain; margin-bottom: 1.25rem;
                     filter: drop-shadow(0 4px 12px rgba(0,0,0,.4)); }
    .hero .logo-placeholder {
      width: 110px; height: 110px; border-radius: 50%; background: var(--blue);
      display: flex; align-items: center; justify-content: center;
      font-size: 3rem; margin: 0 auto 1.25rem;
    }
    .hero h1 { font-size: 2rem; font-weight: 800; letter-spacing: .3px; }
    .hero p  { opacity: .8; max-width: 520px; margin: .5rem auto 0; }

    /* Kamar cards */
    .room-card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08);
                 transition: transform .2s, box-shadow .2s; overflow: hidden; }
    .room-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.14); }

    .room-thumb-wrap { position: relative; overflow: hidden; }
    .room-thumb-img  { width: 100%; height: 220px; object-fit: cover; display: block;
                       transition: transform .3s; }
    .room-card:hover .room-thumb-img { transform: scale(1.03); }

    .room-play-btn {
      position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      background: rgba(0,0,0,.25); border: none; cursor: pointer;
      transition: background .2s;
    }
    .room-play-btn:hover { background: rgba(0,0,0,.45); }
    .play-circle {
      width: 60px; height: 60px; border-radius: 50%;
      background: rgba(255,255,255,.9); display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; color: var(--navy);
      box-shadow: 0 2px 12px rgba(0,0,0,.3);
      transition: transform .2s;
    }
    .room-play-btn:hover .play-circle { transform: scale(1.1); }

    /* Form section */
    .form-section { background: #f8f9fa; padding: 3.5rem 0; }
    .form-card { max-width: 580px; margin: 0 auto; border: none; border-radius: 14px;
                 box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    .form-card .card-header {
      background: var(--navy); color: #fff; border-radius: 14px 14px 0 0;
      padding: 1.5rem; text-align: center;
    }

    /* Fasilitas */
    .fasilitas-item { display: flex; align-items: flex-start; gap: .5rem; margin-bottom: .6rem; }
    .fasilitas-item i { color: var(--blue); margin-top: 2px; flex-shrink: 0; }

    section { padding: 3rem 0; }
    .section-title { font-size: 1.5rem; font-weight: 800; color: #1a2332; margin-bottom: 1.5rem; }
  </style>
</head>
<body>

<!-- ─── Hero ─────────────────────────────────────────────────────────────── -->
<section class="hero">
  <div class="container">
    <?php $logoPath = __DIR__ . '/assets/logo.png'; ?>
    <?php if (file_exists($logoPath)): ?>
      <img src="/assets/logo.png" alt="Logo <?= h($kostName) ?>" class="logo">
    <?php else: ?>
      <div class="logo-placeholder">🏠</div>
    <?php endif; ?>
    <h1><?= h($kostName) ?></h1>
    <p><i class="bi bi-geo-alt-fill me-1"></i><?= h($alamat) ?></p>
    <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
      <a href="#kamar"   class="btn btn-light fw-semibold">Lihat Kamar</a>
      <a href="#daftar"  class="btn btn-outline-light fw-semibold">Daftar Sekarang</a>
    </div>
  </div>
</section>

<!-- ─── Fasilitas Umum ────────────────────────────────────────────────────── -->
<section>
  <div class="container">
    <h2 class="section-title text-center">Fasilitas Kamar</h2>
    <div class="row g-3 justify-content-center">
      <?php
      $fasKamar = [
        ['bi-snow',              'AC Sharp 0.5 PK'],
        ['bi-person-workspace',  'Kasur, Bantal & Sprei'],
        ['bi-box-seam',          'Lemari + Meja Rias (Informa)'],
        ['bi-droplet-half',      'Kamar Mandi Dalam'],
        ['bi-thermometer-sun',   'Water Heater Ariston'],
        ['bi-door-closed',       'Closet TOTO'],
        ['bi-wifi',              'WiFi Biznet 300 Mbps'],
        ['bi-lightning-charge',  'Listrik Token Mandiri'],
      ];
      foreach ($fasKamar as [$icon, $label]): ?>
      <div class="col-6 col-md-3">
        <div class="text-center p-3 bg-white rounded shadow-sm h-100">
          <i class="bi <?= $icon ?> fs-2 text-primary mb-2 d-block"></i>
          <div class="fs-7 fw-semibold"><?= $label ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($fasilUmum): ?>
    <div class="mt-4 text-center">
      <h5 class="fw-bold mb-2">Fasilitas Bersama</h5>
      <p class="text-muted"><?= h($fasilUmum) ?></p>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ─── Kamar ─────────────────────────────────────────────────────────────── -->
<section id="kamar" style="background:#f8f9fa; padding: 3rem 0">
  <div class="container">
    <h2 class="section-title text-center">Kamar Kost PW</h2>
    <p class="text-center text-muted mb-4">
      Mulai dari <strong class="text-primary">Rp 2.000.000</strong> / bulan
      &nbsp;·&nbsp; Sudah termasuk air &nbsp;·&nbsp; Listrik token mandiri
    </p>
    <div class="row g-4 justify-content-center">
      <?php foreach ($rooms as $r):
        $slug      = strtolower($r['nomor_kamar']);
        $thumbFile = __DIR__ . '/assets/kamar/' . $slug . '.jpg';
        $thumbUrl  = file_exists($thumbFile) ? '/assets/kamar/' . $slug . '.jpg' : null;
        $ytId      = extractYoutubeId($r['youtube_url'] ?? '');
        $autoThumb = $ytId ? 'https://img.youtube.com/vi/' . $ytId . '/hqdefault.jpg' : null;
        $imgSrc    = $thumbUrl ?? $autoThumb;
      ?>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="room-card card h-100">

          <!-- Thumbnail / play area -->
          <div class="room-thumb-wrap position-relative">
            <?php if ($imgSrc): ?>
              <img src="<?= h($imgSrc) ?>" alt="Kamar <?= h($r['nomor_kamar']) ?>"
                   class="room-thumb-img">
            <?php else: ?>
              <div class="room-thumb-img d-flex align-items-center justify-content-center bg-light">
                <i class="bi bi-door-open text-secondary" style="font-size:3.5rem"></i>
              </div>
            <?php endif; ?>

            <!-- Status badge overlay -->
            <span class="position-absolute top-0 end-0 m-2
                         badge <?= $r['status']==='tersedia' ? 'bg-success' : 'bg-danger' ?> rounded-pill">
              <?= $r['status']==='tersedia' ? 'Tersedia' : 'Sedang Terisi' ?>
            </span>

            <!-- Play button overlay -->
            <?php if ($ytId): ?>
            <button class="room-play-btn"
                    onclick="playVideo('<?= $ytId ?>','<?= h($r['nomor_kamar']) ?>')">
              <span class="play-circle"><i class="bi bi-play-fill"></i></span>
            </button>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="card-body pb-2">
            <h6 class="fw-bold mb-1"><?= h($r['nomor_kamar']) ?></h6>
            <div class="d-flex gap-3 text-muted fs-7 mb-2">
              <?php if ($r['ukuran']): ?>
              <span><i class="bi bi-aspect-ratio me-1"></i><?= h($r['ukuran']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <!-- CTA buttons -->
          <div class="card-footer bg-white border-top d-flex gap-2 py-2">
            <a href="/kamar.php?nama=<?= urlencode($r['nomor_kamar']) ?>"
               class="btn btn-sm btn-outline-primary flex-fill">
              <i class="bi bi-info-circle me-1"></i>Lihat Detail
            </a>
            <a href="#daftar?kamar=<?= urlencode($r['nomor_kamar']) ?>"
               class="btn btn-sm btn-primary flex-fill"
               onclick="preselectRoom('<?= h($r['nomor_kamar']) ?>')">
              <i class="bi bi-send me-1"></i>Daftar
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <p class="text-center text-muted mt-4 fs-7">
      <i class="bi bi-info-circle me-1"></i>
      Kamar terisi? Daftarkan diri ke waiting list — kami hubungi saat ada kamar kosong.
    </p>
  </div>
</section>

<!-- ─── Form Pendaftaran ──────────────────────────────────────────────────── -->
<section id="daftar" class="form-section">
  <div class="container">

    <?php if ($success): ?>
    <div class="form-card card mx-auto">
      <div class="card-header">
        <h5 class="mb-0 fw-bold">Pendaftaran Diterima!</h5>
      </div>
      <div class="card-body text-center py-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h5 class="mt-3 fw-bold">Terima kasih!</h5>
        <p class="text-muted">
          Data Anda sudah kami terima. Kami akan menghubungi Anda via WhatsApp
          secepatnya untuk info lanjut.
        </p>
        <a href="/index.php#daftar" class="btn btn-primary">
          <i class="bi bi-arrow-left me-1"></i>Daftar Lagi
        </a>
      </div>
    </div>

    <?php else: ?>
    <div class="form-card card">
      <div class="card-header">
        <h5 class="mb-1 fw-bold">Daftar / Waiting List</h5>
        <p class="mb-0 opacity-75 small">Isi form berikut, kami akan hubungi via WA</p>
      </div>
      <div class="card-body p-4">

        <?php if ($errors): ?>
        <div class="alert alert-danger py-2">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="#daftar">
          <!-- Honeypot -->
          <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama" class="form-control"
                     value="<?= h($_POST['nama'] ?? '') ?>"
                     placeholder="Nama lengkap Anda" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">No. WhatsApp <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text text-muted">+62</span>
                <input type="tel" name="no_wa" class="form-control"
                       value="<?= h($_POST['no_wa'] ?? '') ?>"
                       placeholder="08xxxxxxxxxx" required>
              </div>
              <div class="form-text">Kami hanya menghubungi via WhatsApp, tidak akan spam.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Kamar yang Diminati</label>
              <select name="kamar_diminati" class="form-select">
                <option value="">— Belum tahu —</option>
                <?php foreach ($rooms as $r): ?>
                <option value="<?= h($r['nomor_kamar']) ?>"
                        <?= ($r['status']==='terisi' ? 'data-status="terisi"' : '') ?>
                        <?= ($_POST['kamar_diminati']??'')===$r['nomor_kamar'] ? 'selected':'' ?>>
                  <?= h($r['nomor_kamar']) ?> <?= $r['status']==='terisi' ? '(Waiting List)' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Rencana Masuk</label>
              <input type="date" name="tgl_masuk_rencana" class="form-control"
                     value="<?= h($_POST['tgl_masuk_rencana'] ?? '') ?>"
                     min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Pesan / Pertanyaan</label>
              <textarea name="catatan" class="form-control" rows="3"
                        placeholder="Ada yang ingin ditanyakan? Opsional."><?= h($_POST['catatan'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-send me-1"></i>Kirim Pendaftaran
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- ─── Footer ────────────────────────────────────────────────────────────── -->
<footer class="py-4 text-center" style="background:#1a2332; color:rgba(255,255,255,.6)">
  <div class="fs-7">
    <?= h($kostName) ?> &mdash; <?= h($alamat) ?>
    <span class="mx-2">|</span>
    <a href="/auth/login.php" class="text-white-50 text-decoration-none fs-7">Admin</a>
  </div>
</footer>

<!-- ─── Modal Video ────────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalVideo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content bg-black border-0 rounded-4 overflow-hidden">
      <div class="modal-header border-0 pb-1 px-3 pt-3">
        <span class="text-white fw-bold fs-6" id="videoModalTitle"></span>
        <button type="button" class="btn-close btn-close-white ms-auto"
                data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <!-- 9:16 = padding-top 177.78% (Bootstrap tidak punya class ratio-9x16) -->
        <div class="ratio" style="--bs-aspect-ratio: 177.78%">
          <iframe id="videoIframe" src="" allowfullscreen
                  allow="autoplay; encrypted-media; picture-in-picture"></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function playVideo(ytId, nama) {
  document.getElementById('videoModalTitle').textContent = 'Kamar ' + nama;
  document.getElementById('videoIframe').src =
    'https://www.youtube.com/embed/' + ytId + '?autoplay=1&rel=0&playsinline=1';
  new bootstrap.Modal(document.getElementById('modalVideo')).show();
}

document.getElementById('modalVideo').addEventListener('hidden.bs.modal', () => {
  document.getElementById('videoIframe').src = '';
});

// Pre-select kamar dari tombol Daftar di kartu kamar
function preselectRoom(nama) {
  setTimeout(() => {
    const sel = document.querySelector('select[name="kamar_diminati"]');
    if (sel) { sel.value = nama; }
    const el = document.getElementById('daftar');
    if (el) el.scrollIntoView({ behavior: 'smooth' });
  }, 50);
}

// Pre-select dari URL param ?kamar=xxx
const urlKamar = new URLSearchParams(location.search).get('kamar');
if (urlKamar) preselectRoom(urlKamar);
</script>
</body>
</html>
