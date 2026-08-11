<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['konfirmasi_pembayaran'])) {
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
    header('Location: index.php?p=piutang');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bayar_piutang'])) {
    $id = (int)$_POST['bayar_piutang'];
    $jumlah = (float)($_POST['jumlah'] ?? 0);
    $metode = trim($_POST['metode'] ?? 'Tunai');
    $ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND ' . scope_sql('pesanan'), [$id]);
    if (!$ps || $ps['status'] !== 'DP') {
        flash_set('error', 'Piutang tidak ditemukan.');
    } elseif ($jumlah <= 0) {
        flash_set('error', 'Jumlah pembayaran tidak valid.');
    } else {
        $sisa = max(0, (float)$ps['sisa']);
        if ($jumlah > $sisa) {
            $jumlah = $sisa;
        }
        DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id) VALUES (?,?,?,?,?,?,?,?)',
            ['pesanan', $id, date('Y-m-d H:i:s'), $jumlah, $metode, 'Pelunasan piutang', $metode === 'QRIS' ? 'Menunggu QRIS' : 'Lunas', $_SESSION['user_id']]);
        $sisaBaru = $sisa - $jumlah;
        $status = $sisaBaru <= 0 ? 'Lunas' : 'DP';
        DB::run('UPDATE pesanan SET sisa = ?, status = ? WHERE id = ?', [$sisaBaru, $status, $id]);
        log_aktivitas('Pelunasan piutang', $ps['no_pesanan'] . ' | ' . $jumlah . ' | ' . $metode);
        if (setting('wa_notif_pembayaran') && setting('wa_admin_number') !== '') {
            wa_send(setting('wa_admin_number'), "💵 *PELUNASAN PIUTANG*\nNo: " . $ps['no_pesanan'] . "\nPelanggan: " . $ps['pelanggan'] . "\nJumlah: " . rp($jumlah) . "\nMetode: " . $metode . "\nWaktu: " . date('d/m/Y H:i'));
        }
        wa_pelanggan([
            'id' => $id,
            'no_pesanan' => $ps['no_pesanan'],
            'pelanggan' => $ps['pelanggan'],
            'telepon' => $ps['telepon'],
            'total' => $ps['total'],
            'status' => $status,
        ], $sisaBaru <= 0 ? 'lunas' : 'dp');
        flash_set('success', 'Pembayaran piutang diterima.');
    }
    header('Location: index.php?p=piutang');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hapus_pesanan'])) {
    if (!is_superadmin()) {
        flash_set('error', 'Hanya super admin yang dapat menghapus pesanan.');
        header('Location: index.php?p=piutang');
        exit;
    }
    $id = (int)$_POST['hapus_pesanan'];
    $ps = DB::one('SELECT no_pesanan, pelanggan FROM pesanan WHERE id = ?', [$id]);
    DB::run('UPDATE pesanan SET deleted = 1 WHERE id = ?', [$id]);
    log_aktivitas('Hapus pesanan', ($ps['no_pesanan'] ?? '#' . $id) . ' | ' . ($ps['pelanggan'] ?? ''));
    flash_set('success', 'Pesanan dihapus. Data tetap tersimpan di Histori Pesanan.');
    header('Location: index.php?p=piutang');
    exit;
}

$piutang = DB::q("SELECT * FROM pesanan WHERE status = 'DP' AND deleted = 0 AND " . scope_sql('pesanan') . ' ORDER BY tgl ASC');

$judul = 'Piutang';
require __DIR__ . '/../layout/header.php';
?>

<h2>Piutang (Tagihan Belum Lunas)</h2>

<?php $totalPiutang = 0; ?>
<?php foreach ($piutang as $ps): $totalPiutang += (float)$ps['sisa']; endforeach; ?>

<div class="cards">
    <div class="card">
        <div class="card-label">Total Piutang Berjalan</div>
        <div class="card-value"><?= rp($totalPiutang) ?></div>
        <div class="card-sub"><?= count($piutang) ?> tagihan</div>
    </div>
