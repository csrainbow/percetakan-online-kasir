<?php
require_once __DIR__ . '/includes/functions.php';
$db = db();
$products = $db->query("SELECT * FROM products WHERE status=1 ORDER BY game_id, name LIMIT 300")->fetchAll();
if (!$products) {
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

$getCat = function ($name) {
    $n = strtolower($name ?? '');
    $games = [
        ['mobile legend', 'Mobile Legends'], ['free fire', 'Free Fire'], ['pubg', 'PUBG'],
        ['genshin', 'Genshin Impact'], ['honkai', 'Honkai'], ['roblox', 'Roblox'],
        ['minecraft', 'Minecraft'], ['valorant', 'Valorant'], ['call of duty', 'CoD'],
        ['cod', 'CoD'], ['hok', 'Honor of Kings'], ['lol', 'League of Legends'],
        ['ff diamond', 'Free Fire'], ['ml diamond', 'Mobile Legends'], ['diamond', 'Mobile Legends'],
    ];
    foreach ($games as $g) {
        if (strpos($n, $g[0]) !== false) return $g[1];
    }
    $toks = preg_split('/[\s\-]+/', $n);
    if (array_intersect($toks, ['data', 'xl', 'axis', 'three', 'tri', 'indosat', 'ims', 'smartfren', 'telkomsel', 'tsel', 'byu', 'sbyu', 'unlimited', 'kuota'])) {
        return 'Paket Data';
    }
    return 'Voucher';
};

$grads = [
    'linear-gradient(135deg,#22d3ee,#3b82f6)',
    'linear-gradient(135deg,#8b5cf6,#d946ef)',
    'linear-gradient(135deg,#f472b6,#f59e0b)',
    'linear-gradient(135deg,#34d399,#22d3ee)',
    'linear-gradient(135deg,#f87171,#f59e0b)',
    'linear-gradient(135deg,#60a5fa,#8b5cf6)',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(SITE_NAME) ?></title>
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
      <a href="<?= BASE_PATH ?>/admin/">Admin</a>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="container">
    <span class="hero-eyebrow">Isi Ulang Digital</span>
    <h1>Top Up Game <span class="grad">Cepat &amp; Aman</span></h1>
    <p class="hero-sub">Ratusan voucher game dan paket data dengan harga terbaik. Proses otomatis, pembayaran terjamin melalui Midtrans.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="#katalog">Lihat Katalog</a>
    </div>
    <div class="hero-stats">
      <span class="stat"><span class="stat-line"></span>Proses otomatis</span>
      <span class="stat"><span class="stat-line"></span>Pembayaran aman</span>
      <span class="stat"><span class="stat-line"></span>Banyak pilihan voucher</span>
    </div>
  </div>
</section>

<main class="section" id="katalog">
  <div class="container">
    <div class="section-head">
      <h2>Pilih Voucher</h2>
      <span class="count" id="count"></span>
    </div>

    <div class="toolbar">
      <div class="search">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="q" class="input" type="text" placeholder="Cari produk atau judul game&hellip;">
      </div>
      <div class="chips" id="chips"></div>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty">Belum ada produk. Jalankan "Sinkronisasi Pricelist" dari admin, atau pricelist Digiflazz sedang kosong/di-limit.</div>
    <?php else: ?>
    <div class="grid" id="grid">
      <?php foreach ($products as $i => $p):
          $code = $p['code'] ?? $p['buyer_sku_code'] ?? '';
          $name = $p['name'] ?? $p['product_name'] ?? $code;
          $price = (int)($p['price'] ?? $p['product_seller'] ?? 0);
          $cat = $getCat($name);
          $g = $grads[abs(crc32($name)) % count($grads)];
          $initial = strtoupper(trim($name))[0] ?? '?'; ?>
        <div class="card" data-cat="<?= htmlspecialchars($cat) ?>" data-q="<?= htmlspecialchars(strtolower($name . ' ' . $code . ' ' . $cat)) ?>">
          <div class="card-top">
            <div class="card-icon" style="background:<?= $g ?>"><?= htmlspecialchars($initial) ?></div>
            <span class="card-cat"><?= htmlspecialchars($cat) ?></span>
          </div>
          <div class="card-name"><?= htmlspecialchars($name) ?></div>
          <div class="card-code"><?= htmlspecialchars($code) ?></div>
          <div class="card-foot">
            <span class="card-price">Rp <?= number_format($price, 0, ',', '.') ?></span>
            <a class="card-btn" href="<?= BASE_PATH ?>/order.php?code=<?= urlencode($code) ?>">Beli</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>

<footer class="site-footer">
  <div class="container footer-in">
    <div>&copy; <?= date('Y') ?> <b><?= htmlspecialchars(SITE_NAME) ?></b>. Dibuat dengan &lt;3 oleh Tim</div>
    <div class="footer-links">
      <a href="<?= BASE_PATH ?>/cek-status.php">Cek Status</a>
      <a href="<?= BASE_PATH ?>/admin/">Admin</a>
    </div>
  </div>
</footer>

<script>
(function () {
  const q = document.getElementById('q');
  const grid = document.getElementById('grid');
  if (!grid) return;
  const chipsBox = document.getElementById('chips');
  const countEl = document.getElementById('count');
  const cards = Array.from(grid.querySelectorAll('.card'));
  let activeCat = 'Semua';

  const cats = [...new Set(cards.map(c => c.dataset.cat))].sort();
  const chipAll = document.createElement('span');
  chipAll.className = 'chip active'; chipAll.textContent = 'Semua'; chipAll.dataset.cat = 'Semua';
  chipAll.onclick = () => setCat('Semua', chipAll, null);
  chipsBox.appendChild(chipAll);
  cats.forEach(cat => {
    const el = document.createElement('span');
    el.className = 'chip'; el.textContent = cat; el.dataset.cat = cat;
    el.onclick = () => setCat(cat, el, null);
    chipsBox.appendChild(el);
  });

  function setCat(cat, el, ev) {
    activeCat = cat;
    chipsBox.querySelectorAll('.chip').forEach(c => c.classList.toggle('active', c.dataset.cat === cat));
    apply();
  }

  q.addEventListener('input', apply);

  function visible(card) {
    if (activeCat !== 'Semua' && card.dataset.cat !== activeCat) return false;
    const s = q.value.trim().toLowerCase();
    return !s || (card.dataset.q || '').includes(s);
  }

  function apply() {
    let n = 0;
    cards.forEach(c => { const show = visible(c); c.style.display = show ? '' : 'none'; if (show) n++; });
    if (countEl) countEl.textContent = n + ' produk';
    const anyVisible = n > 0;
    let emptyEl = grid.parentElement.querySelector('.empty');
    if (!anyVisible && !emptyEl) {
      emptyEl = document.createElement('div');
      emptyEl.className = 'empty'; emptyEl.textContent = 'Produk tidak ditemukan.';
      grid.after(emptyEl);
    }
    if (emptyEl) emptyEl.style.display = anyVisible ? 'none' : '';
  }
  apply();
})();
</script>
</body>
</html>