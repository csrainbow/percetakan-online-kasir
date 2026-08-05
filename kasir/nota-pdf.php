<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$ref = $_GET['ref'] ?? 'pesanan';
if (!in_array($ref, ['pesanan', 'penjualan'])) {
    $ref = 'pesanan';
}

$publik = ($id > 0 && ($_GET['k'] ?? '') !== '' && hash_equals(nota_token($ref, $id), (string)$_GET['k']));
if (!$publik) {
    require_login();
}

require_once __DIR__ . '/nota-common.php';
$data = nota_data($ref, $id);
if (!$data) {
    if ($publik) {
        http_response_code(404);
        exit('Nota tidak ditemukan.');
    }
    flash_set('error', $ref === 'penjualan' ? 'Transaksi tidak ditemukan.' : 'Pesanan tidak ditemukan.');
    header('Location: index.php?p=' . ($ref === 'penjualan' ? 'penjualan' : 'pesanan'));
    exit;
}
[$ref, $ps, $viewItems, $pembayaran, $totalBayar] = $data;

if (!$publik && scope_user_id() !== 0 && (int)$ps['user_id'] !== scope_user_id()) {
    flash_set('error', $ref === 'penjualan' ? 'Transaksi tidak ditemukan.' : 'Pesanan tidak ditemukan.');
    header('Location: index.php?p=' . ($ref === 'penjualan' ? 'penjualan' : 'pesanan'));
    exit;
}

$user = DB::one('SELECT username FROM users WHERE id = ?', [$ps['user_id']]);

$autoload = __DIR__ . '/../kasir-lib/dompdf-3.1.6/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Library PDF belum terpasang.');
}
require_once $autoload;

ob_start();
require __DIR__ . '/nota-templates/a5-invoice.php';
$html = ob_get_clean();

$dompdf = new Dompdf\Dompdf();
$dompdf->setPaper('A5', 'landscape');
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->render();

$filename = 'nota-' . preg_replace('/[^A-Za-z0-9-]+/', '-', $ps['no_pesanan']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $dompdf->output();
