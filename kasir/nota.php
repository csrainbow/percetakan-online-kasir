<?php
require_once __DIR__ . '/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$ref = $_GET['ref'] ?? 'pesanan';
if (!in_array($ref, ['pesanan', 'penjualan'])) {
    $ref = 'pesanan';
}
$template = $_GET['t'] ?? setting('nota_template', 'struk');
if (!in_array($template, ['struk', 'a5'])) {
    $template = 'struk';
}

require_once __DIR__ . '/nota-common.php';
$data = nota_data($ref, $id);
if (!$data) {
    flash_set('error', $ref === 'penjualan' ? 'Transaksi tidak ditemukan.' : 'Pesanan tidak ditemukan.');
    header('Location: index.php?p=' . ($ref === 'penjualan' ? 'penjualan' : 'pesanan'));
    exit;
}
[$ref, $ps, $viewItems, $pembayaran, $totalBayar] = $data;

if (scope_user_id() !== 0 && (int)$ps['user_id'] !== scope_user_id()) {
    flash_set('error', $ref === 'penjualan' ? 'Transaksi tidak ditemukan.' : 'Pesanan tidak ditemukan.');
    header('Location: index.php?p=' . ($ref === 'penjualan' ? 'penjualan' : 'pesanan'));
    exit;
}

$user = DB::one('SELECT username FROM users WHERE id = ?', [$ps['user_id']]);

$back_page = 'pesanan';
if ($ref === 'penjualan') {
    $back_page = 'penjualan';
}

$k = nota_token($ref, $id);
$qrData = rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/')
    . '/nota-publik.php?ref=' . $ref . '&id=' . $id . '&t=a5&k=' . $k;
$qrSrc = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($qrData)
    . '&code=MobileQRCode&format=png&dpi=300&modulewidth=4&caption=false&backgroundcolor=FFFFFF';

$publik = false;
if ($template === 'a5') {
    require __DIR__ . '/nota-templates/a5.php';
} else {
    require __DIR__ . '/nota-templates/struk.php';
}
