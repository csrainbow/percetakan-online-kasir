<?php
// QRIS Dinamis InterActive (PT. InterAktif Internasional)
// Dokumentasi: https://qris.online/api-doc/
// API LIVE (produksi, uang asli). QRIS berlaku 30 menit sejak dibuat.

define('QRIS_BASE', 'https://qris.interactive.co.id/restapi/qris');
define('QRIS_TTL', 30 * 60);

function qris_api_ready() {
    return setting('qris_api_apikey', '') !== '' && setting('qris_api_mid', '') !== '';
}

function qris_http_get($url, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Kasir-Percetakan/1.0',
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '') {
        return ['ok' => false, 'error' => 'Koneksi gagal: ' . $err, 'code' => 0, 'json' => null];
    }
    $j = json_decode((string)$res, true);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'Respons API tidak valid.', 'code' => $code, 'json' => null];
    }
    return ['ok' => true, 'error' => '', 'code' => $code, 'json' => $j];
}

function qris_create_invoice($trxNumber, $amount) {
    if (!qris_api_ready()) {
        return ['ok' => false, 'error' => 'QRIS API belum dikonfigurasi di menu Pengaturan.'];
    }
    $amount = (int)$amount;
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Nominal transaksi tidak valid.'];
    }
    $q = http_build_query([
        'do' => 'create-invoice',
        'apikey' => setting('qris_api_apikey'),
        'mID' => (int)setting('qris_api_mid'),
        'cliTrxNumber' => (string)$trxNumber,
        'cliTrxAmount' => $amount,
        'useTip' => 'no',
    ]);
    $r = qris_http_get(QRIS_BASE . '/show_qris.php?' . $q, 15);
    if (!$r['ok']) {
        return $r;
    }
    $j = $r['json'];
    $d = $j['data'] ?? [];
    if (($j['status'] ?? 'failed') !== 'success' || empty($d['qris_content'])) {
        $msg = is_string($d['qris_status'] ?? '') && $d['qris_status'] !== ''
            ? $d['qris_status']
            : 'Gagal membuat invoice QRIS (status ' . e((string)($j['status'] ?? 'failed')) . ').';
        return ['ok' => false, 'error' => $msg];
    }
    return ['ok' => true, 'error' => '', 'data' => $d];
}

function qris_check_invoice($invid, $amount, $trxdate) {
    if (!qris_api_ready()) {
        return ['ok' => false, 'error' => 'QRIS API belum dikonfigurasi di menu Pengaturan.'];
    }
    $q = http_build_query([
        'do' => 'checkStatus',
        'apikey' => setting('qris_api_apikey'),
        'mID' => (int)setting('qris_api_mid'),
        'invid' => (string)$invid,
        'trxvalue' => (int)$amount,
        'trxdate' => (string)$trxdate,
    ]);
    $r = qris_http_get(QRIS_BASE . '/checkpaid_qris.php?' . $q, 30);
    if (!$r['ok']) {
        return $r;
    }
    $j = $r['json'];
    $d = $j['data'] ?? [];
    $st = $d['qris_status'] ?? 'unpaid';
    return ['ok' => true, 'error' => '', 'data' => $d, 'paid' => $st === 'paid'];
}

function qris_expiry_str($row) {
    $req = $row['qris_request_date'] ?? '';
    if ($req === '') {
        return '';
    }
    return date('Y-m-d H:i:s', strtotime($req) + QRIS_TTL);
}

function qris_is_expired($row) {
    $req = $row['qris_request_date'] ?? '';
    if ($req === '') {
        return false;
    }
    return strtotime($req) + QRIS_TTL < time();
}

