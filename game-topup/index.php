<?php
require_once __DIR__ . '/includes/functions.php';
$db = db();
$products = $db->query("SELECT * FROM products WHERE status=1 ORDER BY game_id, name LIMIT 200")->fetchAll();
if (!$products) {
    // fallback: ambil langsung dari Digiflazz bila cache kosong
    try {
        $dgf = new Digiflazz();
        $res = $dgf->priceListV2('game');
        if (empty($res['error'])) {
            $products = $res['data']['pricelist'] ?? [];
        }
    } catch (Throwable $e) {
        $products = [];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box;margin:0} body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
header{background:#1e293b;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
header h1{font-size:20px} header a{color:#38bdf8;text-decoration:none;font-weight:600}
main{max-width:1100px;margin:24px auto;padding:0 16px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-top:16px}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:10px}
.card .name{font-weight:700;font-size:15px;line-height:1.3}
.card .price{color:#fbbf24;font-weight:800;font-size:18px}
.card .code{color:#94a3b8;font-size:12px}
.btn{background:#38bdf8;color:#0f172a;text-align:center;padding:10px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:auto}
.btn:hover{background:#0ea5e9}
.empty{color:#94a3b8;text-align:center;padding:40px}
footer{text-align:center;color:#64748b;padding:24px;font-size:13px}
</style>
</head>
<body>
<header>
  <h1>🎮 <?= htmlspecialchars(SITE_NAME) ?></h1>
  <a href="/cek-status.php">Cek Status Order</a>
</header>
<main>
  <h2>Pilih Voucher / Isi Ulang</h2>
  <?php if (empty($products)): ?>
    <div class="empty">Belum ada produk. Jalankan "Sinkronisasi Pricelist" dari admin, atau pricelist Digiflazz sedang kosong/di-limit.</div>
  <?php else: ?>
  <div class="grid">
    <?php foreach ($products as $p):
        $code = $p['code'] ?? $p['buyer_sku_code'] ?? '';
        $name = $p['name'] ?? $p['product_name'] ?? $code;
        $price = (int)($p['price'] ?? $p['product_seller'] ?? 0); ?>
      <div class="card">
        <div class="name"><?= htmlspecialchars($name) ?></div>
        <div class="price">Rp <?= number_format($price, 0, ',', '.') ?></div>
        <div class="code"><?= htmlspecialchars($code) ?></div>
        <a class="btn" href="/order.php?code=<?= urlencode($code) ?>">Beli</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<footer>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?></footer>
</body>
</html>
