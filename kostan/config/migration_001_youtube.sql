-- Migration 001: Tambah kolom youtube_url ke tabel rooms
-- Jalankan via phpMyAdmin (http://localhost:8081) atau MySQL CLI
-- Hanya perlu dijalankan sekali pada database yang sudah ada

ALTER TABLE rooms
  ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL
  COMMENT 'URL video YouTube/Shorts kamar ini'
  AFTER no_pelanggan_listrik;
