<?php
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// 🔥 IP WHITELIST NOTIFIKASI MIDTRANS (Production + Sandbox)
$midtransAllowedIps = [
    '8.215.30.222','147.139.209.49','8.215.32.142','147.139.163.77','8.215.25.24',
    '8.215.3.193','147.139.210.20','149.129.238.95','8.215.9.206','147.139.134.22',
    '149.129.253.222','8.215.56.174','8.215.27.65','147.139.129.139','149.129.192.10',
    '8.215.15.117','149.129.234.6','8.215.79.106','149.129.192.204','8.215.83.17',
    '147.139.197.147','147.139.207.105','147.139.193.191','147.139.201.222','8.215.82.175',
    '149.129.218.45','8.215.10.140','8.215.83.130','147.139.206.209','8.215.75.234',
    '149.129.216.115','147.139.167.196','147.139.179.47','147.139.144.184','147.139.169.196',
    '147.139.168.217','8.215.17.96','149.129.254.13','147.139.203.227','147.139.192.94',
    '147.139.206.250','147.139.213.108','8.215.23.167','147.139.209.91','8.215.21.228',
    '147.139.173.83','147.139.132.215','149.129.227.68','149.129.234.77','147.139.137.231',
    '147.139.180.156','8.215.10.65','8.215.22.163','147.139.215.190','8.215.0.89',
    '8.215.16.140','147.139.165.251','147.139.209.83','147.139.167.157','147.139.192.232',
];

// Site di belakang Cloudflare → REMOTE_ADDR = IP Cloudflare.
// IP asli dibaca dari header CF-Connecting-IP (selalu di-set Cloudflare).
$midtransClientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $midtransClientIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $midtransClientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
}

if (!in_array($midtransClientIp, $midtransAllowedIps, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'forbidden', 'message' => 'IP not allowed']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$order_id = $input['order_id'] ?? '';
$status_code = $input['status_code'] ?? '';
$gross_amount = $input['gross_amount'] ?? '';
$transaction_status = $input['transaction_status'] ?? '';
$signature_key = $input['signature_key'] ?? '';

if (empty($order_id)) { echo json_encode(['status' => 'missing_order_id']); exit; }
if (empty($transaction_status)) { echo json_encode(['status' => 'missing_transaction_status']); exit; }

$server_key = midtrans_server_key();
if (empty($server_key)) { echo json_encode(['status' => 'server_key_missing']); exit; }

$expected = hash('SHA512', $order_id . $status_code . $gross_amount . $server_key);
if (isset($signature_key) && $signature_key !== '' && !hash_equals($expected, $signature_key)) {
    http_response_code(403);
    echo json_encode(['status' => 'invalid_signature']);
    exit;
}

// Cari pembayaran menunggu berdasarkan order_id (disimpan di keterangan)
$pm = DB::one(
    "SELECT * FROM pembayaran WHERE ref_type='pesanan' AND keterangan LIKE ? AND status = 'Menunggu Midtrans' ORDER BY id DESC LIMIT 1",
    ['%Midtrans order: ' . $order_id . '%']
);
if (!$pm) {
    echo json_encode(['status' => 'order_not_found', 'order_id' => $order_id]);
    exit;
}
$pesanan_id = (int)$pm['ref_id'];

$isPaid = in_array($transaction_status, ['capture', 'settlement']);
$isFailed = in_array($transaction_status, ['deny', 'cancel', 'expire']);

if ($isPaid) {
    DB::run(
        "UPDATE pembayaran SET status='Lunas', keterangan='Midtrans: '||? WHERE id=?",
        [$transaction_status, $pm['id']]
    );
    // Hitung ulang sisa pesanan
    $total_bayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id=? AND status='Lunas'", [$pesanan_id])['t'];
    $ps = DB::one("SELECT * FROM pesanan WHERE id=?", [$pesanan_id]);
    if ($ps) {
        $sisa = max(0, (float)$ps['total'] - $total_bayar);
        $stat = $sisa <= 0 ? 'Lunas' : 'DP';
        DB::run("UPDATE pesanan SET sisa=?, status=? WHERE id=?", [$sisa, $stat, $pesanan_id]);
        // Notifikasi WA dp/lunas ke pelanggan (pembayaran terverifikasi otomatis)
        if (!empty($ps['telepon'])) {
            $ev = $sisa <= 0 ? 'lunas' : 'dp';
            wa_pelanggan([
                'id' => $pesanan_id,
                'no_pesanan' => $ps['no_pesanan'],
                'pelanggan' => $ps['pelanggan'],
                'telepon' => $ps['telepon'],
                'total' => (float)$ps['total'],
                'dp' => (float)$pm['jumlah'],
                'sisa' => $sisa,
                'metode' => 'Midtrans',
                'status' => $ev === 'lunas' ? 'Lunas' : 'DP',
            ], $ev);
        }
    }
    echo json_encode(['status' => 'paid', 'order_id' => $order_id]);
} else if ($isFailed) {
    DB::run(
        "UPDATE pembayaran SET status='Gagal', keterangan='Midtrans: '||? WHERE id=?",
        [$transaction_status, $pm['id']]
    );
    echo json_encode(['status' => 'failed', 'order_id' => $order_id]);
} else {
    echo json_encode(['status' => 'received', 'transaction_status' => $transaction_status]);
}
