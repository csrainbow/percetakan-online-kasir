# Percetakan Online + Kasir

Aplikasi **toko online** (PHP vanilla + SQLite) dan **kasir / point-of-sale** untuk usaha percetakan.
Ringan, tanpa framework, tanpa dependency composer — jalan di STB/PC/Raspberry Pi (Debian/Ubuntu/Armbian).

## Isi Repo

```
percetakan-online-kasir/
├── install.sh        # instalasi otomatis (nginx + php-fpm + systemd)
├── uninstall.sh      # hapus aplikasi (data dibackup dulu)
├── toko-online/      # toko online (rainbowprinting.web.id)
│   ├── admin/        # dashboard admin: pesanan, produk, pembayaran, pengaturan
│   ├── customer/     # dashboard pelanggan
│   ├── payment/      # pembayaran & notifikasi (Midtrans)
│   ├── includes/     # functions.php (WA, email, dll)
│   ├── router.php    # pengaman php -S (blokir database.sqlite)
│   └── *.php         # index, products, cart, checkout, cek-pesanan, ...
└── kasir/            # aplikasi kasir
    ├── pages/        # dashboard, penjualan, produk, pesanan, piutang, laporan, histori, pengaturan, log, rekap
    ├── layout/       # header & footer
    ├── nota-templates/ # struk 80mm & nota A5
    ├── etc/          # file cloudflared.service + cron watchdog (opsional)
    ├── assets/       # css & js
    ├── db.php        # koneksi SQLite + migrasi otomatis + data awal
    ├── router.php    # pengaman php -S (blokir folder data)
    ├── wa-send.php   # helper notifikasi WhatsApp (Fonnte / Wablas)
    ├── cloudflared-watchdog.sh # watchdog Cloudflare Tunnel (auto-restart)
    └── config.php    # konfigurasi (zona waktu, NOTA_SECRET, url publik)
```

## Fitur Utama

### Kasir
- Penjualan cepat + struk thermal 80mm & nota A5 (multi-produk, barcode)
- Pesanan percetakan: DP + cicilan/pelunasan, status DP/Lunas/Selesai/Batal
- Keranjang multi-item dalam 1 pesanan
- Histori pesanan + pemulihan (khusus superadmin)
- Nilai penjualan per kasir (atribusi pembayaran per user)
- Piutang, stok, laporan per hari, rekap, log aktivitas
- QRIS statis, export CSV, backup database
- Notifikasi WhatsApp (Fonnte/Wablas): pesanan baru & pembayaran

### Toko Online
- Katalog produk (custom size /m², lembar, buku, rim, pcs)
- Keranjang, checkout, pembayaran (transfer / Midtrans), cek status pesanan
- Akun pelanggan: riwayat pesanan, download hasil desain
- Admin: kelola pesanan (desain → diproses → cetak → selesai), verifikasi pembayaran, produk, halaman, pengaturan
- Notifikasi WhatsApp otomatis:
  - Pesanan baru → ke nomor toko
  - Status berubah (dibayar/DP/diproses/dicetak/selesai) → ke nomor WA pelanggan

## Pembaruan Terkini (kasir, 11 Agu 2026)

- **Zona waktu Asia/Makassar (WITA)** — semua waktu konsisten dengan jam perangkat
  (sebelumnya WIB, selisih 1 jam; riwayat lama sudah disesuaikan ke WITA)
- **Laporan konsisten** — pembayaran dari pesanan Batal/dihapus tidak ikut terhitung;
  export CSV, total pembayaran masuk, dan per-hari kini sama nilainya
- **Dashboard per kasir akurat** — nilai atribusi kasir hanya menghitung pesanan aktif
- **Pesanan multi-item** — 1 pesanan bisa berisi banyak produk (tabel `pesanan_item`)
- **Atribusi kasir** — setiap transaksi tercatat `user_id` pembuatnya (kasir / admin)
- **Nota publik** — URL nota bertanda tangan `NOTA_SECRET` (`nota-publik.php`),
  bisa dibagikan ke pelanggan lewat WhatsApp
