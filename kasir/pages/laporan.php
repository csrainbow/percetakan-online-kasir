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
$sumPesanan = DB::one("SELECT COALESCE(SUM(pe.total),0) total, COUNT(*) c FROM pesanan pe WHERE date(pe.tgl) BETWEEN ? AND ? AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "$extraPe", [$from, $to]);
$sumTerima = DB::one("SELECT COALESCE(SUM(pp.jumlah),0) total, COUNT(*) c FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id WHERE pp.ref_type = 'pesanan' AND pe.deleted = 0 AND pe.status != 'Batal' AND date(pp.tgl) BETWEEN ? AND ? AND $scPay$extraPay", [$from, $to]);
$piutangBerjalan = DB::one("SELECT COALESCE(SUM(sisa),0) total, COUNT(*) c FROM pesanan WHERE status = 'DP' AND deleted = 0 AND " . scope_sql('pesanan'));
$perHari = DB::q("SELECT date(p.tgl) d, COUNT(*) c, COALESCE(SUM(p.total),0) t FROM penjualan p WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP GROUP BY date(p.tgl) ORDER BY d", [$from, $to]);
$perHariPe = DB::q("SELECT date(pe.tgl) d, COUNT(*) c, COALESCE(SUM(pe.total),0) t FROM pesanan pe WHERE date(pe.tgl) BETWEEN ? AND ? AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "$extraPe GROUP BY date(pe.tgl)", [$from, $to]);
$perHariMap = [];
foreach ($perHari as $d) {
    $perHariMap[$d['d']] = ['d' => $d['d'], 'kasir' => (int)$d['c'], 'kasir_t' => (float)$d['t'], 'pesanan' => 0, 'pesanan_t' => 0.0];
}
foreach ($perHariPe as $d) {
    if (!isset($perHariMap[$d['d']])) {
        $perHariMap[$d['d']] = ['d' => $d['d'], 'kasir' => 0, 'kasir_t' => 0.0, 'pesanan' => 0, 'pesanan_t' => 0.0];
    }
    $perHariMap[$d['d']]['pesanan'] = (int)$d['c'];
    $perHariMap[$d['d']]['pesanan_t'] = (float)$d['t'];
}
ksort($perHariMap);
$detail = DB::q("SELECT p.id, p.no_invoice, p.tgl, p.metode, p.total, p.bayar, p.kembalian, p.keterangan, u.username
                 FROM penjualan p LEFT JOIN users u ON u.id = p.user_id
                 WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP ORDER BY p.id DESC", [$from, $to]);
$pesananDetail = DB::q("SELECT pe.id, pe.no_pesanan, pe.tgl, pe.pelanggan, pe.total, pe.sisa, pe.status
                        FROM pesanan pe
                        WHERE date(pe.tgl) BETWEEN ? AND ? AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "$extraPe ORDER BY pe.id DESC", [$from, $to]);
$perProduk = DB::q("SELECT i.nama, SUM(i.qty) qty, SUM(i.subtotal) total
                    FROM penjualan_item i JOIN penjualan p ON p.id = i.penjualan_id
                    WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP GROUP BY i.nama ORDER BY total DESC", [$from, $to]);
$perProdukPe = DB::q("SELECT pi.nama, SUM(pi.qty) qty, SUM(pi.subtotal) total
                      FROM pesanan_item pi JOIN pesanan pe ON pe.id = pi.pesanan_id
                      WHERE date(pe.tgl) BETWEEN ? AND ? AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "$extraPe
                      GROUP BY pi.nama", [$from, $to]);
$perProdukMap = [];
foreach ($perProduk as $p) {
    $perProdukMap[$p['nama']] = ['nama' => $p['nama'], 'qty' => (float)$p['qty'], 'total' => (float)$p['total']];
}
foreach ($perProdukPe as $p) {
    if (!isset($perProdukMap[$p['nama']])) {
        $perProdukMap[$p['nama']] = ['nama' => $p['nama'], 'qty' => 0.0, 'total' => 0.0];
    }
    $perProdukMap[$p['nama']]['qty'] += (float)$p['qty'];
    $perProdukMap[$p['nama']]['total'] += (float)$p['total'];
}
uasort($perProdukMap, function ($a, $b) {
    return $b['total'] <=> $a['total'];
});
$terimaDetail = DB::q("SELECT pp.tgl, pp.jumlah, pp.metode, pe.no_pesanan, pe.pelanggan
                       FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                       WHERE pp.ref_type = 'pesanan' AND pe.deleted = 0 AND pe.status != 'Batal' AND date(pp.tgl) BETWEEN ? AND ? AND $scPay$extraPay ORDER BY pp.id DESC", [$from, $to]);
$pendapatan = (float)$sumPenjualan['total'] + (float)$sumPesanan['total'];
$hppKasir = DB::one("SELECT COALESCE(SUM(pr.harga_beli * i.qty),0) h
                FROM penjualan_item i
                JOIN penjualan p ON p.id = i.penjualan_id
                JOIN produk pr ON pr.id = i.produk_id
                WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP", [$from, $to]);
$hppPe = DB::one("SELECT COALESCE(SUM(pr.harga_beli * pi.qty),0) h
                  FROM pesanan_item pi
                  JOIN pesanan pe ON pe.id = pi.pesanan_id
                  JOIN produk pr ON pr.id = pi.produk_id
                  WHERE date(pe.tgl) BETWEEN ? AND ? AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "$extraPe", [$from, $to]);
$hpp = (float)$hppKasir['h'] + (float)$hppPe['h'];
$labaProdukKasir = DB::q("SELECT i.nama, SUM(i.qty) qty, SUM(i.subtotal) omzet, COALESCE(SUM(pr.harga_beli * i.qty),0) hpp
                     FROM penjualan_item i
                     JOIN penjualan p ON p.id = i.penjualan_id
                     LEFT JOIN produk pr ON pr.id = i.produk_id
                     WHERE date(p.tgl) BETWEEN ? AND ? AND $scP$extraP
                     GROUP BY i.nama ORDER BY omzet DESC", [$from, $to]);
$labaProdukPe = DB::q("SELECT pi.nama, SUM(pi.qty) qty, SUM(pi.subtotal) omzet, COALESCE(SUM(pr.harga_beli * pi.qty),0) hpp
                       FROM pesanan_item pi
                       JOIN pesanan pe ON pe.id = pi.pesanan_id
                       LEFT JOIN produk pr ON pr.id = pi.produk_id
                       WHERE date(pe.tgl) BETWEEN ? AND ? AND pe.status != 'Batal' AND pe.deleted = 0 AND " . scope_sql('pe') . "$extraPe
                       GROUP BY pi.nama", [$from, $to]);
$labaProduk = [];
foreach ($labaProdukKasir as $lp) {
    $labaProduk[$lp['nama']] = ['nama' => $lp['nama'], 'qty' => (float)$lp['qty'], 'omzet' => (float)$lp['omzet'], 'hpp' => (float)$lp['hpp']];
}
foreach ($labaProdukPe as $lp) {
    if (!isset($labaProduk[$lp['nama']])) {
        $labaProduk[$lp['nama']] = ['nama' => $lp['nama'], 'qty' => 0.0, 'omzet' => 0.0, 'hpp' => 0.0];
    }
    $labaProduk[$lp['nama']]['qty'] += (float)$lp['qty'];
    $labaProduk[$lp['nama']]['omzet'] += (float)$lp['omzet'];
    $labaProduk[$lp['nama']]['hpp'] += (float)$lp['hpp'];
}
uasort($labaProduk, function ($a, $b) {
    return $b['omzet'] <=> $a['omzet'];
});
$labaKotor = $pendapatan - $hpp;

if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan-' . $from . '-sampai-' . $to . '.csv');
    $out = fopen('php://output', 'w');
    $csvf = function (array $fields) use ($out) {
        fputcsv($out, $fields, ',', '"', '');
    };
    fputs($out, "\xEF\xBB\xBF");
    $csvf( ['Penjualan', $from . ' s/d ' . $to]);
    $csvf( ['No', 'Invoice', 'Tanggal', 'Metode', 'Total', 'Bayar', 'Kembali', 'Kasir']);
    foreach ($detail as $i => $d) {
        $csvf( [$i + 1, $d['no_invoice'], $d['tgl'], $d['metode'], $d['total'], $d['bayar'], $d['kembalian'], $d['username']]);
    }
    $csvf( []);
    $csvf( ['Pesanan Baru']);
    $csvf( ['No', 'No Pesanan', 'Tanggal', 'Pelanggan', 'Total', 'Status']);
    foreach ($pesananDetail as $i => $pd) {
        $st = in_array($pd['status'], ['Selesai', 'Batal']) ? $pd['status'] : pembayaran_status_label((float)$pd['total'] - (float)$pd['sisa'], (float)$pd['total'], $pd['status']);
        $csvf( [$i + 1, $pd['no_pesanan'], $pd['tgl'], $pd['pelanggan'], $pd['total'], $st]);
    }
    $csvf( []);
    $csvf( ['Produk Terjual']);
    $csvf( ['No', 'Nama', 'Qty', 'Total']);
    foreach ($perProdukMap as $idx => $p) {
        $csvf( [$idx + 1, $p['nama'], $p['qty'], $p['total']]);
    }
    $csvf( []);
    $csvf( ['Laba & Rugi', $from . ' s/d ' . $to]);
    $csvf( ['Pendapatan', number_format($pendapatan, 0, ',', '.')]);
    $csvf( ['HPP (harga pokok)', number_format($hpp, 0, ',', '.')]);
    $csvf( ['Laba Kotor', number_format($labaKotor, 0, ',', '.')]);
    $csvf( []);
    $csvf( ['Laba per Produk']);
    $csvf( ['No', 'Nama', 'Qty', 'Omzet', 'HPP', 'Laba']);
    foreach ($labaProduk as $idx => $lp) {
        $csvf( [$idx + 1, $lp['nama'], $lp['qty'], $lp['omzet'], $lp['hpp'], (float)$lp['omzet'] - (float)$lp['hpp']]);
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
        <div class="card-label">Penjualan Kasir</div>
        <div class="card-value"><?= rp($sumPenjualan['total']) ?></div>
        <div class="card-sub"><?= (int)$sumPenjualan['c'] ?> transaksi</div>
    </div>
    <div class="card">
        <div class="card-label">Pesanan Baru</div>
        <div class="card-value"><?= rp($sumPesanan['total']) ?></div>
        <div class="card-sub"><?= (int)$sumPesanan['c'] ?> pesanan dibuat di rentang ini</div>
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
            <thead><tr><th>Tanggal</th><th>Kasir</th><th>Pesanan</th><th>Total Kasir</th><th>Total Pesanan</th></tr></thead>
            <tbody>
            <?php if (!$perHariMap): ?><tr><td colspan="5" class="muted">Tidak ada data.</td></tr><?php endif; ?>
            <?php foreach ($perHariMap as $d): ?>
                <tr>
                    <td><?= tglOnly($d['d'] . ' 00:00:00') ?></td>
                    <td><?= (int)$d['kasir'] ?></td>
                    <td><?= (int)$d['pesanan'] ?></td>
                    <td><?= rp($d['kasir_t']) ?></td>
                    <td><?= rp($d['pesanan_t']) ?></td>
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
            <?php if (!$perProdukMap): ?><tr><td colspan="3" class="muted">Tidak ada data.</td></tr><?php endif; ?>
            <?php foreach ($perProdukMap as $p): ?>
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
    <h3>Detail Penjualan Kasir</h3>
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
    <h3>Detail Pesanan Baru</h3>
    <table>
        <thead><tr><th>No Pesanan</th><th>Tanggal</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$pesananDetail): ?><tr><td colspan="5" class="muted">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($pesananDetail as $pd): ?>
            <tr>
                <td><?= e($pd['no_pesanan']) ?></td>
                <td><?= tgl($pd['tgl']) ?></td>
                <td><?= e($pd['pelanggan']) ?></td>
                <td><?= rp($pd['total']) ?></td>
                <td><span class="badge <?= e($pd['status']) ?>"><?= in_array($pd['status'], ['Selesai', 'Batal']) ? e($pd['status']) : e(pembayaran_status_label((float)$pd['total'] - (float)$pd['sisa'], (float)$pd['total'], $pd['status'])) ?></span></td>
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
            <div class="card-sub">Penjualan kasir + total pesanan baru</div>
        </div>
        <div class="card">
            <div class="card-label">HPP (Harga Pokok)</div>
            <div class="card-value"><?= rp($hpp) ?></div>
            <div class="card-sub">Modal produk terjual (kasir + pesanan)</div>
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
    <p class="muted kecil">Catatan: HPP dihitung dari harga beli produk (transaksi kasir + item pesanan baru, tidak termasuk pesanan Batal). Pendapatan = nilai kasir + total pesanan baru; pembayaran pesanan yang masuk otomatis mengurangi piutang dan tidak dihitung ganda.</p>
</div>

<div class="panel">
    <h3>Pembayaran Pesanan Diterima (Kas Masuk)</h3>
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
