<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain');

$q = $db->query('SELECT id, name, slug, LENGTH(slug) as slen, hex(slug) as shex FROM products');
echo "=== PRODUCTS ===\n";
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "id={$r['id']}\nname={$r['name']}\nslug=|{$r['slug']}|\nslug_len={$r['slen']}\nslug_hex={$r['shex']}\n";
    
    // Try to fetch by slug
    $s = $db->prepare('SELECT id FROM products WHERE slug = ?');
    $s->execute([$r['slug']]);
    $found = $s->fetch();
    echo "fetch_by_slug=" . ($found ? 'OK' : 'FAIL') . "\n\n";
}
