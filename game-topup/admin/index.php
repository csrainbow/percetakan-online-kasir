<?php
require_once __DIR__ . '/../includes/functions.php';
$db = db();

// Simple auth guard - ganti secret sesuai keinginan
session_start();
$ADMIN_PASS = 'admin123';
if (isset($_POST['admin_login'])) {
    if ($_POST['password'] === $ADMIN_PASS) $_SESSION['gt_admin'] = true;
}
if (isset($_GET['logout'])) unset($_SESSION['gt_admin']);
$authed = !empty($_SESSION['gt_admin']);

if (!$authed) { ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Admin</title>
<style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:40px}.box{max-width:360px;margin:auto;background:#1e293b;padding:24px;border-radius:12px}</style>
</head><body><div class="box"><h2>Login Admin</h2><form method="post">
<input type="hidden" name="admin_login" value="1">
<input type="password" name="password" placeholder="Password" style="width:100%;padding:10px">
<button style="width:100%;margin-top:10px;padding:12px;background:#38bdf8;border:0;color:#0f172a;font-weight:800">Masuk</button>
</form></div></body></html>
<?php exit; }

// Trigger sync via GET ?sync=1
$syncMsg = '';
if (isset($_GET['sync'])) {
    $dgf = new Digiflazz();
    $res = $dgf->priceListV2('game');
    if (empty($res['error']) && isset($res['data']['pricelist'])) {
        $st = $db->prepare("INSERT INTO products (game_id, code, name, price, buy_price, status) VALUES (1,?,?,?,?,1)
                            ON CONFLICT(code) DO UPDATE SET name=excluded.name, buy_price=excluded.buy_price, price=excluded.price");
        $n = 0;
        foreach ($res['data']['pricelist'] as $p) {
            $code = $p['buyer_sku_code'] ?? ''; if (!$code) continue;
            $buy = (int)($p['product_price'] ?? 0); $sell = (int)($p['price'] ?? 0);
            if ($sell <= 0) $sell = (int) floor($buy * 1.1);
            $st->execute([$code, $p['product_name'] ?? $code, $sell, $buy]); $n++;
        }
        $syncMsg = "Sync selesai: $n produk.";
    } else {
        $syncMsg = 'Sync gagal: ' . ($res['error'] ?? 'kredensial/limit');
    }
}

$orders = $db->query("SELECT * FROM orders ORDER BY id DESC LIMIT 30")->fetchAll();
$count = (int) $db->query("SELECT COUNT(*) c FROM products")->fetch()['c'];
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin TopUp</title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:20px}
h1,h2{color:#94a3b8} table{width:100%;border-collapse:collapse;background:#1e293b;border-radius:10px;overflow:hidden}
th,td{padding:9px 10px;border-bottom:1px solid #334155;text-align:left;font-size:13px}
th{background:#334155;color:#cbd5e1}.badge{padding:3px 8px;border-radius:20px;font-weight:700;font-size:11px}
.ok{background:#065f46;color:#6ee7b7}.wait{background:#78350f;color:#fcd34d}.fail{background:#7f1d1d;color:#fca5a5}
a.btn{background:#38bdf8;color:#0f172a;padding:9px 15px;border-radius:8px;text-decoration:none;font-weight:700}
.top{display:flex;gap:12px;align-items:center;margin-bottom:16px}
</style></head><body>
<h1>Admin TopUp</h1>
<div class="top">
  <a class="btn" href="?sync=1">Sync Pricelist Digiflazz</a>
  <span>Produk tersimpan: <?= $count ?></span>
  <a class="btn" href="?logout=1">Logout</a>
</div>
<?php if ($syncMsg): ?><div style="background:#065f46;color:#6ee7b7;padding:10px 14px;border-radius:8px;margin-bottom:14px"><?= htmlspecialchars($syncMsg) ?></div><?php endif; ?>
<h2>Order Terbaru</h2>
<table>
<tr><th>ID</th><th>Ref</th><th>Produk</th><th>ID Game</th><th>Nomor</th><th>Jumlah</th><th>Bayar</th><th>Order</th><th>SN</th><th>Tgl</th></tr>
<?php foreach ($orders as $o):
    $cls = $o['order_status']==='success'?'ok':($o['order_status']==='failed'?'fail':'wait');
    $pc = $o['payment_status']==='paid'?'ok':($o['payment_status']==='expired'?'fail':'wait'); ?>
<tr>
  <td><?= $o['id'] ?></td><td><?= htmlspecialchars($o['ref_id']) ?></td>
  <td><?= htmlspecialchars($o['product_name']) ?></td>
  <td><?= htmlspecialchars($o['player_id']) ?><?= $o['zone_id']?' / '.htmlspecialchars($o['zone_id']):'' ?></td>
  <td><?= htmlspecialchars($o['customer_no']) ?></td>
  <td>Rp <?= number_format((int)$o['amount'],0,',','.') ?></td>
  <td><span class="badge <?= $pc ?>"><?= htmlspecialchars($o['payment_status']) ?></span></td>
  <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($o['order_status']) ?></span></td>
  <td><?= htmlspecialchars($o['sn']) ?></td>
  <td><?= $o['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
