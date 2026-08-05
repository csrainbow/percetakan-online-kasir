<?php
require_once __DIR__ . '/config.php';
require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$id = (int)($_GET['id'] ?? 0);
$p = DB::one('SELECT * FROM penjualan WHERE id = ?', [$id]);
if (!$p || (scope_user_id() !== 0 && (int)$p['user_id'] !== scope_user_id())) {
    flash_set('error', 'Transaksi tidak ditemukan.');
    header('Location: index.php');
    exit;
}
$items = DB::q('SELECT * FROM penjualan_item WHERE penjualan_id = ?', [$id]);
$user = DB::one('SELECT username FROM users WHERE id = ?', [$p['user_id']]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk <?= e($p['no_invoice']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/print.js"></script>
</head>
<body class="<?= setting('struk_lebar', '80') === '58' ? 'struk-lebar-58' : '' ?>">
<div class="no-print aksi-struk">
    <a class="btn" href="index.php">Kembali</a>
    <a class="btn" href="nota.php?ref=penjualan&id=<?= $p['id'] ?>&t=a5">Cetak Nota A5</a>
    <button class="btn" onclick="cetakNota()">Cetak Struk</button>
</div>

<div class="struk">
    <div class="center">
        <h3><?= e(setting('nama_toko')) ?></h3>
        <p class="muted kecil"><?= e(setting('alamat')) ?></p>
        <p class="muted kecil"><?= e(setting('telp')) ?></p>
    </div>
    <hr>
    <table class="meta">
        <tr><td>No</td><td>: <?= e($p['no_invoice']) ?></td></tr>
        <tr><td>Tanggal</td><td>: <?= tgl($p['tgl']) ?></td></tr>
        <tr><td>Metode</td><td>: <?= e($p['metode']) ?></td></tr>
        <?php if (($p['status'] ?? '') === 'Menunggu QRIS'): ?>
            <tr><td>Status</td><td>: Menunggu konfirmasi QRIS</td></tr>
        <?php endif; ?>
        <tr><td>Kasir</td><td>: <?= e($user['username'] ?? '-') ?></td></tr>
    </table>
    <hr>
    <table class="items">
        <?php foreach ($items as $i): ?>
            <tr>
                <td colspan="2"><?= e($i['nama']) ?></td>
            </tr>
            <tr class="muted">
                <td><?= qty($i['qty']) ?> x <?= rp($i['harga']) ?></td>
                <td class="kanan"><?= rp($i['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <hr>
    <table class="meta">
        <tr><td>Total</td><td class="kanan"><b><?= rp($p['total']) ?></b></td></tr>
        <tr><td>Bayar</td><td class="kanan"><?= rp($p['bayar']) ?></td></tr>
        <tr><td>Kembalian</td><td class="kanan"><?= rp($p['kembalian']) ?></td></tr>
    </table>
    <hr>
    <?php if (setting('qris_image')): ?>
        <div class="center">
            <p class="muted kecil">Scan untuk pembayaran QRIS</p>
            <img class="qris-struk" src="<?= e(setting('qris_image')) ?>" alt="QRIS">
        </div>
    <?php endif; ?>
    <?php if (setting('footer_struk')): ?>
        <p class="center muted kecil"><?= e(setting('footer_struk')) ?></p>
    <?php endif; ?>
</div>

<script>
if (location.search.includes('auto=1')) {
    setTimeout(function () { window.print(); }, 400);
}
</script>
<style>
@media print {
    @page { size: <?= setting('struk_lebar', '80') === '58' ? '48mm' : '80mm' ?> auto; margin: 0; }
    body { margin: 0 !important; padding: 0 !important; }
    .struk { margin: 0 !important; padding: 0 !important; }
}
</style>
</body>
</html>
