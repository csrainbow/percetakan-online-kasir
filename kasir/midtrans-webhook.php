<?php
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$order_id = $input['order_id'] ?? '';
$status_code = $input['status_code'] ?? '';
$gross_amount = $input['gross_amount'] ?? '';
$transaction_status = $input['transaction_status'] ?? '';
$signature_key = $input['signature_key'] ?? '';

if (empty($order_id)) { echo json_encode(['status' => 'missing_order_id']); exit; }
if (empty($transaction_status)) { echo json_encode(['status' => 'missing_transaction_status']); exit; }

$server_key = midtrans_server_key();
if (empty($server_key)) { echo json_encode(['status' => 'server_key_missing']); exit; }

$expected = hash('SHA512', $order_id . $status_code . $gross_amount . $server_key);
if (isset($signature_key) && $signature_key !== '' && !hash_equals($expected, $signature_key)) {
    http_response_code(403);
    echo json_encode(['status' => 'invalid_signature']);
    exit;
}

// Cari pembayaran menunggu berdasarkan order_id (disimpan di keterangan)
$pm = DB::one(
    "SELECT * FROM pembayaran WHERE ref_type='pesanan' AND keterangan LIKE ? AND status = 'Menunggu Midtrans' ORDER BY id DESC LIMIT 1",
    ['%Midtrans order: ' . $order_id . '%']
);
if (!$pm) {
    echo json_encode(['status' => 'order_not_found', 'order_id' => $order_id]);
    exit;
}
$pesanan_id = (int)$pm['ref_id'];

$isPaid = in_array($transaction_status, ['capture', 'settlement']);
$isFailed = in_array($transaction_status, ['deny', 'cancel', 'expire']);

if ($isPaid) {
    DB::run(
        "UPDATE pembayaran SET status='Lunas', keterangan='Midtrans: '||? WHERE id=?",
        [$transaction_status, $pm['id']]
    );
    // Hitung ulang sisa pesanan
    $total_bayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id=? AND status='Lunas'", [$pesanan_id])['t'];
    $ps = DB::one("SELECT total FROM pesanan WHERE id=?", [$pesanan_id]);
    if ($ps) {
        $sisa = max(0, (float)$ps['total'] - $total_bayar);
        $stat = $sisa <= 0 ? 'Lunas' : 'DP';
        DB::run("UPDATE pesanan SET sisa=?, status=? WHERE id=?", [$sisa, $stat, $pesanan_id]);
    }
    echo json_encode(['status' => 'paid', 'order_id' => $order_id]);
} else if ($isFailed) {
    DB::run(
        "UPDATE pembayaran SET status='Gagal', keterangan='Midtrans: '||? WHERE id=?",
        [$transaction_status, $pm['id']]
    );
    echo json_encode(['status' => 'failed', 'order_id' => $order_id]);
} else {
    echo json_encode(['status' => 'received', 'transaction_status' => $transaction_status]);
}
