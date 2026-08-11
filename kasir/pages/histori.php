<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pulihkan_pesanan'])) {
    if (!is_superadmin()) {
        flash_set('error', 'Hanya super admin yang dapat memulihkan pesanan.');
        header('Location: index.php?p=histori');
        exit;
    }
    $id = (int)$_POST['pulihkan_pesanan'];
    DB::run('UPDATE pesanan SET deleted = 0 WHERE id = ?', [$id]);
    log_aktivitas('Pulihkan pesanan', '#' . $id);
    flash_set('success', 'Pesanan dipulihkan ke daftar aktif.');
    header('Location: index.php?p=histori');
    exit;
}

$filter = $_GET['status'] ?? 'ALL';
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$q = trim($_GET['q'] ?? '');
$where = 'WHERE ' . scope_sql('pe');
$args = [];
if ($filter !== 'ALL') {
    if ($filter === 'Dihapus') {
        $where .= ' AND pe.deleted = 1';
    } else {
        $where .= ' AND pe.status = ?';
        $args[] = $filter;
    }
}
if ($from !== '') {
    $where .= ' AND date(pe.tgl) >= ?';
    $args[] = $from;
}
if ($to !== '') {
    $where .= ' AND date(pe.tgl) <= ?';
    $args[] = $to;
}
if ($q !== '') {
    $where .= ' AND (pe.no_pesanan LIKE ? OR pe.pelanggan LIKE ?)';
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
}
$hist = DB::q("SELECT pe.*, u.username AS pembuat FROM pesanan pe
               LEFT JOIN users u ON u.id = pe.user_id $where
               ORDER BY pe.id DESC LIMIT 300", $args);

$judul = 'Histori Pesanan';
require __DIR__ . '/../layout/header.php';
?>
<h2>Histori Pesanan</h2>

<div class="panel">
    <h3>Filter</h3>
    <nav class="filter-nav">
        <?php foreach (['ALL', 'DP', 'Lunas', 'Selesai', 'Batal', 'Dihapus'] as $st): ?>
            <a href="index.php?p=histori&status=<?= $st ?><?= $from !== '' ? '&from=' . rawurlencode($from) : '' ?><?= $to !== '' ? '&to=' . rawurlencode($to) : '' ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>" class="<?= $filter === $st ? 'act' : '' ?>"><?= $st === 'ALL' ? 'Semua' : $st ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="form-row">
        <input type="hidden" name="p" value="histori">
        <?php if ($filter !== 'ALL'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
        <input type="date" name="from" value="<?= e($from) ?>" title="Dari tanggal">
        <input type="date" name="to" value="<?= e($to) ?>" title="Sampai tanggal">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari no / pelanggan...">
        <button type="submit" class="btn">Filter</button>
        <?php if ($from !== '' || $to !== '' || $q !== ''): ?>
            <a class="btn abu" href="index.php?p=histori&status=<?= e($filter) ?>">Bersihkan</a>
        <?php endif; ?>
    </form>
</div>

<div class="panel">
    <h3>Daftar Riwayat (<?= count($hist) ?>)</h3>
    <?php if (!$hist): ?>
        <p class="muted">Tidak ada data.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>No Pesanan</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Pembuat</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($hist as $ps): ?>
                <tr>
                    <td><?= e($ps['no_pesanan']) ?></td>
                    <td><?= tgl($ps['tgl']) ?></td>
                    <td><?= e($ps['pelanggan']) ?></td>
                    <td><?= e($ps['pembuat'] ?? '-') ?></td>
                    <td><?= rp($ps['total']) ?></td>
                    <td>
                        <span class="badge <?= e($ps['status']) ?>"><?= in_array($ps['status'], ['Selesai', 'Batal']) ? e($ps['status']) : e(pembayaran_status_label((float)$ps['total'] - (float)$ps['sisa'], (float)$ps['total'], $ps['status'])) ?></span>
                        <?php if ((int)$ps['deleted']): ?>
                            <span class="badge bahaya">Dihapus</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn kecil" href="nota.php?id=<?= $ps['id'] ?>&t=struk">Cetak Struk</a>
                        <a class="btn kecil" href="nota.php?id=<?= $ps['id'] ?>&t=a5">Cetak Nota</a>
                        <?php if ((int)$ps['deleted'] && is_superadmin()): ?>
                            <form method="post" class="confirm inline" data-confirm="Pulihkan pesanan <?= e($ps['no_pesanan']) ?> kembali ke daftar aktif?">
                                <input type="hidden" name="pulihkan_pesanan" value="<?= $ps['id'] ?>">
                                <button type="submit" class="btn kecil ok">Pulihkan</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
