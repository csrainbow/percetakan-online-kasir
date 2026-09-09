<?php
require_once __DIR__ . '/includes/functions.php';
$db = db();
$ref = trim($_GET['ref'] ?? ($_POST['ref'] ?? ''));
$order = null; $queried = false;
if ($ref) {
    $queried = true;
    $st = $db->prepare("SELECT * FROM orders WHERE ref_id=?");
    $st->execute([$ref]);
    $order = $st->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cek Status - <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:0}
.wrap{max-width:480px;margin:40px auto;padding:0 16px}
.box{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:24px}
input{width:100%;padding:11px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:15px}
button{width:100%;margin-top:12px;padding:12px;border:0;border-radius:10px;background:#38bdf8;color:#0f172a;font-weight:800;cursor:pointer}
.result{margin-top:18px;background:#0f172a;border:1px solid #334155;border-radius:10px;padding:14px;font-size:14px}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-weight:700;font-size:12px}
.ok{background:#065f46;color:#6ee7b7}.wait{background:#78350f;color:#fcd34d}.fail{background:#7f1d1d;color:#fca5a5}
a{color:#38bdf8}
</style>
</head>
<body>
<div class="wrap">
<div class="box">
<a href="/" style="color:#38bdf8;text-decoration:none">&larr; Beranda</a>
<h2>Cek Status Order</h2>
<form method="post">
  <input type="text" name="ref" required placeholder="Kode order, contoh: TOPUP-20260909120500-xxxx" value="<?= htmlspecialchars($ref) ?>">
  <button type="submit">Cek</button>
</form>
<?php if ($queried): ?>
  <?php if (!$order): ?>
    <div class="result" style="color:#f87171">Pesanan tidak ditemukan.</div>
  <?php else:
      $cls = $order['order_status']==='success' ? 'ok' : ($order['order_status']==='failed' ? 'fail' : 'wait'); ?>
    <div class="result">
      <div><strong><?= htmlspecialchars($order['product_name']) ?></strong> (<?= htmlspecialchars($order['product_code']) ?>)</div>
      <div style="color:#94a3b8;margin:4px 0"><?= htmlspecialchars($order['ref_id']) ?></div>
      <div>Status Order: <span class="badge <?= $cls ?>"><?= paymentStatusText($order['order_status']) ?></span></div>
      <div>Pembayaran: <span class="badge wait"><?= paymentStatusText($order['payment_status']) ?></span></div>
      <?php if ($order['sn']): ?><div style="margin-top:6px">SN: <strong><?= htmlspecialchars($order['sn']) ?></strong></div><?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
</div>
</body>
</html>
