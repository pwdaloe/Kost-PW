<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$billId = (int)($_GET['bill_id'] ?? 0);
if (!$billId) {
    echo json_encode(['error' => 'bill_id wajib diisi']); exit;
}

$bill = dbFetchOne('
    SELECT b.*, t.nama, t.no_wa, r.nomor_kamar, r.no_pelanggan_listrik
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    WHERE b.id = ?
', [$billId]);

if (!$bill) {
    echo json_encode(['error' => 'Tagihan tidak ditemukan']); exit;
}

$tmpl = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe = "tagihan"');
$teks = $tmpl['template_text'] ?? '';

$barisLainnya = '';
if ((int)$bill['lainnya'] > 0) {
    $ket = $bill['keterangan_lainnya'] ? " ({$bill['keterangan_lainnya']})" : '';
    $barisLainnya = "\n➕ Lainnya{$ket}  : " . formatRupiah((int)$bill['lainnya']);
}

$pesan = str_replace(
    ['{{nama}}','{{kamar}}','{{bulan}}','{{sewa}}','{{baris_lainnya}}',
     '{{total}}','{{jatuh_tempo}}','{{nama_bank}}','{{no_rekening}}',
     '{{nama_pemilik}}','{{no_pelanggan}}'],
    [$bill['nama'], $bill['nomor_kamar'], formatBulan($bill['bulan']),
     formatRupiah((int)$bill['sewa']), $barisLainnya,
     formatRupiah((int)$bill['total']), formatTanggal($bill['tgl_jatuh_tempo']),
     getSetting('nama_bank'), getSetting('no_rekening'),
     getSetting('nama_pemilik'),
     $bill['no_pelanggan_listrik'] ?: 'Belum diset'],
    $teks
);

// Normalisasi nomor WA → 628xxx
$noWa  = '62' . ltrim(preg_replace('/[^0-9]/', '', $bill['no_wa']), '0');
$waUrl = 'https://wa.me/' . $noWa . '?text=' . urlencode($pesan);

// Upsert wa_queue
$existing = dbFetchOne('SELECT id FROM wa_queue WHERE bill_id = ? AND tipe = "tagihan"', [$billId]);
if ($existing) {
    dbExecute('UPDATE wa_queue SET status="sent", pesan_text=?, sent_at=NOW() WHERE id=?',
              [$pesan, $existing['id']]);
} else {
    dbExecute('INSERT INTO wa_queue (bill_id, tipe, pesan_text, status, sent_at) VALUES (?,?,?,"sent",NOW())',
              [$billId, 'tagihan', $pesan]);
}

echo json_encode(['url' => $waUrl]);
