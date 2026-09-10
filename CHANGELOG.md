# Changelog

Semua perubahan penting untuk aplikasi **Kasir Rainbow** (folder `kasir/`).

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).

## [Unreleased] — 2026-09-10
### Kasir — WA Gateway Baileys jadi satu-satunya penyedia (Fonnte pensiun)
- **Fonnte dihapus total** dari kode (`wa_send_fonnte()` dihapus): akun Fonnte kena ban WhatsApp 2x, tidak dipakai lagi.
- **Antrean WA send-hosted**: `wa_send()` kini **hanya menulis ke tabel `wa_queue`** (db.php: tabel baru `wa_queue` dengan status/percobaan/galat). Tidak ada lagi kirim langsung ke provider pihak ke-3 — anti-ban.
- **`cron-wa.php` baru** (jalankan tiap 1 menit via cron): mengambil pesan `tunggu` dari antrean (max 5/run), kirim via **WA Gateway Baileys** (`wa_gateway_send()`), rate-limit **jeda 20 detik antar pesan**, retry maks 3x lalu status `gagal` + alasan tersimpan. **Pengaman**: bila gateway belum connect, cron tidak menyentuh antrean (pesan mengantre tanpa membakar percobaan, terkirim otomatis begitu gateway connect).
- `wa-send.php` (kirim ulang nota manual): fallback Fonnte dihapus; sukses = masuk antrean, gagal = pesan galat tanpa provider luar.
- Helper baru: `wa_potongan_pesan()` (pengaman pesan raksasa), `wa_norm_nomor()` (normalisasi ke 62…), `wa_queue_tunggu()` (jumlah antrean untuk badge).



### Kasir — Struk & Payment Point dipisah (berdiri sendiri)
- **Halaman Payment Point** (`n.php/{ref}/{id}/pay/{token}`, template `nota-templates/pay.php`) = **satu-satunya halaman pembayaran**: ringkasan pesanan + **daftar produk yang belum dibayarkan** + **pilihan QRIS statis & Transfer Bank** + nilai harus-bayar (**sisa + kode unik**) + tombol **Konfirmasi via WhatsApp**.
- **Halaman Struk** (`.../struk/...`) = **murni bukti/struk**: panel transfer & QRIS statis **dihapus** dari struk; tidak ada lagi tombol "Bayar Online" generik — diganti badge **✅ LUNAS** bila sisa = 0, atau tombol **💳 Lanjut Bayar (Rp sisa)** yang mengarah ke Payment Point bila masih ada sisa (mis. DP).
- **Aturan link WhatsApp** (`wa_pelanggan_msg` di `config.php`):
  - `baru` (Belum Bayar) → link **Payment Point** + rincian bank + nilai = tagihan + kode unik.
  - `dp` (masih ada sisa) → link **Payment Point** dengan nominal otomatis = **SISA + kode unik**.
  - `lunas` / `selesai` / `batal` → link **Struk** (bukti lunas).
- **Pengalihan otomatis** (`nota-publik.php`, status 302 ber-token):
  - Buka STRUK/A5 tapi masih ada sisa → dialihkan ke **Payment Point**.
  - Buka PAYMENT POINT tapi sudah lunas → dialihkan ke **Struk** (+ tombol **📄 Buka Halaman Struk** di halaman lunas).
- **Kode Unik per pesanan** (3 digit, deterministik `nota_kode_unik()`): nilai bayar = **tagihan + kode unik** (es. `Rp 105.000` + `434` = `Rp 105.434`) agar pembayaran QRIS statis teridentifikasi.
- File `nota-templates/transfer-options.php` tidak lagi di-include (panel lama pensiun); halaman A5 publik kini murni nota + tombol unduh PDF / konfirmasi WA.

### Kasir — nota publik: Panel "Pilihan Transfer" (riwayat, sebelum dipisah)
- **Nota publik (A5 & struk) kini tampil daftar pilihan transfer** di bawah nota, khusus untuk pelanggan: nilai tagihan, **QRIS statis** (`qris_image`) dan/atau **Transfer Bank** (`bank_nama` / `bank_rekening` / `bank_pemilik`).
- **Kode Unik per pesanan** (3 sifer, deterministik dari `ref:id:NOTA_SECRET`): `nota_kode_unik()` di `config.php`. Nilai yang harus dibayarkan = **tagihan + kode unik** (es. `Rp 360.000` + `069` = `Rp 360.069`) sehingga toko bisa mengenali pembayaran via QRIS statis tanpa invoice ID.
- Tombol **Salin** (kopier kode unik / nilai / no. rekening) + tombol **Konfirmasi via WhatsApp** (wa.me toko, pesan pre-fill dengan no. pesanan, nilai & kode unik).
- Panel hanya tampil di tampilan publik (`$publik`) dan **tidak dicetak** (`@media print`), lokasi: `nota-templates/transfer-options.php` (include dari `a5.php` & `struk.php`).
- Pesanan sudah **Lunas** → panel tampil catatan verde "sudah dibayar lunas" + kode unik referensi (nilai Rp 0).

### Kasir — Payment Point (halaman pembayaran publik)
- **Halaman baru `n.php/{ref}/{id}/pay/{token}`** dengan template `nota-templates/pay.php`: Payment Point lengkap per pesanan — ringkasan pesanan, **daftar produk yang belum dibayar**, sisa tagihan, dan **pilihan pembayaran**.
- **Metode pembayaran** (tab interaktif):
  - **🟢 QRIS** → tampil **QRIS statis** (`qris_image`) + **jumlah yang harus dibayar** (sisa + kode unik) + **kode unik** (tombol *Salin*).
  - **🏦 Transfer Bank** → rekening (`bank_nama` / `bank_rekening` / `bank_pemilik`), jumlah harus-bayar + kode unik, tombol *Salin No. Rekening*.
