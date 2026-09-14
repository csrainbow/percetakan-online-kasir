<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/duitku.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
header('Content-Type: application/json');

$post = $_POST;
duitku_kasir_log('callback diterima', ['merchantOrderId' => $post['merchantOrderId'] ?? '', 'amount' => $post['amount'] ?? '', 'resultCode' => $post['resultCode'] ?? '', 'reference' => $post['reference'] ?? '']);

if (empty($post)) {
    // Health check / tes URL dari dashboard — balas 200 OK.
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Duitku kasir webhook ready']);
    exit;
}

$v = duitku_kasir_verify_callback($post);
if (!$v['ok']) {
    duitku_kasir_log('callback DITOLAK: ' . $v['error']);
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => $v['error']]);
    exit;
}

// Cari pembayaran menunggu berdasarkan merchantOrderId (disimpan di keterangan)
$pm = DB::one(
    "SELECT * FROM pembayaran WHERE ref_type='pesanan' AND keterangan LIKE ? AND status = 'Menunggu Duitku' ORDER BY id DESC LIMIT 1",
    ['%Duitku order: ' . $v['merchantOrderId'] . '%']
);
if (!$pm) {
    duitku_kasir_log('callback: pembayaran tidak ditemukan', ['merchantOrderId' => $v['merchantOrderId']]);
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Notification received (payment unknown)']);
    exit;
}
$pesanan_id = (int)$pm['ref_id'];

// Keamanan: nominal callback harus sama dengan nominal tagihan.
if ((int)$v['amount'] !== (int)round((float)$pm['jumlah'])) {
    duitku_kasir_log('callback DITOLAK: nominal tidak cocok', ['callback_amount' => $v['amount'], 'tagihan' => $pm['jumlah']]);
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'Amount mismatch']);
    exit;
}

if ($v['resultCode'] === '00') {
    DB::run(
        "UPDATE pembayaran SET status='Lunas', keterangan='Duitku lunas ref: '||? WHERE id=?",
        [$v['reference'], $pm['id']]
    );
    // Hitung ulang sisa pesanan
    $total_bayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id=? AND status='Lunas'", [$pesanan_id])['t'];
    $ps = DB::one("SELECT * FROM pesanan WHERE id=?", [$pesanan_id]);
    if ($ps) {
        $sisa = max(0, (float)$ps['total'] - $total_bayar);
        $stat = $sisa <= 0 ? 'Lunas' : 'DP';
        DB::run("UPDATE pesanan SET sisa=?, status=? WHERE id=?", [$sisa, $stat, $pesanan_id]);
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
                'metode' => 'Duitku',
                'status' => $ev === 'lunas' ? 'Lunas' : 'DP',
            ], $ev);
        }
    }
    duitku_kasir_log('callback: LUNAS pesanan_id=' . $pesanan_id);
    echo json_encode(['status' => 'paid', 'merchant_order_id' => $v['merchantOrderId']]);
} else {
    DB::run(
        "UPDATE pembayaran SET status='Gagal', keterangan='Duitku: '||? WHERE id=?",
        [$v['resultCode'], $pm['id']]
    );
    duitku_kasir_log('callback: GAGAL', ['merchantOrderId' => $v['merchantOrderId'], 'resultCode' => $v['resultCode']]);
    echo json_encode(['status' => 'failed', 'merchant_order_id' => $v['merchantOrderId']]);
}
