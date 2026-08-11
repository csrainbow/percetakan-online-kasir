<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'msg' => 'Metode tidak diizinkan.']));
}
require_login();

$id = (int)($_POST['id'] ?? 0);
$ref = $_POST['ref'] ?? 'pesanan';
if (!in_array($ref, ['pesanan', 'penjualan'])) {
    $ref = 'pesanan';
}
if ($id <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'ID tidak valid.']));
}

$ps = DB::one('SELECT * FROM ' . $ref . ' WHERE id = ? AND ' . scope_sql($ref), [$id]);
if (!$ps) {
    exit(json_encode(['ok' => false, 'msg' => 'Transaksi tidak ditemukan.']));
}

$telepon = $ps['telepon'] ?? '';
if ($telepon === '') {
    exit(json_encode(['ok' => false, 'msg' => 'Nomor WhatsApp pelanggan kosong.']));
}

$total = (float)($ps['total'] ?? 0);
$status = $ps['status'] ?? '';
$link = nota_publik_url($ref, $id);
$name = $ps['pelanggan'] ?? '';
$code = $ps['no_pesanan'] ?? ($ref === 'penjualan' ? ('PNJ-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)) : ('#' . $id));
$message = "📋 *NOTA " . ($ref === 'penjualan' ? 'PENJUALAN' : 'PESANAN') . "*\n\n"
    . "Halo $name, berikut nota *$code* dengan total " . rp($total) . " (status: $status).\n\n"
    . "Detail nota: $link\n\n"
    . "Mohon konfirmasi. Terima kasih 🙏\n\n— " . setting('nama_toko', 'PERCETAKAN RAINBOW');

$sent = wa_send($telepon, $message);
if ($sent) {
    log_aktivitas('WA terkirim ke pelanggan', $code . ' | ' . $telepon);
    exit(json_encode(['ok' => true, 'msg' => 'WhatsApp terkirim ke pelanggan.']));
}

$waFallback = wa_href($telepon, $message);
exit(json_encode([
    'ok' => false,
    'fallback' => $waFallback,
    'msg' => 'Fonnte belum dikonfigurasi / gagal terkirim — membuka WhatsApp manual.',
]));
