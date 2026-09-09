<?php
require_once __DIR__ . '/includes/functions.php';
$db = db();
$code = $_GET['code'] ?? '';
$product = null;
if ($code) {
    $st = $db->prepare("SELECT * FROM products WHERE code=? AND status=1");
    $st->execute([$code]);
    $product = $st->fetch();
}
if (!$product) { echo "<h2>Produk tidak ditemukan</h2><a href='/'>Kembali</a>"; exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Beli <?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:0}
.wrap{max-width:520px;margin:40px auto;padding:0 16px}
.box{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:24px}
h2{margin-top:0} label{display:block;margin:12px 0 6px;font-weight:600;color:#cbd5e1}
input,select{width:100%;padding:11px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:15px}
.price{color:#fbbf24;font-weight:800;font-size:22px;margin:6px 0}
button{width:100%;margin-top:18px;padding:13px;border:0;border-radius:10px;background:#38bdf8;color:#0f172a;font-weight:800;font-size:16px;cursor:pointer}
button:hover{background:#0ea5e9}
.err{color:#f87171;margin-top:10px}
#pay-box{margin-top:14px;display:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="box">
    <a href="/" style="color:#38bdf8;text-decoration:none">&larr; Katalog</a>
    <h2 style="margin-top:8px"><?= htmlspecialchars($product['name']) ?></h2>
    <div class="price">Rp <?= number_format((int)$product['price'],0,',','.') ?></div>

    <form id="topup-form">
      <input type="hidden" name="code" value="<?= htmlspecialchars($product['code']) ?>">
      <input type="hidden" name="amount" value="<?= (int)$product['price'] ?>">
      <label>Nama / ID Game</label>
      <input type="text" name="player_id" required placeholder="ID game Anda (sukses/unik)">
      <label>Zona / Server (opsional, jika ada)</label>
      <input type="text" name="zone_id" placeholder="e.g. 2088">
      <label>Nomor HP (untuk notifikasi)</label>
      <input type="text" name="customer_no" required placeholder="08xxxxxxxxxx">
      <button type="submit">Bayar Rp <?= number_format((int)$product['price'],0,',','.') ?></button>
      <div class="err" id="err"></div>
    </form>

    <div id="pay-box">
      <div class="price" style="font-size:16px">Menunggu pembayaran via Midtrans...</div>
      <button id="pay-btn" style="background:#22c55e">Klik untuk Bayar</button>
    </div>
    <a id="snap-url" style="display:none"></a>
  </div>
</div>

<script src="<?= MIDTRANS_IS_PRODUCTION ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' ?>" data-client-key="<?= MT_CLIENT_KEY ?>"></script>
<script>
const form = document.getElementById('topup-form');
const errEl = document.getElementById('err');
let storedResult = null;

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errEl.textContent = '';
  const fd = new FormData(form);
  const body = Object.fromEntries(fd.entries());
  try {
    const r = await fetch('/api/order.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const d = await r.json();
    if (!d.ok) { errEl.textContent = d.message || 'Gagal membuat order'; return; }
    storedResult = d;
    document.getElementById('pay-box').style.display = 'block';
    const snap = window.snap;
    snap.pay(d.token, {
      onSuccess: (r)=> window.location.href = '/status.php?ref='+encodeURIComponent(d.ref_id),
      onPending: (r)=> window.location.href = '/status.php?ref='+encodeURIComponent(d.ref_id),
      onError: (r)=> { errEl.textContent = 'Pembayaran gagal: ' + (r.status_message||''); },
      onClose: ()=> { document.getElementById('pay-box').style.display='none'; }
    });
  } catch (ex) { errEl.textContent = 'Error: '+ex.message; }
});
</script>
</body>
</html>
