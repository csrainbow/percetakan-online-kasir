<?php
if (!defined('LOG_DIR')) { $c=__DIR__.'/../config.php'; if (is_file($c)) @require_once $c; if (!defined('LOG_DIR')) define('LOG_DIR','/var/www/private/toko-logs'); }
// Duitku POP v2 (redirect) — Percetakan Rainbow
// Dok: https://docs.duitku.com/pop/id/
// Alur: api-order.php buat invoice -> redirect ke paymentUrl ->
//       Duitku panggil payment/duitku-callback.php -> payment/duitku-finish.php (return user).
// Kredensial (sandbox dulu): admin > Pengaturan > tab Duitku
//   - duitku_merchant_code, duitku_api_key, duitku_sandbox (1 = sandbox, 0 = production)

define('DUITKU_SANDBOX_BASE', 'https://api-sandbox.duitku.com');
define('DUITKU_PROD_BASE', 'https://api-prod.duitku.com');
define('DUITKU_EXPIRY_MINUTES', 120); // masa berlaku invoice (menit)

function duitku_sandbox() {
    return setting('duitku_sandbox', '1') === '1';
}

function duitku_ready() {
    return false;
}

function duitku_base() {
    return duitku_sandbox() ? DUITKU_SANDBOX_BASE : DUITKU_PROD_BASE;
}

function duitku_log($message, $data = null) {
    $dir = LOG_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $line .= ' - ' . print_r($data, true);
    }
    file_put_contents($dir . '/duitku.log', $line . PHP_EOL, FILE_APPEND);
}

// Auth header Duitku POP (sesuai SDK resmi): SHA256(merchantCode + timestamp_ms + apiKey)
function duitku_headers($timestamp) {
    $merchantCode = setting('duitku_merchant_code', '');
    $apiKey = setting('duitku_api_key', '');
    $sig = hash('sha256', $merchantCode . $timestamp . $apiKey);
    return [
        'Content-Type: application/json',
        'x-duitku-signature: ' . $sig,
        'x-duitku-timestamp: ' . $timestamp,
        'x-duitku-merchantcode: ' . $merchantCode,
    ];
}

function duitku_timestamp_ms() {
    return (string)(int)(microtime(true) * 1000);
}

function duitku_http_post($url, $params, $timeout = 25, $headers = null) {
    $body = json_encode($params);
    if ($headers === null) {
        $headers = array_merge(duitku_headers(duitku_timestamp_ms()), ['Content-Length: ' . strlen($body)]);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Percetakan-Rainbow/1.0',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '') {
        return ['ok' => false, 'error' => 'Koneksi Duitku gagal: ' . $err, 'code' => 0, 'json' => null];
    }
    $j = json_decode((string)$res, true);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'Respons Duitku tidak valid (HTTP ' . $code . ').', 'code' => $code, 'json' => null];
    }
    return ['ok' => true, 'error' => '', 'code' => $code, 'json' => $j];
}

// merchantOrderId wajib unik per invoice & aman untuk URL/callback.
// order_code toko mengandung '/' (INV/20250101/ABC123) -> sanitasi + suffix waktu agar retry bisa buat invoice baru.
function duitku_merchant_order_id($orderCode) {
    $base = preg_replace('/[^A-Za-z0-9]/', '-', (string)$orderCode);
    $base = trim(preg_replace('/-+/', '-', $base), '-');
    if ($base === '') $base = 'ORDER';
    return $base . '-' . time();
}

// Cari order dari merchantOrderId yang dikirim ke Duitku.
function duitku_find_order($merchantOrderId) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM orders WHERE duitku_order_id = ? LIMIT 1");
    $stmt->execute([(string)$merchantOrderId]);
    $row = $stmt->fetch();
    if ($row) return $row;
    // Fallback: order lama tanpa suffix (dibuat sebelum retry) — cocokkan basisnya.
    $base = preg_replace('/-\d{9,11}$/', '', (string)$merchantOrderId);
    if ($base !== '' && $base !== (string)$merchantOrderId) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE duitku_order_id = ? LIMIT 1");
        $stmt->execute([$base]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }
    return false;
}

