<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/qris.php';

header('Content-Type: application/json; charset=utf-8');

$orderCode = (string)($_GET['order_code'] ?? $_POST['order_code'] ?? '');
$phone = (string)($_GET['phone'] ?? $_POST['phone'] ?? '');

if (!$orderCode || !$phone) {
    echo json_encode(['ok' => false, 'error' => 'Data tidak lengkap.']);
    exit;
}

$pr = qris_check_order($orderCode, $phone, 30);

if (!$pr['ok']) {
    echo json_encode(['ok' => false, 'error' => $pr['error'] ?? 'Gagal memeriksa status.']);
    exit;
}

if (!empty($pr['detail'])) {
    $detail = $pr['detail'];
} else {
    $detail = '';
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code=? AND customer_phone=?");
$stmt->execute([$orderCode, $phone]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'Pesanan tidak ditemukan.']);
    exit;
}

if (($order['payment_status'] ?? '') === 'paid') {
    echo json_encode([
        'ok' => true,
        'status' => 'paid',
        'paid' => true,
        'status_teks' => 'Pembayaran sudah dikonfirmasi.',
        'qris_image' => '',
        'nmid' => '',
        'invid' => '',
        'expiry' => '',
        'detail' => $detail,
        'error' => '',
    ]);
    exit;
}

if (!empty($order['qris_content'])) {
    $qrisImage = qris_png_datauri($order['qris_content']);
} else {
    $qrisImage = '';
}

echo json_encode([
    'ok' => true,
    'status' => $pr['status'] ?? 'unpaid',
    'paid' => !empty($pr['paid']),
    'expired' => !empty($pr['expired']),
    'status_teks' => $pr['status_teks'] ?? ($pr['paid'] ? 'Pembayaran diterima.' : 'Menunggu pembayaran.'),
    'qris_image' => $qrisImage,
    'nmid' => qris_nmid($order),
    'invid' => (string)($order['qris_invid'] ?? ''),
    'expiry' => (string)($order['qris_expiry'] ?? ''),
    'detail' => $detail,
    'error' => $pr['error'] ?? '',
]);
