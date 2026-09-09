<?php
/**
 * Webhook / callback dari Digiflazz
 * Digiflazz memanggil URL ini setelah transaksi diproses: Sukses / Gagal / Pending
 * Konfigurasi callback URL di dashboard Digiflazz.
 */
require_once __DIR__ . '/../includes/functions.php';

$raw = file_get_contents('php://input');
$dgf = new Digiflazz();
$cb = $dgf->handleCallback($raw);

$db = db();
if (!$cb['ref_id']) {
    j(['data' => ['rc' => '99', 'message' => 'invalid payload']], 400);
}

$st = $db->prepare("SELECT * FROM orders WHERE ref_id=?");
$st->execute([$cb['ref_id']]);
$order = $st->fetch();

if ($order) {
    $status = 'pending';
    $s = strtolower($cb['status']);
    if (in_array($s, ['sukses', 'success'])) $status = 'success';
    elseif (in_array($s, ['gagal', 'failed'])) $status = 'failed';

    $db->prepare("UPDATE orders SET order_status=?, sn=?, raw=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
        ->execute([$status, $cb['sn'], $cb['raw'], $order['id']]);

    // Notifikasi status ke nomor user (contoh sederhana, tanpa kirim WA di sini)
    error_log("DGF callback: ref={$cb['ref_id']} status={$cb['status']} sn={$cb['sn']}");
}

// Balasan yang diminta Digiflazz
j(['data' => ['rc' => '00', 'message' => 'success']]);
