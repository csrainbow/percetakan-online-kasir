<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Digiflazz.php';

/** Bersihkan nomor tujuan (hilangkan spasi/karakter aneh) */
function cleanNumber(string $n): string {
    return preg_replace('/[^0-9]/', '', $n);
}

/** Generate ref_id unik: TOPUP-YYYYMMDDHHMMSS-xxxx */
function genRefId(): string {
    return 'TOPUP-' . date('YmdHis') . '-' . substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4);
}

/** Simpan order baru */
function createOrder(array $data): array {
    $db = db();
    $ref = $data['ref_id'] ?? genRefId();
    $st = $db->prepare("INSERT INTO orders
        (ref_id, product_code, product_name, customer_no, player_id, zone_id, amount, payment_method)
        VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([
        $ref,
        $data['product_code'],
        $data['product_name'] ?? '',
        cleanNumber($data['customer_no']),
        $data['player_id'] ?? '',
        $data['zone_id'] ?? '',
        (int) $data['amount'],
        $data['payment_method'] ?? 'midtrans',
    ]);
    return ['id' => (int) $db->lastInsertId(), 'ref_id' => $ref];
}

/** Buat Snap token Midtrans untuk pembayaran */
function midtransSnap(string $orderId, int $amount, string $itemName, array $customer = []): array {
    $serverKey = MT_SERVER_KEY;
    $base = MIDTRANS_IS_PRODUCTION ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';

    $customer = [
        'first_name' => substr($customer['name'] ?? 'TopUp', 0, 20),
        'phone' => $customer['phone'] ?? '',
    ];
    if (!empty($customer['email'] ?? '') && filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        $customer['email'] = $customer['email'];
    }
    $params = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $amount,
        ],
        'item_details' => [
            ['id' => 'TOPUP', 'price' => $amount, 'quantity' => 1, 'name' => substr($itemName, 0, 50)],
        ],
        'customer_details' => $customer,
        'finish_redirect_url' => BASE_URL . '/status.php?ref=' . urlencode($orderId),
    ];

    $ch = curl_init($base . '/snap/v1/transactions');
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_USERPWD => $serverKey . ':',
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode($resp, true);
    if ($code >= 400 || !isset($data['token'])) {
        return ['error' => $data['status_message'] ?? 'Midtrans HTTP ' . $code, 'token' => null];
    }
    return ['error' => null, 'token' => $data['token'], 'redirect_url' => $data['redirect_url'] ?? ''];
}

/** Rekap status pembayaran (untuk halaman cursor) */
function paymentStatusText(string $s): string {
    return match ($s) {
        'pending' => 'Menunggu Pembayaran',
        'waiting' => 'Menunggu Penjadwalan',
        'paid' => 'Dibayar',
        'success' => 'Sukses',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        default => ucfirst($s),
    };
}
