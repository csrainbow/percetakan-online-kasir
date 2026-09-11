<?php
require_once __DIR__ . '/../config.php';
require_login();
$page = $page ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul ?? '') ?> - <?= e(setting('nama_toko', APP_NAME)) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body>
<header class="topbar">
    <div class="brand">APLIKASI KASIR PERCETAKAN RAINBOW</div>
    <nav>
        <a href="index.php" class="<?= $page === 'dashboard' ? 'act' : '' ?>">Dashboard</a>
        <a href="index.php?p=penjualan" class="<?= $page === 'penjualan' ? 'act' : '' ?>">Kasir</a>
        <a href="index.php?p=produk" class="<?= $page === 'produk' ? 'act' : '' ?>">Produk & Stok</a>
        <a href="index.php?p=pesanan" class="<?= $page === 'pesanan' ? 'act' : '' ?>">Pesanan</a>
        <a href="index.php?p=histori" class="<?= $page === 'histori' ? 'act' : '' ?>">Histori</a>
        <a href="index.php?p=piutang" class="<?= $page === 'piutang' ? 'act' : '' ?>">Piutang</a>
        <a href="index.php?p=rekap" class="<?= $page === 'rekap' ? 'act' : '' ?>">Rekap</a>
        <a href="index.php?p=laporan" class="<?= $page === 'laporan' ? 'act' : '' ?>">Laporan</a>
        <a href="index.php?p=pengaturan" class="<?= $page === 'pengaturan' ? 'act' : '' ?>">Pengaturan</a>
        <a href="index.php?p=wa-gateway" class="<?= $page === 'wa-gateway' ? 'act' : '' ?>">WA Gateway</a>
        <div class="pp-wrap">
            <a href="#" id="ppToggle" class="pp-toggle">Payment Point ▾</a>
            <div class="pp-panel" id="ppPanel">
                <div class="pp-title">Payment Point — Pesanan Belum Lunas</div>
                <?php
                $ppRows = DB::q(
                    'SELECT pe.id, pe.no_pesanan, pe.pelanggan, pe.sisa, pe.status
                     FROM pesanan pe
                     WHERE pe.deleted = 0 AND pe.sisa > 0 AND pe.status NOT IN (\'Lunas\', \'Selesai\', \'Batal\')
                       AND ' . scope_sql('pe') . '
                     ORDER BY pe.id DESC LIMIT 8'
                );
                if ($ppRows):
                    foreach ($ppRows as $pp): ?>
                        <div class="pp-row" data-no="<?= e($pp['no_pesanan']) ?>">
                            <div class="pp-info">
                                <span class="pp-code"><?= e($pp['no_pesanan']) ?></span>
                                <span class="pp-meta"><?= e($pp['pelanggan']) ?> · sisa <?= rp((float)$pp['sisa']) ?></span>
                            </div>
                            <div class="pp-actions">
                                <a class="btn btn-xs" href="<?= e(nota_publik_url('pesanan', (int)$pp['id'], 'pay')) ?>" target="_blank" rel="noopener">Buka</a>
                                <button type="button" class="btn btn-xs btn-pp" onclick="ppSalin(this)" title="Salin link payment point">Salin</button>
                            </div>
                        </div>
                    <?php endforeach;
                else: ?>
                    <div class="pp-empty">Tidak ada pesanan belum lunas.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (is_superadmin()): ?>
            <a href="index.php?p=log" class="<?= $page === 'log' ? 'act' : '' ?>">Aktivitas</a>
        <?php endif; ?>
        <?php if (is_superadmin()): ?>
            <?php $usersList = DB::q('SELECT id, username, role FROM users ORDER BY id'); ?>
            <form method="get" action="index.php" class="scope-switch">
                <input type="hidden" name="p" value="<?= e($page) ?>">
                <select name="scope" onchange="this.form.submit()" title="Pilih pembukuan">
                    <option value="0" <?= scope_user_id() === 0 ? 'selected' : '' ?>>Semua User</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= scope_user_id() === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['username']) ?><?= $u['role'] === 'superadmin' ? ' (admin)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php else: ?>
            <span class="scope-label">Pembukuan: <b><?= e($_SESSION['username'] ?? '') ?></b></span>
        <?php endif; ?>
        <a href="logout.php" class="out">Keluar</a>
    </nav>
</header>
<main class="wrap">
<?php if ($f = flash_get()): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>
<?php
// Banner peringatan bila WA Gateway putus (cache 60 dtk, tidak memperlambat halaman).
// Hanya tampil bila notifikasi WA aktif & gateway diaktifkan sebagai jalur utama.
if (setting('wa_enabled') && setting('wa_gw_enabled', '1') === '1' && ($page ?? '') !== 'wa-gateway') {
    $gwBanner = wa_gateway_status_cached();
    if (!empty($gwBanner['ok']) && empty($gwBanner['connected'])) {
        $gwSt = e($gwBanner['status'] ?? 'putus');
        echo '<div class="flash error">⚠️ <b>WA Gateway putus</b> (status: ' . $gwSt . '). Notifikasi pelanggan sementara lewat jalur cadangan. '
            . (is_superadmin()
                ? '<a href="index.php?p=wa-gateway"><b>Segera tautkan ulang di sini →</b></a>'
                : 'Hubungi admin untuk menautkan ulang di menu <b>WA Gateway</b>.')
            . '</div>';
        if (is_superadmin()) {
            wa_gateway_alert_admin($gwBanner['status'] ?? 'putus');
        }
    }
}
?>
