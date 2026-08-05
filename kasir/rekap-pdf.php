<?php
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/rekap-common.php';

$tgl = trim($_GET['tgl'] ?? date('Y-m-d'));
$userF = rekap_user_filter();
$r = rekap_data($tgl, $userF);
$namaKasir = '';
if ($userF > 0) {
    $u = DB::one('SELECT username FROM users WHERE id = ?', [$userF]);
    $namaKasir = $u['username'] ?? '';
}

$autoload = __DIR__ . '/../kasir-lib/dompdf-3.1.6/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Library PDF belum terpasang.');
}
require_once $autoload;

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:Arial, Helvetica, sans-serif; }
    body { font-size:10px; color:#111; }
    .kop { text-align:center; border-bottom:2px solid #2c3e50; padding-bottom:6px; margin-bottom:10px; }
    .kop h1 { font-size:16px; color:#2c3e50; }
    .kop p { font-size:9px; color:#555; }
    h2 { font-size:13px; text-align:center; margin:8px 0 2px; }
    .sub { text-align:center; font-size:9px; color:#555; margin-bottom:10px; }
    table { width:100%; border-collapse:collapse; margin:6px 0 12px; }
    th { background:#2c3e50; color:#fff; padding:4px 6px; text-align:left; font-size:9px; }
    td { padding:3px 6px; border-bottom:1px solid #ddd; font-size:9px; }
    .kanan { text-align:right; }
    .total { font-weight:bold; }
    .ringkas { width:100%; margin:4px 0 10px; }
    .ringkas td { border:1px solid #ccc; padding:5px 8px; text-align:center; }
    .ringkas .label { background:#f4f6f7; font-weight:bold; }
    .muted { color:#777; }
</style>
</head>
<body>
<div class="kop">
    <h1><?= e(setting('nama_toko')) ?></h1>
    <p><?= nl2br(e(setting('alamat'))) ?> | Telp: <?= e(setting('telp')) ?></p>
</div>
<h2>REKAP HARIAN</h2>
<div class="sub"><?= tgl_ind($r['tgl'] . ' 00:00:00') ?><?= $namaKasir ? ' - ' . e($namaKasir) : '' ?> | Dicetak: <?= date('d/m/Y H:i') ?> oleh <?= e($_SESSION['username'] ?? '') ?></div>

<table class="ringkas">
    <tr>
        <td class="label">Penjualan Kasir</td>
        <td class="label">Pembayaran Pesanan</td>
        <td class="label">Pendapatan</td>
        <td class="label">HPP</td>
        <td class="label">Laba Kotor</td>
    </tr>
    <tr>
        <td class="total"><?= rp($r['sumPenjualan']['total']) ?><br><span class="muted">(<?= (int)$r['sumPenjualan']['c'] ?> trx)</span></td>
        <td class="total"><?= rp($r['sumPembayaran']['total']) ?><br><span class="muted">(<?= (int)$r['sumPembayaran']['c'] ?> bayar)</span></td>
        <td class="total"><?= rp($r['pendapatan']) ?></td>
        <td class="total"><?= rp($r['hpp']) ?></td>
        <td class="total"><?= rp($r['labaKotor']) ?></td>
    </tr>
</table>

<h3>Transaksi Kasir</h3>
<table>
    <thead><tr><th>No Invoice</th><th>Jam</th><th>Metode</th><th class="kanan">Total</th><th class="kanan">Bayar</th><th class="kanan">Kembali</th><th>Kasir</th></tr></thead>
    <tbody>
    <?php if (!$r['penjualan']): ?>
        <tr><td colspan="7" class="muted">Tidak ada transaksi.</td></tr>
    <?php endif; ?>
    <?php foreach ($r['penjualan'] as $p): ?>
        <tr>
            <td><?= e($p['no_invoice']) ?></td>
            <td><?= date('H:i', strtotime($p['tgl'])) ?></td>
            <td><?= e($p['metode']) ?></td>
            <td class="kanan"><?= rp($p['total']) ?></td>
            <td class="kanan"><?= rp($p['bayar']) ?></td>
            <td class="kanan"><?= rp($p['kembalian']) ?></td>
            <td><?= e($p['username'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h3>Pembayaran Pesanan Diterima</h3>
<table>
    <thead><tr><th>Jam</th><th>No Pesanan</th><th>Pelanggan</th><th class="kanan">Jumlah</th><th>Metode</th></tr></thead>
    <tbody>
    <?php if (!$r['pembayaran']): ?>
        <tr><td colspan="5" class="muted">Tidak ada pembayaran.</td></tr>
    <?php endif; ?>
    <?php foreach ($r['pembayaran'] as $p): ?>
        <tr>
            <td><?= date('H:i', strtotime($p['tgl'])) ?></td>
            <td><?= e($p['no_pesanan']) ?></td>
            <td><?= e($p['pelanggan']) ?></td>
            <td class="kanan"><?= rp($p['jumlah']) ?></td>
            <td><?= e($p['metode']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf = new Dompdf\Dompdf();
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$filename = 'rekap-' . $tgl . ($namaKasir ? '-' . preg_replace('/[^A-Za-z0-9]+/', '', $namaKasir) : '') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $dompdf->output();