- **WA notif ulang** — kirim ulang pesan WhatsApp ke pelanggan dari menu pesanan (`wa-send.php`)
- **Cloudflare Tunnel watchdog** — `cloudflared` auto-restart bila tunnel mati
  (`etc/cloudflared.service` + `etc/cloudflared-watchdog.cron` + `cloudflared-watchdog.sh`)
- Backup database dengan nama ber-timestamp otomatis saat ada perubahan besar

## Kebutuhan

- OS: Debian / Ubuntu / Armbian (raspi, STB)
- Paket: `php-cli php-fpm php-sqlite3 php-curl php-mbstring php-gd nginx curl`
  (diinstal otomatis oleh `install.sh`)
- Port default: kasir `8081`, toko online `8000` (bisa diubah)

## Instalasi (metode curl)

```bash
curl -fsSL https://raw.githubusercontent.com/csrainbow/percetakan-online-kasir/main/install.sh | sudo bash
```

Instalasi langsung dari tarball GitHub: download → salin aplikasi → generate
`NOTA_SECRET` & hash password admin → pasang service & nginx → selesai.

### Variabel opsional (via env)

| Variabel | Default | Fungsi |
|---|---|---|
| `ADMIN_PASSWORD` | `admin123` | Password admin toko online |
| `NOTA_SECRET` | (acak) | Secret tanda tangan URL nota kasir |
| `KASIR_PORT` | `8081` | Port nginx aplikasi kasir |
| `TOKO_PORT` | `8000` | Port php -S toko online |
| `INSTALL_BASE` | `/var/www` | Folder dasar instalasi |
| `SKIP_SERVICES` | `0` | `1` = hanya salin file (uji coba) |

Contoh:

```bash
sudo ADMIN_PASSWORD=rahasia123 NOTA_SECRET=isi-sendiri KASIR_PORT=8081 TOKO_PORT=8000 \
  bash -c "$(curl -fsSL https://raw.githubusercontent.com/csrainbow/percetakan-online-kasir/main/install.sh)"
```

## Login Default

| Aplikasi | URL | Login |
|---|---|---|
| Kasir | `http://<IP>:8081` | `admin` / `admin123` |
| Toko online | `http://<IP>:8000` | `admin` / sesuai `ADMIN_PASSWORD` |

Ganti password segera setelah instalasi (kasir: menu **Pengaturan > Keamanan**; toko: `config.php`).

## Uninstall

```bash
curl -fsSL https://raw.githubusercontent.com/csrainbow/percetakan-online-kasir/main/uninstall.sh | sudo bash
```

Database & upload dibackup otomatis ke `/root/backup-percetakan-<timestamp>` sebelum dihapus.

## Notifikasi WhatsApp

1. Daftar di [Fonnte](https://fonnte.com) (gratis ±1.000 pesan/bulan) atau Wablas
2. Ambil token API dari menu **Device** (Fonnte)
3. Kasir: menu **Pengaturan → Notifikasi WhatsApp**
4. Toko online: admin → **Pengaturan → WhatsApp**

## Keamanan

- File `database.sqlite` & folder `data/` diblokir aksesnya (nginx / router.php)
- Password tersimpan sebagai hash bcrypt
- Session cookie httponly + samesite=Lax
- `NOTA_SECRET` acak per instalasi (jangan disebar)
- Untuk produksi: wajib HTTPS (reverse proxy / Cloudflare Tunnel / CasaOS)

## Backup Data

| Data | Lokasi |
|---|---|
| DB kasir | `<KASIR_DIR>/data/kasir.db` |
| DB toko online | `<TOKO_DIR>/database.sqlite` |
| Upload (desain, bukti pembayaran) | `<TOKO_DIR>/uploads/` |

Jadwalkan dengan cron, contoh tiap malam:

```cron
30 22 * * * cp /var/www/kasir/data/kasir.db /root/backup/kasir-$(date +\%Y\%m\%d).db
```
