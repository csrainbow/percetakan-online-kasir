<?php
require_once __DIR__ . '/config.php';
$q = $db->query('SELECT id, name, slug FROM products');
echo "=== PRODUCTS ===\n";
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "id={$r['id']} name={$r['name']} slug={$r['slug']}\n";
}
$q2 = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
echo "=== TABLES ===\n";
while ($r = $q2->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['name']}\n";
}
