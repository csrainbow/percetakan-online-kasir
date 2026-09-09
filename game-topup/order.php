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
if (!$product) { ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Produk Tidak Ditemukan</title></head>
<body><div style="max-width:460px;margin:80px auto;padding:0 20px;text-align:center">
<h2 style="margin-bottom:12px">Produk tidak ditemukan</h2>
<a href="<?= BASE_PATH ?>/">Kembali ke Katalog</a>
</div></body></html>
<?php exit; }
$price = (int)$product['price'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Beli <?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars(SITE_NAME) ?></title>
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
      <a class="back" href="<?= BASE_PATH ?>/">&larr; Katalog</a>
      <?php $logo = ($product['brand'] ?? '') ? brand_logo_url($product['brand'] ?? '') : ''; ?>
      <div class="prod-thumb-wrap">
        <img class="prod-thumb" src="<?= product_thumb_datauri($product['name'], product_category($product['name'])) ?>" alt="Thumbnail <?= htmlspecialchars($product['name']) ?>">
        <?php if ($logo !== ''): ?><img class="brand-logo brand-logo-lg" src="<?= htmlspecialchars($logo) ?>" alt="logo"><?php endif; ?>
      </div>
      <h1 class="title"><?= htmlspecialchars($product['name']) ?></h1>
      <div class="card-code" style="margin-top:4px"><?= htmlspecialchars($product['code']) ?></div>
      <div class="price-big">Rp <?= number_format($price,0,',','.') ?></div>

      <form id="topup-form">
        <input type="hidden" name="code" value="<?= htmlspecialchars($product['code']) ?>">
        <input type="hidden" name="amount" value="<?= $price ?>">
        <div class="field">
          <label for="f-pid">Nama / ID Game</label>
          <input class="input" id="f-pid" type="text" name="player_id" required placeholder="ID game Anda (sukses/unik)">
        </div>
        <div class="field">
          <label for="f-zone">Zona / Server <small>opsional, jika ada</small></label>
          <input class="input" id="f-zone" type="text" name="zone_id" placeholder="e.g. 2088">
        </div>
        <div class="field">
          <label for="f-no">Nomor HP <small>untuk resi pembelian</small></label>
          <input class="input" id="f-no" type="text" name="customer_no" required placeholder="08xxxxxxxxxx">
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="submit-btn">Bayar Rp <?= number_format($price,0,',','.') ?></button>
        <div class="err" id="err"></div>
      </form>

      <div class="pay-hint" id="pay-box">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <span style="width:10px;height:10px;border-radius:50%;background:var(--gold);box-shadow:0 0 12px var(--gold);animation:pulse 1.4s infinite"></span>
          <strong>Order dibuat!</strong>
        </div>
        <div>Jendela pembayaran Midtrans akan terbuka. Pilih QRIS, transfer, VA, atau e-wallet.</div>
        <button type="button" id="pay-btn" class="btn btn-primary btn-full" style="margin-top:14px">Buka Jendela Bayar</button>
      </div>
    </div>
  </div>
</div>

<footer class="site-footer">
  <div class="container footer-in">
    <div>&copy; <?= date('Y') ?> <b><?= htmlspecialchars(SITE_NAME) ?></b></div>
    <div class="footer-links"><a href="<?= BASE_PATH ?>/cek-status.php">Cek Status</a></div>
  </div>
</footer>

<style>@keyframes pulse{50%{opacity:.35}}</style>
<script src="<?= MIDTRANS_IS_PRODUCTION ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' ?>" data-client-key="<?= MT_CLIENT_KEY ?>"></script>
<script>
const form = document.getElementById('topup-form');
const errEl = document.getElementById('err');
const submitBtn = document.getElementById('submit-btn');
let storedResult = null;

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errEl.textContent = '';
  submitBtn.disabled = true;
  submitBtn.textContent = 'Memproses...';
  const fd = new FormData(form);
  const body = Object.fromEntries(fd.entries());
  try {
    const r = await fetch('<?= BASE_PATH ?>/api/order.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const d = await r.json();
    if (!d.ok) { errEl.textContent = d.message || 'Gagal membuat order'; submitBtn.disabled = false; submitBtn.textContent = 'Bayar Rp <?= number_format($price,0,',','.') ?>'; return; }
    storedResult = d;
    document.getElementById('pay-box').style.display = 'block';
    form.style.display = 'none';
    submitBtn.disabled = false;
    window.snap.pay(d.token, {
      onSuccess: (r)=> window.location.href = '<?= BASE_PATH ?>/status.php?ref='+encodeURIComponent(d.ref_id),
      onPending: (r)=> window.location.href = '<?= BASE_PATH ?>/status.php?ref='+encodeURIComponent(d.ref_id),
      onError: (r)=> { errEl.textContent = 'Pembayaran gagal: ' + (r.status_message||''); },
      onClose: ()=> {}
    });
  } catch (ex) {
    errEl.textContent = 'Error: '+ex.message;
    submitBtn.disabled = false;
    submitBtn.textContent = 'Bayar Rp <?= number_format($price,0,',','.') ?>';
  }
});
document.getElementById('pay-btn').addEventListener('click', () => {
  if (storedResult) window.snap.pay(storedResult.token, {
    onSuccess: (r)=> window.location.href = '<?= BASE_PATH ?>/status.php?ref='+encodeURIComponent(storedResult.ref_id),
    onPending: (r)=> window.location.href = '<?= BASE_PATH ?>/status.php?ref='+encodeURIComponent(storedResult.ref_id),
  });
});
</script>
</body>
</html>