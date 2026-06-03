-- Migration 004: Partial Payment
-- Jalankan via phpMyAdmin — hanya sekali

-- 1. Tambah status 'partial' + kolom paid_amount ke tabel bills
ALTER TABLE bills
  MODIFY status_bayar ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
  ADD COLUMN paid_amount INT NOT NULL DEFAULT 0 AFTER lainnya;

-- 2. Template WA untuk reminder tagihan yang sudah partial
INSERT INTO wa_templates (tipe, template_text)
VALUES ('reminder_partial',
'Halo Kak *{{nama}}* 😊

Mengingatkan bahwa tagihan kost bulan *{{bulan}}* untuk Kamar *{{kamar}}* belum terbayar penuh.

💰 Total Tagihan  : *{{total}}*
✅ Sudah Dibayar  : {{paid_amount}}
⏳ Sisa           : *{{sisa}}*

Mohon segera lunasi sisa pembayaran ya Kak 🙏
Terima kasih!')
ON DUPLICATE KEY UPDATE template_text = VALUES(template_text);
