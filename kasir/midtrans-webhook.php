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

$server_key = midtrans_server_key();
if (empty($server_key)) { echo json_encode(['status' => 'server_key_missing']); exit; }

$expected = hash('SHA512', $order_id . $status_code . $gross_amount . $server_key);
if (!hash_equals($expected, $signature_key)) {
    http_response_code(403);
    echo json_encode(['status' => 'invalid_signature']);
    exit;
}

$db = new PDO('sqlite:' . DB_PATH);
$pesanan = $db->query("SELECT * FROM pesanan WHERE no_pesanan = '" . $db->quote($order_id) . "'")->fetch(PDO::FETCH_ASSOC);
if (!$pesanan) { echo json_encode(['status' => 'order_not_found']); exit; }

$isPaid = in_array($transaction_status, ['capture', 'settlement']);
$isFailed = in_array($transaction_status, ['deny', 'cancel', 'expire']);

if ($isPaid) {
    $db->exec("UPDATE pembayaran SET status='Lunas' WHERE ref_type='pesanan' AND ref_id={$pesanan['id']} AND status='Menunggu Midtrans'");
    $db->exec("UPDATE pesanan SET status='Lunas', sisa=0 WHERE id={$pesanan['id']}");
    echo json_encode(['status' => 'paid', 'order_id' => $order_id]);
} else if ($isFailed) {
    $db->exec("UPDATE pembayaran SET status='Gagal', keterangan='Midtrans: {$transaction_status}' WHERE ref_type='pesanan' AND ref_id={$pesanan['id']} AND status='Menunggu Midtrans'");
    echo json_encode(['status' => 'failed', 'order_id' => $order_id]);
} else {
    echo json_encode(['status' => 'received', 'transaction_status' => $transaction_status]);
}
