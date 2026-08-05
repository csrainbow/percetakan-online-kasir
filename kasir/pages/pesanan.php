<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['simpan_pesanan'])) {
        $itemsJson = trim($_POST['items_json'] ?? '');
        $pelanggan = trim($_POST['pelanggan'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $total = (float)($_POST['total'] ?? 0);
        $dp = (float)($_POST['dp'] ?? 0);
        $metode = trim($_POST['metode'] ?? 'Tunai');
        $estimasi = trim($_POST['estimasi'] ?? '');

        if ($pelanggan === '' || $total <= 0) {
            flash_set('error', 'Nama pelanggan dan total wajib diisi.');
        } else {
            if ($dp > $total) {
                $dp = $total;
            }
            $status = ($dp >= $total) ? 'Lunas' : 'DP';
            $no = next_number('PSN', 'pesanan');
            DB::run('INSERT INTO pesanan (no_pesanan, tgl, pelanggan, telepon, deskripsi, total, dp, sisa, status, user_id, estimasi) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$no, date('Y-m-d H:i:s'), $pelanggan, $telepon, $deskripsi, $total, $dp, $total - $dp, $status, $_SESSION['user_id'], $estimasi]);
            $pid = DB::lastId();
            if ($dp > 0) {
                DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id) VALUES (?,?,?,?,?,?,?,?)',
                    ['pesanan', $pid, date('Y-m-d H:i:s'), $dp, $metode, 'Pembayaran awal / DP', $metode === 'QRIS' ? 'Menunggu QRIS' : 'Lunas', $_SESSION['user_id']]);
            }
            log_aktivitas('Pesanan baru', "{$no} | {$pelanggan} | total {$total} | dp {$dp}");
                        if ($itemsJson !== '') {
                $arr = json_decode($itemsJson, true);
                if (is_array($arr)) {
                    foreach ($arr as $it) {
                        $nama = trim((string)($it['nama'] ?? ''));
                        if ($nama === '') {
                            continue;
                        }
                        $q = (float)($it['qty'] ?? 0);
                        $h = (float)($it['harga'] ?? 0);
                        $st = (float)($it['subtotal'] ?? 0);
                        if ($q <= 0 || $h < 0) {
                            continue;
                        }
                        DB::run('INSERT INTO pesanan_item (pesanan_id, produk_id, nama, qty, harga, subtotal) VALUES (?,?,?,?,?,?)',
                            [$pid, (int)($it['produk_id'] ?? 0) ?: null, $nama, $q, $h, $st]);
                    }
                }
            }
            if (setting('wa_enabled') && setting('wa_admin_number') !== '') {
                $waMsg = "🖨️ *PESANAN BARU*\nNo: {$no}\nPelanggan: {$pelanggan}\nTotal: " . rp($total) . "\nDP: " . rp($dp) . "\nSisa: " . rp($total - $dp) . "\nMetode: {$metode}\nStatus: {$status}\nWaktu: " . date('d/m/Y H:i');
                if ($estimasi !== '') {
                    $waMsg .= "\nTarget: " . date('d/m/Y H:i', strtotime($estimasi));
                }
                wa_send(setting('wa_admin_number'), $waMsg);
            }
            flash_set('success', "Pesanan {$no} dibuat untuk {$pelanggan}.");
        }
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['konfirmasi_pembayaran'])) {
        $id = (int)$_POST['konfirmasi_pembayaran'];
        $pm = DB::one("SELECT pp.*, pe.no_pesanan FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                       WHERE pp.id = ? AND pp.ref_type = 'pesanan' AND " . scope_sql('pe'), [$id]);
        if ($pm && $pm['status'] === 'Menunggu QRIS') {
            DB::run("UPDATE pembayaran SET status = 'Lunas' WHERE id = ?", [$id]);
            log_aktivitas('Konfirmasi QRIS', $pm['no_pesanan'] . ' | ' . $pm['jumlah']);
            flash_set('success', 'Pembayaran QRIS dikonfirmasi.');
        } else {
            flash_set('error', 'Pembayaran tidak ditemukan.');
        }
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['bayar_pesanan'])) {
        $id = (int)$_POST['bayar_pesanan'];
        $jumlah = (float)($_POST['jumlah'] ?? 0);
        $metode = trim($_POST['metode'] ?? 'Tunai');
        $ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
        if (!$ps || !in_array($ps['status'], ['DP', 'Lunas'])) {
            flash_set('error', 'Pesanan tidak dapat dibayar.');
        } elseif ($jumlah <= 0) {
            flash_set('error', 'Jumlah pembayaran tidak valid.');
        } else {
            $totalDibayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id = ?", [$id])['t'];
            $sisa = $ps['total'] - $totalDibayar;
            if ($jumlah > $sisa) {
                $jumlah = $sisa;
            }
            DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id) VALUES (?,?,?,?,?,?,?,?)',
                ['pesanan', $id, date('Y-m-d H:i:s'), $jumlah, $metode, 'Pembayaran pesanan', $metode === 'QRIS' ? 'Menunggu QRIS' : 'Lunas', $_SESSION['user_id']]);
            $baru = $totalDibayar + $jumlah;
            $sisaBaru = $ps['total'] - $baru;
            $status = $sisaBaru <= 0 ? 'Lunas' : 'DP';
            DB::run('UPDATE pesanan SET sisa = ?, status = ? WHERE id = ?', [$sisaBaru, $status, $id]);
            log_aktivitas('Pembayaran pesanan', $ps['no_pesanan'] . ' | jumlah ' . $jumlah . ' | ' . $metode);
            if (setting('wa_notif_pembayaran') && setting('wa_admin_number') !== '') {
                wa_send(setting('wa_admin_number'), "💵 *PEMBAYARAN PESANAN*\nNo: " . $ps['no_pesanan'] . "\nPelanggan: " . $ps['pelanggan'] . "\nJumlah: " . rp($jumlah) . "\nMetode: " . $metode . "\nSisa: " . rp(max(0, $sisaBaru)) . "\nWaktu: " . date('d/m/Y H:i'));
            }
            flash_set('success', 'Pembayaran diterima.');
        }
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['selesai_pesanan'])) {
        $id = (int)$_POST['selesai_pesanan'];
        $ps = DB::one('SELECT no_pesanan FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
        DB::run("UPDATE pesanan SET status = 'Selesai' WHERE id = ? AND status IN ('DP','Lunas') AND " . scope_sql('pesanan'), [$id]);
        log_aktivitas('Pesanan selesai', $ps['no_pesanan'] ?? '#' . $id);
        flash_set('success', 'Pesanan ditandai selesai / diambil.');
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['batal_pesanan'])) {
        $id = (int)$_POST['batal_pesanan'];
        $ps = DB::one('SELECT no_pesanan FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
        DB::run("UPDATE pesanan SET status = 'Batal' WHERE id = ? AND status != 'Selesai' AND " . scope_sql('pesanan'), [$id]);
        log_aktivitas('Pesanan dibatalkan', $ps['no_pesanan'] ?? '#' . $id);
        flash_set('success', 'Pesanan dibatalkan.');
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['hapus_pesanan'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya super admin yang dapat menghapus pesanan.');
            header('Location: index.php?p=pesanan');
            exit;
        }
        $id = (int)$_POST['hapus_pesanan'];
        $ps = DB::one('SELECT no_pesanan, pelanggan FROM pesanan WHERE id = ?', [$id]);
        DB::run('UPDATE pesanan SET deleted = 1 WHERE id = ?', [$id]);
        log_aktivitas('Hapus pesanan', ($ps['no_pesanan'] ?? '#' . $id) . ' | ' . ($ps['pelanggan'] ?? ''));
        flash_set('success', 'Pesanan dihapus. Data tetap tersimpan di Histori Pesanan.');
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['edit_pesanan'])) {
        $id = (int)$_POST['edit_pesanan'];
        $pelanggan = trim($_POST['pelanggan'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $total = (float)($_POST['total'] ?? 0);
        $dp = (float)($_POST['dp'] ?? 0);
        $metode = trim($_POST['metode'] ?? 'Tunai');
        $estimasi = trim($_POST['estimasi'] ?? '');
        $backStatus = trim($_POST['filter'] ?? 'ALL');
        $ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
        if (!$ps) {
            flash_set('error', 'Pesanan tidak ditemukan.');
        } elseif ($pelanggan === '' || $total <= 0) {
            flash_set('error', 'Nama pelanggan dan total wajib diisi.');
        } else {
            if ($dp > $total) {
                $dp = $total;
            }
            $sudahBayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id = ?", [$id])['t'];
            $delta = max(0, $dp - (float)$ps['dp']);
            if ($delta > 0) {
                DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id) VALUES (?,?,?,?,?,?,?,?)',
                    ['pesanan', $id, date('Y-m-d H:i:s'), $delta, $metode, 'Perubahan DP / tambah uang muka (edit)', $metode === 'QRIS' ? 'Menunggu QRIS' : 'Lunas', $_SESSION['user_id']]);
                $sudahBayar += $delta;
            }
            $sisa = max(0, $total - $sudahBayar);
            $status = $ps['status'];
            if (in_array($status, ['DP', 'Lunas'])) {
                $status = $sisa <= 0 ? 'Lunas' : 'DP';
            }
            DB::run('UPDATE pesanan SET pelanggan = ?, telepon = ?, deskripsi = ?, total = ?, dp = ?, sisa = ?, status = ?, estimasi = ? WHERE id = ?',
                [$pelanggan, $telepon, $deskripsi, $total, $dp, $sisa, $status, $estimasi, $id]);
            log_aktivitas('Edit pesanan', $ps['no_pesanan'] . ' | total baru ' . $total);
            flash_set('success', 'Pesanan diperbarui.');
        }
        header('Location: index.php?p=pesanan' . ($backStatus !== 'ALL' ? '&status=' . rawurlencode($backStatus) : ''));
        exit;
    }
}

