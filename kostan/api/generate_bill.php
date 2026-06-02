<?php
// Generate tagihan manual untuk 1 penghuni tertentu
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$tenantId = (int)($_POST['tenant_id'] ?? 0);
$bulan    = $_POST['bulan'] ?? date('Y-m-01');
$bulan    = date('Y-m-01', strtotime($bulan));

if (!$tenantId) {
    echo json_encode(['error' => 'tenant_id wajib diisi']); exit;
}

$tenant = dbFetchOne('
    SELECT t.id, t.nama, r.harga_sewa
    FROM tenants t JOIN rooms r ON t.kamar_id = r.id
    WHERE t.id = ? AND t.status = "aktif"
', [$tenantId]);

if (!$tenant) {
    echo json_encode(['error' => 'Penghuni tidak ditemukan atau tidak aktif']); exit;
}

$exists = dbFetchOne('SELECT id FROM bills WHERE tenant_id=? AND bulan=?', [$tenantId, $bulan]);
if ($exists) {
    echo json_encode(['error' => 'Tagihan bulan ini sudah ada', 'bill_id' => $exists['id']]); exit;
}

$tglHariJt     = (int) getSetting('tgl_jatuh_tempo') ?: 5;
$tglJatuhTempo = date('Y-m-' . str_pad($tglHariJt, 2, '0', STR_PAD_LEFT), strtotime($bulan));

$billId = dbExecute('
    INSERT INTO bills (tenant_id, bulan, sewa, tgl_jatuh_tempo)
    VALUES (?, ?, ?, ?)
', [$tenantId, $bulan, $tenant['harga_sewa'], $tglJatuhTempo]);

echo json_encode(['success' => true, 'bill_id' => $billId,
                  'msg' => "Tagihan {$tenant['nama']} berhasil dibuat."]);
