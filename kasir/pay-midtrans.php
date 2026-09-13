<?php
require_once __DIR__ . '/config.php';
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

$server_key = midtrans_server_key();
if ($server_key === '') {
    echo json_encode(['error' => 'Midtrans belum dikonfigurasi']);
    exit;
}

$sisa = max(0, (float)$ps['sisa']);
if ($sisa <= 0) {
    echo json_encode(['error' => 'Pembayaran sudah lunas']);
    exit;
}
$jumlah = min($sisa, (float)$ps['total']);

// order_id harus unik agar tidak collide jika retry / repeat payment
$order_id = $ps['no_pesanan'] . '-' . date('His');
$now = date('Y-m-d H:i:s');

DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id, token) VALUES (?,?,?,?,?,?,?,?,?)',
    ['pesanan', $id, $now, $jumlah, 'Midtrans', 'Pembayaran via Payment Point', 'Menunggu Midtrans', 0, '']);
$pm_id = DB::lastId();

// order_id disimpan di keterangan utk dipetakan webhook
DB::run("UPDATE pembayaran SET keterangan=? WHERE id=?", ['Midtrans order: ' . $order_id, $pm_id]);

$is_prod = midtrans_is_production();
$snap_url = $is_prod
    ? 'https://app.midtrans.com/snap/v1/transactions'
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

// 🔥 Webhook kasir = /kasir/midtrans-webhook.php (override notifikasi per transaksi,
// agar tidak tertimpa Payment Notification URL global toko-online).
$webhook_url = rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/') . '/midtrans-webhook.php';

$payload = json_encode([
    'transaction_details' => ['order_id' => $order_id, 'gross_amount' => (int)round($jumlah)],
    'customer_details' => ['first_name' => $ps['pelanggan'] ?: 'Pelanggan', 'phone' => $ps['telepon']],
    'enabled_payments' => ['snap'],
    'expiry' => ['unit' => 'minute', 'duration' => 10],
    'credit_card' => ['secure' => true],
]);

$ch = curl_init($snap_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($server_key . ':'),
        'X-Override-Notification: ' . $webhook_url
    ],
    CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    DB::run("UPDATE pembayaran SET status='Gagal', keterangan='Midtrans curl: '||? WHERE id=?", [$err, $pm_id]);
    echo json_encode(['error' => 'Gagal terhubung ke Midtrans']);
    exit;
}

if (!in_array($httpcode, [200, 201])) {
    DB::run("UPDATE pembayaran SET status='Gagal', keterangan='Midtrans HTTP $httpcode' WHERE id=?", [$pm_id]);
    echo json_encode(['error' => 'Midtrans API error', 'httpcode' => $httpcode]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['token'])) {
    DB::run("UPDATE pembayaran SET status='Gagal', keterangan='Midtrans invalid response' WHERE id=?", [$pm_id]);
    echo json_encode(['error' => 'Invalid Midtrans response']);
    exit;
}

// token (snap token) kembali disimpan
DB::run("UPDATE pembayaran SET token=? WHERE id=?", [$data['token'], $pm_id]);
echo json_encode(['snap_token' => $data['token'], 'order_id' => $order_id, 'jumlah' => $jumlah]);