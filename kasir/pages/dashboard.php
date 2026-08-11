<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hapus_penjualan'])) {
    $id = (int)$_POST['hapus_penjualan'];
    $back = $_POST['back'] ?? 'penjualan';
    $penj = DB::one('SELECT * FROM penjualan WHERE id = ? AND ' . scope_sql('penjualan'), [$id]);
    if ($penj) {
        $items = DB::q('SELECT * FROM penjualan_item WHERE penjualan_id = ?', [$id]);
        foreach ($items as $i) {
            if ($i['produk_id']) {
                DB::run('UPDATE produk SET stok = stok + ? WHERE id = ?', [$i['qty'], $i['produk_id']]);
            }
        }
        $pesananTerkait = DB::q("SELECT DISTINCT ref_id FROM pembayaran WHERE ref_type = 'pesanan' AND keterangan LIKE ?", ['%' . $penj['no_invoice'] . '%']);
        foreach ($pesananTerkait as $pt) {
            DB::run("DELETE FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ? AND keterangan LIKE ?", [$pt['ref_id'], '%' . $penj['no_invoice'] . '%']);
            $sisaBayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ?", [$pt['ref_id']])['t'];
            if ($sisaBayar <= 0) {
                DB::run('UPDATE pesanan SET deleted = 1 WHERE id = ?', [$pt['ref_id']]);
            }
        }
        DB::run('DELETE FROM penjualan_item WHERE penjualan_id = ?', [$id]);
        DB::run('DELETE FROM penjualan WHERE id = ?', [$id]);
        log_aktivitas('Hapus transaksi', $penj['no_invoice']);
        flash_set('success', 'Transaksi dihapus, stok dikembalikan.');
    } else {
        flash_set('error', 'Transaksi tidak ditemukan.');
    }
    header('Location: index.php?p=' . ($back === 'dashboard' ? 'dashboard' : 'penjualan'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hapus_pesanan'])) {
    if (!is_superadmin()) {
        flash_set('error', 'Hanya super admin yang dapat menghapus pesanan.');
        header('Location: index.php?p=dashboard');
        exit;
    }
    $id = (int)$_POST['hapus_pesanan'];
    $ps = DB::one('SELECT no_pesanan, pelanggan FROM pesanan WHERE id = ?', [$id]);
    DB::run('UPDATE pesanan SET deleted = 1 WHERE id = ?', [$id]);
    log_aktivitas('Hapus pesanan', ($ps['no_pesanan'] ?? '#' . $id) . ' | ' . ($ps['pelanggan'] ?? ''));
    flash_set('success', 'Pesanan dihapus. Data tetap tersimpan di Histori Pesanan.');
    header('Location: index.php?p=dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['konfirmasi_penjualan'])) {
    $id = (int)$_POST['konfirmasi_penjualan'];
    $penj = DB::one('SELECT * FROM penjualan WHERE id = ? AND ' . scope_sql('penjualan'), [$id]);
    if ($penj && $penj['status'] === 'Menunggu QRIS') {
        DB::run("UPDATE penjualan SET status = 'Lunas' WHERE id = ?", [$id]);
        log_aktivitas('Konfirmasi QRIS', $penj['no_invoice'] . ' | ' . $penj['total']);
        flash_set('success', 'Pembayaran QRIS dikonfirmasi. Transaksi sah.');
    } else {
        flash_set('error', 'Transaksi tidak ditemukan.');
    }
    header('Location: index.php?p=dashboard');
    exit;
}

$sc = scope_sql('p');
$hariIni = DB::one("SELECT COALESCE(SUM(total),0) total, SUM(c) c FROM (
    SELECT p.total AS total, 1 AS c FROM penjualan p WHERE date(p.tgl) = date('now','localtime') AND $sc
    UNION ALL
    SELECT pe.total AS total, 1 AS c FROM pesanan pe
    WHERE date(pe.tgl) = date('now','localtime') AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "
)");
$piutang = DB::one("SELECT COALESCE(SUM(pe.sisa),0) sisa, COUNT(*) c FROM pesanan pe WHERE pe.status = 'DP' AND pe.deleted = 0 AND " . scope_sql('pe'));
$pesananAktif = DB::one("SELECT COUNT(*) c FROM pesanan pe WHERE pe.status IN ('DP','Lunas') AND pe.deleted = 0 AND " . scope_sql('pe'));
$stokMenipis = DB::q("SELECT * FROM produk WHERE stok <= stok_min ORDER BY (stok - stok_min) ASC LIMIT 5");
$pesananTerbaru = DB::q("SELECT * FROM pesanan pe WHERE " . scope_sql('pe') . " AND pe.deleted = 0 AND pe.status != 'Batal' ORDER BY pe.id DESC LIMIT 5");
if (!$pesananTerbaru) {
    $pesananTerbaru = DB::q("SELECT * FROM penjualan p WHERE $sc ORDER BY p.id DESC LIMIT 5");
}
$penjualan7 = DB::q("SELECT date(p.tgl) d, COUNT(*) c, COALESCE(SUM(p.total),0) t FROM penjualan p WHERE date(p.tgl) >= date('now','localtime','-6 days') AND $sc GROUP BY date(p.tgl) ORDER BY d");
$transaksiTerbaru = DB::q("SELECT * FROM penjualan p WHERE $sc ORDER BY p.id DESC LIMIT 8");

$gab7 = [];
foreach ($penjualan7 as $r) {
    $gab7[$r['d']] = ['d' => $r['d'], 'kasir' => (int)$r['c'], 'pesanan' => 0, 't' => (float)$r['t']];
}
$pesanan7 = DB::q("SELECT date(pe.tgl) d, COUNT(*) c, COALESCE(SUM(pe.total),0) t FROM pesanan pe
                   WHERE date(pe.tgl) >= date('now','localtime','-6 days') AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "
                   GROUP BY date(pe.tgl)");
foreach ($pesanan7 as $r) {
    if (!isset($gab7[$r['d']])) {
        $gab7[$r['d']] = ['d' => $r['d'], 'kasir' => 0, 'pesanan' => 0, 't' => 0.0];
    }
    $gab7[$r['d']]['pesanan'] = (int)$r['c'];
    $gab7[$r['d']]['t'] += (float)$r['t'];
}
$aktivitas7 = array_values($gab7);
usort($aktivitas7, function ($a, $b) {
    return strcmp($a['d'], $b['d']);
});

$pesananTerbaruTr = DB::q("SELECT pe.no_pesanan, pe.tgl, pe.total, pe.status, pe.id, pe.sisa FROM pesanan pe
                           WHERE " . scope_sql('pe') . " AND pe.deleted = 0 AND pe.status != 'Batal' ORDER BY pe.id DESC LIMIT 8");
$gabTr = [];
foreach ($transaksiTerbaru as $tr) {
    $gabTr[] = ['jenis' => 'Kasir', 'no' => $tr['no_invoice'], 'tgl' => $tr['tgl'], 'total' => (float)$tr['total'],
        'metode' => $tr['metode'], 'status' => $tr['status'] ?? 'Lunas', 'id' => $tr['id']];
}
foreach ($pesananTerbaruTr as $ps) {
    $gabTr[] = ['jenis' => 'Pesanan', 'no' => $ps['no_pesanan'], 'tgl' => $ps['tgl'], 'total' => (float)$ps['total'],
        'metode' => $ps['status'], 'status' => $ps['status'], 'sisa' => (float)($ps['sisa'] ?? 0), 'id' => $ps['id']];
}
usort($gabTr, function ($a, $b) {
    return strcmp($b['tgl'], $a['tgl']);
});
$transaksiTerbaru = array_slice($gabTr, 0, 8);

$perKasir = [];
if (is_superadmin() && scope_user_id() === 0) {
    $perKasir = DB::q("SELECT u.id, u.username, u.role,
        (SELECT COUNT(*) FROM penjualan p WHERE p.user_id = u.id) jml_penjualan,
        (SELECT COALESCE(SUM(p.total),0) FROM penjualan p WHERE p.user_id = u.id) total_penjualan,
        (SELECT COUNT(*) FROM pesanan pe WHERE pe.user_id = u.id AND pe.status IN ('DP','Lunas')) pesanan_aktif,
        (SELECT COALESCE(SUM(pp.jumlah),0) FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
         WHERE pp.user_id = u.id AND pp.ref_type = 'pesanan' AND pe.deleted = 0 AND pe.status != 'Batal'
         AND (pp.keterangan IS NULL OR pp.keterangan NOT LIKE '%via kasir%')) terima_pesanan
        FROM users u");
usort($perKasir, function ($a, $b) {
    return ((float)$b['total_penjualan'] + (float)$b['terima_pesanan']) <=> ((float)$a['total_penjualan'] + (float)$a['terima_pesanan']);
});
}

$judul = 'Dashboard';
require __DIR__ . '/../layout/header.php';
?>
<h2>Dashboard</h2>

<div class="cards">
    <div class="card">
        <div class="card-label">Penjualan Hari Ini</div>
        <div class="card-value"><?= rp($hariIni['total']) ?></div>
        <div class="card-sub"><?= (int)$hariIni['c'] ?> transaksi</div>
    </div>
    <div class="card">
        <div class="card-label">Piutang Berjalan</div>
        <div class="card-value"><?= rp($piutang['sisa']) ?></div>
        <div class="card-sub"><?= (int)$piutang['c'] ?> pesanan</div>
    </div>
    <div class="card">
        <div class="card-label">Pesanan Aktif</div>
        <div class="card-value"><?= (int)$pesananAktif['c'] ?></div>
        <div class="card-sub">DP / Lunas belum diambil</div>
    </div>
    <div class="card">
        <div class="card-label">Produk Stok Menipis</div>
        <div class="card-value"><?= count($stokMenipis) ?></div>
        <div class="card-sub">stok kurang dari / sama dengan stok min</div>
    </div>
</div>

<?php if ($perKasir): ?>
<div class="panel">
    <h3>Ringkasan per Kasir</h3>
    <table>
        <thead><tr><th>Kasir</th><th>Transaksi</th><th>Penjualan Kasir</th><th>Pembayaran Pesanan</th><th>Nilai Penjualan</th><th>Pesanan Aktif</th></tr></thead>
        <tbody>
        <?php foreach ($perKasir as $pk): ?>
            <tr>
                <td><?= e($pk['username']) ?><?= $pk['role'] === 'superadmin' ? ' <span class="muted">(admin)</span>' : '' ?></td>
                <td><?= (int)$pk['jml_penjualan'] ?></td>
                <td><?= rp($pk['total_penjualan']) ?></td>
                <td><?= rp($pk['terima_pesanan']) ?></td>
                <td><b><?= rp((float)$pk['total_penjualan'] + (float)$pk['terima_pesanan']) ?></b></td>
                <td><?= (int)$pk['pesanan_aktif'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="grid2">
    <div class="panel">
        <h3>Stok Menipis</h3>
        <?php if (!$stokMenipis): ?>
            <p class="muted">Semua stok aman.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Nama</th><th>Stok</th><th>Min</th></tr></thead>
                <tbody>
                <?php foreach ($stokMenipis as $s): ?>
                    <tr>
                        <td><?= e($s['nama']) ?></td>
                        <td class="badge warn"><?= qty($s['stok']) ?> <?= e($s['satuan']) ?></td>
                        <td><?= qty($s['stok_min']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Pesanan Terbaru</h3>
        <?php if (!$pesananTerbaru): ?>
            <p class="muted">Belum ada pesanan.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>No</th><th>Pelanggan</th><th>Sisa</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($pesananTerbaru as $ps): ?>
                    <?php $labelPs = !empty($ps['no_pesanan']) && !in_array($ps['status'], ['Selesai', 'Batal']) ? pembayaran_status_label((float)$ps['total'] - (float)$ps['sisa'], (float)$ps['total'], $ps['status']) : ($ps['status'] ?? 'Lunas'); ?>
                    <tr>
                        <td><?= e($ps['no_pesanan'] ?? $ps['no_invoice']) ?></td>
                        <td><?= e($ps['pelanggan'] ?? ('Transaksi ' . $ps['metode'])) ?></td>
                        <td><?= rp($ps['sisa'] ?? $ps['total']) ?></td>
                        <td>
                            <span class="badge <?= e($ps['status'] ?? 'Lunas') ?>"><?= e($labelPs) ?></span>
                            <?php if (!empty($ps['estimasi']) && is_telat($ps)): ?>
                                <span class="badge bahaya">TELAT</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (is_superadmin() && !empty($ps['no_pesanan'])): ?>
                                <form method="post" class="confirm inline" data-confirm="Hapus pesanan <?= e($ps['no_pesanan']) ?> dari daftar? Data tetap tersimpan di Histori Pesanan.">
                                    <input type="hidden" name="hapus_pesanan" value="<?= $ps['id'] ?>">
                                    <input type="hidden" name="back" value="dashboard">
                                    <button type="submit" class="btn kecil bahaya">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <h3>Transaksi Terakhir</h3>
    <?php if (!$transaksiTerbaru): ?>
        <p class="muted">Belum ada transaksi.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Jenis</th><th>No</th><th>Tanggal</th><th>Total</th><th>Status/Metode</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($transaksiTerbaru as $tr): ?>
                <tr>
                    <td><?= e($tr['jenis']) ?></td>
                    <td><?= e($tr['no']) ?></td>
                    <td><?= tgl($tr['tgl']) ?></td>
                    <td><?= rp($tr['total']) ?></td>
                    <td><?= $tr['jenis'] === 'Pesanan'
                        ? e(in_array($tr['status'], ['Selesai', 'Batal']) ? $tr['status'] : pembayaran_status_label($tr['total'] - $tr['sisa'], $tr['total'], $tr['status']))
                        : e($tr['metode']) ?></td>
                    <td>
                        <?php if ($tr['jenis'] === 'Kasir' && $tr['status'] === 'Menunggu QRIS'): ?>
                            <span class="badge warn">Menunggu QRIS</span>
                        <?php endif; ?>
                        <?php if ($tr['jenis'] === 'Pesanan'): ?>
                            <a class="btn kecil" href="nota.php?ref=pesanan&id=<?= $tr['id'] ?>&t=struk">Cetak Struk</a>
                            <a class="btn kecil" href="nota.php?ref=pesanan&id=<?= $tr['id'] ?>&t=a5">Cetak Nota</a>
                            <?php if (is_superadmin()): ?>
                                <form method="post" class="confirm inline" data-confirm="Hapus pesanan <?= e($tr['no']) ?> dari daftar? Data tetap tersimpan di Histori Pesanan.">
                                    <input type="hidden" name="hapus_pesanan" value="<?= $tr['id'] ?>">
                                    <input type="hidden" name="back" value="dashboard">
                                    <button type="submit" class="btn kecil bahaya">Hapus</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($tr['status'] === 'Menunggu QRIS'): ?>
                                <form method="post" class="confirm inline" data-confirm="Pastikan dana QRIS sudah masuk di GoPay Merchant, lalu konfirmasi transaksi <?= e($tr['no']) ?>?">
                                    <input type="hidden" name="konfirmasi_penjualan" value="<?= $tr['id'] ?>">
                                    <button type="submit" class="btn kecil ok">Konfirmasi Dana Masuk</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn kecil" href="struk.php?id=<?= $tr['id'] ?>">Cetak Struk</a>
                            <a class="btn kecil" href="nota.php?ref=penjualan&id=<?= $tr['id'] ?>&t=a5">Cetak Nota</a>
                            <a class="btn kecil" href="edit-penjualan.php?id=<?= $tr['id'] ?>&back=dashboard">Edit</a>
                            <form method="post" class="confirm inline" data-confirm="Hapus transaksi <?= e($tr['no']) ?>? Stok akan dikembalikan.">
                                <input type="hidden" name="hapus_penjualan" value="<?= $tr['id'] ?>">
                                <input type="hidden" name="back" value="dashboard">
                                <button type="submit" class="btn kecil bahaya">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Transaksi 7 Hari Terakhir</h3>
    <table id="tabel" class="filterable">
        <thead><tr><th data-k="Tanggal">Tanggal</th><th data-k="Kasir">Kasir</th><th data-k="Pesanan">Pesanan</th><th data-k="Total">Total</th></tr></thead>
        <tbody>
        <?php if (!$aktivitas7): ?>
            <tr><td colspan="4" class="muted">Belum ada data.</td></tr>
        <?php endif; ?>
        <?php foreach ($aktivitas7 as $s): ?>
            <tr data-s="<?= e($s['d'] . ' ' . tglOnly($s['d'] . ' 00:00:00') . ' ' . $s['kasir'] . ' ' . $s['pesanan'] . ' ' . number_format((float)$s['t'], 0, ',', '.')) ?>">
                <td><?= tglOnly($s['d'] . ' 00:00:00') ?></td>
                <td><?= (int)$s['kasir'] ?></td>
                <td><?= (int)$s['pesanan'] ?></td>
                <td><?= rp($s['t']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
