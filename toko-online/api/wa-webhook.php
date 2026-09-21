<?php
/**
 * ============================================================
 * 🔥 META WHATSAPP CLOUD API — WEBHOOK (FINAL BERSIH)
 * ============================================================
 * Callback URL  : https://rainbowprinting.web.id/api/wa-webhook.php
 * Verify Token  : rainbowprint-wa-v1
 * ------------------------------------------------------------
 *  GET  → handshake: hub_mode=subscribe & hub_verify_token cocok
 *         → balas hub_challenge POLOS (text, bukan JSON!)
 *  POST → event dari Meta → balas 200 SEECEPATNYA
 * ============================================================
 */

// ——— pastikan tidak ada output error yang mengotori challenge ———
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ——— Verify token webhook (WAJIB sama persis di dashboard Meta) ———
$verifyToken = 'rainbowprint-wa-v1';

// ——— helper log ———
$waLogDir = dirname(dirname(__DIR__)) . '/logs';
if (!is_dir($waLogDir)) { @mkdir($waLogDir, 0775, true); }
$waLogFile = $waLogDir . '/wa-webhook.log';

if (!function_exists('wa_wh_log')) {
    function wa_wh_log($m) {
        global $waLogFile;
        @file_put_contents(
            $waLogFile,
            '[' . date('Y-m-d H:i:s') . '] ' . json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

// ============================================================
// 1) GET — VERIFIKASI WEBHOOK (handshake hand Meta)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $mode      = $_GET['hub_mode'] ?? '';
    $inToken   = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    wa_wh_log([
        'GET'          => true,
        'mode'         => $mode,
        'token_cocok'  => hash_equals($verifyToken, (string)$inToken),
    ]);

    if ($mode === 'subscribe' && hash_equals($verifyToken, (string)$inToken)) {
        // ✅ Balas challenge POLOS (bukan JSON!)
        header('Content-Type: text/plain');
        echo (string)$challenge;
        exit;
    }

    // ❌ Gagal
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Verifikasi webhook gagal: token tidak cocok.';
    exit;
}

// ============================================================
// 2) POST — EVENT DARI META (balas 200 SEECEPATNYA)
// ============================================================
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

// ✅ Balas 200 dulu sebelum proses apa pun — Meta timeout ~10 detik
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);

// Log setelahnya (tidak memblokir 200)
wa_wh_log([
    'POST'  => true,
    'data'  => $data,
    'raw'   => $raw,
]);
exit;
