<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['simpan_toko'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa mengubah pengaturan.');
        } else {
            set_setting('nama_toko', trim($_POST['nama_toko'] ?? ''));
            set_setting('alamat', trim($_POST['alamat'] ?? ''));
            set_setting('telp', trim($_POST['telp'] ?? ''));
            set_setting('kota', trim($_POST['kota'] ?? ''));
            set_setting('footer_struk', trim($_POST['footer_struk'] ?? ''));
            set_setting('url_publik', rtrim(trim($_POST['url_publik'] ?? ''), '/'));
            $nt = $_POST['nota_template'] ?? 'struk';
            set_setting('nota_template', in_array($nt, ['struk', 'a5']) ? $nt : 'struk');
            $sl = $_POST['struk_lebar'] ?? '80';
            set_setting('struk_lebar', in_array($sl, ['80', '58']) ? $sl : '80');
            flash_set('success', 'Pengaturan toko disimpan.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['test_wa'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa menguji notifikasi.');
        } else {
            $tujuan = trim($_POST['test_wa_tujuan'] ?? '');
            if ($tujuan === '') {
                $tujuan = setting('wa_admin_number', '');
            }
            if ($tujuan === '') {
                flash_set('error', 'Isi nomor tujuan test terlebih dahulu.');
            } else {
                $pesan = "🔔 *TEST NOTIFIKASI WA KASIR*\n\nJika Anda menerima pesan ini berarti notifikasi WhatsApp berfungsi. Waktu test: " . date('d/m/Y H:i:s');
                $ok = wa_send($tujuan, $pesan);
                flash_set($ok ? 'success' : 'error', $ok
                    ? 'Pesan test terkirim ke ' . $tujuan . '.'
                    : 'Pesan test GAGAL terkirim. Cek log aktivitas (provider: ' . setting('wa_provider', 'fonnte') . ').');
            }
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['simpan_bank'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa mengubah pengaturan.');
        } else {
            set_setting('bank_nama', trim($_POST['bank_nama'] ?? ''));
            set_setting('bank_rekening', trim($_POST['bank_rekening'] ?? ''));
            set_setting('bank_pemilik', trim($_POST['bank_pemilik'] ?? ''));
            flash_set('success', 'Pengaturan bank disimpan.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['simpan_wa'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa mengubah pengaturan.');
        } else {
            set_setting('wa_enabled', !empty($_POST['wa_enabled']) ? '1' : '');
            $wp = $_POST['wa_provider'] ?? 'fonnte';
            set_setting('wa_provider', in_array($wp, ['fonnte', 'wablas', 'meta']) ? $wp : 'fonnte');
            set_setting('wa_token', trim($_POST['wa_token'] ?? ''));
            set_setting('wa_meta_token', trim($_POST['wa_meta_token'] ?? ''));
            set_setting('wa_meta_phone_id', trim($_POST['wa_meta_phone_id'] ?? ''));
            set_setting('wa_meta_template', trim($_POST['wa_meta_template'] ?? '') !== '' ? trim($_POST['wa_meta_template']) : 'kasir_notifikasi');
            set_setting('wa_meta_lang', trim($_POST['wa_meta_lang'] ?? '') !== '' ? trim($_POST['wa_meta_lang']) : 'id');
            set_setting('wa_admin_number', trim($_POST['wa_admin_number'] ?? ''));
            set_setting('wa_notif_pembayaran', !empty($_POST['wa_notif_pembayaran']) ? '1' : '');
            flash_set('success', 'Pengaturan notifikasi disimpan.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['simpan_midtrans'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa mengubah pengaturan.');
        } else {
            set_setting('midtrans_is_production', !empty($_POST['midtrans_is_production']) ? '1' : '0');
            set_setting('midtrans_server_key_sandbox', trim($_POST['midtrans_server_key_sandbox'] ?? ''));
            set_setting('midtrans_server_key_production', trim($_POST['midtrans_server_key_production'] ?? ''));
            set_setting('midtrans_client_key_sandbox', trim($_POST['midtrans_client_key_sandbox'] ?? ''));
            set_setting('midtrans_client_key_production', trim($_POST['midtrans_client_key_production'] ?? ''));
            flash_set('success', 'Pengaturan Midtrans disimpan.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    $uploads = [
        'qris'       => ['key' => 'qris_image',   'base' => 'qris',       'label' => 'QRIS'],
        'logo_nota'  => ['key' => 'logo_image',   'base' => 'logo-nota',  'label' => 'Logo Nota'],
        'logo_struk' => ['key' => 'logo_struk',   'base' => 'logo-struk', 'label' => 'Logo Struk'],
    ];
    foreach ($uploads as $upField => $upCfg) {
        if (!empty($_FILES[$upField]) && $_FILES[$upField]['error'] === UPLOAD_ERR_OK) {
            if (!is_superadmin()) {
                flash_set('error', 'Hanya super admin yang bisa mengunggah ' . $upCfg['label'] . '.');
                header('Location: index.php?p=pengaturan');
                exit;
            }
            $file = $_FILES[$upField];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $unset = false;
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                flash_set('error', 'Format ' . $upCfg['label'] . ' harus PNG/JPG/JPEG/WEBP.');
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                flash_set('error', 'Ukuran ' . $upCfg['label'] . ' maksimal 2 MB.');
            } else {
                $dest = __DIR__ . '/../assets/' . $upCfg['base'] . '.' . $ext;
                @unlink($dest);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (setting($upCfg['key']) && setting($upCfg['key']) !== 'assets/' . $upCfg['base'] . '.' . $ext) {
                        @unlink(__DIR__ . '/../' . setting($upCfg['key']));
                    }
                    set_setting($upCfg['key'], 'assets/' . $upCfg['base'] . '.' . $ext);
                    flash_set('success', $upCfg['label'] . ' berhasil diunggah.');
                } else {
                    flash_set('error', 'Gagal mengunggah ' . $upCfg['label'] . '.');
                }
            }
            header('Location: index.php?p=pengaturan');
            exit;
        }
    }

    if (!empty($_POST['simpan_logo'])) {
        $ln = max(10, min(45, (float)($_POST['logo_nota_size'] ?? 24)));
        set_setting('logo_nota_size', $ln);
        flash_set('success', 'Ukuran logo nota disimpan.');
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['ganti_pass'])) {
        $lama = $_POST['pass_lama'] ?? '';
        $baru = $_POST['pass_baru'] ?? '';
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
        if (!$user || !password_verify($lama, $user['password'])) {
            flash_set('error', 'Password lama salah.');
        } elseif (strlen($baru) < 6) {
            flash_set('error', 'Password baru minimal 6 karakter.');
        } else {
            DB::run('UPDATE users SET password = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            log_aktivitas('Ganti password sendiri', $user['username']);
            flash_set('success', 'Password diganti.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['backup'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa backup.');
            header('Location: index.php?p=pengaturan');
            exit;
        }
        DB::run('PRAGMA wal_checkpoint(TRUNCATE)');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=kasir-backup-' . date('Ymd-His') . '.db');
        readfile(DB_PATH);
        exit;
    }

    if (!empty($_POST['reset_data'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa reset data.');
        } else {
            $pass = $_POST['reset_pass'] ?? '';
            $confirm = strtoupper(trim($_POST['reset_confirm'] ?? ''));
            $user = DB::one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
            if (!$user || !password_verify($pass, $user['password'])) {
                flash_set('error', 'Password admin salah. Reset dibatalkan.');
            } elseif ($confirm !== 'RESET') {
                flash_set('error', 'Konfirmasi salah. Ketik RESET untuk melanjutkan.');
            } else {
                DB::run('DELETE FROM penjualan_item');
                DB::run('DELETE FROM penjualan');
                DB::run('DELETE FROM pesanan');
                DB::run('DELETE FROM pembayaran');
                DB::run("DELETE FROM sqlite_sequence WHERE name IN ('penjualan','penjualan_item','pesanan','pembayaran')");
                log_aktivitas('Reset data transaksi', '');
                flash_set('success', 'Semua data transaksi direset. Perhitungan mulai dari awal.');
            }
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['simpan_user'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa menambah user.');
            header('Location: index.php?p=pengaturan');
            exit;
        }
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? 'kasir') === 'superadmin' ? 'superadmin' : 'kasir';
        if ($username === '' || strlen($password) < 6) {
            flash_set('error', 'Username wajib diisi dan password minimal 6 karakter.');
        } elseif (DB::one('SELECT COUNT(*) c FROM users WHERE username = ?', [$username])['c'] > 0) {
            flash_set('error', 'Username sudah terdaftar.');
        } else {
            DB::run('INSERT INTO users (username, password, role) VALUES (?, ?, ?)', [$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            log_aktivitas('Tambah user', $username . ' (' . $role . ')');
            flash_set('success', "User {$username} ditambahkan.");
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['hapus_user'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa menghapus user.');
            header('Location: index.php?p=pengaturan');
            exit;
        }
        $id = (int)$_POST['hapus_user'];
        if ($id === (int)$_SESSION['user_id']) {
            flash_set('error', 'Tidak bisa menghapus akun sendiri.');
        } else {
            $del = DB::one('SELECT username FROM users WHERE id = ?', [$id]);
            DB::run('DELETE FROM users WHERE id = ?', [$id]);
            log_aktivitas('Hapus user', $del ? $del['username'] : '#' . $id);
            flash_set('success', 'User dihapus. Riwayat pembukuannya tetap tersimpan.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }

    if (!empty($_POST['reset_pass_user'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang bisa mereset password.');
            header('Location: index.php?p=pengaturan');
            exit;
        }
        $id = (int)$_POST['reset_pass_user'];
        $pass = $_POST['pass_baru_user'] ?? '';
        $u = DB::one('SELECT username FROM users WHERE id = ?', [$id]);
        if (!$u) {
            flash_set('error', 'User tidak ditemukan.');
        } elseif (strlen($pass) < 6) {
            flash_set('error', 'Password minimal 6 karakter.');
        } else {
            DB::run('UPDATE users SET password = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            log_aktivitas('Reset password user', $u['username']);
            flash_set('success', 'Password user ' . $u['username'] . ' diganti.');
        }
        header('Location: index.php?p=pengaturan');
        exit;
    }
}

$judul = 'Pengaturan';
$usersAdmin = is_superadmin() ? DB::q('SELECT u.id, u.username, u.role, u.created_at,
    (SELECT COUNT(*) FROM penjualan p WHERE p.user_id = u.id) jml_penjualan,
    (SELECT COUNT(*) FROM pesanan pe WHERE pe.user_id = u.id) jml_pesanan
    FROM users u ORDER BY u.id') : [];
require __DIR__ . '/../layout/header.php';
?>
<h2>Pengaturan</h2>

<div class="grid2">
    <?php if (is_superadmin()): ?>
    <div class="panel">
        <h3>Info Toko</h3>
        <form method="post">
            <label>Nama Toko
                <input type="text" name="nama_toko" value="<?= e(setting('nama_toko')) ?>" required>
            </label>
            <label>Alamat
                <input type="text" name="alamat" value="<?= e(setting('alamat')) ?>">
            </label>
            <label>No. Telepon / WA
                <input type="text" name="telp" value="<?= e(setting('telp')) ?>">
            </label>
            <label>Kota (untuk kop nota)
                <input type="text" name="kota" value="<?= e(setting('kota')) ?>">
            </label>
            <label>Teks Bawah Struk
                <input type="text" name="footer_struk" value="<?= e(setting('footer_struk')) ?>">
            </label>
            <label>URL Publik Kasir (untuk QR di struk/nota)
                <input type="text" name="url_publik" value="<?= e(setting('url_publik', 'https://rainbowprinting.web.id/kasir')) ?>" placeholder="https://rainbowprinting.web.id/kasir">
            </label>
            <label>Tipe Nota Bawaan
                <select name="nota_template">
                    <option value="struk" <?= setting('nota_template', 'struk') === 'struk' ? 'selected' : '' ?>>Struk 58/80mm</option>
                    <option value="a5" <?= setting('nota_template', 'struk') === 'a5' ? 'selected' : '' ?>>Invoice A5 (gaya percetakan-online)</option>
                </select>
            </label>
            <label>Lebar Struk (printer thermal)
                <select name="struk_lebar">
                    <option value="80" <?= setting('struk_lebar', '80') === '80' ? 'selected' : '' ?>>80mm</option>
                    <option value="58" <?= setting('struk_lebar', '80') === '58' ? 'selected' : '' ?>>58mm</option>
                </select>
            </label>
            <p class="muted kecil">Halaman Pesanan / Piutang / Kasir menyediakan pilihan cetak langsung; ini hanya default bila tidak ada pilihan.</p>
            <button type="submit" class="btn" name="simpan_toko" value="1">Simpan</button>
        </form>
    </div>

    <div class="panel">
        <h3>QRIS Pembayaran</h3>
        <?php if (setting('qris_image')): ?>
            <p class="muted">QRIS aktif, tampil di halaman kasir & struk.</p>
            <img class="qris-preview" src="<?= e(setting('qris_image')) ?>" alt="QRIS">
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="form-row">
            <input type="file" name="qris" accept=".png,.jpg,.jpeg,.webp" required>
            <button type="submit" class="btn">Unggah QRIS</button>
        </form>
        <p class="muted kecil">PNG/JPG/WebP, maks 2 MB. QRIS statis dari bank/penyedia Anda.</p>
    </div>

    <div class="panel grid-full">
        <h3>Logo Nota &amp; Struk</h3>
        <p class="muted">Logo tampil di struk thermal, nota A5, dan file PDF. Kosongkan dengan mengunggah logo baru; belum diunggah = memakai <code>logo.png</code> bawaan.</p>
        <div class="logo-duo">
            <div>
                <p class="muted kecil"><b>Logo Nota (A5 / PDF)</b></p>
                <?php if (setting('logo_image')): ?>
                    <?php $li = setting('logo_image'); $liv = is_file(__DIR__ . '/../' . $li) ? @filemtime(__DIR__ . '/../' . $li) : 0; ?>
                    <img class="qris-preview" src="<?= e($li) ?>?v=<?= $liv ?>" alt="Logo Nota">
                <?php else: ?>
                    <img class="qris-preview" src="assets/logo.png" alt="Logo Nota (bawaan)">
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="form-row">
                    <input type="file" name="logo_nota" accept=".png,.jpg,.jpeg,.webp" required>
                    <button type="submit" class="btn">Unggah Logo Nota</button>
                </form>
            </div>
            <div>
                <p class="muted kecil"><b>Logo Struk (thermal 58/80mm)</b></p>
                <?php if (setting('logo_struk')): ?>
                    <?php $ls = setting('logo_struk'); $lsv = is_file(__DIR__ . '/../' . $ls) ? @filemtime(__DIR__ . '/../' . $ls) : 0; ?>
                    <img class="qris-preview" src="<?= e($ls) ?>?v=<?= $lsv ?>" alt="Logo Struk">
                <?php else: ?>
                    <img class="qris-preview" src="assets/logo.png" alt="Logo Struk (bawaan)">
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="form-row">
                    <input type="file" name="logo_struk" accept=".png,.jpg,.jpeg,.webp" required>
                    <button type="submit" class="btn">Unggah Logo Struk</button>
                </form>
            </div>
        </div>
        <p class="muted kecil">PNG/JPG/WebP, maks 2 MB per gambar. Logo otomatis menyesuaikan orientasi (kotak/bulat/portrait/landscape) tanpa merubah rasio. Lebar logo nota A5 dapat diatur di bawah (min 10 mm – maks 45 mm, default 24 mm).</p>
        <form method="post" class="form-row">
            <label>Ukuran Logo Nota A5 (lebar)
                <input type="number" name="logo_nota_size" min="10" max="45" step="1" required value="<?= e(setting('logo_nota_size', 24)) ?>" style="width:90px;"> mm
            </label>
            <button type="submit" class="btn" name="simpan_logo" value="1">Simpan Ukuran</button>
        </form>
    </div>

    <div class="panel">
        <h3>Data Pembayaran Transfer Bank</h3>
        <form method="post">
            <label>Nama Bank
                <input type="text" name="bank_nama" value="<?= e(setting('bank_nama')) ?>" placeholder="Bank Central Asia">
            </label>
            <label>No. Rekening
                <input type="text" name="bank_rekening" value="<?= e(setting('bank_rekening')) ?>" placeholder="7935405254">
            </label>
            <label>Atas Nama
                <input type="text" name="bank_pemilik" value="<?= e(setting('bank_pemilik')) ?>" placeholder="Nur Ismani">
            </label>
            <p class="muted kecil">Informasi ini otomatis ditampilkan pada template notifikasi WA "Pesanan Diterima" agar pelanggan tahu cara membayar.</p>
            <button type="submit" class="btn" name="simpan_bank" value="1">Simpan Data Bank</button>
        </form>
    </div>

    <div class="panel">
        <h3>Notifikasi WhatsApp (Pesanan Baru)</h3>
        <form method="post">
            <label class="chk">
                <input type="checkbox" name="wa_enabled" value="1" <?= setting('wa_enabled') ? 'checked' : '' ?>>
                Aktifkan notifikasi WhatsApp
            </label>
            <label>Penyedia Gateway
                <select name="wa_provider">
                    <option value="fonnte" <?= setting('wa_provider', 'fonnte') === 'fonnte' ? 'selected' : '' ?>>Fonnte (api.fonnte.com)</option>
                    <option value="wablas" <?= setting('wa_provider') === 'wablas' ? 'selected' : '' ?>>Wablas (patp.wablas.com)</option>
                    <option value="meta" <?= setting('wa_provider') === 'meta' ? 'selected' : '' ?>>WhatsApp Business Cloud API (Meta, resmi)</option>
                </select>
            </label>
            <label>API Token
                <input type="password" name="wa_token" value="<?= e(setting('wa_token')) ?>" placeholder="Token dari Fonnte/Wablas">
            </label>
            <label>Meta — Access Token (Permanent)
                <input type="password" name="wa_meta_token" value="<?= e(setting('wa_meta_token')) ?>" placeholder="Token dari Meta for Developers">
            </label>
            <label>Meta — Phone Number ID
                <input type="text" name="wa_meta_phone_id" value="<?= e(setting('wa_meta_phone_id')) ?>" placeholder="cth: 123456789012345">
            </label>
            <label>Meta — Nama Template
                <input type="text" name="wa_meta_template" value="<?= e(setting('wa_meta_template')) ?>" placeholder="kasir_notifikasi">
            </label>
            <label>Meta — Kode Bahasa Template
                <input type="text" name="wa_meta_lang" value="<?= e(setting('wa_meta_lang', 'id')) ?>" placeholder="id">
            </label>
            <label>Nomor Admin Tujuan Notifikasi
                <input type="text" name="wa_admin_number" value="<?= e(setting('wa_admin_number')) ?>" placeholder="08xxxxxxxxxx">
            </label>
            <label class="chk">
                <input type="checkbox" name="wa_notif_pembayaran" value="1" <?= setting('wa_notif_pembayaran') ? 'checked' : '' ?>>
                Juga kirim notif saat pembayaran pesanan diterima
            </label>
            <p class="muted kecil">Kelola template pesan lewat menu <b>Pesanan</b> → tombol <b>Template WA</b>. Uji koneksi di bawah ini:</p>
            <div class="form-row" style="margin-bottom:10px;">
                <input type="text" name="test_wa_tujuan" value="<?= e(setting('wa_admin_number')) ?>" placeholder="Nomor tujuan test (08xx...)">
                <button type="submit" class="btn kecil" name="test_wa" value="1">Test Kirim</button>
            </div>
            <p class="muted kecil"><b>Fonnte/Wablas:</b> daftar di fonnte.com / wablas.com, salin API token, tempel di "API Token". <b>Gunakan sumber siluman/device — berisiko kena pembatasan WhatsApp.</b><br>
            <b>Meta (resmi, direkomendasikan):</b> buka developers.facebook.com → Create App → Business → WhatsApp → dapatkan <i>Access Token</i> & <i>Phone Number ID</i> dari dashboard. Buat <i>Message Template</i> bernama <code>kasir_notifikasi</code> kategori <b>Utility</b>, bahasa <code>id</code>, body: <code>{{1}}</code> (isi = pesan notifikasi). Semua notifikasi kasir akan dikirim lewat template ini.<br>
            Nomor admin tujuan diisi dengan nomor admin (tanpa izin nomor baru apapun).</p>
            <button type="submit" class="btn" name="simpan_wa" value="1">Simpan Notifikasi</button>
        </form>
    </div>

    <div class="panel">
        <h3>Payment Gateway — Midtrans</h3>
        <form method="post">
            <label>Mode
                <select name="midtrans_is_production">
                    <option value="0" <?= setting('midtrans_is_production', '0') === '0' ? 'selected' : '' ?>>Sandbox (Uji Coba)</option>
                    <option value="1" <?= setting('midtrans_is_production') === '1' ? 'selected' : '' ?>>Production</option>
                </select>
            </label>
            <label>Server Key (Sandbox)
                <input type="password" name="midtrans_server_key_sandbox" value="<?= e(setting('midtrans_server_key_sandbox')) ?>" placeholder="SB-Mid-server-..." required>
            </label>
            <label>Server Key (Production)
                <input type="password" name="midtrans_server_key_production" value="<?= e(setting('midtrans_server_key_production')) ?>" placeholder="Mid-server-..." required>
            </label>
            <label>Client Key (Sandbox)
                <input type="password" name="midtrans_client_key_sandbox" value="<?= e(setting('midtrans_client_key_sandbox')) ?>" placeholder="SB-Mid-client-..." required>
            </label>
            <label>Client Key (Production)
                <input type="password" name="midtrans_client_key_production" value="<?= e(setting('midtrans_client_key_production')) ?>" placeholder="Mid-client-..." required>
            </label>
            <button type="submit" class="btn" name="simpan_midtrans" value="1">Simpan Midtrans</button>
        </form>
        <p class="muted kecil">Daftar di <b>midtrans.com</b>. Masukkan Server Key & Client Key dari dashboard Merchant. Mode Sandbox untuk uji coba (transaksi tidak diproses benar). URL notifikasi: <code><?= e(setting('url_publik', 'https://rainbowprinting.web.id/kasir')) ?>/midtrans-webhook.php</code></p>
    </div>

    <?php endif; ?>

    <div class="panel">
        <h3>Keamanan</h3>
        <form method="post">
            <label>Password Lama
                <input type="password" name="pass_lama" required>
            </label>
            <label>Password Baru (min 6 karakter)
                <input type="password" name="pass_baru" required>
            </label>
            <button type="submit" class="btn" name="ganti_pass" value="1">Ganti Password</button>
        </form>
    </div>

    <?php if (is_superadmin()): ?>
    <div class="panel">
        <h3>Backup & Data</h3>
        <p class="muted">Unduh file database (termasuk semua transaksi, produk, pesanan). Simpan di tempat aman.</p>
        <form method="post">
            <button type="submit" class="btn" name="backup" value="1">Unduh Backup (.db)</button>
        </form>
        <p class="muted kecil">Lokasi file: <code>data/kasir.db</code></p>
        <hr>
        <h3>Reset Data Transaksi</h3>
        <p class="muted">Hapus SEMUA transaksi kasir, pesanan, dan pembayaran agar perhitungan dimulai dari nol. Produk, stok, kategori, dan pengaturan toko TIDAK ikut dihapus.</p>
        <form method="post" onsubmit="return confirm('Yakin reset SEMUA data transaksi? Tindakan ini tidak dapat dibatalkan. Disarankan unduh backup terlebih dahulu.');">
            <label>Ketik RESET untuk konfirmasi
                <input type="text" name="reset_confirm" placeholder="RESET" required>
            </label>
            <label>Password Admin
                <input type="password" name="reset_pass" required>
            </label>
            <button type="submit" class="btn bahaya" name="reset_data" value="1">Reset Semua Transaksi</button>
        </form>
    </div>

    <div class="panel grid-full">
        <h3>User &amp; Pembukuan</h3>
        <p class="muted">Tambah user kasir baru. Setiap kasir punya pembukuan terpisah (transaksi, pesanan, piutang, laporan). Stok & produk dipakai bersama.</p>
        <form method="post" class="form-row">
            <input type="text" name="username" placeholder="Username baru" required>
            <input type="password" name="password" placeholder="Password (min 6)" required>
            <select name="role">
                <option value="kasir">Kasir</option>
                <option value="superadmin">Super Admin</option>
            </select>
            <button type="submit" class="btn" name="simpan_user" value="1">Tambah User</button>
        </form>
        <table>
            <thead><tr><th>Username</th>            <th>Peran</th><th>Transaksi</th><th>Pesanan</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($usersAdmin as $u): ?>
                <tr>
                    <td><?= e($u['username']) ?><?= $u['id'] === (int)$_SESSION['user_id'] ? ' <span class="muted">(Anda)</span>' : '' ?></td>
                    <td><?= $u['role'] === 'superadmin' ? 'Super Admin' : 'Kasir' ?></td>
                    <td><?= (int)$u['jml_penjualan'] ?></td>
                    <td><?= (int)$u['jml_pesanan'] ?></td>
                    <td><?= tglOnly($u['created_at']) ?></td>
                    <td>
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <details class="bayar-inline">
                                <summary class="btn kecil">Reset Pass</summary>
                                <form method="post" class="form-row">
                                    <input type="hidden" name="reset_pass_user" value="<?= $u['id'] ?>">
                                    <input type="password" name="pass_baru_user" placeholder="Password baru (min 6)" required>
                                    <button type="submit" class="btn kecil">Ganti</button>
                                </form>
                            </details>
                            <form method="post" class="confirm inline" data-confirm="Hapus user <?= e($u['username']) ?>? Riwayat pembukuannya tetap tersimpan.">
                                <input type="hidden" name="hapus_user" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn kecil bahaya">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
