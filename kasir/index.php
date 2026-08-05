<?php
require_once __DIR__ . '/config.php';
require_login();

$page = $_GET['p'] ?? 'dashboard';
$pages = ['dashboard', 'penjualan', 'produk', 'pesanan', 'histori', 'piutang', 'rekap', 'laporan', 'pengaturan', 'log'];
if (!in_array($page, $pages)) {
    $page = 'dashboard';
}

if (isset($_GET['scope']) && is_superadmin()) {
    $scope = (int)$_GET['scope'];
    if ($scope === 0 || DB::one('SELECT COUNT(*) c FROM users WHERE id = ?', [$scope])['c'] > 0) {
        $_SESSION['scope_user_id'] = $scope;
    }
    header('Location: index.php?p=' . rawurlencode($page));
    exit;
}

require __DIR__ . '/pages/' . $page . '.php';
