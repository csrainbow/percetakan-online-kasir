<?php
// payment/duitku-callback.php — server-to-server callback dari Duitku.
// Daftarkan URL ini di dashboard Duitku (Project Settings > Callback URL):
//   https://rainbowprinting.web.id/payment/duitku-callback.php
// Selalu balas HTTP 200 agar Duitku tidak retry terus-menerus.

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/duitku.php';

header('Content-Type: application/json');

$post = $_POST;
duitku_log('callback diterima', array_merge($post, ['signature' => isset($post['signature']) ? substr((string)$post['signature'], 0, 8) . '...' : '']));

if (empty($post)) {
    // Health check / tes URL dari dashboard (tanpa body) — balas 200 OK.
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Duitku callback endpoint ready']);
    exit;
}

$v = duitku_verify_callback($post);
if (!$v['ok']) {
    duitku_log('callback DITOLAK: ' . $v['error']);
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => $v['error']]);
    exit;
}

$order = duitku_find_order($v['merchantOrderId']);
if (!$order) {
    duitku_log('callback: order tidak ditemukan', ['merchantOrderId' => $v['merchantOrderId']]);
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Notification received (order unknown)']);
    exit;
}

// Keamanan: nominal callback harus sama dengan total order.
if ((int)$v['amount'] !== (int)round((float)$order['total'])) {
    duitku_log('callback DITOLAK: nominal tidak cocok', ['callback_amount' => $v['amount'], 'order_total' => $order['total']]);
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'Amount mismatch']);
    exit;
}

if ($v['resultCode'] === '00') {
    $changed = duitku_mark_order_paid($order, (float)$v['amount'], $v['reference']);
    duitku_log('callback: order ' . $order['order_code'] . ' LUNAS' . ($changed ? '' : ' (sudah paid sebelumnya)'));
} else {
    duitku_log('callback: pembayaran GAGAL/expired', ['order' => $order['order_code'], 'resultCode' => $v['resultCode']]);
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
exit;
