<?php
require_once __DIR__ . '/../config.php';
require_login();
if (!is_superadmin()) {
    flash_set('error', 'Halaman ini hanya untuk super admin.');
    header('Location: index.php');
    exit;
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$to = $_GET['to'] ?? date('Y-m-d');
if ($to < $from) {
    $t = $from;
    $from = $to;
    $to = $t;
}

$logs = DB::q("SELECT l.id, l.user_id, l.aksi, l.detail, l.tgl, u.username
               FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id
               WHERE date(l.tgl) BETWEEN ? AND ?
               ORDER BY l.id DESC LIMIT 500", [$from, $to]);

$judul = 'Log Aktivitas';
require __DIR__ . '/../layout/header.php';
?>
<h2>Log Aktivitas</h2>

<div class="panel">
    <form method="get" class="form-row">
        <input type="hidden" name="p" value="log">
        <label>Dari
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label>Sampai
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <button type="submit" class="btn">Tampilkan</button>
    </form>
</div>

<div class="panel">
    <h3>Riwayat (<?= count($logs) ?>)</h3>
    <?php if (!$logs): ?>
        <p class="muted">Tidak ada aktivitas pada rentang tanggal ini.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= tgl($l['tgl']) ?></td>
                    <td><?= e($l['username'] ?? 'Sistem') ?></td>
                    <td><span class="badge"><?= e($l['aksi']) ?></span></td>
                    <td><?= e($l['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
