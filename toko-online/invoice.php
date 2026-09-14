<?php
require_once __DIR__ . '/config.php';

$orderCode = $_GET['order'] ?? '';
if (empty($orderCode)) {
    header('Location: /index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /index.php');
    exit;
}

// 🔥 HITUNG TOTAL PEMBAYARAN YANG SUDAH TERVERIFIKASI
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = max(0, $order['total'] - $totalPaid);

// Cek apakah pesanan pakai jasa desain
$hasJasaStmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
$hasJasaStmt->execute([$order['id']]);
$hasJasaDesain = $hasJasaStmt->fetch()['c'] > 0;

// 🔥 VALIDASI AKSES INVOICE
$canViewInvoice = false;

if ($hasJasaDesain) {
    if ($order['payment_status'] === 'paid') {
        $canViewInvoice = true;
    } elseif ($order['payment_status'] === 'dp' && in_array($order['status'], ['desain', 'processed', 'printing', 'done'])) {
        $canViewInvoice = true;
    }
} else {
    if ($order['payment_status'] === 'paid') {
        $canViewInvoice = true;
    } elseif ($order['payment_status'] === 'dp' && in_array($order['status'], ['processed', 'printing', 'done'])) {
        $canViewInvoice = true;
    }
}

if (!$canViewInvoice) {
    $_SESSION['error'] = "Invoice belum dapat diakses. Pastikan pembayaran sudah lunas atau DP sudah diverifikasi.";
    header('Location: /customer/order-detail.php?order=' . urlencode($orderCode));
    exit;
}

$items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$order['id']]);
$items = $items->fetchAll();

$storeName = getSetting('store_name') ?: SITE_NAME;
$storeAddress = getSetting('store_address') ?: '';
$storePhone = getSetting('store_phone') ?: '';
$adminEmail = getSetting('admin_email') ?: '';
$invoiceFooter = getSetting('invoice_footer') ?: 'Terima kasih telah berbelanja di ' . $storeName;
$storeCity = getSetting('store_city') ?: 'Samarinda';

// 🔥 Logo nota (sama pola kasir)
$logoImg = setting('logo_image', '/logo.png?v=2');
if ($logoImg && strpos($logoImg, 'data:') !== 0 && strpos($logoImg, 'http') !== 0) {
    $logoImg = BASE_URL . ltrim($logoImg, '/');
}
// 🔥 Lebar logo nota (mm, sama pola kasir)
$logoW = max(10, min(45, (float)setting('logo_nota_size', 24)));

$pageTitle = 'Invoice ' . $order['order_code'];
include 'includes/header.php';

$grandTotal = floatval($order['total']);
$sisaAmount = $sisaPembayaran; // 🔥 Sisa pembayaran yang sebenarnya
$totalDibayar = $totalPaid;

// 🔥 Status pembayaran ala kasir (LUNAS / DOWN PAYMENT / BELUM BAYAR)
if ($sisaAmount <= 0) {
    $payLabel = 'LUNAS';
} elseif ($totalDibayar > 0) {
    $payLabel = 'DOWN PAYMENT';
} else {
    $payLabel = 'BELUM BAYAR';
}

