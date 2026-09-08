# Changelog

Semua perubahan penting untuk aplikasi **Kasir Rainbow** (folder `kasir/`).

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).

## [Unreleased] — 2026-09-08

### Ditambahkan
- **Integrasi CSLINK (shortener URL)**:
  - `link_short.php` — `csapi_shorten()` untuk memendekkan link nota menjadi `https://cslink.web.id/XXXXXXXX` (cache di tabel `link_cache`, retry 3x, fallback ke URL asli bila gagal).
  - `n.php` — short URL publik `/n.php/{ref}/{id}/{t}/{token}` yang menampilkan nota tanpa login.
  - Link pendek dipakai di semua pesan WhatsApp pelanggan (`wa_pelanggan_msg()` baru/dp/lunas/selesai) dan QR barcode WhatsApp.
- **Pembayaran Midtrans gambungan lama** dipertahankan: `midtrans-snap.php`, `midtrans-webhook.php`, `receive.php` (endpoint Menerima hasil / notifikasi).
- **Pembayaran via Midtrans (Snap)** sudah terpasang di kasir — tombol "Bayar via Midtrans" di halaman pesanan membuat Snap token & memuat `snap.pay()`. Berjalan di mode **sandbox** sekarang; saat production siap, cukup isi server/client key production di Pengaturan.

### Diperbaiki
- **Tampilan nota struk berantakan**: tambah `<base href>` di `nota-templates/struk.php` sehingga CSS/JS (`assets/style.css`, `assets/print.js`) ter-resolve benar (sebelumnya 404 → tampilan acak).
- **Tombol "Kembali" untuk customer → "Keluar"** di `nota-templates/struk.php`: pengunjung publik melihat tombol **Keluar** (menutup tab), admin tetap melihat **Kembali** + **Cetak Nota**.
- **Logo struk/nota tidak berubah** setelah diganti: semua `src` logo kini memakai cache-buster `?v=<mtime>`:
  - `nota-templates/struk.php` (logo struk)
  - `nota-templates/a5-invoice.php` (logo nota A5)
  - `struk.php` (cetak dari kasir)
  - `pages/pengaturan.php` (preview logo di menu Pengaturan)
  - File PDF (`nota-pdf.php`) sudah pakai data URI sehingga selalu fresh.
- **"Gagal mengunggah logo"**: tambah `@unlink($dest)` sebelum `move_uploaded_file` di `pages/pengaturan.php` agar upload bisa menimpa file lama (sebelumnya gagal saat file tujuan dimiliki user lain).
- **Tanda `]*>` bocor di halaman A5**: ganti literal regex `<meta name="viewport"[^>]*>` di `nota-templates/a5.php` menjadi tag `<meta name="viewport" content="width=device-width, initial-scale=1">` yang benar.
- **`nota-pdf.php` error 500**: ekstensi PHP **GD** tidak terpasang (dibutuhkan dompdf untuk render logo). Instal `php8.3-gd` + restart service — PDF kini menghasilkan file valid.
- **Integrasi Midtrans Snap dirapikan siap production** (tiga file):
  - `midtrans-snap.php` — endpoint diubah ke Snap yang benar `…/snap/v1/transactions` (sebelumnya `/v2/transactions` yang salah), `order_id` dibuat unik per percobaan (no_pesanan + waktu), base URL kondisional sandbox/production, dan error curl ditangani.
  - `midtrans-webhook.php` — query diparameterisasi (perbaiki bug `$db->quote()` yang salah), pemetaan notifikasi via `keterangan` berisi `Midtrans order: <order_id>`, hitung ulang sisa pesanan dengan benar, verifikasi signature SHA512.
  - `pages/pesanan.php` — URL `snap.js` kini kondisional: memakai `app.sandbox.midtrans.com` saat sandbox, `app.midtrans.com` saat production (sebelumnya selalu production sehingga sandbox gagal).

### Diubah
- `config.php` — `require link_short.php`, `wa_pelanggan_msg()` memakai `nota_link()`, tambahan helper nota/token.
- `pages/pengaturan.php` — upload logo + preview pakai cache-buster.
- `pages/pesanan.php`, `pages/dashboard.php`, `pages/laporan.php` — pembaruan terkait nota/WA.