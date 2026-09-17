<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Produk - Percetakan Rainbow';

// 🔥 AMBIL SEMUA PRODUK (untuk filter)
$allProducts = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

// 🔥 FILTER PRODUK BERDASARKAN KATEGORI (jika ada)
$filterCategory = $_GET['category'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$sortQuery = $_GET['sort'] ?? 'newest';

$displayProducts = $allProducts;
if ($filterCategory !== '') {
    $displayProducts = array_filter($displayProducts, function($p) use ($filterCategory) {
        return $p['category'] === $filterCategory;
    });
    $displayProducts = array_values($displayProducts);
}
if ($searchQuery !== '') {
    $qLower = strtolower($searchQuery);
    $displayProducts = array_filter($displayProducts, function($p) use ($qLower) {
        return str_contains(strtolower($p['name']), $qLower)
            || str_contains(strtolower($p['category'] ?? ''), $qLower);
    });
    $displayProducts = array_values($displayProducts);
}

// 🔥 SORT
switch ($sortQuery) {
    case 'price_asc':  usort($displayProducts, fn($a,$b) => (int)$a['price'] <=> (int)$b['price']); break;
    case 'price_desc': usort($displayProducts, fn($a,$b) => (int)$b['price'] <=> (int)$a['price']); break;
    case 'name':       usort($displayProducts, fn($a,$b) => strcmp($a['name'], $b['name'])); break;
    default: break; // newest dahulu (urutan awal)
}

// 🔥 AMBIL SEMUA PRODUK UNTUK DITAMPILKAN (full listing di beranda)
$products = $displayProducts;

include 'includes/header.php';
?>

<style>
/* ============================================
   HOME / PRODUK PAGE STYLES (Percetakan Rainbow)
   ============================================ */

/* 🔥 SECTION TITLE */
.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}
.section-title h2 {
    font-size: 22px;
    color: #111111;
    margin: 0;
    white-space: nowrap;
}
.section-title::after {
    content: '';
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, rgba(255,0,170,0.7), rgba(45,212,191,0.3), transparent);
}

/* 🔥 KATEGORI (chip filter) */
.category-scroll {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    min-width: 0;
    max-width: 100%;
}
.category-chips {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    overflow-x: auto;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(45,212,191,.45) transparent;
    padding: 4px 2px 8px;
    border-radius: 12px;
    flex: 1;
    min-width: 0;
    max-width: 100%;
}
.category-chips::-webkit-scrollbar { height: 6px; }
.category-chips::-webkit-scrollbar-thumb { background: rgba(45,212,191,.45); border-radius: 999px; }
.category-chips::-webkit-scrollbar-track { background: transparent; }
.category-chip { flex-shrink: 0; white-space: nowrap; }
.cat-arrow {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid #e6e9f0;
    background: #fff;
    color: #333;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    align-items: center;
    justify-content: center;
    display: none;
    transition: all 0.2s;
    padding: 0;
}
.cat-arrow:hover { border-color: rgba(45,212,191,0.6); color: #14b8a6; }
.cat-arrow.prev { margin-right: 8px; }
.cat-arrow.next { margin-left: 8px; }
@media (max-width: 768px) {
    .cat-arrow { display: none !important; }
}
.category-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    background: #fff;
    border: 1px solid #e6e9f0;
    border-radius: 50px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
}
.category-chip:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(45,212,191,0.25);
    border-color: rgba(45,212,191,0.6);
}
.category-chip.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 10px 26px rgba(13,27,42,0.35);
}
.category-chip .chip-icon {
    font-size: 17px;
}

