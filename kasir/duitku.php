<?php
// Duitku POP v2 (redirect) — sisi KASIR (payment point nota).
// Cerminkan pola pay-midtrans.php + midtrans-webhook.php.
// Kredensial: menu Pengaturan kasir > Duitku (pengaturan table).
// Callback per-transaksi dikirim ke /kasir/duitku-webhook.php (override URL dashboard).

define('KASIR_DUITKU_SANDBOX_BASE', 'https://api-sandbox.duitku.com');
define('KASIR_DUITKU_PROD_BASE', 'https://api-prod.duitku.com');
define('KASIR_DUITKU_EXPIRY_MINUTES', 120);

function duitku_kasir_sandbox() {
    return setting('duitku_sandbox', '1') === '1';
}

function duitku_kasir_ready() {
    return false;
}

function duitku_kasir_base() {
    return duitku_kasir_sandbox() ? KASIR_DUITKU_SANDBOX_BASE : KASIR_DUITKU_PROD_BASE;
}

function duitku_kasir_log($message, $data = null) {
    $dir = LOG_DIR;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $line .= ' - ' . print_r($data, true);
    }
    file_put_contents($dir . '/duitku.log', $line . PHP_EOL, FILE_APPEND);
}

function duitku_kasir_timestamp_ms() {
    return (string)(int)(microtime(true) * 1000);
}

// Auth header Duitku POP (sesuai SDK resmi): SHA256(merchantCode + timestamp + apiKey)
function duitku_kasir_headers($timestamp) {
    $merchantCode = setting('duitku_merchant_code', '');
    $apiKey = setting('duitku_api_key', '');
    return [
        'Content-Type: application/json',
        'x-duitku-signature: ' . hash('sha256', $merchantCode . $timestamp . $apiKey),
        'x-duitku-timestamp: ' . $timestamp,
        'x-duitku-merchantcode: ' . $merchantCode,
    ];
}

function duitku_kasir_http_post($url, $params, $timeout = 25, $headers = null) {
    $body = json_encode($params);
    if ($headers === null) {
        $headers = array_merge(duitku_kasir_headers(duitku_kasir_timestamp_ms()), ['Content-Length: ' . strlen($body)]);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Kasir-Rainbow/1.0',
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

// merchantOrderId unik per percobaan (no_pesanan aman untuk URL/callback).
function duitku_kasir_merchant_order_id($noPesanan) {
    $base = preg_replace('/[^A-Za-z0-9]/', '-', (string)$noPesanan);
    $base = trim(preg_replace('/-+/', '-', $base), '-');
    if ($base === '') $base = 'KSR';
    return $base . '-' . date('His');
}

// Buat invoice Duitku untuk sisa tagihan pesanan kasir.
// $pesanan: row pesanan (no_pesanan, pelanggan, telepon, total). $jumlah: nominal (sisa).
// $returnUrl: URL kembali setelah bayar (wajib lengkap ref/id/t/k — n.php polos = 404).
function duitku_kasir_create_invoice($pesanan, $jumlah, $returnUrl = '') {
    if (!duitku_kasir_ready()) {
        return ['ok' => false, 'error' => 'Duitku belum dikonfigurasi di menu Pengaturan kasir.'];
    }
    $amount = (int)round((float)$jumlah);
    if ($amount < 1000) {
        return ['ok' => false, 'error' => 'Nominal Duitku minimal Rp 1.000.'];
    }
    $merchantOrderId = duitku_kasir_merchant_order_id($pesanan['no_pesanan'] ?? '');
    $name = trim((string)($pesanan['pelanggan'] ?? 'Pelanggan'));
    if ($name === '') $name = 'Pelanggan';
    $phone = preg_replace('/[^0-9]/', '', (string)($pesanan['telepon'] ?? ''));

    $baseUrl = rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/');
    if ($returnUrl === '') {
        // Jangan pernah pakai /n.php polos (tanpa segmen ref/id/t/k = 404 "Nota tidak ditemukan").
        $returnUrl = $baseUrl . '/';
    }
    $params = [
        'paymentAmount' => $amount,
        'merchantOrderId' => $merchantOrderId,
        'productDetails' => 'Pesanan ' . ($pesanan['no_pesanan'] ?? $merchantOrderId),
        'customerVaName' => substr($name, 0, 20),
        'email' => 'cs@rainbowprinting.web.id',
        'phoneNumber' => $phone,
        'callbackUrl' => $baseUrl . '/duitku-webhook.php',
        'returnUrl' => $returnUrl,
        'expiryPeriod' => KASIR_DUITKU_EXPIRY_MINUTES,
    ];

    duitku_kasir_log('create invoice', ['merchantOrderId' => $merchantOrderId, 'amount' => $amount]);
    $r = duitku_kasir_http_post(duitku_kasir_base() . '/api/merchant/createInvoice', $params, 25);
    if (!$r['ok']) {
        duitku_kasir_log('create invoice GAGAL (http)', $r);
        return $r;
    }
    $j = $r['json'];
    $statusCode = (string)($j['statusCode'] ?? '');
    $paymentUrl = (string)($j['paymentUrl'] ?? '');
    $reference = (string)($j['reference'] ?? '');
    if ($statusCode !== '00' || $paymentUrl === '') {
        $msg = (string)($j['statusMessage'] ?? 'Gagal membuat invoice Duitku.');
        duitku_kasir_log('create invoice GAGAL (api)', $j);
        return ['ok' => false, 'error' => $msg];
    }
    duitku_kasir_log('create invoice OK', ['reference' => $reference]);
    return ['ok' => true, 'error' => '', 'paymentUrl' => $paymentUrl, 'reference' => $reference, 'merchantOrderId' => $merchantOrderId];
}

// Verifikasi callback Duitku (POST form). Sesuai SDK resmi: MD5(merchantCode+amount+merchantOrderId+apiKey).
function duitku_kasir_verify_callback($post) {
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
