<?php
/**
 * Sync pricelist Digiflazz ke tabel products.
 * Jalankan: php cli/sync-pricelist.php [game|prepaid]
 *   - default: prepaid (semua produk: pulsa, data, game, e-money)
 *   - opsi:    game    (hanya produk game)
 * Cron disarankan tiap >= 15 menit (batas API pricelist 1x/5 menit).
 */
require_once __DIR__ . '/../includes/functions.php';

$type = $argv[1] ?? 'prepaid';
if (!in_array($type, ['prepaid', 'game'], true)) {
    echo "Penggunaan: php cli/sync-pricelist.php [game|prepaid]\n";
    exit(1);
}

$lock = fopen('/tmp/dgf-pricelist.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "SKIP: proses sync lain sedang berjalan.\n";
    exit(0);
}

$db = db();
$dgf = new Digiflazz();

echo "Mengambil pricelist ($type) dari Digiflazz...\n";
$res = $dgf->priceListV2($type);

if (!empty($res['error'])) {
    echo "ERROR: {$res['error']}\n";
    exit(1);
}

$d = $res['data'] ?? [];
if (is_string($d['rc'] ?? null)) {
    echo "ERROR Digiflazz rc={$d['rc']}: {$d['message']}\n";
    exit(1);
}
$list = $d['pricelist'] ?? $d['data'] ?? (isset($d[0]) ? $d : []);
echo "Mendapat " . count($list) . " item.\n";
if (empty($list)) exit(0);

$inserted = 0;
$updated = 0;
$st = $db->prepare("INSERT INTO products (game_id, code, name, price, buy_price, stock, status)
                    VALUES (1,?,?,?,?,1,1)
                    ON CONFLICT(code) DO UPDATE SET
                      name=excluded.name,
                      buy_price=excluded.buy_price,
                      price=excluded.price,
                      stock=excluded.stock,
                      status=1");

foreach ($list as $p) {
    $code = $p['buyer_sku_code'] ?? '';
    if (!$code) continue;
    $name = $p['product_name'] ?? $code;
    $buy = (int) ($p['product_price'] ?? $p['price'] ?? 0); // modal (jarang tersedia sbg product_price)
    $sell = (int) ($p['price'] ?? 0);                        // harga di katalog (sama dgn modal bila tanpa margin)
    if ($sell <= 0) $sell = (int) floor($buy * 1.1);
    $rows = $st->execute([$code, $name, $sell, $buy]);
    if ($rows === 1) $inserted++;
    else $updated++;
}

$db->exec("INSERT OR REPLACE INTO settings (key,value) VALUES ('pricelist_updated', '" . date('Y-m-d H:i:s') . "')");
echo "Selesai. Insert: $inserted, Update: $updated di " . date('H:i:s') . "\n";
flock($lock, LOCK_UN);