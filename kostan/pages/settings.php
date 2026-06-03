<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Pengaturan';

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Simpan pengaturan umum
    if ($action === 'save_settings') {
        $keys = ['nama_kost','nama_pemilik','nama_bank','no_rekening','tgl_jatuh_tempo','alamat'];
        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            dbExecute('INSERT INTO settings (key_name, value) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE value=?', [$key, $val, $val]);
        }
        $flash = ['type' => 'success', 'msg' => 'Pengaturan berhasil disimpan.'];
    }

    // Simpan template WA
    if ($action === 'save_template') {
        $tipe = in_array($_POST['tipe'] ?? '', ['tagihan','reminder','reminder_partial','kamar_kosong'])
                ? $_POST['tipe'] : 'tagihan';
        $teks = $_POST['template_text'] ?? '';
        dbExecute('INSERT INTO wa_templates (tipe, template_text) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE template_text=?', [$tipe, $teks, $teks]);
        $flash = ['type' => 'success', 'msg' => "Template WA <strong>{$tipe}</strong> disimpan."];
    }

    // Ganti password admin
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new1    = $_POST['new_password']     ?? '';
        $new2    = $_POST['confirm_password'] ?? '';

        $admin = dbFetchOne('SELECT id, password FROM admin WHERE id=?', [$_SESSION['admin_id']]);

        if (!password_verify($current, $admin['password'])) {
            $flash = ['type' => 'danger', 'msg' => 'Password lama salah.'];
        } elseif (strlen($new1) < 6) {
            $flash = ['type' => 'danger', 'msg' => 'Password baru minimal 6 karakter.'];
        } elseif ($new1 !== $new2) {
            $flash = ['type' => 'danger', 'msg' => 'Konfirmasi password tidak cocok.'];
        } else {
            dbExecute('UPDATE admin SET password=? WHERE id=?',
                      [password_hash($new1, PASSWORD_DEFAULT), $admin['id']]);
            $flash = ['type' => 'success', 'msg' => 'Password berhasil diubah.'];
        }
    }
}

// ─── Load current values ──────────────────────────────────────────────────────

$s = [];
$rows = dbFetchAll('SELECT key_name, value FROM settings');
foreach ($rows as $r) $s[$r['key_name']] = $r['value'];

$tplTagihan       = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe="tagihan"');
$tplReminder      = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe="reminder"');
$tplReminderParsial = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe="reminder_partial"');
$tplKamarKosong   = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe="kamar_kosong"');

require __DIR__ . '/../includes/header.php';
?>

