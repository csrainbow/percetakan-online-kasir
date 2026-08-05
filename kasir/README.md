# Kasir Percetakan (PHP + SQLite)

Aplikasi kasir / point-of-sale untuk usaha percetakan, dibuat dengan PHP vanilla + SQLite.
Ringan, tanpa framework, cocok dijalankan di STB berbasis Armbian (HG860P) maupun PC/Raspberry Pi.

## Fitur

- **Kasir / penjualan cepat** - cari produk, keranjang, hitung kembalian, cetak struk
- **Struk thermal 80mm** - tampilan print HTML, bisa di-cetak ke printer apa pun
- **Pesanan/order percetakan** - pelanggan, deskripsi, **DP + cicilan/pelunasan**, status DP/Lunas/Selesai/Batal
- **Piutang** - tagihan belum lunas, terima pembayaran langsung dari halaman piutang
- **Stok produk** - kategori, satuan (pcs/m2/rim/lbr), peringatan stok menipis
- **Laporan** - per hari, detail transaksi, produk terjual, pembayaran masuk, export CSV
- **QRIS** - unggah gambar QRIS statis, tampil di halaman kasir & struk
- **Backup database** - unduh file .db dari menu Pengaturan

## Login Default

- URL: `http://<ip-stb>:8080`
- Username: `admin`
- Password: `admin123`

Ganti password segera di menu **Pengaturan > Keamanan**.

## Install di Armbian (STB)

```bash
sudo apt update
sudo apt install -y php-cli php-sqlite3 php-fpm nginx

# salin project, contoh di /var/www/kasir
sudo cp -r kasir-print /var/www/kasir
sudo chown -R www-data:www-data /var/www/kasir/data
```

### Cara cepat (untuk coba/test)
```bash
cd /var/www/kasir
php -S 0.0.0.0:8080 router.php
```

### Cara permanen (Nginx + PHP-FPM)

Buat file `/etc/nginx/sites-available/kasir`:

```nginx
server {
    listen 80;
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
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl restart nginx
sudo systemctl enable php-fpm nginx
```

## Backup Otomatis (disarankan)

Jadwalkan tiap malam ke USB/cloud, contoh ke folder USB `/mnt/usb`:

```bash
crontab -e
```

```cron
30 22 * * * mkdir -p /mnt/usb/kasir-backup && cp /var/www/kasir/data/kasir.db /mnt/usb/kasir-backup/kasir-$(date +\%Y\%m\%d).db
```

## Struktur Folder

```
kasir-print/
├── index.php          # router halaman
├── login.php          # login
├── logout.php
├── struk.php          # cetak struk
├── config.php         # konfigurasi & helper
├── db.php             # koneksi SQLite + migrasi + data awal
├── router.php         # pengaman untuk php -S (blokir folder data)
├── data/kasir.db      # database (dibuat otomatis)
├── layout/            # header & footer
├── pages/             # dashboard, penjualan, produk, pesanan, piutang, laporan, pengaturan
└── assets/            # css, js
```

## Akses Online

Direncanakan: Cloudflare Tunnel / DDNS + port forwarding. Akan diisi setelah aplikasi rampung
dan berjalan normal di STB (lihat `README-ONLINE.md` saat dibutuhkan).
