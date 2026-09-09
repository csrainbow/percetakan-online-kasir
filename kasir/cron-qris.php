<?php
// Cron QRIS Dinamis InterActive — jalankan tiap 1 menit (maks 30x cek per invoice / 30 menit).
// Aturan penyedia: JANGAN auto-check terus-menerus. 1x/menit cukup.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Akses hanya dari CLI.');
}

require_once __DIR__ . '/config.php';

$lockPath = __DIR__ . '/data/qris-cron.lock';
if (!is_dir(dirname($lockPath))) {
    exit(0);
}
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$log = [];
$dibuat = 0;
$lunas = 0;
$gagal = 0;

// 1) Buat invoice untuk transaksi QRIS yang belum punya invoice (mis. API sempat mati / kredensial baru diisi).
if (qris_api_ready()) {
    $penjualanBelum = DB::q("SELECT id, no_invoice, total FROM penjualan
                             WHERE status = 'Menunggu QRIS' AND (qris_content = '' OR qris_invid = '')
                             ORDER BY id ASC LIMIT 5");
    foreach ($penjualanBelum as $j) {
        $r = qris_refresh('penjualan', (int)$j['id'], $j['no_invoice'], (int)round((float)$j['total']));
        if ($r['ok']) {
            $dibuat++;
        } else {
            $gagal++;
            $log[] = 'penjualan#' . $j['id'] . ' issue gagal: ' . $r['error'];
        }
    }

    $pembayaranBelum = DB::q("SELECT pp.id, pp.jumlah FROM pembayaran pp
                              JOIN pesanan pe ON pe.id = pp.ref_id
                              WHERE pp.status = 'Menunggu QRIS' AND pp.ref_type = 'pesanan'
                                AND (pp.qris_content = '' OR pp.qris_invid = '')
                                AND pe.status != 'Batal' AND pe.deleted = 0
                              ORDER BY pp.id ASC LIMIT 5");
    foreach ($pembayaranBelum as $jm) {
        $r = qris_refresh('pembayaran', (int)$jm['id'], 'PB' . $jm['id'], (int)round((float)$jm['jumlah']));
        if ($r['ok']) {
            $dibuat++;
        } else {
            $gagal++;
            $log[] = 'pembayaran#' . $jm['id'] . ' issue gagal: ' . $r['error'];
        }
    }
}

// 2) Cek status invoice yang sudah dibuat (minimal 60 detik sejak cek terakhir).
$cutoff = date('Y-m-d H:i:s', time() - 60);
$batasRun = 10;

$menungguPenjualan = DB::q("SELECT id FROM penjualan
                            WHERE status = 'Menunggu QRIS' AND qris_invid <> '' AND qris_content <> ''
                              AND qris_check_count < 30
                              AND (qris_last_check = '' OR qris_last_check < ?)
                            ORDER BY id ASC LIMIT " . ($batasRun - $lunas), [$cutoff]);
foreach ($menungguPenjualan as $j) {
    if ($lunas >= $batasRun) {
        break;
    }
    $r = qris_poll('penjualan', (int)$j['id'], 60);
    if ($r['paid']) {
        $lunas++;
        $log[] = 'penjualan#' . $j['id'] . ' LUNAS otomatis';
    } elseif ($r['expired']) {
        $log[] = 'penjualan#' . $j['id'] . ' expired';
    } elseif (!$r['ok']) {
        $gagal++;
        $log[] = 'penjualan#' . $j['id'] . ' cek gagal: ' . $r['error'];
    }
}

$menungguPembayaran = DB::q("SELECT pp.id FROM pembayaran pp
                             JOIN pesanan pe ON pe.id = pp.ref_id
                             WHERE pp.status = 'Menunggu QRIS' AND pp.ref_type = 'pesanan'
                               AND pp.qris_invid <> '' AND pp.qris_content <> ''
                               AND pp.qris_check_count < 30
                               AND (pp.qris_last_check = '' OR pp.qris_last_check < ?)
                               AND pe.status != 'Batal' AND pe.deleted = 0
                             ORDER BY pp.id ASC LIMIT " . ($batasRun - $lunas), [$cutoff]);
foreach ($menungguPembayaran as $jm) {
    if ($lunas >= $batasRun) {
        break;
    }
    $r = qris_poll('pembayaran', (int)$jm['id'], 60);
    if ($r['paid']) {
        $lunas++;
        $log[] = 'pembayaran#' . $jm['id'] . ' LUNAS otomatis';
    } elseif ($r['expired']) {
        $log[] = 'pembayaran#' . $jm['id'] . ' expired';
    } elseif (!$r['ok']) {
        $gagal++;
        $log[] = 'pembayaran#' . $jm['id'] . ' cek gagal: ' . $r['error'];
    }
}

if ($log) {
    file_put_contents(
        __DIR__ . '/data/qris-cron.log',
        '[' . date('Y-m-d H:i:s') . '] dibuat=' . $dibuat . ' lunas=' . $lunas . ' gagal=' . $gagal . "\n" . implode("\n", $log) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

flock($lock, LOCK_UN);
fclose($lock);
exit(0);