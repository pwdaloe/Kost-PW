<?php
/**
 * Cron: Kirim reminder H+1 untuk tagihan yang sudah lewat jatuh tempo & belum bayar
 * Jadwal cPanel: 0 8 * * * php /home/USERNAME/public_html/kostan/cron/send_reminders.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../config/db.php';

$log  = [];
$kemarin = date('Y-m-d', strtotime('-1 day'));

// Tagihan jatuh tempo kemarin, belum bayar, belum ada reminder di antrian
$targets = dbFetchAll("
    SELECT b.id AS bill_id, b.total, b.bulan, b.tgl_jatuh_tempo,
           t.nama, t.no_wa, r.nomor_kamar
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    WHERE b.tgl_jatuh_tempo = ?
      AND b.status_bayar = 'unpaid'
      AND b.id NOT IN (SELECT bill_id FROM wa_queue WHERE tipe = 'reminder')
", [$kemarin]);

$tmpl = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe = "reminder"');
$teks = $tmpl['template_text'] ?? '';

foreach ($targets as $t) {
    $pesan = str_replace(
        ['{{nama}}','{{bulan}}','{{total}}','{{jatuh_tempo}}'],
        [$t['nama'], formatBulan($t['bulan']),
         formatRupiah((int)$t['total']), formatTanggal($t['tgl_jatuh_tempo'])],
        $teks
    );

    dbExecute('INSERT INTO wa_queue (bill_id, tipe, pesan_text, status) VALUES (?,?,?,?)',
              [$t['bill_id'], 'reminder', $pesan, 'pending']);

    $log[] = "Reminder dibuat: {$t['nama']} — Kamar {$t['nomor_kamar']} — " . formatRupiah((int)$t['total']);
}

$n = count($log);
echo date('[Y-m-d H:i:s]') . " send_reminders selesai: {$n} reminder dibuat.\n";
foreach ($log as $l) echo "  → {$l}\n";
