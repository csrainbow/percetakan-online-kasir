<?php
// payment/duitku-pay.php?order=INV-xxx — buat / pakai-ulang invoice Duitku lalu redirect bayar.
// Publik by order-code (tanpa login) seperti order-success.php — aman untuk guest.
// Logika: sudah lunas -> order-success | invoice pending -> pakai URL tersimpan |
//          invoice gagal/expired/tak ada -> buat invoice baru -> redirect paymentUrl.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/duitku.php';

$orderCode = $_GET['order'] ?? '';
if (empty($orderCode)) {
    header('Location: /index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();
if (!$order) {
    header('Location: /index.php');
    exit;
}

if (($order['payment_status'] ?? '') === 'paid') {
    header('Location: /order-success.php?order=' . urlencode($orderCode));
    exit;
}

if (($order['payment_method'] ?? '') !== 'duitku') {
    header('Location: /order-success.php?order=' . urlencode($orderCode));
    exit;
}

if (!duitku_ready()) {
    $_SESSION['error'] = 'Pembayaran online (Duitku) belum dikonfigurasi. Hubungi admin.';
    header('Location: /order-success.php?order=' . urlencode($orderCode));
    exit;
}

// Kalau ada invoice yang masih pending di Duitku -> pakai URL tersimpan.
if (!empty($order['duitku_order_id'])) {
    $st = duitku_check_status($order['duitku_order_id']);
    if ($st['ok'] && $st['code'] === '00') {
        duitku_mark_order_paid($order, (float)($st['amount'] !== '' ? $st['amount'] : $order['total']), $st['reference']);
        header('Location: /payment/duitku-finish.php?order=' . urlencode($orderCode) . '&resultCode=00');
        exit;
    }
    if (!empty($order['duitku_payment_url'])) {
        // Anggap masih bisa dipakai (pending) — arahkan ke halaman bayar tersimpan.
        header('Location: ' . $order['duitku_payment_url']);
        exit;
    }
}

// Buat invoice baru (termasuk kasus invoice lama expired).
$email = '';
if (!empty($_SESSION['customer_id'])) {
    $c = $db->prepare("SELECT email FROM customers WHERE id = ?");
    $c->execute([intval($_SESSION['customer_id'])]);
    $row = $c->fetch();
    if ($row) $email = $row['email'] ?? '';
}
$inv = duitku_create_invoice($order, ['name' => $order['customer_name'], 'email' => $email, 'phone' => $order['customer_phone']]);
if (!$inv['ok']) {
    $_SESSION['error'] = 'Gagal membuat pembayaran Duitku: ' . $inv['error'];
    header('Location: /order-success.php?order=' . urlencode($orderCode));
    exit;
}

header('Location: ' . $inv['paymentUrl']);
exit;