// 🔥 Bangun item nota (sama pola kasir: nama + catatan baris kecil)
$viewItems = [];
foreach ($items as $item) {
    $line = [
        'nama'  => $item['product_name'],
        'qty'   => $item['quantity'],
        'harga' => $item['price'],
        'total' => $item['subtotal'],
        'note'  => [],
    ];
    if ($item['design_service'] === 'jasa') {
        $line['note'][] = 'Jasa Desain';
    } elseif ($item['design_service'] === 'upload') {
        $line['note'][] = 'Upload File Desain';
    }
    if ($item['width'] && $item['height']) {
        $line['note'][] = intval($item['width']) . ' x ' . intval($item['height']) . ' cm';
    } elseif (!empty($item['unit_label'])) {
        $line['note'][] = $item['unit_label'];
    }
    if (!empty($item['material_name'])) {
        $line['note'][] = 'Bahan: ' . $item['material_name'];
    }
    $varData = !empty($item['variants']) ? json_decode($item['variants'], true) : [];
    if (is_array($varData)) {
        foreach ($varData as $vr) {
            if (is_array($vr)) {
                $line['note'][] = '+ ' . ($vr['name'] ?? '') . ' ' . formatRupiah($vr['price'] ?? 0);
            }
        }
    }
    $viewItems[] = $line;
}
?>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
.invoice-wrapper { margin:16px auto;background:#fff;padding:6px 4px;border:1px solid #ccc;box-shadow:0 2px 10px rgba(0,0,0,0.08);width:100%;max-width:none; }
.invoice-head { width:100%;border-collapse:collapse;margin-bottom:6px; }
.invoice-head td { vertical-align:top; }
.invoice-head-left { font-size:8px;color:#555; }
.invoice-head-left strong { font-size:11px;color:#2c3e50; }
.invoice-head-right { text-align:right;font-size:8px;color:#555; }
.invoice-head-right .inv-no { font-size:9px;font-weight:bold;color:#2c3e50;margin-top:2px; }
.invoice-head-right p,.invoice-head-left p { font-size:8px!important;margin:0!important;line-height:1.2!important; }
.logo-nota { float:left;width:<?= $logoW ?>mm;height:auto;max-width:30%;object-fit:contain;margin:0 8px 4px 0; }
.invoice-mid { width:100%;border-collapse:collapse;margin-bottom:6px;padding:4px 6px;background:#f8f9fa;border-radius:4px;font-size:8px; }
.invoice-mid td { padding:4px; }
.invoice-table { width:100%;border-collapse:collapse;margin-bottom:4px; }
.invoice-table th { background:#2c3e50;color:#fff;padding:3px 4px;text-align:left;font-size:8px; }
.invoice-table td { padding:2px 4px;border-bottom:1px solid #eee;font-size:8px;line-height:1.2; }
.invoice-table .inv-spacer td { height:14px; }
.invoice-table .item-note { font-size:7px;color:#999; }
.invoice-bottom { width:100%;border-collapse:collapse;margin-top:4px;font-size:8px; }
.invoice-bottom td { vertical-align:top;padding:0 4px; }
.invoice-bottom-left { font-size:8px;color:#555; }
.invoice-bottom-left ol { margin:2px 0;padding-left:10px;font-size:7px;line-height:1.3; }
.invoice-bottom-left ol li { margin-bottom:1px; }
.invoice-bottom-right { min-width:200px;font-size:8px; }
.invoice-bottom-right table { width:100%;border-collapse:collapse; }
.invoice-bottom-right td { padding:2px 4px;border-bottom:1px solid #ddd;font-size:8px; }
.invoice-bottom-right .total-row td { font-weight:bold;font-size:10px;border-top:2px solid #2c3e50; }
.invoice-sign { width:100%;border-collapse:collapse;margin-top:8px; }
.invoice-sign td { text-align:center;width:50%;font-size:8px; }
.invoice-sign .sign-space { margin-bottom:18px; }
.invoice-footer2 { margin-top:5px;padding-top:4px;border-top:1px solid #ddd;text-align:center;font-size:7px;color:#999; }
.print-btn { position:fixed;top:80px;right:20px;padding:10px 20px;background:#2c3e50;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;z-index:100; }
.print-btn:hover { background:#1a252f; }
.page-break { page-break-after:always; }
@media print {
    @page { size:A5 landscape; margin:3mm; }
    * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .navbar,.footer,.print-btn,.no-print{display:none!important;}
    .invoice-wrapper{box-shadow:none;border:none;padding:6px 4px;margin:0;max-width:none;width:100%;}
    body{background:#fff;}
    .main-content{padding:0;margin:0;max-width:none;}
    .container.main-content{max-width:none;padding:0;}
}
</style>

<button onclick="window.print()" class="print-btn no-print">Cetak / Simpan PDF</button>

<?php
$perPage = 5;
$chunks = array_chunk($viewItems, $perPage);
$totalPages = count($chunks);
$globalNo = 1;
$pageNo = 0;
foreach ($chunks as $chunk):
    $pageNo++;
    $isLast = ($pageNo === $totalPages);
    $invCode = $pageNo > 1 ? $order['order_code'] . '/L-' . ($pageNo - 1) : $order['order_code'];
?>
<div class="invoice-wrapper<?= !$isLast ? ' page-break' : '' ?>">
    <table class="invoice-head">
        <tr>
            <td class="invoice-head-left">
                <?php if ($logoImg): ?>
                    <img class="logo-nota" src="<?= htmlspecialchars($logoImg) ?>" alt="Logo">
                <?php endif; ?>
                <strong><?= htmlspecialchars($storeName) ?></strong>
                <p><?= nl2br(htmlspecialchars($storeAddress)) ?></p>
                <?php if ($storePhone): ?>
                <p>Telp: <?= htmlspecialchars($storePhone) ?></p>
                <?php endif; ?>
                <?php if ($adminEmail): ?>
                <p><?= htmlspecialchars($adminEmail) ?></p>
                <?php endif; ?>
            </td>
            <td class="invoice-head-right">
                <p><?= htmlspecialchars($storeCity) ?>, <?= date('d M Y', strtotime($order['created_at'])) ?></p>
                <p><strong>Kepada Yth,</strong></p>
                <p><?= htmlspecialchars($order['customer_name']) ?></p>
                <?php if ($order['customer_phone']): ?>
                <p><?= htmlspecialchars($order['customer_phone']) ?></p>
                <?php endif; ?>
                <?php if ($order['customer_address']): ?>
                <p style="font-size:7px;color:#999;"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="invoice-mid">
        <tr>
            <td><strong>No. Pesanan:</strong> <?= htmlspecialchars($invCode) ?></td>
            <td><strong>Kasir:</strong> -</td>
            <td><strong>Status Pembayaran:</strong> <?= $payLabel ?></td>
            <?php if ($sisaAmount > 0): ?>
            <td><strong>Sisa:</strong> <?= formatRupiah($sisaAmount) ?></td>
            <?php endif; ?>
        </tr>
    </table>

    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width:30px;text-align:center;">No</th>
                <th>Nama Barang</th>
                <th style="width:35px;text-align:center;">Qty</th>
                <th style="width:80px;text-align:right;">Harga</th>
                <th style="width:80px;text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($chunk as $item): ?>
            <tr>
                <td style="text-align:center;"><?= $globalNo++ ?></td>
                <td>
                    <?= htmlspecialchars($item['nama']) ?>
                    <?php if (!empty($item['note'])): ?>
                        <?php foreach ($item['note'] as $nt): ?>
                            <br><span class="item-note"><?= htmlspecialchars($nt) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;"><?= $item['qty'] ?></td>
                <td style="text-align:right;"><?= formatRupiah($item['harga']) ?></td>
                <td style="text-align:right;"><?= formatRupiah($item['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($chunk) <= 5): ?>
            <?php for ($i = 0; $i < 5; $i++): ?>
            <tr class="inv-spacer">
                <td></td><td></td><td></td><td></td><td></td>
            </tr>
            <?php endfor; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($isLast): ?>
    <table class="invoice-bottom">
        <tr>
            <td class="invoice-bottom-left">
                <?php if ($order['notes']): ?>
                <p style="margin-bottom:3px;"><strong>Catatan:</strong> <?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                <?php endif; ?>
                <ol>
                    <li>Pekerjaan akan diselesaikan dalam waktu kurang lebih 2 hari.</li>
                    <li>Apabila ada Kesalahan Desain/File setelah di cetak dari Customer bukan Tanggung jawab Kami.</li>
                    <li>Pekerjaan dilaksanakan Setelah ada bukti transfer dan kesepakatan Sebelumnya.</li>
                    <li>Apabila ada hal lain yang kurang berkenan silahkan hubungi No. yang ada di website resmi kami.</li>
                    <li>Terima kasih atas kepercayaan Anda.</li>
                </ol>
            </td>
            <td class="invoice-bottom-right">
                <table>
                    <tr><td>Total Pesanan</td><td style="text-align:right;"><?= formatRupiah($grandTotal) ?></td></tr>
                    <tr><td>Sudah Dibayar</td><td style="text-align:right;color:#27ae60;"><?= formatRupiah($totalDibayar) ?></td></tr>
                    <tr class="total-row">
                        <td><strong>Jumlah Pelunasan</strong></td>
                        <td style="text-align:right;<?= $sisaAmount > 0 ? 'color:#e74c3c;' : 'color:#27ae60;' ?>">
                            <strong><?= formatRupiah($sisaAmount) ?></strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="invoice-sign">
        <tr>
            <td>
                <p class="sign-space">Penerima,</p>
                <p style="font-weight:bold;"><?= htmlspecialchars($order['customer_name']) ?></p>
            </td>
            <td>
                <p class="sign-space">Hormat Kami,</p>
                <p style="font-weight:bold;"><?= htmlspecialchars($storeName) ?></p>
            </td>
        </tr>
    </table>

    <div class="invoice-footer2">
        <?= nl2br(htmlspecialchars($invoiceFooter)) ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php include 'includes/footer.php'; ?>