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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="container header-in">
    <a class="brand" href="<?= BASE_PATH ?>/"><span class="brand-badge">T</span>TopUp<span>Games</span></a>
    <nav class="nav-links">
      <a href="<?= BASE_PATH ?>/cek-status.php">Cek Status</a>
    </nav>
  </div>
</header>

<div class="page">
  <div class="page-slim">
    <div class="box">
      <a class="back" href="<?= BASE_PATH ?>/">&larr; Beranda</a>
      <h1 class="title">Cek Status Order</h1>
      <p style="color:var(--muted);font-size:13.5px;margin:6px 0 20px">Masukkan kode order yang Anda terima setelah pembayaran.</p>
      <form method="post">
        <div class="field" style="margin-bottom:14px">
          <input class="input mono" type="text" name="ref" required placeholder="TOPUP-2026090912xxxx-xxxx" value="<?= htmlspecialchars($ref) ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Cek Status</button>
      </form>

      <?php if ($queried): ?>
        <?php if (!$order): ?>
          <div class="msg err" style="margin-top:20px">Pesanan tidak ditemukan. Periksa kembali kode order Anda.</div>
        <?php else:
            $cls = $order['order_status']==='success' ? 'ok' : ($order['order_status']==='failed' ? 'fail' : 'wait'); ?>
          <div class="rowlist" style="margin-top:20px">
            <div class="row"><span class="k">Produk</span><span class="v"><?= htmlspecialchars($order['product_name']) ?></span></div>
            <div class="row"><span class="k">Kode Order</span><span class="v mono"><?= htmlspecialchars($order['ref_id']) ?></span></div>
            <div class="row"><span class="k">Status Order</span><span class="v"><span class="badge <?= $cls ?>"><?= paymentStatusText($order['order_status']) ?></span></span></div>
            <div class="row"><span class="k">Pembayaran</span><span class="v"><span class="badge <?= $order['payment_status']==='paid'?'paid':($order['payment_status']==='expired'?'expired':'wait') ?>"><?= paymentStatusText($order['payment_status']) ?></span></span></div>
            <?php if ($order['sn']): ?>
              <div class="row"><span class="k">SN</span><span class="v"><span class="sn-box" style="margin:0"><?= htmlspecialchars($order['sn']) ?></span></span></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<footer class="site-footer">
  <div class="container footer-in">
    <div>&copy; <?= date('Y') ?> <b><?= htmlspecialchars(SITE_NAME) ?></b></div>
    <div class="footer-links"><a href="<?= BASE_PATH ?>/">Katalog</a></div>
  </div>
</footer>
</body>
</html>