<?php
/* ============================================================
 * VERIFIKASI + KIRIM TEMPLATE — Meta Cloud API
 * Token diambil otomatis dari config.php (cari konstanta EAA).
 * Token TIDAK PERNAH di-echo — hanya status.
 * ============================================================ */
require "/var/www/percetakan-online/config.php";

/* 1) Temukan token + phone number id dari semua konstanta user */
$tok = "";
$pho = "";
foreach (get_defined_constants(true)["user"] as $k => $v) {
    if (is_string($v)) {
        if (strpos($v, "EAA") === 0 && $tok === "") $tok = $v;
        if (preg_match("/^1\d{6,}$/", $v) && $pho === "") $pho = $v;
    }
}
if ($tok === "") { fwrite(STDERR, "Token EAA tidak ditemukan.\n"); exit(1); }
if ($pho === "") { fwrite(STDERR, "Phone Number ID tidak ditemukan.\n"); exit(1); }
echo "Token : OK (" . strlen($tok) . " char) | Phone ID : $pho\n";

function gph($url, $tok, $body = null, $method = "GET") {
    $opts = ["http" => [
        "method" => $method,
        "ignore_errors" => true,
        "header" => "Authorization: Bearer $tok\r\nContent-Type: application/json\r\n",
    ]];
    if ($body !== null) $opts["http"]["content"] = $body;
    return file_get_contents($url, false, stream_context_create($opts));
}

echo "\n=== 1) Validasi token via /me ===\n";
echo gph("https://graph.facebook.com/v25.0/me?fields=id,name", $tok) . "\n";

echo "\n=== 2) Kirim template jaspers_market_order_confirmation_v1 -> 6282252569185 ===\n";
$payload = json_encode([
    "messaging_product" => "whatsapp",
    "to" => "6282252569185",
    "type" => "template",
    "template" => [
        "name" => "jaspers_market_order_confirmation_v1",
        "language" => ["code" => "en_US"],
        "components" => [[
            "type" => "body",
            "parameters" => [
                ["type" => "text", "text" => "John Doe"],
                ["type" => "text", "text" => "123456"],
                ["type" => "text", "text" => "19 Sep 2026"],
            ],
        ]],
    ],
]);
echo gph("https://graph.facebook.com/v25.0/$pho/messages", $tok, $payload, "POST") . "\n";
