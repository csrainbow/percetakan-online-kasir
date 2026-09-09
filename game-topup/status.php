<?php
require_once __DIR__ . '/includes/functions.php';
$db = db();
$ref = $_GET['ref'] ?? '';
$order = null;
if ($ref) {
    $st = $db->prepare("SELECT * FROM orders WHERE ref_id=?");
    $st->execute([$ref]);
    $order = $st->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Status Pesanan - <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:0}
.wrap{max-width:560px;margin:40px auto;padding:0 16px}
.box{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:24px}
table{width:100%;border-collapse:collapse;margin-top:12px}
td{padding:8px 6px;border-bottom:1px solid #334155;font-size:14px}
td:first-child{color:#94a3b8;width:40%}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-weight:700;font-size:13px}
.ok{background:#065f46;color:#6ee7b7}.wait{background:#78350f;color:#fcd34d}.fail{background:#7f1d1d;color:#fca5a5}
a{color:#38bdf8}
</style>
</head>
<body>
<div class="wrap">
<div class="box">
<a href="/" style="color:#38bdf8;text-decoration:none">&larr; Kembali</a>
<h2>Status Pesanan</h2>
<?php if (!$order): ?>
  <p style="color:#f87171">Pesanan tidak ditemukan. Masukkan kode order di <a href="/cek-status.php">Cek Status</a>.</p>
<?php else:
  $cls = $order['order_status']==='success' ? 'ok' : ($order['order_status']==='failed' ? 'fail' : 'wait'); ?>
  <table>
    <tr><td>Kode Order</td><td><?= htmlspecialchars($order['ref_id']) ?></td></tr>
    <tr><td>Produk</td><td><?= htmlspecialchars($order['product_name']) ?> (<?= htmlspecialchars($order['product_code']) ?>)</td></tr>
    <tr><td>ID Game</td><td><?= htmlspecialchars($order['player_id']) ?><?= $order['zone_id'] ? ' • '.htmlspecialchars($order['zone_id']) : '' ?></td></tr>
    <tr><td>Jumlah Bayar</td><td>Rp <?= number_format((int)$order['amount'],0,',','.') ?></td></tr>
    <tr><td>Status Pembayaran</td><td><span class="badge wait"><?= paymentStatusText($order['payment_status']) ?></span></td></tr>
    <tr><td>Status Order</td><td><span class="badge <?= $cls ?>"><?= paymentStatusText($order['order_status']) ?></span></td></tr>
    <?php if ($order['sn']): ?><tr><td>SN / Voucher</td><td><?= htmlspecialchars($order['sn']) ?></td></tr><?php endif; ?>
  </table>
  <?php if ($order['order_status']==='pending'): ?><p style="color:#fcd34d;font-size:13px">Order sedang diproses. Halaman ini bisa di-refresh; atau cek lagi nanti.</p><?php endif; ?>
<?php endif; ?>
</div>
</div>
</body>
</html>
