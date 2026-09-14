<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/duitku.php';
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}
$ref = trim((string)($input['ref'] ?? 'pesanan'));
$id = (int)($input['id'] ?? 0);
$k = trim((string)($input['k'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameter']);
    exit;
}
if ($k === '' || $k !== nota_token($ref, $id)) {
    http_response_code(404);
    echo json_encode(['error' => 'Link pembayaran tidak valid']);
    exit;
}

$ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND deleted = 0', [$id]);
if (!$ps || !in_array($ps['status'], ['DP', 'Lunas'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Pesanan tidak ditemukan']);
    exit;
}

if (!duitku_kasir_ready()) {
    echo json_encode(['error' => 'Duitku belum dikonfigurasi']);
    exit;
}

$sisa = max(0, (float)$ps['sisa']);
if ($sisa <= 0) {
    echo json_encode(['error' => 'Pembayaran sudah lunas']);
    exit;
}
$jumlah = min($sisa, (float)$ps['total']);
$now = date('Y-m-d H:i:s');

DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id, token) VALUES (?,?,?,?,?,?,?,?,?)',
    ['pesanan', $id, $now, $jumlah, 'Duitku', 'Pembayaran via Payment Point (Duitku)', 'Menunggu Duitku', 0, '']);
$pm_id = DB::lastId();

$inv = duitku_kasir_create_invoice($ps, $jumlah);
if (!$inv['ok']) {
    DB::run("UPDATE pembayaran SET status='Gagal', keterangan=? WHERE id=?", ['Duitku: ' . $inv['error'], $pm_id]);
    duitku_kasir_log('pay-duitku GAGAL', ['pesanan' => $ps['no_pesanan'], 'error' => $inv['error']]);
    echo json_encode(['error' => $inv['error']]);
    exit;
}

// merchantOrderId + referensi disimpan di keterangan utk dipetakan webhook;
// payment_url disimpan di token (dipakai tombol bayar ulang).
DB::run("UPDATE pembayaran SET keterangan=?, token=? WHERE id=?",
    ['Duitku order: ' . $inv['merchantOrderId'] . ' ref: ' . $inv['reference'], $inv['paymentUrl'], $pm_id]);

echo json_encode(['payment_url' => $inv['paymentUrl'], 'reference' => $inv['reference'], 'merchant_order_id' => $inv['merchantOrderId'], 'jumlah' => $jumlah]);
