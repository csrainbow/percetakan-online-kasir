<?php
require_once __DIR__ . '/../config.php';
require_login();

$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d');
if ($to < $from) {
    $t = $from;
    $from = $to;
    $to = $t;
}

$userF = (int)($_GET['user'] ?? 0);
$extraP = '';
$extraPe = '';
$extraPay = '';
if (is_superadmin() && scope_user_id() === 0 && $userF > 0) {
    $extraP = " AND p.user_id = $userF";
    $extraPe = " AND pe.user_id = $userF";
    $extraPay = " AND pp.user_id = $userF";
}
$scPay = scope_user_id() > 0 ? 'pp.user_id = ' . scope_user_id() : '1=1';
$kasirList = is_superadmin() ? DB::q('SELECT id, username, role FROM users ORDER BY id') : [];

$scP = scope_sql('p');
$scPe = scope_sql('pe');
$sumPenjualan = DB::one("SELECT COALESCE(SUM(p.total),0) total, COUNT(*) c FROM penjualan p WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP", [$from, $to]);
$sumTerima = DB::one("SELECT COALESCE(SUM(pp.jumlah),0) total, COUNT(*) c FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id WHERE pp.ref_type = 'pesanan' AND date(pp.tgl) BETWEEN ? AND ? AND $scPay$extraPay", [$from, $to]);
$piutangBerjalan = DB::one("SELECT COALESCE(SUM(sisa),0) total, COUNT(*) c FROM pesanan WHERE status = 'DP' AND deleted = 0 AND " . scope_sql('pesanan'));
$perHari = DB::q("SELECT date(p.tgl) d, COUNT(*) c, COALESCE(SUM(p.total),0) t FROM penjualan p WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP GROUP BY date(p.tgl) ORDER BY d", [$from, $to]);
$detail = DB::q("SELECT p.id, p.no_invoice, p.tgl, p.metode, p.total, p.bayar, p.kembalian, p.keterangan, u.username
                 FROM penjualan p LEFT JOIN users u ON u.id = p.user_id
                 WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP ORDER BY p.id DESC", [$from, $to]);
$perProduk = DB::q("SELECT i.nama, SUM(i.qty) qty, SUM(i.subtotal) total
                    FROM penjualan_item i JOIN penjualan p ON p.id = i.penjualan_id
                    WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP GROUP BY i.nama ORDER BY total DESC", [$from, $to]);
$terimaDetail = DB::q("SELECT pp.tgl, pp.jumlah, pp.metode, pe.no_pesanan, pe.pelanggan
                       FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                       WHERE pp.ref_type = 'pesanan' AND date(pp.tgl) BETWEEN ? AND ? AND $scPay$extraPay ORDER BY pp.id DESC", [$from, $to]);
$terimaKasir = DB::one("SELECT COALESCE(SUM(pp.jumlah),0) t FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                        WHERE pp.ref_type = 'pesanan' AND date(pp.tgl) BETWEEN ? AND ?
                        AND (pp.keterangan IS NULL OR pp.keterangan NOT LIKE '%via kasir%') AND $scPay$extraPay", [$from, $to]);
$pendapatan = (float)$sumPenjualan['total'] + (float)$terimaKasir['t'];
$hpp = DB::one("SELECT COALESCE(SUM(pr.harga_beli * i.qty),0) h
                FROM penjualan_item i
                JOIN penjualan p ON p.id = i.penjualan_id
                JOIN produk pr ON pr.id = i.produk_id
                WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP", [$from, $to]);
$labaProduk = DB::q("SELECT i.nama, SUM(i.qty) qty, SUM(i.subtotal) omzet, COALESCE(SUM(pr.harga_beli * i.qty),0) hpp
                     FROM penjualan_item i
                     JOIN penjualan p ON p.id = i.penjualan_id
                     LEFT JOIN produk pr ON pr.id = i.produk_id
                     WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP
                     GROUP BY i.nama ORDER BY omzet DESC", [$from, $to]);
$labaKotor = $pendapatan - (float)$hpp['h'];

if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan-' . $from . '-sampai-' . $to . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Penjualan', $from . ' s/d ' . $to]);
    fputcsv($out, ['No', 'Invoice', 'Tanggal', 'Metode', 'Total', 'Bayar', 'Kembali', 'Kasir']);
    foreach ($detail as $i => $d) {
        fputcsv($out, [$i + 1, $d['no_invoice'], $d['tgl'], $d['metode'], $d['total'], $d['bayar'], $d['kembalian'], $d['username']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Produk Terjual']);
    fputcsv($out, ['No', 'Nama', 'Qty', 'Total']);
    foreach ($perProduk as $i => $p) {
        fputcsv($out, [$i + 1, $p['nama'], $p['qty'], $p['total']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Laba & Rugi', $from . ' s/d ' . $to]);
    fputcsv($out, ['Pendapatan', number_format($pendapatan, 0, ',', '.')]);
    fputcsv($out, ['HPP (harga pokok)', number_format((float)$hpp['h'], 0, ',', '.')]);
    fputcsv($out, ['Laba Kotor', number_format($labaKotor, 0, ',', '.')]);
    fputcsv($out, []);
    fputcsv($out, ['Laba per Produk']);
    fputcsv($out, ['No', 'Nama', 'Qty', 'Omzet', 'HPP', 'Laba']);
    foreach ($labaProduk as $i => $lp) {
        fputcsv($out, [$i + 1, $lp['nama'], $lp['qty'], $lp['omzet'], $lp['hpp'], (float)$lp['omzet'] - (float)$lp['hpp']]);
    }
    fclose($out);
    exit;
}

$judul = 'Laporan';
require __DIR__ . '/../layout/header.php';
?>
<h2>Laporan</h2>

<div class="panel">
    <form method="get" class="form-row">
        <input type="hidden" name="p" value="laporan">
        <label>Dari
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label>Sampai
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <?php if ($kasirList): ?>
            <label>Kasir
                <select name="user">
                    <option value="0" <?= $userF === 0 ? 'selected' : '' ?>>Semua Kasir</option>
                    <?php foreach ($kasirList as $k): ?>
                        <option value="<?= (int)$k['id'] ?>" <?= $userF === (int)$k['id'] ? 'selected' : '' ?>>
                            <?= e($k['username']) ?><?= $k['role'] === 'superadmin' ? ' (admin)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <button type="submit" class="btn">Tampilkan</button>
        <a class="btn" href="index.php?p=laporan&from=<?= e($from) ?>&to=<?= e($to) ?>&user=<?= $userF ?>&export=1">Export CSV</a>
    </form>
</div>

<div class="cards">
    <div class="card">
        <div class="card-label">Total Penjualan</div>
        <div class="card-value"><?= rp($sumPenjualan['total']) ?></div>
        <div class="card-sub"><?= (int)$sumPenjualan['c'] ?> transaksi</div>
    </div>
    <div class="card">
        <div class="card-label">Pembayaran Pesanan Masuk</div>
        <div class="card-value"><?= rp($sumTerima['total']) ?></div>
        <div class="card-sub"><?= (int)$sumTerima['c'] ?> pembayaran</div>
    </div>
    <div class="card">
        <div class="card-label">Piutang Berjalan</div>
        <div class="card-value"><?= rp($piutangBerjalan['total']) ?></div>
        <div class="card-sub"><?= (int)$piutangBerjalan['c'] ?> tagihan</div>
    </div>
</div>

<div class="grid2">
    <div class="panel">
        <h3>Per Hari</h3>
        <table>
            <thead><tr><th>Tanggal</th><th>Transaksi</th><th>Total</th></tr></thead>
            <tbody>
            <?php if (!$perHari): ?><tr><td colspan="3" class="muted">Tidak ada data.</td></tr><?php endif; ?>
            <?php foreach ($perHari as $d): ?>
                <tr>
                    <td><?= tglOnly($d['d'] . ' 00:00:00') ?></td>
                    <td><?= (int)$d['c'] ?></td>
                    <td><?= rp($d['t']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>Produk Terjual</h3>
        <table>
            <thead><tr><th>Produk</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>
            <?php if (!$perProduk): ?><tr><td colspan="3" class="muted">Tidak ada data.</td></tr><?php endif; ?>
            <?php foreach ($perProduk as $p): ?>
                <tr>
                    <td><?= e($p['nama']) ?></td>
                    <td><?= qty($p['qty']) ?></td>
                    <td><?= rp($p['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <h3>Detail Transaksi Penjualan</h3>
    <table id="tabel" class="filterable">
        <thead>
        <tr>
            <th data-k="Invoice">Invoice</th>
            <th data-k="Tanggal">Tanggal</th>
            <th data-k="Metode">Metode</th>
            <th data-k="Total">Total</th>
            <th data-k="Bayar">Bayar</th>
            <th data-k="Kembali">Kembali</th>
            <th data-k="Kasir">Kasir</th>
            <th data-k="Struk">Struk</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$detail): ?><tr><td colspan="8" class="muted">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($detail as $d): ?>
            <tr data-s="<?= e(strtolower($d['no_invoice'] . ' ' . $d['tgl'] . ' ' . $d['metode'] . ' ' . $d['username'] . ' ' . number_format((float)$d['total'], 0, ',', '.'))) ?>">
                <td><?= e($d['no_invoice']) ?></td>
                <td><?= tgl($d['tgl']) ?></td>
                <td><?= e($d['metode']) ?></td>
                <td><?= rp($d['total']) ?></td>
                <td><?= rp($d['bayar']) ?></td>
                <td><?= rp($d['kembalian']) ?></td>
                <td><?= e($d['username'] ?? '-') ?></td>
                <td><a class="btn-link" href="struk.php?id=<?= $d['id'] ?>">Cetak</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel">
    <h3>Laba & Rugi (<?= tglOnly($from . ' 00:00:00') ?> s/d <?= tglOnly($to . ' 00:00:00') ?>)</h3>
    <div class="cards">
        <div class="card">
            <div class="card-label">Pendapatan</div>
            <div class="card-value"><?= rp($pendapatan) ?></div>
            <div class="card-sub">Penjualan kasir + pembayaran pesanan</div>
        </div>
        <div class="card">
            <div class="card-label">HPP (Harga Pokok)</div>
            <div class="card-value"><?= rp($hpp['h']) ?></div>
            <div class="card-sub">Modal produk terjual (harga beli)</div>
        </div>
        <div class="card">
            <div class="card-label">Laba Kotor</div>
            <div class="card-value <?= $labaKotor >= 0 ? 'baik' : 'bahaya' ?>"><?= rp($labaKotor) ?></div>
            <div class="card-sub">Pendapatan - HPP</div>
        </div>
    </div>
    <table>
        <thead><tr><th>Produk</th><th>Qty</th><th>Omzet</th><th>HPP</th><th>Laba</th></tr></thead>
        <tbody>
        <?php if (!$labaProduk): ?><tr><td colspan="5" class="muted">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($labaProduk as $lp): ?>
            <tr>
                <td><?= e($lp['nama']) ?></td>
                <td><?= qty($lp['qty']) ?></td>
                <td><?= rp($lp['omzet']) ?></td>
                <td><?= rp($lp['hpp']) ?></td>
                <td class="<?= (float)$lp['omzet'] - (float)$lp['hpp'] >= 0 ? 'baik' : 'bahaya' ?>"><?= rp((float)$lp['omzet'] - (float)$lp['hpp']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted kecil">Catatan: HPP dihitung dari harga beli produk di transaksi kasir. Pembayaran pesanan (pekerjaan jasa/custom) dianggap pendapatan tanpa HPP. Pembayaran pesanan yang ditandai "via kasir" tidak dihitung lagi karena sudah masuk di penjualan kasir.</p>
</div>

<div class="panel">
    <h3>Pembayaran Pesanan Diterima</h3>
    <table>
        <thead><tr><th>Tanggal</th><th>No Pesanan</th><th>Pelanggan</th><th>Jumlah</th><th>Metode</th></tr></thead>
        <tbody>
        <?php if (!$terimaDetail): ?><tr><td colspan="5" class="muted">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($terimaDetail as $p): ?>
            <tr>
                <td><?= tgl($p['tgl']) ?></td>
                <td><?= e($p['no_pesanan']) ?></td>
                <td><?= e($p['pelanggan']) ?></td>
                <td><?= rp($p['jumlah']) ?></td>
                <td><?= e($p['metode']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