$filter = $_GET['status'] ?? 'ALL';
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$q = trim($_GET['q'] ?? '');
$where = 'WHERE ' . scope_sql('pesanan') . ' AND deleted = 0';
$args = [];
if ($filter !== 'ALL') {
    $where .= ' AND status = ?';
    $args[] = $filter;
}
if ($from !== '') {
    $where .= ' AND date(tgl) >= ?';
    $args[] = $from;
}
if ($to !== '') {
    $where .= ' AND date(tgl) <= ?';
    $args[] = $to;
}
if ($q !== '') {
    $where .= ' AND (no_pesanan LIKE ? OR pelanggan LIKE ? OR deskripsi LIKE ?)';
    $args[] = "%$q%";
    $args[] = "%$q%";
    $args[] = "%$q%";
}
$pesanan = DB::q("SELECT * FROM pesanan $where ORDER BY id DESC", $args);

$usersMap = [];
foreach (DB::q('SELECT id, username FROM users') as $u) {
    $usersMap[(int)$u['id']] = $u['username'];
}

$produkHitung = DB::q('SELECT p.id, p.nama, p.satuan, p.harga_jual, k.nama AS kategori
                       FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
                       WHERE p.harga_jual > 0 ORDER BY p.nama');

$judul = 'Pesanan';
require __DIR__ . '/../layout/header.php';
?>
<script>
window.PESANAN_PRODUK = <?= json_encode(array_map(function ($p) {
    return ['id' => (int)$p['id'], 'nama' => $p['nama'], 'satuan' => $p['satuan'],
        'harga' => (float)$p['harga_jual'], 'kategori' => $p['kategori'] ?? ''];
}, $produkHitung)) ?>;
</script>
<h2>Pesanan / Order Percetakan</h2>

<div class="grid2">
    <div class="panel">
        <h3>Buat Pesanan Baru</h3>
        <form method="post">
            <label>Nama Pelanggan
                <input type="text" name="pelanggan" required placeholder="Nama / instansi">
            </label>
            <label>No. Telepon
                <input type="text" name="telepon" placeholder="opsional">
            </label>
            <label>Deskripsi Pesanan
                <textarea name="deskripsi" id="deskripsiPesanan" rows="3" placeholder="Contoh: banner 2x1 m, kartu nama 2 sisi 500 pcs"></textarea>
            </label>
            <label>Produk (opsional - hitung otomatis m2)
                <select name="produk_hitung" id="produkHitung">
                    <option value="">- Pilih produk -</option>
                    <?php foreach ($produkHitung as $ph): ?>
                        <option value="<?= $ph['id'] ?>"><?= e($ph['nama']) ?> (<?= rp($ph['harga_jual']) ?>/<?= e($ph['satuan']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div id="m2Box" class="hidden">
                <div class="form-row">
                    <label>Panjang (m)
                        <input type="number" id="panjangM" min="0" step="0.01" value="1">
                    </label>
                    <label>Lebar (m)
                        <input type="number" id="lebarM" min="0" step="0.01" value="1">
                    </label>
                </div>
            </div>
            <div class="form-row">
                <label>Jumlah (Qty)
                    <input type="number" id="qtyPesanan" min="0" step="0.01" value="1">
                </label>
                <p class="muted kecil" id="m2Info"></p>
                <button type="button" class="btn kecil" id="btnTambahItem">+ Tambah ke Pesanan</button>
            </div>
            <table id="tabelItem" style="margin-top:6px;">
                <thead>
                    <tr><th style="text-align:left;">Nama</th><th style="width:60px;text-align:center;">Qty</th><th style="width:90px;text-align:right;">Harga</th><th style="width:100px;text-align:right;">Subtotal</th><th style="width:30px;"></th></tr>
                </thead>
                <tbody id="daftarItem"></tbody>
            </table>
            <p class="muted kecil" id="itemInfo">Belum ada item. Pilih produk lalu klik &quot;+ Tambah ke Pesanan&quot; (bisa lebih dari satu).</p>
            <div class="form-row">
                <label>Total Harga (Rp)
                    <input type="number" name="total" id="totalPesanan" min="0" step="0.01" required>
                </label>
                <label>Uang Muka / DP (Rp)
                    <input type="number" name="dp" min="0" step="0.01" value="0">
                </label>
                <label>Metode Pembayaran
                    <select name="metode">
                        <option>Tunai</option>
                        <option>QRIS</option>
                        <option>Transfer</option>
                    </select>
                </label>
            </div>
            <label>Target Selesai (opsional)
                <input type="datetime-local" name="estimasi">
            </label>
            <input type="hidden" name="items_json" id="itemsPesanan">
            <button type="submit" class="btn" name="simpan_pesanan" value="1">Buat Pesanan</button>
        </form>
    </div>

    <div class="panel">
        <h3>Filter</h3>
        <nav class="filter-nav">
            <?php foreach (['ALL', 'DP', 'Lunas', 'Selesai', 'Batal'] as $st): ?>
                <a href="index.php?p=pesanan&status=<?= $st ?><?= $from !== '' ? '&from=' . rawurlencode($from) : '' ?><?= $to !== '' ? '&to=' . rawurlencode($to) : '' ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>" class="<?= $filter === $st ? 'act' : '' ?>"><?= $st === 'ALL' ? 'Semua' : $st ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="get" class="form-row">
            <input type="hidden" name="p" value="pesanan">
            <?php if ($filter !== 'ALL'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
            <input type="date" name="from" value="<?= e($from) ?>" title="Dari tanggal">
            <input type="date" name="to" value="<?= e($to) ?>" title="Sampai tanggal">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari no / pelanggan / deskripsi...">
            <button type="submit" class="btn">Filter</button>
            <?php if ($from !== '' || $to !== '' || $q !== ''): ?>
                <a class="btn abu" href="index.php?p=pesanan&status=<?= e($filter) ?>">Bersihkan</a>
            <?php endif; ?>
        </form>
        <?php if (setting('qris_image')): ?>
            <p style="margin-top:10px;">
                <button type="button" class="btn" id="btnQris">Tampilkan QRIS Pembayaran</button>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (setting('qris_image')): ?>
<div id="modalQris" class="modal hidden">
    <div class="modal-box">
        <h3>QRIS Pembayaran</h3>
        <img src="<?= e(setting('qris_image')) ?>" alt="QRIS">
        <button type="button" class="btn" id="btnTutupQris">Tutup</button>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Daftar Pesanan (<?= count($pesanan) ?>)</h3>
    <?php if (!$pesanan): ?>
        <p class="muted">Tidak ada pesanan.</p>
    <?php endif; ?>
    <?php foreach ($pesanan as $ps): ?>
        <?php $pmQris = DB::one("SELECT * FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ? AND status = 'Menunggu QRIS' ORDER BY id DESC LIMIT 1", [$ps['id']]); ?>
        <div class="order-card">
            <div class="order-head">
                <div>
                    <strong><?= e($ps['no_pesanan']) ?></strong>
                    <span class="muted"><?= tgl($ps['tgl']) ?></span>
                    <?php if (!empty($ps['user_id']) && isset($usersMap[(int)$ps['user_id']])): ?>
                        <span class="muted kecil">oleh <?= e($usersMap[(int)$ps['user_id']]) ?></span>
                    <?php endif; ?>
                    <?php if ($pmQris): ?>
                        <span class="badge warn">QRIS Menunggu</span>
                    <?php endif; ?>
                    <?php if (is_telat($ps)): ?>
                        <span class="badge bahaya">TELAT</span>
                    <?php endif; ?>
                    <?php if (!empty($ps['estimasi'])): ?>
                        <span class="muted kecil">Target: <?= tgl($ps['estimasi']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="badge <?= e($ps['status']) ?>"><?= e($ps['status']) ?></div>
            </div>
            <div class="order-body">
                <div>
                    <strong><?= e($ps['pelanggan']) ?></strong>
                    <?php if ($ps['telepon']): ?><span class="muted"> - <?= e($ps['telepon']) ?></span><?php endif; ?>
                    <?php if ($ps['deskripsi']): ?><p class="muted"><?= e($ps['deskripsi']) ?></p><?php endif; ?>
                </div>
                <div class="order-angka">
                    <div>Total: <b><?= rp($ps['total']) ?></b></div>
                    <div class="muted">Sudah dibayar: <?= rp($ps['total'] - $ps['sisa']) ?></div>
                    <div class="<?= $ps['sisa'] > 0 ? 'bahaya' : '' ?>">Sisa: <?= rp($ps['sisa']) ?></div>
                </div>
            </div>
            <div class="order-aksi">
                <?php if ($pmQris): ?>
                    <form method="post" class="confirm inline" data-confirm="Pastikan dana QRIS sebesar <?= rp($pmQris['jumlah']) ?> sudah masuk di GoPay Merchant, lalu konfirmasi?">
                        <input type="hidden" name="konfirmasi_pembayaran" value="<?= $pmQris['id'] ?>">
                        <button type="submit" class="btn kecil ok">Konfirmasi Dana QRIS Masuk</button>
                    </form>
                <?php endif; ?>
                <details class="bayar-inline">
                    <summary class="btn kecil">Edit Pesanan</summary>
                    <form method="post" class="edit-pesanan">
                        <input type="hidden" name="edit_pesanan" value="<?= $ps['id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <label>Nama Pelanggan
                            <input type="text" name="pelanggan" value="<?= e($ps['pelanggan']) ?>" required>
                        </label>
                        <label>No. Telepon
                            <input type="text" name="telepon" value="<?= e($ps['telepon']) ?>">
                        </label>
                        <label>Deskripsi Pesanan
                            <textarea name="deskripsi" rows="2"><?= e($ps['deskripsi']) ?></textarea>
                        </label>
                        <div class="form-row">
                            <label>Total (Rp)
                                <input type="number" name="total" min="0" step="0.01" value="<?= (float)$ps['total'] ?>" required>
                            </label>
                            <label>DP / Uang Muka (Rp)
                                <input type="number" name="dp" min="0" step="0.01" value="<?= (float)$ps['dp'] ?>">
                            </label>
                            <label>Metode (untuk DP baru)
                                <select name="metode">
                                    <option>Tunai</option>
                                    <option>QRIS</option>
                                    <option>Transfer</option>
                                </select>
                            </label>
                        </div>
                        <label>Target Selesai
                            <input type="datetime-local" name="estimasi" value="<?= $ps['estimasi'] ? str_replace(' ', 'T', $ps['estimasi']) : '' ?>">
                        </label>
                        <button type="submit" class="btn kecil ok">Simpan Perubahan</button>
                    </form>
                </details>
                <a class="btn kecil" href="nota.php?id=<?= $ps['id'] ?>&t=struk">Cetak Struk</a>
                <a class="btn kecil" href="nota.php?id=<?= $ps['id'] ?>&t=a5">Cetak Nota</a>
                <?php if ($ps['status'] === 'DP' || ($ps['status'] === 'Lunas' && $ps['sisa'] > 0)): ?>
                    <details class="bayar-inline">
                        <summary class="btn kecil">Terima Pembayaran</summary>
                        <form method="post" class="form-row">
                            <input type="hidden" name="bayar_pesanan" value="<?= $ps['id'] ?>">
                            <input type="number" name="jumlah" min="0" step="0.01" value="<?= max(0, (float)$ps['sisa']) ?>" required>
                            <select name="metode">
                                <option>Tunai</option>
                                <option>QRIS</option>
                                <option>Transfer</option>
                            </select>
                            <button type="submit" class="btn kecil">Bayar</button>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if (in_array($ps['status'], ['DP', 'Lunas'])): ?>
                    <form method="post" class="confirm inline" data-confirm="Tandai pesanan <?= e($ps['no_pesanan']) ?> selesai?">
                        <input type="hidden" name="selesai_pesanan" value="<?= $ps['id'] ?>">
                        <button type="submit" class="btn kecil ok">Selesai</button>
                    </form>
                <?php endif; ?>
                <?php if ($ps['status'] !== 'Selesai'): ?>
                    <form method="post" class="confirm inline" data-confirm="Batalkan pesanan <?= e($ps['no_pesanan']) ?>?">
                        <input type="hidden" name="batal_pesanan" value="<?= $ps['id'] ?>">
                        <button type="submit" class="btn kecil bahaya">Batal</button>
                    </form>
                <?php endif; ?>
                <?php if (is_superadmin()): ?>
                    <form method="post" class="confirm inline" data-confirm="Hapus pesanan <?= e($ps['no_pesanan']) ?> dari daftar? Data tetap tersimpan di Histori Pesanan.">
                        <input type="hidden" name="hapus_pesanan" value="<?= $ps['id'] ?>">
                        <button type="submit" class="btn kecil bahaya">Hapus</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
