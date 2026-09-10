<?php
// Proxy gambar QR WhatsApp gateway (gateway hanya listen di 127.0.0.1).
// Login dulu agar tidak bisa diakses publik.
require_once __DIR__ . '/config.php';
require_login();

$base = rtrim(setting('wa_gw_base', 'http://127.0.0.1:3001'), '/');
if ($base === '') {
    http_response_code(503);
    exit('gateway belum dikonfigurasi');
}
$ch = curl_init($base . '/qr.png');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
]);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 200 || !is_string($res) || $res === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('QR belum tersedia (gateway belum menerbitkan QR / sudah connect)');
}
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $res;
