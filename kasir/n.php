<?php
require_once __DIR__ . '/config.php';

$pi = trim($_SERVER['PATH_INFO'] ?? '', '/');
$seg = $pi === '' ? [] : explode('/', $pi);

$ref = $seg[0] ?? '';
$id  = (int)($seg[1] ?? 0);
$t   = $seg[2] ?? 'a5';
$k   = $seg[3] ?? '';

if (!in_array($ref, ['pesanan', 'penjualan'])) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}
if ($id <= 0 || $k !== nota_token($ref, $id)) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}
if (!in_array($t, ['struk', 'a5'])) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}

$_GET['ref'] = $ref;
$_GET['id']  = (string)$id;
$_GET['t']   = $t;
$_GET['k']   = $k;

require __DIR__ . '/nota-publik.php';