<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nota <?= e($ps['no_pesanan']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<script src="assets/print.js"></script>
</head>
<body class="<?= setting('struk_lebar', '80') === '58' ? 'struk-lebar-58' : '' ?>">
<div class="no-print aksi-struk">
    <a class="btn" href="index.php?p=<?= e($back_page) ?>">Kembali</a>
    <button class="btn" onclick="cetakNota()">Cetak Nota</button>
</div>

<div class="struk">
    <?php if (setting('logo_struk', 'logo.png')): ?>
        <img class="logo-struk" src="<?= e(setting('logo_struk', 'logo.png')) ?>" alt="Logo">
    <?php endif; ?>
    <div class="center">
        <h3><?= e(setting('nama_toko')) ?></h3>
        <p class="muted kecil"><?= e(setting('alamat')) ?></p>
        <p class="muted kecil"><?= e(setting('telp')) ?></p>
    </div>
    <hr>
    <table class="meta">
        <tr><td>No. Pesanan</td><td>: <?= e($ps['no_pesanan']) ?></td></tr>
        <tr><td>Tanggal</td><td>: <?= tgl($ps['tgl']) ?></td></tr>
        <tr><td>Pelanggan</td><td>: <?= e($ps['pelanggan']) ?></td></tr>
        <?php if ($ps['telepon']): ?>
            <tr><td>Telepon</td><td>: <?= e($ps['telepon']) ?></td></tr>
        <?php endif; ?>
        <tr><td>Status</td><td>: <?= in_array($ps['status'], ['Selesai', 'Batal']) ? e($ps['status']) : e($ps['pembayaran_status']) ?></td></tr>
        <?php if ($ps['deskripsi']): ?>
            <tr><td>Pesanan</td><td>: <?= nl2br(e($ps['deskripsi'])) ?></td></tr>
        <?php endif; ?>
        <tr><td>Kasir</td><td>: <?= e($user['username'] ?? '-') ?></td></tr>
    </table>
    <hr>
    <table class="meta">
        <tr><td>Total Harga</td><td class="kanan"><?= rp($ps['total']) ?></td></tr>
        <tr><td>Uang Muka / DP</td><td class="kanan"><?= rp($ps['dp']) ?></td></tr>
        <tr><td>Total Dibayar</td><td class="kanan"><?= rp($totalBayar) ?></td></tr>
        <tr><td>Sisa Tagihan</td><td class="kanan"><b><?= rp($ps['sisa']) ?></b></td></tr>
    </table>
    <hr>
    <?php if ($pembayaran): ?>
        <table class="items">
            <?php foreach ($pembayaran as $pb): ?>
                <tr>
                    <td colspan="2"><?= tgl($pb['tgl']) ?> - <?= e($pb['metode']) ?></td>
                </tr>
                <tr class="muted">
                    <td><?= e($pb['keterangan'] ?: 'Pembayaran') ?></td>
                    <td class="kanan"><?= rp($pb['jumlah']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <hr>
    <?php endif; ?>
    <?php if ($ref === 'penjualan' && ($ps['status'] ?? '') === 'Menunggu QRIS'): ?>
        <p class="center muted kecil">Menunggu konfirmasi QRIS ??? transaksi sah setelah dana masuk.</p>
    <?php endif; ?>
    <?php if ($ps['pembayaran_status'] === 'Belum Bayar' || $ps['pembayaran_status'] === 'DP'): ?>
        <p class="center muted kecil">Barang akan dikirim/diambil setelah pelunasan.</p>
    <?php elseif ($ps['status'] === 'Selesai'): ?>
        <p class="center muted kecil">Pesanan sudah selesai dan diambil.</p>
    <?php endif; ?>
    <?php if (setting('qris_image')): ?>
        <div class="center">
            <p class="muted kecil">Scan untuk pembayaran QRIS</p>
            <img class="qris-struk" src="<?= e(setting('qris_image')) ?>" alt="QRIS">
        </div>
    <?php endif; ?>
    <?php if ($ps['status'] === 'Selesai'): ?>
    <div class="center">
        <p class="muted kecil">Scan untuk nota digital / cetak A5</p>
        <img class="qris-struk" src="<?= e($qrSrc) ?>" alt="QR Nota">
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
</body>
</html>


