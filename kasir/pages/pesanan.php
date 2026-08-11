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
                $waMsg = "??????? *PESANAN BARU*\nNo: {$no}\nPelanggan: {$pelanggan}\nTotal: " . rp($total) . "\nDP: " . rp($dp) . "\nSisa: " . rp($total - $dp) . "\nMetode: {$metode}\nStatus: {$status}\nWaktu: " . date('d/m/Y H:i');
                if ($estimasi !== '') {
                    $waMsg .= "\nTarget: " . date('d/m/Y H:i', strtotime($estimasi));
                }
                wa_send(setting('wa_admin_number'), $waMsg);
            }
            if ($telepon !== '') {
                $eventBaru = 'baru';
                if ($dp > 0 && $metode !== 'QRIS') {
                    $eventBaru = $dp >= $total ? 'lunas' : 'dp';
                }
                wa_pelanggan([
                    'id' => $pid,
                    'no_pesanan' => $no,
                    'pelanggan' => $pelanggan,
                    'telepon' => $telepon,
                    'total' => $total,
                    'status' => $status,
                ], $eventBaru);
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
            $pe = DB::one('SELECT * FROM pesanan WHERE id = ?', [(int)$pm['ref_id']]);
            if ($pe && !empty($pe['telepon'])) {
                $totalBayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id = ?", [(int)$pe['id']])['t'];
                $ev = ((float)$pe['total'] - $totalBayar) <= 0 ? 'lunas' : 'dp';
                wa_pelanggan([
                    'id' => (int)$pe['id'],
                    'no_pesanan' => $pe['no_pesanan'],
                    'pelanggan' => $pe['pelanggan'],
                    'telepon' => $pe['telepon'],
                    'total' => (float)$pe['total'],
                    'status' => $ev === 'lunas' ? 'Lunas' : 'DP',
                ], $ev);
            }
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
                wa_send(setting('wa_admin_number'), "???? *PEMBAYARAN PESANAN*\nNo: " . $ps['no_pesanan'] . "\nPelanggan: " . $ps['pelanggan'] . "\nJumlah: " . rp($jumlah) . "\nMetode: " . $metode . "\nSisa: " . rp(max(0, $sisaBaru)) . "\nWaktu: " . date('d/m/Y H:i'));
            }
            wa_pelanggan([
                'id' => $id,
                'no_pesanan' => $ps['no_pesanan'],
                'pelanggan' => $ps['pelanggan'],
                'telepon' => $ps['telepon'],
                'total' => $ps['total'],
                'status' => $status,
            ], $sisaBaru <= 0 ? 'lunas' : 'dp');
            flash_set('success', 'Pembayaran diterima.');
        }
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['selesai_pesanan'])) {
        $id = (int)$_POST['selesai_pesanan'];
        $ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
        DB::run("UPDATE pesanan SET status = 'Selesai' WHERE id = ? AND status IN ('DP','Lunas') AND " . scope_sql('pesanan'), [$id]);
        log_aktivitas('Pesanan selesai', $ps['no_pesanan'] ?? '#' . $id);
        if ($ps) {
            wa_pelanggan([
                'id' => $id,
                'no_pesanan' => $ps['no_pesanan'],
                'pelanggan' => $ps['pelanggan'],
                'telepon' => $ps['telepon'],
                'total' => $ps['total'],
                'status' => 'Selesai',
            ], 'selesai');
        }
        flash_set('success', 'Pesanan ditandai selesai / diambil.');
        header('Location: index.php?p=pesanan');
        exit;
    }

    if (!empty($_POST['batal_pesanan'])) {
        if (!is_superadmin()) {
            flash_set('error', 'Membatalkan pesanan hanya untuk super admin. Kasir wajib melapor ke admin.');
            header('Location: index.php?p=pesanan');
            exit;
        }
        $id = (int)$_POST['batal_pesanan'];
        $ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
        DB::run("UPDATE pesanan SET status = 'Batal' WHERE id = ? AND status != 'Selesai' AND " . scope_sql('pesanan'), [$id]);
        log_aktivitas('Pesanan dibatalkan', $ps['no_pesanan'] ?? '#' . $id);
        if ($ps) {
            wa_pelanggan([
                'id' => $id,
                'no_pesanan' => $ps['no_pesanan'],
                'pelanggan' => $ps['pelanggan'],
                'telepon' => $ps['telepon'],
                'total' => $ps['total'],
                'status' => 'Batal',
            ], 'batal');
        }
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
        if (!is_superadmin()) {
            flash_set('error', 'Edit pesanan hanya untuk super admin. Kasir wajib melapor ke admin untuk perubahan.');
            header('Location: index.php?p=pesanan');
            exit;
        }
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
            if (in_array($status, ['DP', 'Lunas', 'Proses'])) {
                $status = $sisa <= 0 ? 'Lunas' : 'DP';
            }
            DB::run('UPDATE pesanan SET pelanggan = ?, telepon = ?, deskripsi = ?, total = ?, dp = ?, sisa = ?, status = ?, estimasi = ? WHERE id = ?',
                [$pelanggan, $telepon, $deskripsi, $total, $dp, $sisa, $status, $estimasi, $id]);
            $editItems = trim($_POST['edit_items_json'] ?? '');
            if ($editItems !== '') {
                $arr = json_decode($editItems, true);
                if (is_array($arr)) {
                    DB::run('DELETE FROM pesanan_item WHERE pesanan_id = ?', [$id]);
                    foreach ($arr as $it) {
                        $nama = trim((string)($it['nama'] ?? ''));
                        if ($nama === '') {
                            continue;
                        }
                        $q = (float)($it['qty'] ?? 0);
                        $h = (float)($it['harga'] ?? 0);
                        if ($q <= 0 || $h < 0) {
                            continue;
                        }
                        DB::run('INSERT INTO pesanan_item (pesanan_id, produk_id, nama, qty, harga, subtotal) VALUES (?,?,?,?,?,?)',
                            [$id, (int)($it['produk_id'] ?? 0) ?: null, $nama, $q, $h, (float)$q * (float)$h]);
                    }
                }
            }
            log_aktivitas('Edit pesanan', $ps['no_pesanan'] . ' | total baru ' . $total);
            if ($status === 'DP' && $ps['status'] !== 'DP' && $telepon !== '') {
                wa_pelanggan([
                    'id' => $id,
                    'no_pesanan' => $ps['no_pesanan'],
                    'pelanggan' => $pelanggan,
                    'telepon' => $telepon,
                    'total' => $total,
                    'status' => 'DP',
                ], 'dp');
            }
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

$itemsByPesanan = [];
if ($pesanan) {
    $listId = implode(',', array_map('intval', array_column($pesanan, 'id')));
    foreach (DB::q("SELECT id, pesanan_id, nama, qty, harga, subtotal FROM pesanan_item WHERE pesanan_id IN ($listId) ORDER BY pesanan_id, id") as $it) {
        $itemsByPesanan[(int)$it['pesanan_id']][] = $it;
    }
}

$usersMap = [];
foreach (DB::q('SELECT id, username FROM users') as $u) {
    $usersMap[(int)$u['id']] = $u['username'];
}

$produkHitung = DB::q('SELECT p.id, p.nama, p.satuan, p.harga_jual, k.nama AS kategori
                       FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
                       WHERE p.harga_jual > 0 ORDER BY p.nama');
$produkM2 = [];
foreach ($produkHitung as $ph) {
    $sat = strtolower((string)$ph['satuan']);
    $kat = strtolower((string)$ph['kategori']);
    if ($sat === 'm2' || strpos($kat, 'banner') !== false || strpos($kat, 'spanduk') !== false) {
        $produkM2[(int)$ph['id']] = true;
    }
}

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
        <?php $statusLabel = in_array($ps['status'], ['Selesai', 'Batal']) ? $ps['status'] : pembayaran_status_label($ps['total'] - $ps['sisa'], $ps['total'], $ps['status']); ?>
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
                <div class="badge <?= e($ps['status']) ?>"><?= e($statusLabel) ?></div>
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
                <?php if (is_superadmin()): ?>
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
<label>Produk yang Dipesan (bisa diubah)
                            <table>
                                <thead>
                                    <tr><th style="text-align:left;">Nama</th><th style="width:60px;text-align:center;">Qty</th><th style="width:120px;text-align:center;">P &times; L (M2)</th><th style="width:100px;text-align:right;">Harga</th><th style="width:110px;text-align:right;">Subtotal</th><th style="width:30px;"></th></tr>
                                </thead>
                                <tbody class="ej-tbody">
                                <?php $itemsPesan = $itemsByPesanan[$ps['id']] ?? []; ?>
                                <?php foreach ($itemsPesan as $it): ?>
                                    <?php $itM2 = isset($produkM2[(int)($it['produk_id'] ?? 0)]); ?>
                                    <tr class="ei-item<?= $itM2 ? ' ei-m2' : '' ?>" data-pid="<?= (int)($it['produk_id'] ?? 0) ?>">
                                        <td><input type="text" class="ei-nama" value="<?= e($it['nama']) ?>"></td>
                                        <td style="text-align:center;">
                                            <input type="number" class="ei-qty" min="0" step="0.01" style="width:50px;" value="<?= $itM2 ? '1' : (float)$it['qty'] ?>">
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if ($itM2): ?>
                                                <input type="number" class="ei-p" min="0" step="0.01" style="width:50px;" value="<?= (float)$it['qty'] ?>" title="Panjang (m)">
                                                &times;
                                                <input type="number" class="ei-l" min="0" step="0.01" style="width:50px;" value="1" title="Lebar (m)">
                                            <?php else: ?>
                                                <span class="muted kecil">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;"><input type="number" class="ei-harga" min="0" step="0.01" style="width:90px;" value="<?= (float)$it['harga'] ?>"></td>
                                        <td class="ei-st" style="text-align:right;"><?= rp((float)$it['qty'] * (float)$it['harga']) ?></td>
                                        <td><button type="button" class="btn kecil bahaya ei-hapus">x</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$itemsPesan): ?><tr class="ei-placeholder"><td colspan="5" class="muted">Belum ada item.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                            <span class="form-row">
                                <select class="ei-produk">
                                    <option value="">- Pilih produk -</option>
                                </select>
                                <button type="button" class="btn kecil ei-tambah">+ Tambah item</button>
                            </span>
                        </label>
                        <input type="hidden" name="edit_items_json" class="ei-json" value="">
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
                        <button type="button" class="btn kecil abu" onclick="this.closest('details').removeAttribute('open')">Batal</button>
                    </form>
                </details>
                <?php endif; ?>
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
                <?php if ($ps['status'] !== 'Selesai' && is_superadmin()): ?>
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
<script>
(function () {
    if (!window.PESANAN_PRODUK) return;
    var produk = window.PESANAN_PRODUK;
    function rpJs(n) {
        return 'Rp ' + Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }
    document.querySelectorAll('form.edit-pesanan').forEach(function (form) {
        var tbody = form.querySelector('.ej-tbody');
        var jsonInput = form.querySelector('.ei-json');
        var totalInput = form.querySelector('[name="total"]');
        if (!tbody || !jsonInput) return;
        var addSel = form.querySelector('.ei-produk');
        var addBtn = form.querySelector('.ei-tambah');
        if (addSel) {
            addSel.innerHTML = '<option value="">- Pilih produk -</option>' + produk.map(function (p) {
                return '<option value="' + p.id + '">' + p.nama + (p.satuan ? ' (' + p.satuan + ')' : '') + ' (' + rpJs(p.harga) + ')' + '</option>';
            }).join('');
        }
        function isM2p(p) {
            var sat = String(p.satuan || '').toLowerCase();
            var kat = String(p.kategori || '').toLowerCase();
            return sat === 'm2' || kat.indexOf('banner') > -1 || kat.indexOf('spanduk') > -1;
        }
        function syncJson() {
            var arr = [];
            tbody.querySelectorAll('.ei-item').forEach(function (tr) {
                var nama = tr.querySelector('.ei-nama').value.trim();
                if (!nama) return;
                var m2 = tr.classList.contains('ei-m2');
                var q = parseFloat(tr.querySelector('.ei-qty').value) || 0;
                var h = parseFloat(tr.querySelector('.ei-harga').value) || 0;
                if (m2) {
                    var p2 = parseFloat(tr.querySelector('.ei-p').value) || 0;
                    var l2 = parseFloat(tr.querySelector('.ei-l').value) || 0;
                    q = q * p2 * l2;
                }
                arr.push({ produk_id: tr.dataset.pid || null, nama: nama, qty: q, harga: h });
            });
            jsonInput.value = JSON.stringify(arr);
        }
        function recalc() {
            tbody.querySelectorAll('.ei-placeholder').forEach(function (tr) { tr.remove(); });
            var sum = 0;
            tbody.querySelectorAll('.ei-item').forEach(function (tr) {
                var m2 = tr.classList.contains('ei-m2');
                var q = parseFloat(tr.querySelector('.ei-qty').value) || 0;
                var h = parseFloat(tr.querySelector('.ei-harga').value) || 0;
                if (m2) {
                    var p2 = parseFloat(tr.querySelector('.ei-p').value) || 0;
                    var l2 = parseFloat(tr.querySelector('.ei-l').value) || 0;
                    q = q * p2 * l2;
                }
                var st = q * h;
                tr.querySelector('.ei-st').textContent = rpJs(st);
                sum += st;
            });
            if (totalInput && sum > 0) totalInput.value = sum;
            syncJson();
        }
        function makeRow(nama, qty, harga, pid, m2) {
            var tr = document.createElement('tr');
            tr.className = 'ei-item' + (m2 ? ' ei-m2' : '');
            tr.dataset.pid = pid || '';
            var tdN = document.createElement('td');
            var inN = document.createElement('input');
            inN.type = 'text'; inN.className = 'ei-nama'; inN.value = nama;
            tdN.appendChild(inN);
            var tdQ = document.createElement('td');
            tdQ.style.textAlign = 'center';
            var inQ = document.createElement('input');
            inQ.type = 'number'; inQ.className = 'ei-qty'; inQ.min = '0'; inQ.step = '0.01'; inQ.style.width = '50px'; inQ.value = qty;
            tdQ.appendChild(inQ);
            var tdU = document.createElement('td');
            tdU.style.textAlign = 'center';
            if (m2) {
                var inP = document.createElement('input');
                inP.type = 'number'; inP.className = 'ei-p'; inP.min = '0'; inP.step = '0.01'; inP.style.width = '50px'; inP.value = 1; inP.title = 'Panjang (m)';
                var inL = document.createElement('input');
                inL.type = 'number'; inL.className = 'ei-l'; inL.min = '0'; inL.step = '0.01'; inL.style.width = '50px'; inL.value = 1; inL.title = 'Lebar (m)';
                tdU.appendChild(inP);
                tdU.appendChild(document.createTextNode(' \u00d7 '));
                tdU.appendChild(inL);
            } else {
                tdU.appendChild(document.createTextNode('-'));
            }
            var tdH = document.createElement('td');
            tdH.style.textAlign = 'right';
            var inH = document.createElement('input');
            inH.type = 'number'; inH.className = 'ei-harga'; inH.min = '0'; inH.step = '0.01'; inH.style.width = '90px'; inH.value = harga;
            tdH.appendChild(inH);
            var tdS = document.createElement('td');
            tdS.className = 'ei-st';
            tdS.style.textAlign = 'right';
            var tdD = document.createElement('td');
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn kecil bahaya ei-hapus'; btn.textContent = 'x';
            tdD.appendChild(btn);
            tr.appendChild(tdN); tr.appendChild(tdQ); tr.appendChild(tdU); tr.appendChild(tdH); tr.appendChild(tdS); tr.appendChild(tdD);
            inN.addEventListener('input', recalc);
            inQ.addEventListener('input', recalc);
            if (m2) {
                inP.addEventListener('input', recalc);
                inL.addEventListener('input', recalc);
            }
            inH.addEventListener('input', recalc);
            btn.addEventListener('click', function () { tr.remove(); recalc(); });
            return tr;
        }
        if (addBtn && addSel) {
            addBtn.addEventListener('click', function () {
                var pid = parseInt(addSel.value, 10);
                var p = null;
                produk.forEach(function (x) { if (x.id === pid) p = x; });
                if (!p) { window.alert('Pilih produk terlebih dahulu.'); return; }
                tbody.appendChild(makeRow(p.nama, 1, p.harga, p.id, isM2p(p)));
                recalc();
            });
        }
        form.addEventListener('submit', syncJson);
        recalc();
    });
})();
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>