function qris_png_datauri($content, $size = 6, $margin = 4) {
    static $loaded = false;
    if (!$loaded && is_file(__DIR__ . '/lib/phpqrcode.php')) {
        require_once __DIR__ . '/lib/phpqrcode.php';
        $loaded = true;
    }
    if ($content === '' || !class_exists('QRcode')) {
        return '';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'qris');
    if ($tmp === false) {
        return '';
    }
    try {
        QRcode::png((string)$content, $tmp, QR_ECLEVEL_M, $size, $margin, false, 0xFFFFFF, 0x000000);
        $png = file_get_contents($tmp);
    } catch (Throwable $e) {
        @unlink($tmp);
        return '';
    }
    @unlink($tmp);
    if ($png === '') {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($png);
}

function qris_nmid($row) {
    return (string)($row['qris_nmid'] ?? '') !== ''
        ? $row['qris_nmid']
        : setting('qris_api_nmid', '');
}

function qris_image_src($row) {
    if (!empty($row['qris_content'])) {
        return qris_png_datauri($row['qris_content']);
    }
    return setting('qris_image', '');
}

// Buat/susun ulang invoice untuk satu baris (penjualan atau pembayaran).
// Mengembalikan array ke-2: ['row'=>row, 'ok'=>bool, 'created'=>bool, 'error'=>str]
function qris_refresh($table, $id, $trxNumber, $amount) {
    $row = DB::one("SELECT * FROM $table WHERE id = ?", [$id]);
    if (!$row) {
        return ['row' => null, 'ok' => false, 'created' => false, 'error' => 'Data tidak ditemukan.'];
    }
    $needIssue = empty($row['qris_content']) || empty($row['qris_invid']);
    if (!$needIssue && qris_is_expired($row)) {
        $needIssue = true;
    }
    if (!$needIssue) {
        return ['row' => $row, 'ok' => true, 'created' => false, 'error' => ''];
    }
    $r = qris_create_invoice($trxNumber, $amount);
    if (!$r['ok']) {
        return ['row' => $row, 'ok' => false, 'created' => false, 'error' => $r['error']];
    }
    $d = $r['data'];
    $exp = date('Y-m-d H:i:s', strtotime($d['qris_request_date']) + QRIS_TTL);
    DB::run("UPDATE $table SET qris_content = ?, qris_invid = ?, qris_nmid = ?, qris_request_date = ?, qris_expiry = ?, qris_check_count = 0 WHERE id = ?",
        [$d['qris_content'], (string)$d['qris_invoiceid'], (string)$d['qris_nmid'], $d['qris_request_date'], $exp, $id]);
    $row = DB::one("SELECT * FROM $table WHERE id = ?", [$id]);
    return ['row' => $row, 'ok' => true, 'created' => true, 'error' => ''];
}

function qris_mark_penjualan_lunas($id) {
    $penj = DB::one('SELECT * FROM penjualan WHERE id = ?', [$id]);
    if (!$penj || $penj['status'] !== 'Menunggu QRIS') {
        return false;
    }
    DB::run("UPDATE penjualan SET status = 'Lunas' WHERE id = ?", [$id]);
    log_aktivitas('Konfirmasi QRIS', $penj['no_invoice'] . ' | ' . $penj['total']);
    return true;
}

function qris_mark_pembayaran_lunas($id) {
    $pm = DB::one("SELECT * FROM pembayaran WHERE id = ? AND ref_type = 'pesanan'", [$id]);
    if (!$pm || $pm['status'] !== 'Menunggu QRIS') {
        return false;
    }
    DB::run("UPDATE pembayaran SET status = 'Lunas' WHERE id = ?", [$id]);
    log_aktivitas('Konfirmasi QRIS', 'Pembayaran #' . $id . ' | ' . $pm['jumlah']);
    $pe = DB::one('SELECT * FROM pesanan WHERE id = ?', [(int)$pm['ref_id']]);
    if ($pe) {
        $totalBayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id = ?", [(int)$pe['id']])['t'];
        $sisaBaru = max(0, (float)$pe['total'] - $totalBayar);
        DB::run('UPDATE pesanan SET sisa = ?, status = ? WHERE id = ?', [$sisaBaru, $sisaBaru <= 0 ? 'Lunas' : 'DP', (int)$pe['id']]);
        if (!empty($pe['telepon'])) {
            $ev = $sisaBaru <= 0 ? 'lunas' : 'dp';
            wa_pelanggan([
                'id' => (int)$pe['id'],
                'no_pesanan' => $pe['no_pesanan'],
                'pelanggan' => $pe['pelanggan'],
                'telepon' => $pe['telepon'],
                'total' => (float)$pe['total'],
                'status' => $ev === 'lunas' ? 'Lunas' : 'DP',
            ], $ev);
        }
    }
    return true;
}

// Cek status satu invoice ke API (batasan: maks 30x / 30 menit per invoice).
// $kind = 'penjualan' | 'pembayaran'. Mengembalikan ['paid'=>bool, 'expired'=>bool, 'ok'=>bool, 'error'=>str, 'detail'=>str]
function qris_poll($kind, $id, $minGap = 60) {
    if ($kind === 'penjualan') {
        $row = DB::one("SELECT * FROM penjualan WHERE id = ? AND status = 'Menunggu QRIS' AND qris_invid <> '' AND qris_content <> ''", [$id]);
        $amount = $row['total'] ?? 0;
    } else {
        $row = DB::one("SELECT * FROM pembayaran WHERE id = ? AND status = 'Menunggu QRIS' AND qris_invid <> '' AND qris_content <> ''", [$id]);
        $amount = $row['jumlah'] ?? 0;
    }
    if (!$row) {
        if ($kind === 'penjualan' && DB::one('SELECT COUNT(*) c FROM penjualan WHERE id = ?', [$id])['c'] > 0) {
            return ['paid' => true, 'expired' => false, 'ok' => true, 'error' => '', 'detail' => 'Transaksi sudah dikonfirmasi.'];
        }
        if ($kind === 'pembayaran' && DB::one('SELECT COUNT(*) c FROM pembayaran WHERE id = ?', [$id])['c'] > 0) {
            return ['paid' => true, 'expired' => false, 'ok' => true, 'error' => '', 'detail' => 'Pembayaran sudah dikonfirmasi.'];
        }
        return ['paid' => false, 'expired' => false, 'ok' => false, 'error' => 'Belum ada invoice QRIS.', 'detail' => ''];
    }
    if (qris_is_expired($row)) {
        return ['paid' => false, 'expired' => true, 'ok' => true, 'error' => 'QRIS sudah kedaluwarsa. Buat ulang bila perlu.', 'detail' => ''];
    }
    if (!qris_api_ready()) {
        return ['paid' => false, 'expired' => false, 'ok' => false, 'error' => 'QRIS API belum dikonfigurasi di menu Pengaturan.', 'detail' => ''];
    }
    if ((int)$row['qris_check_count'] >= 30) {
        return ['paid' => false, 'expired' => false, 'ok' => false, 'error' => 'Batas pengecekan 30x tercapai. Konfirmasi manual.', 'detail' => ''];
    }
    $lastCheck = $row['qris_last_check'] ?? '';
    if ($lastCheck !== '' && (time() - strtotime($lastCheck)) < $minGap) {
        return ['paid' => false, 'expired' => false, 'ok' => false, 'error' => 'Cek terlambat dilakukan. Coba lagi dalam beberapa detik.', 'detail' => ''];
    }
    $r = qris_check_invoice($row['qris_invid'], $amount, date('Y-m-d', strtotime($row['qris_request_date'])));
    if (!$r['ok']) {
        return ['paid' => false, 'expired' => false, 'ok' => false, 'error' => $r['error'], 'detail' => ''];
    }
    $table = $kind === 'penjualan' ? 'penjualan' : 'pembayaran';
    DB::run("UPDATE $table SET qris_last_check = ?, qris_check_count = qris_check_count + 1 WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
    if ($r['paid']) {
        $detail = 'Metode: ' . ($r['data']['qris_payment_methodby'] ?? '-');
        if ($kind === 'penjualan') {
            qris_mark_penjualan_lunas($id);
        } else {
            qris_mark_pembayaran_lunas($id);
        }
        return ['paid' => true, 'expired' => false, 'ok' => true, 'error' => '', 'detail' => $detail];
    }
    return ['paid' => false, 'expired' => false, 'ok' => true, 'error' => '', 'detail' => ''];
}