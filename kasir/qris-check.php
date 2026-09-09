<?php
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function qris_json_out($arr) {
    echo json_encode($arr);
    exit;
}

$kind = $_GET['k'] ?? ($_POST['k'] ?? '');
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$cek = !empty($_GET['cek']) || !empty($_POST['cek']);

if ($kind === 'penjualan') {
    $row = DB::one('SELECT * FROM penjualan WHERE id = ? AND ' . scope_sql('penjualan'), [$id]);
    if (!$row) {
        qris_json_out(['ok' => false, 'error' => 'Transaksi tidak ditemukan.']);
    }
    if ($row['status'] !== 'Menunggu QRIS') {
        qris_json_out(['ok' => true, 'status' => 'paid', 'status_teks' => 'Transaksi sudah lunas.', 'qris_image' => '', 'nmid' => '', 'error' => '']);
    }
    $r = qris_refresh('penjualan', $id, $row['no_invoice'], (int)round((float)$row['total']));
} else {
    $row = DB::one("SELECT pp.*, pe.no_pesanan, pe.pelanggan FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                    WHERE pp.id = ? AND pp.ref_type = 'pesanan' AND " . scope_sql('pe'), [$id]);
    if (!$row) {
        qris_json_out(['ok' => false, 'error' => 'Pembayaran tidak ditemukan.']);
    }
    if ($row['status'] !== 'Menunggu QRIS') {
        qris_json_out(['ok' => true, 'status' => 'paid', 'status_teks' => 'Pembayaran sudah lunas.', 'qris_image' => '', 'nmid' => '', 'error' => '']);
    }
    $r = qris_refresh('pembayaran', $id, 'PB' . $id, (int)round((float)$row['jumlah']));
}

$row = $r['row'];
if (!$r['ok']) {
    qris_json_out(['ok' => false, 'error' => $r['error']]);
}

$paid = false;
$detail = '';
if ($cek) {
    $pr = qris_poll($kind, $id, 30);
    $paid = $pr['paid'];
    $detail = $pr['detail'];
    if (!$pr['ok'] && !$pr['expired'] && !$paid) {
        $row = DB::one(($kind === 'penjualan' ? 'SELECT * FROM penjualan' : 'SELECT * FROM pembayaran') . ' WHERE id = ?', [$id]);
        if (!$row) {
            qris_json_out(['ok' => true, 'status' => 'paid', 'paid' => true, 'status_teks' => 'Transaksi sudah dikonfirmasi.', 'qris_image' => '', 'nmid' => '', 'detail' => $detail, 'error' => '']);
        }
        qris_json_out(['ok' => false, 'error' => $pr['error']]);
    }
    if ($paid) {
        qris_json_out([
            'ok' => true,
            'status' => 'paid',
            'paid' => true,
            'status_teks' => 'PEMBAYARAN DITERIMA — transaksi otomatis dikonfirmasi. Muat ulang halaman.',
            'detail' => $detail,
            'qris_image' => '',
            'nmid' => '',
            'error' => '',
        ]);
    }
}

$expired = qris_is_expired($row);
$amt = $kind === 'penjualan' ? (float)$row['total'] : (float)$row['jumlah'];
$statusTeks = $expired
    ? 'QRIS kedaluwarsa. Klik "Periksa Status" untuk membuat QRIS baru.'
    : 'Menunggu pembayaran. QRIS berlaku 30 menit.';

qris_json_out([
    'ok' => true,
    'created' => $r['created'],
    'status' => 'unpaid',
    'paid' => false,
    'status_teks' => $statusTeks,
    'expired' => $expired,
    'amount' => $amt,
    'qris_image' => qris_image_src($row),
    'nmid' => qris_nmid($row),
    'invid' => (string)($row['qris_invid'] ?? ''),
    'request_date' => (string)($row['qris_request_date'] ?? ''),
    'expiry' => (string)$row['qris_expiry'] !== '' ? $row['qris_expiry'] : qris_expiry_str($row),
    'kind' => $kind,
    'id' => $id,
    'error' => '',
]);