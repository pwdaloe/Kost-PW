<?php
/**
 * Cron: Deteksi tagihan H-3 jatuh tempo, masukkan ke wa_queue
 * Jadwal cPanel: 0 8 * * * php /home/USERNAME/public_html/kostan/cron/check_billing.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../config/db.php';

$log  = [];
$tgl3 = date('Y-m-d', strtotime('+3 days'));

// Tagihan jatuh tempo 3 hari ke depan yang belum masuk antrian WA
$targets = dbFetchAll("
    SELECT b.id AS bill_id, b.total, t.nama, t.no_wa, r.nomor_kamar, r.no_pelanggan_listrik
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    WHERE b.tgl_jatuh_tempo = ?
      AND b.status_bayar = 'unpaid'
      AND b.id NOT IN (SELECT bill_id FROM wa_queue WHERE tipe = 'tagihan')
", [$tgl3]);

$tmpl = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe = "tagihan"');
$teks = $tmpl['template_text'] ?? '';

foreach ($targets as $t) {
    $bill = dbFetchOne('SELECT * FROM bills WHERE id=?', [$t['bill_id']]);

    $barisLainnya = '';
    if ((int)$bill['lainnya'] > 0) {
        $ket = $bill['keterangan_lainnya'] ? " ({$bill['keterangan_lainnya']})" : '';
        $barisLainnya = "\n➕ Lainnya{$ket}  : " . formatRupiah((int)$bill['lainnya']);
    }

    $pesan = str_replace(
        ['{{nama}}','{{kamar}}','{{bulan}}','{{sewa}}','{{baris_lainnya}}',
         '{{total}}','{{jatuh_tempo}}','{{nama_bank}}','{{no_rekening}}',
         '{{nama_pemilik}}','{{no_pelanggan}}'],
        [$t['nama'], $t['nomor_kamar'], formatBulan($bill['bulan']),
         formatRupiah((int)$bill['sewa']), $barisLainnya,
         formatRupiah((int)$bill['total']), formatTanggal($bill['tgl_jatuh_tempo']),
         getSetting('nama_bank'), getSetting('no_rekening'),
         getSetting('nama_pemilik'),
         $t['no_pelanggan_listrik'] ?: 'Belum diset'],
        $teks
    );

    dbExecute('INSERT INTO wa_queue (bill_id, tipe, pesan_text, status) VALUES (?,?,?,?)',
              [$t['bill_id'], 'tagihan', $pesan, 'pending']);

    $log[] = "Antrian dibuat: {$t['nama']} — Kamar {$t['nomor_kamar']} — " . formatRupiah((int)$t['total']);
}

$n = count($log);
echo date('[Y-m-d H:i:s]') . " check_billing selesai: {$n} antrian dibuat.\n";
foreach ($log as $l) echo "  → {$l}\n";
