<?php
// ============================================
// FACEBOOK COMMERCE CATALOG FEED (CSV)
// https://rainbowprinting.web.id/facebook-feed.csv
// Dipakai oleh Meta Commerce Manager / Dynamic Ads.
// Baris produk di-render terhadap tabel products + product_images.
// ============================================
require_once __DIR__ . '/config.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: inline; filename="facebook-feed.csv"');
header('Cache-Control: no-cache');

$base = 'https://rainbowprinting.web.id';

// ✅ Field wajib Meta Catalog:
// id, title, description, availability, condition, price, currency, link, image_link
$header = [
    'id',
    'title',
    'description',
    'availability',
    'condition',
    'price',
    'currency',
    'link',
    'image_link',
    'brand',
    'google_product_category',
    'custom_label_0',
];

$out = fopen('php://output', 'w');

// BOM UTF-8 agar aman dibaca excel/editor
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $header);

$rows = $db->query("SELECT * FROM products ORDER BY id")->fetchAll();

$catGoogle = [
    'Spanduk'                => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Certificate & Sign Supplies',
    'Stiker'                 => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Stickers & Labels',
    'Stiker Outdoor'         => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Stickers & Labels',
    'Kartu Nama'             => 'Office Supplies > Paper & Desk Supplies > Paper.',
    'Roll Banner'            => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Certificate & Sign Supplies',
    'Y Banner'               => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Certificate & Sign Supplies',
    'Brosur/Famflet'         => 'Office Supplies > Print Production Ads & Services > Print Production',
    'Kaos Panjang dan Pendek'=> 'Apparel & Accessories > Clothing',
    'Desain'                 => 'Office Supplies > Print Production Ads & Services > Print Production',
    'Kalender'               => 'Office Supplies > Paper & Desk Supplies > Paper.',
    'Undangan'               => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Invitations & Announcements',
    'Foto'                   => 'Arts & Entertainment > Photography > Photography & Videography Services',
    'Sablon'                 => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Stickers & Labels',
    'Banner'                 => 'Arts & Entertainment > Party & Celebration & Gift Supplies > Certificate & Sign Supplies',
];

foreach ($rows as $p) {
    $name = (string)$p['name'];
    $slug = (string)$p['slug'];
    $desc = trim(strip_tags((string)($p['description'] ?? '')));
    if ($desc === '') {
        $desc = 'Cetak ' . $name . ' berkualitas dari Percetakan Rainbow Samarinda. Harga tertera dapat disesuaikan dengan ukuran/bahan pada halaman produk.';
    }

    // Ambil gambar utama: kolom `image` dahulu, fallback product_images.
    $img = (string)($p['image'] ?? '');
    if ($img === '') {
        $st = $db->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1");
        $st->execute([(int)$p['id']]);
        $rowImg = $st->fetch();
        if ($rowImg) $img = (string)$rowImg['image'];
    }
    $imgUrl = $img !== ''
        ? $base . '/uploads/' . rawurlencode($img) . '?v=' . filemtime(__DIR__ . '/uploads/' . $img)
        : $base . '/logo.png';

    $price = (float)$p['price'];
    if ((int)$p['custom_size'] === 1 && (float)$p['price_per_m2'] > 0) {
        $price = (float)$p['price_per_m2'];
    }

    $link = $base . '/product.php?slug=' . rawurlencode($slug);

    $cat = $catGoogle[(string)$p['category']] ?? 'Arts & Entertainment';

    fputcsv($out, [
        (int)$p['id'],
        $name,
        $desc,
        'in stock',
        'new',
        number_format($price, 0, '', ''),
        'IDR',
        $link,
        $imgUrl,
        'Percetakan Rainbow',
        $cat,
        (string)($p['category'] ?? ''),
    ]);
}

fclose($out);
exit;