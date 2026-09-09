<?php
/**
 * Sync pricelist Digiflazz ke tabel products.
 * Jalankan: php cli/sync-pricelist.php  (atau via cron tiap 1 jam)
 */
require_once __DIR__ . '/../includes/functions.php';

$db = db();
$dgf = new Digiflazz();

echo "Mengambil pricelist dari Digiflazz...\n";
$res = $dgf->priceListV2('game');

if (!empty($res['error'])) {
    echo "ERROR: {$res['error']}\n";
    exit(1);
}

$list = $res['data']['pricelist'] ?? [];
echo "Mendapat " . count($list) . " item.\n";

$inserted = 0;
$updated = 0;
$st = $db->prepare("INSERT INTO products (game_id, code, name, price, buy_price, status)
                    VALUES (?,?,?,?,?,1)
                    ON CONFLICT(code) DO UPDATE SET name=excluded.name, buy_price=excluded.buy_price, price=excluded.price");

foreach ($list as $p) {
    $code = $p['buyer_sku_code'] ?? '';
    if (!$code) continue;
    $name = $p['product_name'] ?? $code;
    // Harga jual = harga beli + margin (default 0 margin; set margin manual)
    $buy = (int) ($p['product_price'] ?? 0);   // harga profit provider (harga modal anda)
    $sell = (int) ($p['price'] ?? 0);          // harga ke konsumen
    if ($sell <= 0) $sell = (int) floor($buy * 1.1);
    $gameId = 1;
    $st->execute([$gameId, $code, $name, $sell, $buy]);
    $inserted++;
}

$db->exec("INSERT OR REPLACE INTO settings (key,value) VALUES ('pricelist_updated', '" . date('Y-m-d H:i:s') . "')");
echo "Selesai. Total diproses: $inserted\n";
