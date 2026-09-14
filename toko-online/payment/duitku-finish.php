<?php
// payment/duitku-finish.php — halaman kembali setelah bayar di Duitku.
// Daftarkan pola URL ini di dashboard Duitku (Project Settings > Return URL):
//   https://rainbowprinting.web.id/payment/duitku-finish.php
// returnUrl aktual per-invoice: .../duitku-finish.php?order=INV-xxx (dibuat otomatis).
// Publik by order-code (tanpa login) seperti order-success.php — aman untuk guest.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/duitku.php';

$orderCode = $_GET['order'] ?? '';
$refResult = $_GET['resultCode'] ?? '';

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

// Cek status terbaru ke Duitku bila order belum lunas & ada referensi invoice.
$statusNote = '';
if (($order['payment_status'] ?? '') !== 'paid' && !empty($order['duitku_order_id']) && duitku_ready()) {
    $st = duitku_check_status($order['duitku_order_id']);
    if ($st['ok'] && $st['code'] === '00') {
        duitku_mark_order_paid($order, (float)($st['amount'] !== '' ? $st['amount'] : $order['total']), $st['reference']);
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
        $stmt->execute([$orderCode]);
        $order = $stmt->fetch();
        $statusNote = 'Status terverifikasi langsung dari Duitku.';
    } elseif ($st['ok']) {
        $statusNote = 'Status Duitku: ' . ($st['message'] !== '' ? $st['message'] : 'menunggu pembayaran.');
    }
}

$isPaid = ($order['payment_status'] ?? '') === 'paid';
$pageTitle = 'Status Pembayaran - Percetakan Rainbow';
include __DIR__ . '/../includes/header.php';
?>
<style>
.duitku-finish { max-width: 560px; margin: 30px auto; background: #fff; padding: 36px 30px; border-radius: 12px; border: 1px solid #e9ecef; text-align: center; }
.duitku-finish .big { font-size: 52px; margin-bottom: 10px; }
.duitku-finish h1 { font-size: 22px; color: #111; margin-bottom: 8px; }
.duitku-finish p { color: #555; font-size: 14px; }
.duitku-finish .box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 14px; margin: 18px 0; font-size: 14px; text-align: left; }
.duitku-finish .btn { display: inline-block; padding: 12px 26px; border-radius: 8px; font-size: 15px; font-weight: 600; text-decoration: none; margin: 6px 4px; }
.duitku-finish .btn-primary { background: #111; color: #fff; }
.duitku-finish .btn-outline { background: #fff; color: #111; border: 1px solid #111; }
</style>
<div class="duitku-finish">
    <?php if ($isPaid): ?>
        <div class="big">✅</div>
        <h1>Pembayaran Berhasil!</h1>
        <p>Terima kasih! Pembayaran Anda telah kami terima. Pesanan akan segera diproses.</p>
    <?php elseif ($refResult === '01'): ?>
        <div class="big">❌</div>
        <h1>Pembayaran Gagal / Kedaluwarsa</h1>
        <p>Transaksi tidak selesai. Pesanan Anda tetap tersimpan — silakan buat pembayaran baru.</p>
    <?php else: ?>
        <div class="big">⏳</div>
        <h1>Menunggu Pembayaran</h1>
        <p>Pembayaran Anda sedang diproses / menunggu konfirmasi dari Duitku. Jika Anda sudah membayar, status akan berubah otomatis dalam beberapa saat.</p>
    <?php endif; ?>
    <div class="box">
        <div><strong>Kode pesanan:</strong> <?= htmlspecialchars($order['order_code']) ?></div>
        <div><strong>Total:</strong> <?= formatRupiah($order['total']) ?></div>
        <div><strong>Status:</strong> <?= $isPaid ? 'LUNAS' : 'Belum dibayar' ?></div>
        <?php if ($statusNote): ?><div style="margin-top:6px;color:#666;"><?= htmlspecialchars($statusNote) ?></div><?php endif; ?>
    </div>
    <?php if (!$isPaid && ($order['payment_method'] ?? '') === 'duitku'): ?>
        <a class="btn btn-primary" href="/payment/duitku-pay.php?order=<?= urlencode($order['order_code']) ?>">💳 Bayar Sekarang</a>
    <?php endif; ?>
    <a class="btn btn-outline" href="/order-success.php?order=<?= urlencode($order['order_code']) ?>">Lihat Pesanan</a>
    <a class="btn btn-outline" href="/cek-pesanan.php">Cek Pesanan</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
