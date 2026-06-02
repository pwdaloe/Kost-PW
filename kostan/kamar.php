<?php
require_once __DIR__ . '/config/db.php';

$nama = trim($_GET['nama'] ?? '');
if ($nama === '') {
    header('Location: /index.php#kamar'); exit;
}

$room = dbFetchOne('SELECT * FROM rooms WHERE nomor_kamar = ?', [$nama]);
if (!$room) {
    header('Location: /index.php#kamar'); exit;
}

$ytId     = extractYoutubeId($room['youtube_url'] ?? '');
$slug     = strtolower($room['nomor_kamar']);
$thumbFile = __DIR__ . '/assets/kamar/' . $slug . '.jpg';
$thumbUrl  = file_exists($thumbFile) ? '/assets/kamar/' . $slug . '.jpg' : null;
$autoThumb = $ytId ? 'https://img.youtube.com/vi/' . $ytId . '/hqdefault.jpg' : null;
$imgSrc    = $thumbUrl ?? $autoThumb;

$kostName = getSetting('nama_kost')  ?: 'Kost PW';
$alamat   = getSetting('alamat')     ?: 'Balai Pustaka-Rawamangun, Jakarta Timur';

$fasilitas = $room['fasilitas']
    ? array_map('trim', explode(',', $room['fasilitas']))
    : [];

// Fasilitas umum per kamar (selalu ada)
$fasDefault = [
    ['bi-snow',             'AC Sharp 0.5 PK'],
    ['bi-person-workspace', 'Kasur, Bantal & Sprei'],
    ['bi-box-seam',         'Lemari + Meja Rias (Informa)'],
    ['bi-droplet-half',     'Kamar Mandi Dalam'],
    ['bi-thermometer-sun',  'Water Heater Ariston'],
    ['bi-door-closed',      'Closet TOTO'],
    ['bi-wifi',             'WiFi Biznet 300 Mbps'],
    ['bi-lightning-charge', 'Listrik Token Mandiri'],
];

$shareUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://'
          . $_SERVER['HTTP_HOST'] . '/kamar.php?nama=' . urlencode($room['nomor_kamar']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kamar <?= h($room['nomor_kamar']) ?> — <?= h($kostName) ?></title>
  <meta name="description" content="Kamar <?= h($room['nomor_kamar']) ?> di <?= h($kostName) ?>. <?= h($alamat) ?>. Fasilitas lengkap, AC, kamar mandi dalam, WiFi cepat.">

  <!-- Open Graph untuk preview saat share WA/IG -->
  <meta property="og:title"       content="Kamar <?= h($room['nomor_kamar']) ?> — <?= h($kostName) ?>">
  <meta property="og:description" content="Kamar eksklusif di <?= h($alamat) ?>. AC, kamar mandi dalam, WiFi 300Mbps.">
  <?php if ($imgSrc): ?>
  <meta property="og:image"       content="<?= h($imgSrc) ?>">
  <?php endif; ?>
  <meta property="og:url"         content="<?= h($shareUrl) ?>">
  <meta property="og:type"        content="website">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --navy: #003087; --blue: #1a56db; }
    body  { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8f9fa; }

    .top-bar {
      background: var(--navy); color: #fff; padding: .75rem 1rem;
      display: flex; align-items: center; gap: .75rem;
    }
    .top-bar a { color: rgba(255,255,255,.75); text-decoration: none; font-size: .9rem; }
    .top-bar a:hover { color: #fff; }

    .video-wrap { position: relative; background: #000; border-radius: 12px; overflow: hidden; }
    .video-wrap .ratio { border-radius: 12px; }

    .thumb-wrap { position: relative; border-radius: 12px; overflow: hidden; cursor: pointer; }
    .thumb-wrap img { width: 100%; border-radius: 12px; display: block; }
    .thumb-play {
      position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      background: rgba(0,0,0,.3); transition: background .2s;
    }
    .thumb-wrap:hover .thumb-play { background: rgba(0,0,0,.5); }
    .play-btn {
      width: 72px; height: 72px; border-radius: 50%; background: rgba(255,255,255,.92);
      display: flex; align-items: center; justify-content: center; font-size: 2rem;
      color: var(--navy); box-shadow: 0 4px 16px rgba(0,0,0,.3);
    }

    .info-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07); padding: 1.5rem; }
    .status-badge-tersedia { background: #d1fae5; color: #065f46; }
    .status-badge-terisi   { background: #fee2e2; color: #991b1b; }
    .status-badge { display:inline-block; padding:.3em .9em; border-radius:20px; font-weight:700; font-size:.85rem; }

    .fas-item { display: flex; align-items: center; gap: .6rem; padding: .4rem 0;
                border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
    .fas-item:last-child { border-bottom: none; }
    .fas-item i { color: var(--blue); width: 20px; text-align: center; }

    .share-btn { border: 2px solid var(--navy); color: var(--navy); background: transparent;
                 border-radius: 8px; padding: .5rem 1rem; font-weight: 600; cursor: pointer;
                 transition: all .2s; }
    .share-btn:hover { background: var(--navy); color: #fff; }

    .cta-daftar { background: var(--navy); color: #fff; border: none; border-radius: 8px;
                  padding: .75rem 1.5rem; font-weight: 700; width: 100%; font-size: 1rem;
                  transition: background .2s; }
    .cta-daftar:hover { background: var(--blue); }
  </style>
</head>
<body>

<!-- Top Bar -->
<div class="top-bar">
  <a href="/index.php#kamar"><i class="bi bi-arrow-left me-1"></i>Semua Kamar</a>
  <span class="text-white-50 mx-1">/</span>
  <span class="fw-semibold"><?= h($room['nomor_kamar']) ?></span>
  <div class="ms-auto">
    <a href="/index.php"><strong><?= h($kostName) ?></strong></a>
  </div>
</div>

<div class="container py-4" style="max-width:960px">
  <div class="row g-4">

    <!-- ─── Kolom kiri: video / thumbnail ──────────────────────────────────── -->
    <div class="col-lg-7">

      <?php if ($ytId): ?>
        <!-- Thumbnail dengan play button → load iframe saat diklik -->
        <div class="thumb-wrap" id="thumbWrap" onclick="loadVideo()">
          <?php $displayThumb = $thumbUrl ?? 'https://img.youtube.com/vi/' . $ytId . '/maxresdefault.jpg'; ?>
          <img src="<?= h($displayThumb) ?>" alt="Kamar <?= h($room['nomor_kamar']) ?>">
          <div class="thumb-play">
            <div class="play-btn"><i class="bi bi-play-fill ms-1"></i></div>
          </div>
        </div>
        <!-- Iframe (hidden awalnya) -->
        <div class="video-wrap d-none" id="videoWrap">
          <div class="ratio" style="--bs-aspect-ratio: 177.78%">
            <iframe id="ytIframe" src="" allowfullscreen
                    allow="autoplay; encrypted-media; picture-in-picture"></iframe>
          </div>
        </div>

      <?php elseif ($imgSrc): ?>
        <img src="<?= h($imgSrc) ?>" alt="Kamar <?= h($room['nomor_kamar']) ?>"
             class="w-100 rounded-3 shadow-sm">
      <?php else: ?>
        <div class="rounded-3 bg-white shadow-sm d-flex align-items-center justify-content-center"
             style="height:320px">
          <i class="bi bi-door-open text-secondary" style="font-size:5rem"></i>
        </div>
      <?php endif; ?>

      <!-- Tombol share -->
      <div class="d-flex gap-2 mt-3">
        <button class="share-btn flex-fill" onclick="copyLink()">
          <i class="bi bi-link-45deg me-1"></i>Copy Link
        </button>
        <a href="https://wa.me/?text=<?= urlencode('Lihat kamar ' . $room['nomor_kamar'] . ' di Kost PW: ' . $shareUrl) ?>"
           target="_blank" class="btn btn-success flex-fill fw-semibold">
          <i class="bi bi-whatsapp me-1"></i>Share WA
        </a>
      </div>
      <div id="copyFeedback" class="text-success fs-7 mt-1" style="display:none">
        ✓ Link berhasil disalin!
      </div>
    </div>

    <!-- ─── Kolom kanan: info kamar ────────────────────────────────────────── -->
    <div class="col-lg-5">

      <div class="info-card mb-3">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <h3 class="fw-bold mb-0"><?= h($room['nomor_kamar']) ?></h3>
          <span class="status-badge status-badge-<?= $room['status'] ?>">
            <?= $room['status'] === 'tersedia' ? '✓ Tersedia' : 'Sedang Terisi' ?>
          </span>
        </div>

        <?php if ($room['ukuran']): ?>
        <div class="text-muted mb-2">
          <i class="bi bi-aspect-ratio me-1"></i><?= h($room['ukuran']) ?>
          &nbsp;·&nbsp;
          <i class="bi bi-layers me-1"></i>Lantai <?= (int)$room['lantai'] ?>
        </div>
        <?php endif; ?>

        <?php if ($room['harga_sewa']): ?>
        <div class="fw-bold fs-4 text-primary mb-1">
          <?= formatRupiah((int)$room['harga_sewa']) ?>
          <span class="fs-6 fw-normal text-muted">/ bulan</span>
        </div>
        <div class="text-muted fs-7 mb-3">Sudah termasuk air. Listrik token mandiri.</div>
        <?php endif; ?>

        <a href="/index.php?kamar=<?= urlencode($room['nomor_kamar']) ?>#daftar"
           class="cta-daftar d-block text-center text-decoration-none">
          <i class="bi bi-send me-1"></i>
          <?= $room['status'] === 'tersedia' ? 'Daftar Kamar Ini' : 'Masuk Waiting List' ?>
        </a>
      </div>

      <!-- Fasilitas -->
      <div class="info-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Fasilitas Kamar</h6>
        <?php foreach ($fasDefault as [$icon, $label]): ?>
        <div class="fas-item">
          <i class="bi <?= $icon ?>"></i>
          <span><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Lokasi -->
      <div class="info-card mt-3">
        <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Lokasi</h6>
        <p class="text-muted mb-1 fs-7"><?= h($alamat) ?></p>
        <p class="text-muted fs-7 mb-0">Dekat RS Persahabatan, UNJ, kawasan Pulo Gadung</p>
      </div>

    </div>
  </div>

  <!-- Kamar Lainnya -->
  <?php
  $others = dbFetchAll('SELECT nomor_kamar, ukuran, harga_sewa, status, youtube_url FROM rooms
                        WHERE nomor_kamar != ? ORDER BY id', [$room['nomor_kamar']]);
  ?>
  <?php if ($others): ?>
  <hr class="my-4">
  <h6 class="fw-bold mb-3">Kamar Lainnya</h6>
  <div class="row g-3">
    <?php foreach ($others as $o):
      $oSlug  = strtolower($o['nomor_kamar']);
      $oThumb = file_exists(__DIR__ . '/assets/kamar/' . $oSlug . '.jpg')
                ? '/assets/kamar/' . $oSlug . '.jpg' : null;
      $oYtId  = extractYoutubeId($o['youtube_url'] ?? '');
      $oImg   = $oThumb ?? ($oYtId ? 'https://img.youtube.com/vi/' . $oYtId . '/mqdefault.jpg' : null);
    ?>
    <div class="col-6 col-md-4 col-lg-2">
      <a href="/kamar.php?nama=<?= urlencode($o['nomor_kamar']) ?>" class="text-decoration-none">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:10px;overflow:hidden">
          <?php if ($oImg): ?>
          <img src="<?= h($oImg) ?>" alt="<?= h($o['nomor_kamar']) ?>"
               style="height:80px;object-fit:cover;width:100%">
          <?php else: ?>
          <div class="d-flex align-items-center justify-content-center bg-light" style="height:80px">
            <i class="bi bi-door-open text-muted fs-3"></i>
          </div>
          <?php endif; ?>
          <div class="p-2 text-center">
            <div class="fw-semibold fs-7"><?= h($o['nomor_kamar']) ?></div>
            <span class="badge <?= $o['status']==='tersedia' ? 'bg-success' : 'bg-secondary' ?> rounded-pill"
                  style="font-size:.65rem"><?= $o['status']==='tersedia' ? 'Tersedia' : 'Terisi' ?></span>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadVideo() {
  document.getElementById('thumbWrap').classList.add('d-none');
  const wrap  = document.getElementById('videoWrap');
  const frame = document.getElementById('ytIframe');
  wrap.classList.remove('d-none');
  frame.src = 'https://www.youtube.com/embed/<?= $ytId ?>?autoplay=1&rel=0&playsinline=1';
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText('<?= addslashes($shareUrl) ?>');
  } catch(e) {
    // Fallback untuk browser lama
    const ta = document.createElement('textarea');
    ta.value = '<?= addslashes($shareUrl) ?>';
    document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
  }
  const fb = document.getElementById('copyFeedback');
  fb.style.display = 'block';
  setTimeout(() => fb.style.display = 'none', 3000);
}
</script>
</body>
</html>
