<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$billId   = (int)($_POST['bill_id'] ?? 0);
$tglBayar = $_POST['tgl_bayar'] ?? date('Y-m-d');
$nominal  = (int) str_replace(['.', ','], '', $_POST['nominal'] ?? '0');
$metode   = in_array($_POST['metode'] ?? '', ['transfer','tunai','qris'])
            ? $_POST['metode'] : 'transfer';
$catatan  = trim($_POST['catatan'] ?? '');

if (!$billId || $nominal <= 0) {
    echo json_encode(['error' => 'Data tidak lengkap']); exit;
}

$bill = dbFetchOne('SELECT id, total, paid_amount, status_bayar FROM bills WHERE id = ?', [$billId]);
if (!$bill) {
    echo json_encode(['error' => 'Tagihan tidak ditemukan']); exit;
}
if ($bill['status_bayar'] === 'paid') {
    echo json_encode(['error' => 'Tagihan sudah lunas']); exit;
}

$sisa = (int)$bill['total'] - (int)$bill['paid_amount'];

// Validasi: tidak boleh lebih dari sisa (no overpay)
if ($nominal > $sisa) {
    echo json_encode([
        'error' => 'Jumlah pembayaran ('. formatRupiah($nominal) .') melebihi sisa tagihan ('.
                   formatRupiah($sisa) .'). Overpayment belum didukung.'
    ]); exit;
}

// Hitung total paid setelah payment ini
$newPaidAmount = (int)$bill['paid_amount'] + $nominal;
$newStatus     = $newPaidAmount >= (int)$bill['total'] ? 'paid' : 'partial';
$newTglBayar   = $newStatus === 'paid' ? $tglBayar : null;

// Update bills
dbExecute('
    UPDATE bills
    SET paid_amount = ?, status_bayar = ?, tgl_bayar = ?
    WHERE id = ?
', [$newPaidAmount, $newStatus, $newTglBayar, $billId]);

// Simpan record pembayaran
dbExecute('
    INSERT INTO payments (bill_id, tgl_bayar, nominal, metode, catatan, confirmed_by)
    VALUES (?, ?, ?, ?, ?, ?)
', [$billId, $tglBayar, $nominal, $metode, $catatan ?: null, $_SESSION['admin_username']]);

echo json_encode([
    'success'       => true,
    'new_status'    => $newStatus,
    'new_paid'      => $newPaidAmount,
    'sisa'          => (int)$bill['total'] - $newPaidAmount,
    'is_fully_paid' => $newStatus === 'paid',
]);
