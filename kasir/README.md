# Kasir Percetakan (PHP + SQLite)

Aplikasi kasir / point-of-sale untuk usaha percetakan, PHP vanilla + SQLite.
Ringan, tanpa framework, tanpa dependency composer — jalan di STB (Armbian),
PC, maupun Raspberry Pi.

## Fitur

- **Kasir / penjualan cepat** — cari produk, keranjang, hitung kembalian, cetak struk
- **Struk thermal 80mm** & **nota A5** (multi-template, bisa dicetak ke printer apa pun)
- **Pesanan / order percetakan** — pelanggan, deskripsi, **DP + cicilan/pelunasan**,
  status DP / Lunas / Selesai / Batal
- **Multi-item** — 1 pesanan bisa berisi banyak produk (tabel `pesanan_item`)
- **Atribusi kasir** — setiap penjualan & pembayaran tercatat `user_id` pembuatnya;
  superadmin bisa memfilter data per kasir
- **Piutang** — tagihan belum lunas, terima pembayaran langsung dari halaman piutang
- **Stok produk** — kategori, satuan (pcs / m² / rim / lbr), peringatan stok menipis
- **Laporan** — per hari, detail transaksi, produk terjual, pembayaran masuk, export CSV
- **QRIS** — unggah gambar QRIS statis, tampil di halaman kasir & struk
- **Nota publik** — URL nota bertanda tangan `NOTA_SECRET` (`nota-publik.php`),
  bisa dibagikan ke pelanggan via WhatsApp
- **Notifikasi WhatsApp** (Fonnte / Wablas) — pesanan baru, pembayaran, dan kirim ulang ke pelanggan
- **Backup database** — unduh file `.db` dari menu Pengaturan

## Zona Waktu

Aplikasi memakai **Asia/Makassar (WITA)** — `date_default_timezone_set('Asia/Makassar')` di `config.php`.
Log aktivitas memakai jam sistem (`datetime('now','localtime')`). Keduanya harus sama-sama
WITA agar laporan konsisten dengan jam perangkat; sesuaikan zona waktu OS:

```bash
sudo timedatectl set-timezone Asia/Makassar
```

> Riwayat data lama yang tercatat sebelum perubahan zona waktu sudah disesuaikan
> otomatis pada proses migrasi (11 Agu 2026).

## Login Default

- Username: `admin`
- Password: `admin123` (ganti segera di **Pengaturan > Keamanan**)

## Install di Armbian (STB) / Linux

```bash
sudo apt update
sudo apt install -y php-cli php-sqlite3 php-fpm nginx curl
sudo mkdir -p /var/www/kasir
sudo cp -r kasir/* /var/www/kasir
sudo chown -R www-data:www-data /var/www/kasir/data
```

### Cara cepat (uji coba / STB ringan)

```bash
cd /var/www/kasir
php -S 0.0.0.0:8000 router.php
```

`router.php` memblokir akses langsung ke folder `data/` dan file `.db`.

### Cara permanen (Nginx + PHP-FPM)

```nginx
server {
    listen 8081;
    server_name _;
    root /var/www/kasir;
    index index.php;

    location ^~ /data/ { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/kasir /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## Akses Online (Cloudflare Tunnel)

1. Pastikan `cloudflared` terpasang: `curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm64 -o /usr/local/bin/cloudflared`
2. Login / pasang tunnel, buat `config.yml` yang menyambungkan hostname ke `localhost:8000`
3. Jalankan sebagai service (`Type=notify`, `Restart=always`).
   Contoh unit & cron tersedia di folder `etc/`:

   ```bash
   sudo cp etc/cloudflared.service /etc/systemd/system/
   sudo systemctl daemon-reload && sudo systemctl enable --now cloudflared
   ```

4. **Watchdog** (auto-restart bila tunnel mati, cek tiap 5 menit):

   ```bash
   sudo cp cloudflared-watchdog.sh /usr/local/bin/
   sudo chmod +x /usr/local/bin/cloudflared-watchdog.sh
   sudo cp etc/cloudflared-watchdog.cron /etc/cron.d/
   ```

## Backup Otomatis

Jadwalkan tiap malam (mis. ke folder USB):

```bash
crontab -e
```

```cron
30 22 * * * mkdir -p /mnt/usb/kasir-backup && cp /var/www/kasir/data/kasir.db /mnt/usb/kasir-backup/kasir-$(date +\%Y\%m\%d).db
```

Nama backup ber-timestamp (`kasir.db.bak-<tanggal>`) dibuat otomatis saat perubahan besar
(migrasi, pembersihan data, perubahan zona waktu).

## Struktur Folder

```
kasir/
├── index.php              # router halaman
├── login.php / logout.php
├── config.php             # zona waktu, DB_PATH, NOTA_SECRET, helper
├── db.php                 # koneksi SQLite + migrasi + data awal
├── router.php             # pengaman php -S (blokir folder data)
├── wa-send.php            # helper notifikasi WhatsApp
├── nota-publik.php        # nota publik bertanda tangan (dibagikan ke pelanggan)
├── nota.php / nota-pdf.php / struk.php / label.php / rekap-pdf.php
├── rekap-common.php       # rekapitulasi harian (kasir & penjualan)
├── edit-penjualan.php
├── data/kasir.db          # database (dibuat otomatis, jangan di-commit)
├── etc/                   # cloudflared.service + cloudflared-watchdog.cron
├── cloudflared-watchdog.sh
├── layout/                # header & footer
├── nota-templates/        # struk 80mm, nota A5 (a5.php, a5-invoice.php)
├── pages/                 # dashboard, penjualan, produk, pesanan, piutang,
│                          # laporan, histori, pengaturan, log, rekap
└── assets/                # css, js
```