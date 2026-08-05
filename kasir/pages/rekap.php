<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rekap-common.php';
require_login();

$tgl = trim($_GET['tgl'] ?? date('Y-m-d'));
$userF = rekap_user_filter();
$kasirList = (is_superadmin() && scope_user_id() === 0) ? DB::q('SELECT id, username, role FROM users ORDER BY id') : [];
$r = rekap_data($tgl, $userF);
$namaKasir = '';
if ($userF > 0) {
    $u = DB::one('SELECT username FROM users WHERE id = ?', [$userF]);
    $namaKasir = $u['username'] ?? '';
}

$judul = 'Rekap Harian';
require __DIR__ . '/../layout/header.php';
?>
<h2>Rekap Harian</h2>

<div class="panel">
    <form method="get" class="form-row">
        <input type="hidden" name="p" value="rekap">
        <label>Tanggal
            <input type="date" name="tgl" value="<?= e($tgl) ?>">
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
        <a class="btn" href="rekap-pdf.php?tgl=<?= e($tgl) ?>&user=<?= $userF ?>">🖨️ Cetak PDF</a>
    </form>
</div>

<div class="cards">
    <div class="card">
        <div class="card-label">Penjualan Kasir</div>
        <div class="card-value"><?= rp($r['sumPenjualan']['total']) ?></div>
        <div class="card-sub"><?= (int)$r['sumPenjualan']['c'] ?> transaksi</div>
    </div>
    <div class="card">
        <div class="card-label">Pembayaran Pesanan</div>
        <div class="card-value"><?= rp($r['sumPembayaran']['total']) ?></div>
        <div class="card-sub"><?= (int)$r['sumPembayaran']['c'] ?> pembayaran</div>
    </div>
    <div class="card">
        <div class="card-label">Pendapatan</div>
        <div class="card-value"><?= rp($r['pendapatan']) ?></div>
        <div class="card-sub">Kasir + pembayaran pesanan (non-kasir)</div>
    </div>
    <div class="card">
        <div class="card-label">Laba Kotor</div>
        <div class="card-value <?= $r['labaKotor'] >= 0 ? 'baik' : 'bahaya' ?>"><?= rp($r['labaKotor']) ?></div>
        <div class="card-sub">Pendapatan - HPP</div>
    </div>
</div>

<?php if ($r['perMetode']): ?>
<div class="panel">
    <h3>Per Metode Pembayaran (Kasir)</h3>
    <table>
        <thead><tr><th>Metode</th><th>Transaksi</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($r['perMetode'] as $m): ?>
            <tr>
                <td><?= e($m['metode']) ?></td>
                <td><?= (int)$m['c'] ?></td>
                <td><?= rp($m['t']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Transaksi Kasir (<?= tglOnly($r['tgl'] . ' 00:00:00') ?><?= $namaKasir ? ' - ' . e($namaKasir) : '' ?>)</h3>
    <?php if (!$r['penjualan']): ?>
        <p class="muted">Tidak ada transaksi.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>No Invoice</th><th>Jam</th><th>Metode</th><th>Total</th><th>Bayar</th><th>Kembali</th><th>Kasir</th></tr></thead>
            <tbody>
            <?php foreach ($r['penjualan'] as $p): ?>
                <tr>
                    <td><?= e($p['no_invoice']) ?></td>
                    <td><?= date('H:i', strtotime($p['tgl'])) ?></td>
                    <td><?= e($p['metode']) ?></td>
                    <td><?= rp($p['total']) ?></td>
                    <td><?= rp($p['bayar']) ?></td>
                    <td><?= rp($p['kembalian']) ?></td>
                    <td><?= e($p['username'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Pembayaran Pesanan Diterima (<?= tglOnly($r['tgl'] . ' 00:00:00') ?><?= $namaKasir ? ' - ' . e($namaKasir) : '' ?>)</h3>
    <?php if (!$r['pembayaran']): ?>
        <p class="muted">Tidak ada pembayaran.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Jam</th><th>No Pesanan</th><th>Pelanggan</th><th>Jumlah</th><th>Metode</th></tr></thead>
            <tbody>
            <?php foreach ($r['pembayaran'] as $p): ?>
                <tr>
                    <td><?= date('H:i', strtotime($p['tgl'])) ?></td>
                    <td><?= e($p['no_pesanan']) ?></td>
                    <td><?= e($p['pelanggan']) ?></td>
                    <td><?= rp($p['jumlah']) ?></td>
                    <td><?= e($p['metode']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