function duitku_create_invoice($order, $customer) {
    global $db;
    if (!duitku_ready()) {
        return ['ok' => false, 'error' => 'Duitku belum dikonfigurasi di Pengaturan (merchant code / API key kosong).'];
    }
    $amount = (int)round((float)($order['total'] ?? 0));
    if ($amount < 1000) {
        return ['ok' => false, 'error' => 'Nominal Duitku minimal Rp 1.000.'];
    }
    $merchantCode = setting('duitku_merchant_code', '');
    $apiKey = setting('duitku_api_key', '');
    $merchantOrderId = duitku_merchant_order_id($order['order_code'] ?? '');

    $name = trim((string)($customer['name'] ?? $order['customer_name'] ?? 'Customer'));
    if ($name === '') $name = 'Customer';
    $email = trim((string)($customer['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = setting('admin_email', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = 'cs@rainbowprinting.web.id';
    }
    $phone = preg_replace('/[^0-9]/', '', (string)($customer['phone'] ?? $order['customer_phone'] ?? ''));

    $baseUrl = rtrim(BASE_URL, '/');
    // Auth lewat header (x-duitku-*); signature TIDAK dikirim di body (dok terbaru).
    $params = [
        'paymentAmount' => $amount,
        // paymentMethod dikosongkan -> Duitku tampilkan semua kanal aktif merchant
        'merchantOrderId' => $merchantOrderId,
        'productDetails' => 'Pesanan ' . ($order['order_code'] ?? $merchantOrderId),
        'customerVaName' => substr($name, 0, 20),
        'email' => $email,
        'phoneNumber' => $phone,
        'callbackUrl' => $baseUrl . '/payment/duitku-callback.php',
        'returnUrl' => $baseUrl . '/payment/duitku-finish.php?order=' . urlencode($order['order_code'] ?? ''),
        'expiryPeriod' => DUITKU_EXPIRY_MINUTES,
    ];

    duitku_log('create invoice', ['merchantOrderId' => $merchantOrderId, 'amount' => $amount, 'sandbox' => duitku_sandbox() ? 1 : 0]);
    $r = duitku_http_post(duitku_base() . '/api/merchant/createInvoice', $params, 25);
    if (!$r['ok']) {
        duitku_log('create invoice GAGAL (http)', $r);
        return $r;
    }
    $j = $r['json'];
    $statusCode = (string)($j['statusCode'] ?? $j['status_code'] ?? '');
    $paymentUrl = (string)($j['paymentUrl'] ?? $j['payment_url'] ?? '');
    $reference = (string)($j['reference'] ?? '');
    if ($statusCode !== '00' || $paymentUrl === '') {
        $msg = (string)($j['statusMessage'] ?? $j['status_message'] ?? 'Gagal membuat invoice Duitku.');
        duitku_log('create invoice GAGAL (api)', $j);
        return ['ok' => false, 'error' => $msg];
    }

    // Simpan referensi ke order (dipakai callback + tombol "Bayar Sekarang")
    try {
        $db->prepare("UPDATE orders SET duitku_order_id=?, duitku_reference=?, duitku_payment_url=?, payment_deadline=datetime('now', '+" . DUITKU_EXPIRY_MINUTES . " minutes') WHERE id=?")
            ->execute([$merchantOrderId, $reference, $paymentUrl, $order['id']]);
    } catch (Exception $e) {
        duitku_log('simpan referensi gagal: ' . $e->getMessage());
    }
    duitku_log('create invoice OK', ['reference' => $reference]);
    return ['ok' => true, 'error' => '', 'paymentUrl' => $paymentUrl, 'reference' => $reference, 'merchantOrderId' => $merchantOrderId];
}

// Verifikasi callback server-to-server dari Duitku (POST form), sesuai SDK resmi.
// signature = MD5(merchantCode + amount + merchantOrderId + apiKey)
function duitku_verify_callback($post) {
    $merchantCode = (string)($post['merchantCode'] ?? '');
    $amount = (string)($post['amount'] ?? '');
    $merchantOrderId = (string)($post['merchantOrderId'] ?? '');
    $signature = (string)($post['signature'] ?? '');
    $resultCode = (string)($post['resultCode'] ?? '');
    $reference = (string)($post['reference'] ?? '');
    if ($merchantCode === '' || $amount === '' || $merchantOrderId === '' || $signature === '') {
        return ['ok' => false, 'error' => 'Parameter callback tidak lengkap.'];
    }
    if ($merchantCode !== setting('duitku_merchant_code', '')) {
        return ['ok' => false, 'error' => 'Merchant code tidak cocok.'];
    }
    $expected = md5($merchantCode . $amount . $merchantOrderId . setting('duitku_api_key', ''));
    if (!hash_equals($expected, strtolower($signature))) {
        return ['ok' => false, 'error' => 'Signature callback tidak valid.'];
    }
    return ['ok' => true, 'error' => '', 'amount' => $amount, 'merchantOrderId' => $merchantOrderId, 'resultCode' => $resultCode, 'reference' => $reference];
}

// Cek status transaksi ke Duitku (dipakai halaman finish + tombol bayar ulang).
// Sesuai SDK resmi Duitku POP: base + /api/merchant/transactionStatus,
// signature = MD5(merchantCode + merchantOrderId + apiKey) di body, tanpa header x-duitku.
// statusCode: 00 = lunas, 01 = pending, selain itu gagal/expired.
function duitku_check_status($merchantOrderId) {
    if (!duitku_ready()) {
        return ['ok' => false, 'error' => 'Duitku belum dikonfigurasi.'];
    }
    $merchantCode = setting('duitku_merchant_code', '');
    $params = [
        'merchantCode' => $merchantCode,
        'merchantOrderId' => (string)$merchantOrderId,
        'signature' => md5($merchantCode . (string)$merchantOrderId . setting('duitku_api_key', '')),
    ];
    $r = duitku_http_post(duitku_base() . '/api/merchant/transactionStatus', $params, 25, ['Content-Type: application/json']);
    if (!$r['ok']) return $r;
    $j = $r['json'];
    $code = (string)($j['statusCode'] ?? $j['status_code'] ?? '');
    // 00 = sukses/lunas, 01 = pending/gagal (lihat statusMessage)
    return ['ok' => true, 'error' => '', 'code' => $code, 'message' => (string)($j['statusMessage'] ?? $j['status_message'] ?? ''), 'reference' => (string)($j['reference'] ?? ''), 'amount' => (string)($j['amount'] ?? ''), 'json' => $j];
}

// Tandai order LUNAS (dipakai callback + finish). Idempotent: lewati jika sudah paid.
function duitku_mark_order_paid($order, $amount = null, $reference = '') {
    global $db;
    if (!$order || ($order['payment_status'] ?? '') === 'paid') return false;
    $amount = $amount === null ? (float)($order['total'] ?? 0) : (float)$amount;

    // Hitung total yang sudah terverifikasi (pola sama seperti Midtrans)
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
    $stmt->execute([$order['id']]);
    $totalPaid = (float)$stmt->fetch()['total'];
    $newTotalPaid = $totalPaid + $amount;

    // Cek jasa desain (mempengaruhi status order)
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
    $stmt->execute([$order['id']]);
    $hasJasa = $stmt->fetch()['c'] > 0;

    if ($newTotalPaid >= (float)$order['total']) {
        $newPaymentStatus = 'paid';
        $newOrderStatus = $hasJasa ? 'desain' : 'processed';
    } else {
        $newPaymentStatus = 'dp';
        $newOrderStatus = $hasJasa ? 'desain' : 'processed';
    }
    $db->prepare("UPDATE orders SET payment_status=?, status=? WHERE id=?")
        ->execute([$newPaymentStatus, $newOrderStatus, $order['id']]);

    // Catat ke tabel payments (hindari duplikat per referensi)
    $ref = $reference !== '' ? $reference : ('duitku-' . ($order['duitku_order_id'] ?? $order['order_code']));
    $chk = $db->prepare("SELECT id FROM payments WHERE order_id=? AND proof_image=? LIMIT 1");
    $chk->execute([$order['id'], $ref]);
    if (!$chk->fetch()) {
        $db->prepare("INSERT INTO payments (order_id, amount, bank_name, account_number, account_name, proof_image, payment_type, status, created_at) VALUES (?, ?, 'Duitku', 'Online', 'Duitku', ?, 'duitku', 'approved', CURRENT_TIMESTAMP)")
            ->execute([$order['id'], $amount, $ref]);
    }

    // WA ke pelanggan (pola Midtrans)
    try {
        if (function_exists('waOrderStatus') && !empty($order['customer_phone'])) {
            waOrderStatus($db, (int)$order['id'], $newPaymentStatus === 'paid' ? 'paid' : 'dp');
        }
    } catch (Throwable $e) {
        duitku_log('WA error: ' . $e->getMessage());
    }

    // Email admin
    try {
        $adminEmail = getSetting('admin_email');
        if ($adminEmail) {
            $label = $newPaymentStatus === 'paid' ? 'LUNAS' : 'DP';
            $subject = "Pembayaran $label (Duitku) - " . $order['order_code'];
            $message = "Pembayaran baru dari Duitku:\n\n";
            $message .= "Kode: " . $order['order_code'] . "\n";
            $message .= "Customer: " . $order['customer_name'] . "\n";
            $message .= "Status: " . $label . "\n";
            $message .= "Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n";
            $message .= "Total dibayar: Rp " . number_format($newTotalPaid, 0, ',', '.') . "\n";
            $message .= "Sisa: Rp " . number_format(max(0, $order['total'] - $newTotalPaid), 0, ',', '.') . "\n";
            $message .= "Referensi: " . $ref . "\n\n";
            $message .= "Link: https://rainbowprinting.web.id/admin/order-detail.php?id=" . $order['id'];
            sendEmail($adminEmail, $subject, $message);
        }
    } catch (Exception $e) {
        duitku_log('Email admin error: ' . $e->getMessage());
    }
    return true;
}
