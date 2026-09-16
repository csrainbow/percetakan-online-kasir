<?php
// QRIS Dinamis InterActive (PT. InterAktif Internasional) — web utama Percetakan Rainbow
// Dokumentasi: https://qris.online/api-doc/ — API LIVE (uang asli). QRIS berlaku 30 menit.

define('QRIS_BASE', 'https://qris.interactive.co.id/restapi/qris');
define('QRIS_TTL', 30 * 60);

function qris_api_ready() {
    return false;
}

function qris_http_get($url, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Percetakan-Ikky/1.0',
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
        return ['ok' => false, 'error' => 'QRIS API belum dikonfigurasi di Pengaturan.'];
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
    if (!$r['ok']) return $r;
    $j = $r['json'];
    $d = $j['data'] ?? [];
    if (($j['status'] ?? 'failed') !== 'success' || empty($d['qris_content'])) {
        $msg = is_string($d['qris_status'] ?? '') && $d['qris_status'] !== ''
            ? $d['qris_status']
            : 'Gagal membuat invoice QRIS (status ' . htmlspecialchars((string)($j['status'] ?? 'failed')) . ').';
        return ['ok' => false, 'error' => $msg];
    }
    return ['ok' => true, 'error' => '', 'data' => $d];
}

function qris_check_invoice($invid, $amount, $trxdate) {
    if (!qris_api_ready()) {
        return ['ok' => false, 'error' => 'QRIS API belum dikonfigurasi di Pengaturan.'];
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
    if (!$r['ok']) return $r;
    $j = $r['json'];
    $d = $j['data'] ?? [];
    $st = $d['qris_status'] ?? 'unpaid';
    return ['ok' => true, 'error' => '', 'data' => $d, 'paid' => $st === 'paid'];
}

function qris_expiry_str($row) {
    $req = $row['qris_request_date'] ?? '';
    if ($req === '') return '';
    return date('Y-m-d H:i:s', strtotime($req) + QRIS_TTL);
}

function qris_is_expired($row) {
    $req = $row['qris_request_date'] ?? '';
    if ($req === '') return false;
    return strtotime($req) + QRIS_TTL < time();
}

function qris_png_datauri($content, $size = 6, $margin = 4) {
    static $loaded = false;
    if (!$loaded && is_file(__DIR__ . '/lib/phpqrcode.php')) {
        require_once __DIR__ . '/lib/phpqrcode.php';
        $loaded = true;
    }
    if ($content === '' || !class_exists('QRcode')) return '';
    $tmp = tempnam(sys_get_temp_dir(), 'qris');
    if ($tmp === false) return '';
    try {
        QRcode::png((string)$content, $tmp, QR_ECLEVEL_M, $size, $margin, false, 0xFFFFFF, 0x000000);
        $png = file_get_contents($tmp);
    } catch (Throwable $e) {
        @unlink($tmp);
        return '';
    }
    @unlink($tmp);
    if ($png === '') return '';
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
    $img = setting('qris_image', '');
    return $img !== '' ? '/uploads/' . htmlspecialchars($img) : '';
}

function qris_trx_number($orderCode) {
    return preg_replace('/[^A-Za-z0-9-]/', '-', $orderCode);
}

function qris_issue_order($orderCode, $total, $orderId) {
    global $db;
    $tn = qris_trx_number($orderCode);
    $r = qris_create_invoice($tn, (int)round((float)$total));
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error'], 'created' => false];
    $d = $r['data'];
    $exp = date('Y-m-d H:i:s', strtotime($d['qris_request_date']) + QRIS_TTL);
    $stmt = $db->prepare("UPDATE orders SET qris_content=?, qris_invid=?, qris_nmid=?, qris_request_date=?, qris_expiry=?, qris_check_count=0 WHERE id=?");
    $stmt->execute([$d['qris_content'], (string)$d['qris_invoiceid'], (string)$d['qris_nmid'], $d['qris_request_date'], $exp, $orderId]);
    $row = $db->prepare("SELECT * FROM orders WHERE id=?");
    $row->execute([$orderId]);
    return ['ok' => true, 'error' => '', 'row' => $row->fetch(), 'created' => true];
}

function qris_mark_order_paid($order) {
    global $db;
    if (!$order || ($order['payment_status'] ?? '') === 'paid') return false;
    $db->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")->execute([$order['id']]);
    $code = $order['order_code'] ?? '';
    $total = formatRupiah($order['total'] ?? 0);
    $adminEmail = getSetting('admin_email');
    if ($adminEmail) {
        sendEmail($adminEmail, "Pesanan QRIS Lunas — $code",
            "Pesanan $code ($order[customer_name]) telah lunas via QRIS Dinamis.\nTotal: $total.\nLink: https://rainbowprinting.web.id/admin/order-detail.php?id=$order[id]");
    }
    return true;
}

function qris_check_order($orderCode, $phone, $minGap = 30) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code=? AND customer_phone=?");
    $stmt->execute([$orderCode, $phone]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => 'Pesanan tidak ditemukan.'];
    if (($row['payment_status'] ?? '') === 'paid') {
        return ['ok' => true, 'paid' => true, 'status' => 'paid', 'status_teks' => 'Pembayaran sudah dikonfirmasi.', 'error' => ''];
    }
    if (empty($row['qris_invid']) || empty($row['qris_content'])) {
        return ['ok' => false, 'error' => 'Belum ada invoice QRIS.'];
    }
    if (qris_is_expired($row)) {
        return ['ok' => true, 'expired' => true, 'status' => 'expired', 'status_teks' => 'QRIS sudah kedaluwarsa.', 'error' => ''];
    }
    if (!qris_api_ready()) {
        return ['ok' => false, 'error' => 'QRIS API belum dikonfigurasi di Pengaturan.'];
    }
    if ((int)($row['qris_check_count'] ?? 0) >= 30) {
        return ['ok' => false, 'error' => 'Batas pengecekan 30x tercapai. Konfirmasi manual.'];
    }
    $lastCheck = $row['qris_last_check'] ?? '';
    if ($lastCheck !== '' && (time() - strtotime($lastCheck)) < $minGap) {
        return ['ok' => false, 'error' => 'Cek terlambat. Coba lagi dalam beberapa detik.'];
    }
    $amount = (int)round((float)($row['total'] ?? 0));
    $trxdate = substr($row['qris_request_date'] ?? '', 0, 10);
    $r = qris_check_invoice($row['qris_invid'], $amount, $trxdate);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error'], 'paid' => false];
    if ($r['paid']) {
        qris_mark_order_paid($row);
        return ['ok' => true, 'paid' => true, 'status' => 'paid', 'status_teks' => 'PEMBAYARAN DITERIMA — transaksi otomatis dikonfirmasi.', 'detail' => $r['data']['qris_payment_methodby'] ?? '-', 'error' => ''];
    }
    $db->prepare("UPDATE orders SET qris_last_check=?, qris_check_count=qris_check_count+1 WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), $row['id']]);
    return ['ok' => true, 'paid' => false, 'status' => 'unpaid', 'status_teks' => 'Menunggu pembayaran.', 'error' => ''];
}