<?php if ($flash['msg']): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-3" role="alert">
  <?= $flash['msg'] ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

  <!-- ─── Pengaturan Umum ─────────────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-gear me-2"></i>Pengaturan Umum</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_settings">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Nama Kost</label>
              <input type="text" name="nama_kost" class="form-control"
                     value="<?= h($s['nama_kost'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Alamat</label>
              <input type="text" name="alamat" class="form-control"
                     value="<?= h($s['alamat'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nama Pemilik</label>
              <input type="text" name="nama_pemilik" class="form-control"
                     value="<?= h($s['nama_pemilik'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tgl Jatuh Tempo (setiap tgl)</label>
              <input type="number" name="tgl_jatuh_tempo" class="form-control" min="1" max="28"
                     value="<?= h($s['tgl_jatuh_tempo'] ?? '5') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Bank</label>
              <input type="text" name="nama_bank" class="form-control"
                     value="<?= h($s['nama_bank'] ?? '') ?>" placeholder="BCA / BRI / Mandiri">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">No. Rekening</label>
              <input type="text" name="no_rekening" class="form-control font-monospace"
                     value="<?= h($s['no_rekening'] ?? '') ?>">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Simpan Pengaturan
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ─── Ganti Password ──────────────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Ganti Password Admin</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Password Lama</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Password Baru</label>
              <input type="password" name="new_password" class="form-control"
                     minlength="6" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-warning">
                <i class="bi bi-key me-1"></i>Ganti Password
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Variabel template -->
    <div class="card mt-3">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>Variabel Template WA</div>
      <div class="card-body">
        <div class="row g-1 fs-7">
          <?php foreach ([
            '{{nama}}','{{kamar}}','{{bulan}}','{{sewa}}','{{baris_lainnya}}',
            '{{total}}','{{jatuh_tempo}}','{{nama_bank}}','{{no_rekening}}',
            '{{nama_pemilik}}','{{no_pelanggan}}'
          ] as $var): ?>
          <div class="col-6">
            <code class="text-primary"><?= h($var) ?></code>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-2 text-muted fs-7">
          <code>{{baris_lainnya}}</code> otomatis kosong jika tidak ada biaya tambahan.
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Template WA Tagihan ─────────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-whatsapp text-success me-2"></i>Template WA — Tagihan</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="tipe"   value="tagihan">
          <textarea name="template_text" class="form-control font-monospace"
                    rows="16" style="font-size:.82rem"><?= h($tplTagihan['template_text'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-success mt-3">
            <i class="bi bi-save me-1"></i>Simpan Template Tagihan
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ─── Template WA Reminder ────────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-whatsapp text-warning me-2"></i>Template WA — Reminder Tagihan</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="tipe"   value="reminder">
          <textarea name="template_text" class="form-control font-monospace"
                    rows="16" style="font-size:.82rem"><?= h($tplReminder['template_text'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-warning mt-3">
            <i class="bi bi-save me-1"></i>Simpan Template Reminder
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ─── Template WA Reminder Parsial ──────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-whatsapp text-warning me-2"></i>Template WA — Reminder Parsial
        <span class="badge bg-warning text-dark ms-2 fs-7">Sudah bayar sebagian</span>
      </div>
      <div class="card-body">
        <div class="alert alert-info py-2 fs-7 mb-3">
          Digunakan saat H+1 jatuh tempo dan status tagihan masih <strong>Parsial</strong>.
          Variabel tambahan: <code>{{paid_amount}}</code> <code>{{sisa}}</code> <code>{{kamar}}</code>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="tipe"   value="reminder_partial">
          <textarea name="template_text" class="form-control font-monospace"
                    rows="14" style="font-size:.82rem"><?= h($tplReminderParsial['template_text'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-warning mt-3">
            <i class="bi bi-save me-1"></i>Simpan Template Reminder Parsial
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ─── Template WA Kamar Kosong ────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-door-open-fill text-primary me-2"></i>Template WA — Kamar Kosong
        <span class="badge bg-primary ms-2 fs-7">Kandidat Waitlist</span>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="tipe"   value="kamar_kosong">
          <textarea name="template_text" class="form-control font-monospace"
                    rows="16" style="font-size:.82rem"><?= h($tplKamarKosong['template_text'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-primary mt-3">
            <i class="bi bi-save me-1"></i>Simpan Template Kamar Kosong
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ─── Variabel template kamar_kosong ──────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>Variabel Template — Kamar Kosong</div>
      <div class="card-body">
        <div class="row g-2 fs-7">
          <?php
          $varsKamar = [
            '{{nama}}'   => 'Nama kandidat',
            '{{kamar}}'  => 'Nama kamar (Aurelia, Bella, dst)',
            '{{ukuran}}' => 'Ukuran kamar (3x3.8m)',
            '{{harga}}'  => 'Harga sewa (Rp X.XXX.XXX)',
          ];
          foreach ($varsKamar as $var => $desc): ?>
          <div class="col-12 d-flex align-items-start gap-2">
            <code class="text-primary flex-shrink-0"><?= h($var) ?></code>
            <span class="text-muted"><?= $desc ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <hr class="my-3">
        <p class="text-muted fs-7 mb-0">
          Template ini digunakan saat admin mengirim notifikasi
          kamar tersedia ke kandidat di halaman
          <a href="/pages/candidates.php">Kandidat</a>.
        </p>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