/* 🔥 FILTER BAR */
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    background: #fff;
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,0.05);
    box-shadow: 0 8px 28px rgba(0,0,0,0.07);
    margin-bottom: 24px;
    position: relative;
}
.filter-bar::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent1), var(--accent2), var(--accent3));
    border-radius: 16px 16px 0 0;
    opacity: 0.55;
}
.filter-bar .filter-search {
    flex: 1;
    min-width: 220px;
    display: flex;
    gap: 8px;
}
.filter-bar .filter-search input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #dde2ea;
    font-size: 14px;
    background: #fafbfc;
    transition: all 0.3s;
}
.filter-bar .filter-search input:focus {
    outline: none;
    border-color: #00c2d1;
    box-shadow: 0 0 0 3px rgba(0,194,209,0.15);
    background: #fff;
}
.filter-bar .filter-sort select {
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #dde2ea;
    font-size: 14px;
    background: #fafbfc;
    color: #333;
    cursor: pointer;
}
.filter-bar .filter-sort select:focus {
    outline: none;
    border-color: #00c2d1;
}
.btn-grad {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    color: var(--dark);
    font-weight: 700;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
    text-decoration: none;
    box-shadow: 0 6px 22px rgba(45,212,191,0.25);
}
.btn-grad:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 34px rgba(255,0,170,0.3);
    color: var(--dark);
}
.btn-outline-dark {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: 10px;
    background: #fff;
    color: #111;
    font-weight: 600;
    font-size: 14px;
    border: 1px solid #111;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}
.btn-outline-dark:hover {
    background: #111;
    color: #fff;
}
.filter-result-info {
    font-size: 13px;
    color: #6b7280;
    margin: -12px 0 20px;
    padding: 0 4px;
}
.filter-result-info strong {
    color: #111;
}

/* 🔥 PRODUCT GRID */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 20px;
}
.product-card {
    background: linear-gradient(180deg, #fff 0%, #f8f9fb 100%);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 10px 34px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    border: 1px solid rgba(0,0,0,0.06);
    position: relative;
    display: flex;
    flex-direction: column;
}
.product-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent1), var(--accent2), var(--accent3));
    z-index: 2;
    opacity: 0;
    transition: opacity 0.4s;
}
.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 24px 56px rgba(255,0,170,0.14), 0 14px 28px rgba(0,0,0,0.1);
    border-color: rgba(255,0,170,0.25);
}
.product-card:hover::before {
    opacity: 1;
}
.product-img-link {
    display: block;
    text-decoration: none;
    position: relative;
    overflow: hidden;
    background: #f1f3f7;
    height: 180px;
}
.product-img-link .product-img {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    transition: transform 0.35s;
    background-size: cover;
    background-position: center;
}
.product-card:hover .product-img {
    transform: scale(1.06);
}
.product-category-tag {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(13,27,42,0.88);
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 4px 10px;
    border-radius: 20px;
    z-index: 1;
}
.product-info {
    padding: 13px 16px 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.product-info h3 {
    font-size: 14px;
    margin: 0 0 6px;
    line-height: 1.4;
    color: #111111;
    flex: 1;
}
.product-price {
    font-size: 17px;
    font-weight: 700;
    color: #111111;
    margin-bottom: 10px;
}
.product-price small {
    font-size: 11px;
    font-weight: 500;
    color: #6c757d;
}
.product-info .btn {
    display: block;
    width: 100%;
    padding: 9px;
    text-align: center;
    border-radius: 10px;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid #111111;
    color: #111111;
    background: transparent;
    font-weight: 600;
}
.product-info .btn:hover {
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    border-color: transparent;
    color: var(--dark);
}

/* 🔥 EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    grid-column: 1 / -1;
}
.empty-state .icon {
    font-size: 64px;
    display: block;
    margin-bottom: 15px;
}
.empty-state h2 {
    color: #111111;
    font-size: 22px;
    margin-bottom: 8px;
}
.empty-state p {
    color: #6c757d;
    margin-bottom: 18px;
}

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar .filter-search { min-width: 100%; }
    .filter-bar .filter-sort select { width: 100%; }
    .btn-grad, .btn-outline-dark { justify-content: center; }
    .product-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .product-img-link { height: 130px; }
    .product-info { padding: 10px 12px 12px; }
    .product-info h3 { font-size: 12.5px; }
    .product-price { font-size: 14px; }
}

@media (max-width: 480px) {
    .product-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .product-img-link { height: 120px; }
    .product-info { padding: 9px 10px 11px; }
    .product-info h3 { font-size: 12px; }
    .product-price { font-size: 13px; }
    .category-chips { gap: 7px; }
    .category-chip { padding: 7px 13px; font-size: 12px; }
}

@media (max-width: 359px) {
    .product-grid { gap: 8px; }
    .product-info h3 { font-size: 11px; }
    .product-info .btn { padding: 7px; font-size: 12px; }
}
</style>

<!-- 🔥 KATEGORI -->
<?php $cats = getCategories(); ?>
<?php if (!empty($cats)): ?>
<div class="section-title"><h2>📂 Kategori</h2></div>
<div class="category-scroll" id="catScroll">
    <button type="button" class="cat-arrow prev" id="catPrev" aria-label="Geser kategori ke kiri">‹</button>
    <div class="category-chips" id="catChips">
        <a href="?" class="category-chip <?= $filterCategory === '' ? 'active' : '' ?>">
            <span class="chip-icon">🔖</span> Semua
        </a>
        <?php foreach ($cats as $cat): ?>
        <a href="?category=<?= urlencode($cat['name']) ?>" class="category-chip <?= $filterCategory === $cat['name'] ? 'active' : '' ?>">
            <span class="chip-icon"><?= htmlspecialchars($cat['icon']) ?></span>
            <?= htmlspecialchars($cat['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <button type="button" class="cat-arrow next" id="catNext" aria-label="Geser kategori ke kanan">›</button>
</div>
<script>
(function () {
    var wrap = document.getElementById("catScroll");
    if (!wrap) return;
    var prev = document.getElementById("catPrev");
    var next = document.getElementById("catNext");
    var chips = document.getElementById("catChips");
    function upd() {
        var max = chips.scrollWidth - chips.clientWidth - 2;
        if (window.innerWidth <= 768) { prev.style.display = "none"; next.style.display = "none"; return; }
        prev.style.display = chips.scrollLeft > 4 ? "flex" : "none";
        next.style.display = chips.scrollLeft < max - 4 ? "flex" : "none";
    }
    prev.addEventListener("click", function () { chips.scrollBy({ left: -260, behavior: "smooth" }); });
    next.addEventListener("click", function () { chips.scrollBy({ left: 260, behavior: "smooth" }); });
    chips.addEventListener("scroll", upd);
    window.addEventListener("resize", upd);
    upd();
})();
</script>
<?php endif; ?>

<!-- 🔥 FILTER & PENCARIAN -->
<form method="GET" action="" class="filter-bar">
    <div class="filter-search">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="🔍 Cari produk...">
        <?php if ($filterCategory !== ''): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>">
        <?php endif; ?>
    </div>
    <div class="filter-sort">
        <select name="sort">
            <option value="newest" <?= $sortQuery === 'newest' ? 'selected' : '' ?>>Terbaru</option>
            <option value="name" <?= $sortQuery === 'name' ? 'selected' : '' ?>>Nama A-Z</option>
            <option value="price_asc" <?= $sortQuery === 'price_asc' ? 'selected' : '' ?>>Harga Terendah</option>
            <option value="price_desc" <?= $sortQuery === 'price_desc' ? 'selected' : '' ?>>Harga Tertinggi</option>
        </select>
    </div>
    <button type="submit" class="btn-grad"><i class="fas fa-search"></i> Cari</button>
    <?php if ($searchQuery !== '' || $filterCategory !== ''): ?>
        <a href="?" class="btn-outline-dark"><i class="fas fa-undo"></i> Reset</a>
    <?php endif; ?>
</form>

<?php if ($searchQuery !== '' || $filterCategory !== ''): ?>
<p class="filter-result-info">
    Menampilkan <strong><?= count($displayProducts) ?></strong> produk
    <?= $filterCategory !== '' ? 'dalam kategori <strong>' . htmlspecialchars($filterCategory) . '</strong>' : '' ?>
    <?= $searchQuery !== '' ? 'yang cocok <strong>"' . htmlspecialchars($searchQuery) . '"</strong>' : '' ?>
</p>
<?php endif; ?>

<!-- 🔥 DAFTAR PRODUK -->
<div class="section-title"><h2>🔥 Semua Produk</h2></div>
<div class="product-grid">
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <span class="icon">🔍</span>
            <h2>Tidak ada produk ditemukan</h2>
            <p>Belum ada produk yang cocok dengan pencarianmu.</p>
            <a href="?" class="btn-grad">Lihat Semua Produk</a>
        </div>
    <?php else: ?>
        <?php foreach ($products as $p):
            $firstImg = getFirstProductImage($p['id']);
            $priceDisplay = $p['custom_size'] ? formatRupiah($p['price_per_m2']) : formatRupiah($p['price']);
        ?>
        <div class="product-card">
            <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="product-img-link">
                <div class="product-img" style="<?= $firstImg ? 'background-image:url(/uploads/' . htmlspecialchars($firstImg) . ');' : '' ?>">
                    <?php if (!$firstImg):
                        $icons = ['Brosur'=>'📄','Kartu Nama'=>'🪪','Banner'=>'🖼️','Sticker'=>'🏷️','Undangan'=>'💌','Foto'=>'📸','Desain'=>'🎨','Kalender'=>'📅'];
                        echo $icons[$p['category']] ?? '📄';
                    endif; ?>
                </div>
                <span class="product-category-tag"><?= htmlspecialchars($p['category']) ?></span>
            </a>
            <div class="product-info">
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <p class="product-price"><?= $priceDisplay ?></p>
                <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="btn">Detail</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>