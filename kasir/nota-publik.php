<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$ref = $_GET['ref'] ?? 'pesanan';
if (!in_array($ref, ['pesanan', 'penjualan'])) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}
if ($id <= 0 || ($_GET['k'] ?? '') !== nota_token($ref, $id)) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}

$template = $_GET['t'] ?? 'a5';
if (!in_array($template, ['struk', 'a5', 'pay'])) {
    $template = 'a5';
}

require_once __DIR__ . '/nota-common.php';
$data = nota_data($ref, $id);
if (!$data) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}
[$ref, $ps, $viewItems, $pembayaran, $totalBayar] = $data;

$user = DB::one('SELECT username FROM users WHERE id = ?', [$ps['user_id']]);

$k = $_GET['k'];
$publik = true;

// ------------------------------------------------------------------
//  STRUK vs PAYMENT POINT BERDIRI SENDIRI (aturan pengalihan):
//  - Link WA saat BELUM BAYAR / DP → halaman PAYMENT POINT.
//    Bila pelanggan membuka STRUK tapi masih ada sisa tagihan,
//    otomatis dialihkan ke PAYMENT POINT (nominal = sisa + kode unik).
//  - Setelah KONFIRMASI BAYAR (lunas) → halaman STRUK.
//    Bila pelanggan membuka PAYMENT POINT tapi sudah lunas,
//    otomatis dialihkan ke STRUK (bukti lunas).
// ------------------------------------------------------------------
$ppSisaRedirect = 0.0;
if ($ref === 'pesanan') {
    $ppSisaRedirect = max(0, (float)($ps['sisa'] ?? 0));
} elseif ($ref === 'penjualan') {
    $ppSisaRedirect = ((string)($ps['status'] ?? '') === 'Menunggu QRIS') ? max(0, (float)$ps['total']) : 0.0;
}
if ($template === 'pay' && $ppSisaRedirect <= 0) {
    header('Location: ' . rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/')
        . '/n.php/' . rawurlencode($ref) . '/' . (int)$id . '/struk/' . rawurlencode($k), true, 302);
    exit;
}
if (in_array($template, ['struk', 'a5'], true) && $ppSisaRedirect > 0) {
    header('Location: ' . rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/')
        . '/n.php/' . rawurlencode($ref) . '/' . (int)$id . '/pay/' . rawurlencode($k), true, 302);
    exit;
}
if ($template === 'struk') {
    $back_page = 'pesanan';
    $qrData = rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/')
        . '/nota-publik.php?ref=' . $ref . '&id=' . $id . '&t=a5&k=' . $k;
    $qrSrc = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($qrData)
        . '&code=MobileQRCode&format=png&dpi=300&modulewidth=4&caption=false&backgroundcolor=FFFFFF';
    require __DIR__ . '/nota-templates/struk.php';
} elseif ($template === 'pay') {
    require __DIR__ . '/nota-templates/pay.php';
} else {
    require __DIR__ . '/nota-templates/a5.php';
}
