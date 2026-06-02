<?php
/**
 * Generate wa.me URL untuk notifikasi kamar kosong ke kandidat
 * GET: candidate_id=X&room_id=Y
 */
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$candidateId = (int)($_GET['candidate_id'] ?? 0);
$roomId      = (int)($_GET['room_id']      ?? 0);

if (!$candidateId || !$roomId) {
    echo json_encode(['error' => 'candidate_id dan room_id wajib diisi']); exit;
}

$candidate = dbFetchOne(
    'SELECT id, nama, no_wa FROM candidates WHERE id = ?',
    [$candidateId]
);
if (!$candidate) {
    echo json_encode(['error' => 'Kandidat tidak ditemukan']); exit;
}

$room = dbFetchOne(
    'SELECT id, nomor_kamar, ukuran, harga_sewa FROM rooms WHERE id = ?',
    [$roomId]
);
if (!$room) {
    echo json_encode(['error' => 'Kamar tidak ditemukan']); exit;
}

$tmpl = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe = "kamar_kosong"');
if (!$tmpl) {
    echo json_encode(['error' => 'Template "kamar_kosong" belum ada. Buat di halaman Pengaturan.']); exit;
}

$pesan = str_replace(
    ['{{nama}}', '{{kamar}}', '{{ukuran}}', '{{harga}}'],
    [
        $candidate['nama'],
        $room['nomor_kamar'],
        $room['ukuran'] ?: '-',
        $room['harga_sewa'] ? formatRupiah((int)$room['harga_sewa']) : 'Hubungi kami',
    ],
    $tmpl['template_text']
);

$noWa  = '62' . ltrim(preg_replace('/[^0-9]/', '', $candidate['no_wa']), '0');
$waUrl = 'https://wa.me/' . $noWa . '?text=' . urlencode($pesan);

echo json_encode(['url' => $waUrl]);
