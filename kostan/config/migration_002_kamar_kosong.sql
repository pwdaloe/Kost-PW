-- Migration 002: Tambah template WA "kamar_kosong"
-- Jalankan via phpMyAdmin (http://localhost:8081) — hanya sekali

INSERT INTO wa_templates (tipe, template_text)
VALUES ('kamar_kosong',
'Halo Kak *{{nama}}* 👋

Kabar gembira! Ada kamar yang tersedia di *Kost PW Balai Pustaka*.

🏠 Kamar   : *{{kamar}}*
📐 Ukuran  : {{ukuran}}
💰 Harga   : {{harga}} / bulan

Fasilitas:
✅ AC Sharp 0.5 PK
✅ Kasur, bantal & sprei
✅ Lemari + meja rias
✅ Kamar mandi dalam
✅ Water heater Ariston
✅ WiFi Biznet 300 Mbps
✅ Listrik token mandiri

Jika tertarik, segera balas pesan ini ya Kak 🙏
Tempat terbatas!')
ON DUPLICATE KEY UPDATE template_text = VALUES(template_text);
