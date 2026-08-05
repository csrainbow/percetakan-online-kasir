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
    <div class="brand"><?= e(setting('nama_toko', APP_NAME)) ?></div>
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
