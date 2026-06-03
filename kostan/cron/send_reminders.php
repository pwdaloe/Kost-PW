<?php
/**
 * Cron: Reminder H+1 jatuh tempo
 * - status 'unpaid'   → template 'reminder' (belum bayar sama sekali)
 * - status 'partial'  → template 'reminder_partial' (sudah bayar sebagian)
 * Jadwal cPanel: 0 8 * * * php /home/USERNAME/public_html/kostan/cron/send_reminders.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../config/db.php';

$log     = [];
$kemarin = date('Y-m-d', strtotime('-1 day'));

// Tagihan jatuh tempo kemarin, belum lunas, belum ada reminder di antrian
$targets = dbFetchAll("
    SELECT b.id AS bill_id, b.total, b.paid_amount, b.bulan, b.tgl_jatuh_tempo, b.status_bayar,
           t.nama, t.no_wa, r.nomor_kamar
    FROM bills b
    JOIN tenants t ON b.tenant_id = t.id
    JOIN rooms   r ON t.kamar_id  = r.id
    WHERE b.tgl_jatuh_tempo = ?
      AND b.status_bayar IN ('unpaid','partial')
      AND b.id NOT IN (SELECT bill_id FROM wa_queue WHERE tipe IN ('reminder','reminder_partial'))
", [$kemarin]);

$tmplUnpaid  = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe = "reminder"');
$tmplPartial = dbFetchOne('SELECT template_text FROM wa_templates WHERE tipe = "reminder_partial"');

foreach ($targets as $t) {
    $isPartial = $t['status_bayar'] === 'partial';
    $tipe      = $isPartial ? 'reminder_partial' : 'reminder';
    $teks      = $isPartial
                 ? ($tmplPartial['template_text'] ?? '')
                 : ($tmplUnpaid['template_text']  ?? '');

    $sisa = (int)$t['total'] - (int)$t['paid_amount'];

    $pesan = str_replace(
        ['{{nama}}','{{kamar}}','{{bulan}}','{{total}}','{{paid_amount}}','{{sisa}}','{{jatuh_tempo}}'],
        [
            $t['nama'],
            $t['nomor_kamar'],
            formatBulan($t['bulan']),
            formatRupiah((int)$t['total']),
            formatRupiah((int)$t['paid_amount']),
            formatRupiah($sisa),
            formatTanggal($t['tgl_jatuh_tempo']),
        ],
        $teks
    );

    dbExecute('INSERT INTO wa_queue (bill_id, tipe, pesan_text, status) VALUES (?,?,?,?)',
              [$t['bill_id'], $tipe, $pesan, 'pending']);

    $label = $isPartial
             ? "Partial reminder: {$t['nama']} — sisa " . formatRupiah($sisa)
             : "Reminder: {$t['nama']} — Kamar {$t['nomor_kamar']}";
    $log[] = $label;
}

$n = count($log);
echo date('[Y-m-d H:i:s]') . " send_reminders selesai: {$n} reminder dibuat.\n";
foreach ($log as $l) echo "  → {$l}\n";