- Tombol **💬 Konfirmasi via WhatsApp** (pre-fill no. pesanan, nilai & kode unik) + tombol **Keluar**; halaman **mobile-first** dan tidak di-print.
- **Tautan masuk**: tombol **💳 Bayar Online** di toolbar struk publik (bila sisa &gt; 0), tautan **Buka Payment Point** di panel transfer, dan link `pay` disisipkan di pesan WhatsApp **baru/DP** (`wa_pelanggan_msg`).
- Route `n.php` & `nota-publik.php` menerima `t=pay`.

## [Unreleased] — 2026-09-09

### Game Top-Up (folder `game-topup/`) — akses via cslink.web.id
- **Website kini diakses di `https://cslink.web.id/top-up/`**: routing via nginx internal `127.0.0.1:4040` (prefix `/top-up` di-strip sebelum diteruskan ke `php -S :8090`, sisanya tetap shortener CSLINK `:4000`). `config.php`: `BASE_URL=https://cslink.web.id`, `BASE_PATH=/top-up`; semua tautan & fetch JS memakai `BASE_PATH`.
- **Tambahan sistemd**: service `game-topup.service` menjalankan `php -S 127.0.0.1:8090` dengan `router.php`.
- **Redesign UI modern** (`assets/style.css` bersama): tema gelap gradien cyan-violet, glassmorphism, font Plus Jakarta Sans, hero section, pencarian live + filter kategori, kartu produk hover-lift, header sticky, footer; `order.php`, `status.php`, `cek-status.php`, `admin/index.php` disamakan.
- **`config.php` di-hardening**: `define()` kini ber-guard `defined()` agar tidak konflik dengan `config.local.php` (server punya kredensial lokal).

## [Unreleased] — 2026-09-08

### Ditambahkan
- **Integrasi CSLINK (shortener URL)**:
  - `link_short.php` — `csapi_shorten()` untuk memendekkan link nota menjadi `https://cslink.web.id/XXXXXXXX` (cache di tabel `link_cache`, retry 3x, fallback ke URL asli bila gagal).
  - `n.php` — short URL publik `/n.php/{ref}/{id}/{t}/{token}` yang menampilkan nota tanpa login.
  - Link pendek dipakai di semua pesan WhatsApp pelanggan (`wa_pelanggan_msg()` baru/dp/lunas/selesai) dan QR barcode WhatsApp.
- **Pembayaran Midtrans gambungan lama** dipertahankan: `midtrans-snap.php`, `midtrans-webhook.php`, `receive.php` (endpoint Menerima hasil / notifikasi).
- **Pembayaran via Midtrans (Snap)** sudah terpasang di kasir — tombol "Bayar via Midtrans" di halaman pesanan membuat Snap token & memuat `snap.pay()`. Berjalan di mode **sandbox** sekarang; saat production siap, cukup isi server/client key production di Pengaturan.

### Toko Online (folder `toko-online/`) — sinkronisasi server → repo
- **Midtrans aktif & diselaraskan dengan pola kasir**: `payment/create.php` kini memakai setting **`midtrans_is_production`** (bukan deteksi prefix `SB-`); `includes/functions.php` + `admin/settings.php` mendapat field **Mode Midtrans** (Sandbox/Production). Key sandbox terisi & teruji end-to-end via `/snap/v1/transactions` → `redirect_url` sandbox valid, `orders.midtrans_token` tersimpan, opsi "Midtrans" muncul di checkout.
- **Router perbaikan**: `router.php` menambah redirect 301 untuk `/kasir`, `/admin`, `/uploads` (tanpa trailing slash) agar redirect relatif tidak nyasar.
- **Halaman statis SEO baru** ditambahkan: `faq.php`, `privacy-policy.php`, `terms-of-service.php`, `robots.txt`, `sitemap.xml`, `favicon.svg`.

### Game Top-Up (folder `game-topup/`) — baru
- **Website isi ulang / voucher game** (monorepo baru) berbasis PHP vanilla + SQLite, terintegrasi **Digiflazz** (API topup) & **Midtrans Snap** (payment).
- `config.php` — kredensial Digiflazz (`DGF_USERNAME`/`DGF_APIKEY`) & Midtrans (`MT_SERVER_KEY`/`MT_CLIENT_KEY`) + flag `MIDTRANS_IS_PRODUCTION`.
- `includes/Digiflazz.php` — wrapper API Digiflazz: pricelist, topup, cek status, callback parser.
- `includes/functions.php` — helper order (ref_id unik `TOPUP-...`), `midtransSnap()`, `cleanNumber()`, status text.
- Halaman: `index.php` (katalog), `order.php` (form + Snap pay), `status.php`, `cek-status.php`, `admin/index.php` (login sederhana + sync pricelist + daftar order).
- API: `api/order.php` (buat order → token Snap), `api/midtrans-callback.php` (webhook bayar → trigger topup ke Digiflazz), `api/digiflazz-callback.php` (callback status topup → update order + simpan SN).
- `cli/sync-pricelist.php` — sync pricelist Digiflazz ke tabel products (via cron).
- `router.php` — pengaman `php -S` (blokir `data/`, `cli/`, `config.php`, `includes/`).
- **Teruji end-to-end di server** (sandbox): katalog 200, order tersimpan, Snap token valid (HTTP 201), `payment_status=pending → waiting`.
- Catatan: akun Digiflazz **dev** kena rate-limit pricelist (`rc:83`) — perlu akun production + saldo untuk transaksi riil; alur sudah siap.

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