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

$bill = dbFetchOne('SELECT id, total, status_bayar FROM bills WHERE id = ?', [$billId]);
if (!$bill) {
    echo json_encode(['error' => 'Tagihan tidak ditemukan']); exit;
}
if ($bill['status_bayar'] === 'paid') {
    echo json_encode(['error' => 'Tagihan sudah lunas']); exit;
}

dbExecute('UPDATE bills SET status_bayar="paid", tgl_bayar=? WHERE id=?', [$tglBayar, $billId]);

dbExecute('
    INSERT INTO payments (bill_id, tgl_bayar, nominal, metode, catatan, confirmed_by)
    VALUES (?, ?, ?, ?, ?, ?)
', [$billId, $tglBayar, $nominal, $metode, $catatan ?: null, $_SESSION['admin_username']]);

echo json_encode(['success' => true]);
