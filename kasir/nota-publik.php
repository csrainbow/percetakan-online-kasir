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
require __DIR__ . '/nota-templates/a5.php';