</div>

<?php if (setting('qris_image')): ?>
<div class="panel">
    <button type="button" class="btn" id="btnQris">Tampilkan QRIS Pembayaran</button>
</div>
<div id="modalQris" class="modal hidden">
    <div class="modal-box">
        <h3>QRIS Pembayaran</h3>
        <img src="<?= e(setting('qris_image')) ?>" alt="QRIS">
        <button type="button" class="btn" id="btnTutupQris">Tutup</button>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <?php if (!$piutang): ?>
        <p class="muted">Tidak ada piutang. Semua tagihan lunas.</p>
    <?php endif; ?>
    <?php foreach ($piutang as $ps): $pmQris = DB::one("SELECT * FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ? AND status = 'Menunggu QRIS' ORDER BY id DESC LIMIT 1", [$ps['id']]); ?>
        <div class="order-card">
            <div class="order-head">
                <div>
                    <strong><?= e($ps['no_pesanan']) ?></strong>
                    <span class="muted"><?= tgl($ps['tgl']) ?></span>
                    <?php if ($pmQris): ?>
                        <span class="badge warn">QRIS Menunggu</span>
                    <?php endif; ?>
                </div>
                <div class="badge DP"><?= e(pembayaran_status_label($ps['total'] - $ps['sisa'], $ps['total'], $ps['status'])) ?></div>
            </div>
            <div class="order-body">
                <div>
                    <strong><?= e($ps['pelanggan']) ?></strong>
                    <?php if ($ps['telepon']): ?><span class="muted"> - <?= e($ps['telepon']) ?></span><?php endif; ?>
                    <?php if ($ps['deskripsi']): ?><p class="muted"><?= e($ps['deskripsi']) ?></p><?php endif; ?>
                </div>
                <div class="order-angka">
                    <div>Total: <b><?= rp($ps['total']) ?></b></div>
                    <div class="muted">Dibayar: <?= rp($ps['total'] - $ps['sisa']) ?></div>
                    <div class="bahaya">Sisa tagihan: <b><?= rp($ps['sisa']) ?></b></div>
                </div>
            </div>
            <div class="order-aksi">
                <?php if ($pmQris): ?>
                    <form method="post" class="confirm inline" data-confirm="Pastikan dana QRIS sebesar <?= rp($pmQris['jumlah']) ?> sudah masuk di GoPay Merchant, lalu konfirmasi?">
                        <input type="hidden" name="konfirmasi_pembayaran" value="<?= $pmQris['id'] ?>">
                        <button type="submit" class="btn kecil ok">Konfirmasi Dana QRIS Masuk</button>
                    </form>
                <?php endif; ?>
                <a class="btn kecil" href="nota.php?id=<?= $ps['id'] ?>&t=struk">Cetak Struk</a>
                <a class="btn kecil" href="nota.php?id=<?= $ps['id'] ?>&t=a5">Cetak Nota</a>
                <?php if (is_superadmin()): ?>
                    <form method="post" class="confirm inline" data-confirm="Hapus pesanan <?= e($ps['no_pesanan']) ?> dari daftar? Data tetap tersimpan di Histori Pesanan.">
                        <input type="hidden" name="hapus_pesanan" value="<?= $ps['id'] ?>">
                        <button type="submit" class="btn kecil bahaya">Hapus</button>
                    </form>
                <?php endif; ?>
                <details class="bayar-inline">
                    <summary class="btn kecil">Terima Pembayaran</summary>
                    <form method="post" class="form-row">
                        <input type="hidden" name="bayar_piutang" value="<?= $ps['id'] ?>">
                        <input type="number" name="jumlah" min="0" step="500" value="<?= max(0, (float)$ps['sisa']) ?>" required>
                        <select name="metode">
                            <option>Tunai</option>
                            <option>QRIS</option>
                            <option>Transfer</option>
                        </select>
                        <button type="submit" class="btn kecil">Bayar</button>
                    </form>
                </details>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
