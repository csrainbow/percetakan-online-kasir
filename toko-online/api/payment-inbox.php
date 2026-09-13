<?php
// ============================================
// API PAYMENT-INBOX — penerima notifikasi pembayaran
// (API key milik sendiri, self-hosted)
//
// Digunakan oleh: forwarder notifikasi mutasi / SMS / email,
// cron, atau skrip pihak lain yang meng-*push* data pembayaran.
//
// Autentikasi:  Authorization: Bearer <API_KEY>
//               opsional ?token= atau token di body
//
// Body (JSON):
//   { "rows": [ { "amount":158317, "txdate":"2026-09-10", "refno":"...", "description":"TRF dari ...", "bank":"BCA" } ] }
// atau fields tunggal:
//   { "amount":158317, "description":"..." }
// atau teks mentah SMS/mutasi:
//   { "text": "10/09 10:00 BCA 158317 TRF" }
// ============================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ownapi.php';
require_once __DIR__ . '/../includes/payment_autocheck.php';

if (!ownapi_auth()) {
    ownapi_json(['ok' => false, 'error' => 'Unauthorized: API key tidak valid.'], 401);
}

$input = ownapi_input();

$hits = [];

// Mode 1: rows/fields terstruktur
$rows = $input['rows'] ?? [];
if (!is_array($rows)) $rows = [];
foreach ($rows as $r) {
    if (!is_array($r)) continue;
    $amount = ownapi_parse_amount($r['amount'] ?? null);
    if ($amount <= 0) continue;
    $date = trim((string)($r['txdate'] ?? $r['date'] ?? $r['tanggal'] ?? ''));
    $hits[] = [
        'amount' => $amount,
        'txdate' => $date,
        'refno' => trim((string)($r['refno'] ?? $r['ref'] ?? $r['reqid'] ?? '')),
        'bank_name' => trim((string)($r['bank'] ?? $r['bank_name'] ?? '')),
        'description' => trim((string)($r['description'] ?? $r['desc'] ?? $r['note'] ?? '')),
    ];
}

// Mode 2: single field
if (empty($rows) && empty($hits)) {
    $amount = ownapi_parse_amount($input['amount'] ?? null);
    if ($amount > 0) {
        $hits[] = [
            'amount' => $amount,
            'txdate' => trim((string)($input['txdate'] ?? $input['date'] ?? '')),
            'refno' => trim((string)($input['refno'] ?? $input['ref'] ?? '')),
            'bank_name' => trim((string)($input['bank'] ?? $input['bank_name'] ?? '')),
            'description' => trim((string)($input['description'] ?? $input['desc'] ?? '')),
        ];
    }
}

// Mode 3: teks mentah (SMS/mutasi) diparse otomatis
if (empty($hits) && !empty($input['text'])) {
    $hits = pm_parse_text((string)$input['text']);
}

if (empty($hits)) {
    ownapi_json(['ok' => false, 'error' => 'Tidak ada nominal valid ditemukan.'], 422);
}

$res = pm_run('api', $hits);

ownapi_json([
    'ok' => true,
    'received' => count($hits),
    'summary' => $res['summary'],
    'processed' => !empty($res['locked']) ? false : true,
], 200);