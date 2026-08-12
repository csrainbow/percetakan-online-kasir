<?php
class DB {
    private static $pdo = null;

    public static function get() {
        if (self::$pdo === null) {
            if (!is_dir(dirname(DB_PATH))) {
                mkdir(dirname(DB_PATH), 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::migrate();
        }
        return self::$pdo;
    }

    private static function migrate() {
        $s = self::$pdo;
        $s->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'kasir',
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS kategori (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL UNIQUE
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS produk (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT UNIQUE,
            nama TEXT NOT NULL,
            kategori_id INTEGER,
            satuan TEXT NOT NULL DEFAULT 'pcs',
            harga_beli REAL NOT NULL DEFAULT 0,
            harga_jual REAL NOT NULL DEFAULT 0,
            stok REAL NOT NULL DEFAULT 0,
            stok_min REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS penjualan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            no_invoice TEXT NOT NULL UNIQUE,
            tgl TEXT NOT NULL,
            total REAL NOT NULL DEFAULT 0,
            bayar REAL NOT NULL DEFAULT 0,
            kembalian REAL NOT NULL DEFAULT 0,
            metode TEXT NOT NULL DEFAULT 'Tunai',
            user_id INTEGER,
            keterangan TEXT DEFAULT ''
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS penjualan_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            penjualan_id INTEGER NOT NULL,
            produk_id INTEGER,
            nama TEXT NOT NULL,
            harga REAL NOT NULL,
            qty REAL NOT NULL,
            subtotal REAL NOT NULL,
            FOREIGN KEY (penjualan_id) REFERENCES penjualan(id) ON DELETE CASCADE
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS pesanan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            no_pesanan TEXT NOT NULL UNIQUE,
            tgl TEXT NOT NULL,
            pelanggan TEXT NOT NULL,
            telepon TEXT DEFAULT '',
            deskripsi TEXT DEFAULT '',
            total REAL NOT NULL DEFAULT 0,
            dp REAL NOT NULL DEFAULT 0,
            sisa REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'DP',
            user_id INTEGER,
            keterangan TEXT DEFAULT ''
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS pesanan_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pesanan_id INTEGER NOT NULL,
            produk_id INTEGER,
            nama TEXT NOT NULL,
            qty REAL NOT NULL DEFAULT 1,
            harga REAL NOT NULL DEFAULT 0,
            subtotal REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS pembayaran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ref_type TEXT NOT NULL DEFAULT 'pesanan',
            ref_id INTEGER NOT NULL,
            tgl TEXT NOT NULL,
            jumlah REAL NOT NULL DEFAULT 0,
            metode TEXT NOT NULL DEFAULT 'Tunai',
            keterangan TEXT DEFAULT ''
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS pengaturan (
            key TEXT PRIMARY KEY,
            value TEXT DEFAULT ''
        )");
        $s->exec("CREATE TABLE IF NOT EXISTS log_aktivitas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            aksi TEXT NOT NULL,
            detail TEXT DEFAULT '',
            tgl TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )");

        $cols = self::q('PRAGMA table_info(users)');
        $hasRole = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'role') {
                $hasRole = true;
                break;
            }
        }
        if (!$hasRole) {
            self::run("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'kasir'");
            self::run("UPDATE users SET role = 'superadmin' WHERE id = (SELECT MIN(id) FROM users)");
        }

        $cols = self::q('PRAGMA table_info(produk)');
        $hasBarcode = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'barcode') {
                $hasBarcode = true;
                break;
            }
        }
        if (!$hasBarcode) {
            self::run("ALTER TABLE produk ADD COLUMN barcode TEXT DEFAULT ''");
        }

        $cols = self::q('PRAGMA table_info(pesanan)');
        $hasEstimasi = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'estimasi') {
                $hasEstimasi = true;
                break;
            }
        }
        if (!$hasEstimasi) {
            self::run("ALTER TABLE pesanan ADD COLUMN estimasi TEXT DEFAULT ''");

        $cols = self::q('PRAGMA table_info(pesanan)');
        $hasDeleted = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'deleted') {
                $hasDeleted = true;
                break;
            }
        }
        if (!$hasDeleted) {
            self::run("ALTER TABLE pesanan ADD COLUMN deleted INTEGER NOT NULL DEFAULT 0");
        }

        }

        $cols = self::q('PRAGMA table_info(penjualan)');
        $hasStatusP = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'status') {
                $hasStatusP = true;
                break;
            }
        }
        if (!$hasStatusP) {
            self::run("ALTER TABLE penjualan ADD COLUMN status TEXT NOT NULL DEFAULT 'Lunas'");
        }

        $cols = self::q('PRAGMA table_info(pembayaran)');
        $hasStatusB = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'status') {
                $hasStatusB = true;
                break;
            }
        }
        if (!$hasStatusB) {
            self::run("ALTER TABLE pembayaran ADD COLUMN status TEXT NOT NULL DEFAULT 'Lunas'");

        $cols = self::q('PRAGMA table_info(pembayaran)');
        $hasUserIdP = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'user_id') {
                $hasUserIdP = true;
                break;
            }
        }
        if (!$hasUserIdP) {
            self::run("ALTER TABLE pembayaran ADD COLUMN user_id INTEGER DEFAULT 0");
        }

        }

        $u = self::one('SELECT COUNT(*) c FROM users');
        if ($u['c'] == 0) {
            self::run('INSERT INTO users (username, password, role) VALUES (?, ?, ?)', ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'superadmin']);
        }

        $kategori = ['Kartu Nama', 'Banner & Spanduk', 'Undangan', 'Stempel', 'Foto Copy & Cetak', 'ATK', 'Merchandise', 'Lainnya'];
        foreach ($kategori as $k) {
            self::run('INSERT OR IGNORE INTO kategori (nama) VALUES (?)', [$k]);
        }

        $defaults = [
            'nama_toko' => 'Percetakan Saya',
            'alamat' => 'Jl. Contoh No. 1, Kota Anda',
            'telp' => '0812-0000-0000',
            'kota' => 'Kota Anda',
            'footer_struk' => 'Terima kasih atas kunjungan Anda',
            'qris_image' => '',
            'logo_image' => '',
            'logo_struk' => '',
            'nota_template' => 'struk',
            'struk_lebar' => '80',
            'url_publik' => 'https://rainbowprinting.web.id/kasir',
        ];
        foreach ($defaults as $k => $v) {
            self::run('INSERT OR IGNORE INTO pengaturan (key, value) VALUES (?, ?)', [$k, $v]);
        }

        $p = self::one('SELECT COUNT(*) c FROM produk');
        if ($p['c'] == 0) {
            $kat = [];
            foreach (self::q('SELECT id, nama FROM kategori') as $r) {
                $kat[$r['nama']] = $r['id'];
            }
            $produk = [
                ['KN-001', 'Kartu Nama 1 Sisi (500 pcs)', 'Kartu Nama', 'paket', 20000, 30000, 50, 5, '8991234500017'],
                ['KN-002', 'Kartu Nama 2 Sisi (500 pcs)', 'Kartu Nama', 'paket', 28000, 40000, 50, 5, '8991234500024'],
                ['BN-001', 'Banner (per m2)', 'Banner & Spanduk', 'm2', 15000, 30000, 100, 10, ''],
                ['ST-001', 'Stempel Warna (kayu)', 'Stempel', 'pcs', 35000, 55000, 20, 3, '8991234500031'],
                ['FC-001', 'Foto Copy per Lembar', 'Foto Copy & Cetak', 'lbr', 100, 250, 5000, 100, ''],
                ['UV-001', 'Undangan Pernikahan (1 pcs)', 'Undangan', 'pcs', 2500, 5000, 500, 50, ''],
                ['ATK-001', 'Kertas HVS A4 80gsm (rim)', 'ATK', 'rim', 45000, 55000, 30, 5, '8991234500048'],
            ];
            foreach ($produk as $pr) {
                self::run('INSERT INTO produk (kode, nama, kategori_id, satuan, harga_beli, harga_jual, stok, stok_min, barcode) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$pr[0], $pr[1], $kat[$pr[2]] ?? null, $pr[3], $pr[4], $pr[5], $pr[6], $pr[7], $pr[8]]);
            }
        }
    }

    public static function q($sql, $args = []) {
        $st = self::get()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function one($sql, $args = []) {
        $r = self::q($sql, $args);
        return $r ? $r[0] : null;
    }

    public static function run($sql, $args = []) {
        $st = self::get()->prepare($sql);
        $st->execute($args);
        return $st;
    }

    public static function lastId() {
        return self::get()->lastInsertId();
    }
}
