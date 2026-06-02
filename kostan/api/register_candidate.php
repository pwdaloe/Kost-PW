<?php
// Endpoint alternatif untuk submit form via AJAX (opsional)
// Form di index.php sudah POST langsung, file ini bisa dipakai untuk integrasi JS di masa depan
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$nama     = trim($_POST['nama']    ?? '');
$noWa     = trim($_POST['no_wa']   ?? '');
$kamar    = trim($_POST['kamar_diminati'] ?? '');
$tgl      = $_POST['tgl_masuk_rencana'] ?? '';
$catatan  = trim($_POST['catatan'] ?? '');
$honeypot = $_POST['website'] ?? '';

if ($honeypot !== '') {
    echo json_encode(['success' => true]); exit;
}

if ($nama === '' || $noWa === '') {
    echo json_encode(['error' => 'Nama dan No. WA wajib diisi']); exit;
}
if (!preg_match('/^[0-9+\s\-]{8,20}$/', $noWa)) {
    echo json_encode(['error' => 'Format No. WA tidak valid']); exit;
}

dbExecute('
    INSERT INTO candidates (nama, no_wa, kamar_diminati, tgl_masuk_rencana, catatan, sumber)
    VALUES (?, ?, ?, ?, ?, "form_web")
', [$nama, $noWa, $kamar ?: null, $tgl ?: null, $catatan ?: null]);

echo json_encode(['success' => true]);
