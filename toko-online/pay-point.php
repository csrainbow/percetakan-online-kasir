<?php
// ============================================
// PAYMENT POINT (WEB) — halaman bayar publik
// Berisi QRIS + daftar rekening + ringkasan
// dan sisa tagihan pesanan (pola Payment Point
// kasir: n.php/pesanan/{id}/pay/{token}).
// Akses: ?order=<order_code>&t=<token>
// ============================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/qris.php';

$pageTitle = 'Payment Point';
$order = null;
$error = '';

$orderCode = trim($_GET['order'] ?? '');
$token = trim($_GET['t'] ?? '');

if ($orderCode !== '' && $token !== '') {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = '❌ Pesanan tidak ditemukan.';
        $order = null;
    } elseif (empty($order['customer_phone'])) {
        $error = '❌ Pesanan tidak memiliki nomor WhatsApp terdaftar.';
        $order = null;
    } else {
        // 🔥 Validasi token
        $expected = wa_web_pay_token($order['order_code'], $order['customer_phone']);
        if ($token !== $expected) {
            $error = '❌ Link pembayaran tidak valid atau sudah berubah. Gunakan link terbaru dari WhatsApp/email Anda.';
            $order = null;
        }
    }
}

if (!$order && $error === '' && ($orderCode !== '' || $token !== '')) {
    $error = '❌ Link pembayaran tidak lengkap.';
}

include 'includes/header.php';
?>

<style>
.pp-container { max-width: 720px; margin: 0 auto; }
.pp-container h1 { font-size: 24px; color: #111111; margin-bottom: 10px; }
.pp-container .subtitle { color: #6c757d; font-size: 14px; margin-bottom: 20px; }

.pp-summary { background: #f8f9fa; padding: 18px 22px; border-radius: 10px; margin-bottom: 18px; border-left: 4px solid var(--danger); }
.pp-summary .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; }
.pp-summary .item { text-align: center; }
.pp-summary .item .label { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
.pp-summary .item .value { font-size: 18px; font-weight: bold; color: #111111; }
.pp-summary .item .value.success { color: var(--success); }
.pp-summary .item .value.danger { color: var(--danger); }

.pp-card { background: #fff; padding: 20px 24px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border: 1px solid #e9ecef; margin-bottom: 18px; }
.pp-card h3 { margin: 0 0 12px; font-size: 16px; color: #111111; }

.bank-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 15px; margin-bottom: 8px; background: #fff; transition: all 0.2s; }
.bank-card .bank-name { font-weight: bold; font-size: 15px; }
.bank-card .bank-detail { color: #6c757d; font-size: 13px; }

.pp-qris { text-align: center; }
.pp-qris img { max-width: 220px; width: 100%; display: block; margin: 0 auto 8px; border: 1px solid #e2e8f0; border-radius: 8px; }
.pp-qris small { color: #6c757d; font-size: 12px; }

.pp-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }
.pp-actions .btn { flex: 1; text-align: center; }
.btn { display: inline-block; padding: 11px 24px; border-radius: 6px; font-size: 14px; text-decoration: none; border: none; cursor: pointer; transition: all 0.3s; }
.btn-primary { background: #111111; color: #fff; }
.btn-warning { background: var(--danger); color: #fff; }
.btn-outline { background: #fff; color: #111111; border: 1px solid #111111; }
.btn-outline:hover { background: #f8f9fa; }
.alert-box { background: #f8d7da; color: #721c24; padding: 14px 18px; border-radius: 6px; border: 1px solid #f5c6cb; margin-bottom: 15px; font-size: 14px; }
.alert-box a { color: #721c24; font-weight: bold; }
.date-line { font-size: 12px; color: #999; margin-top: 12px; text-align: center; }
</style>

<div class="pp-container">
    <h1>💳 Payment Point</h1>
    <p class="subtitle">Silakan lakukan pembayaran sesuai tagihan di bawah ini.</p>

    <?php if ($error): ?>
        <div style="background:#fff;padding:25px;border-radius:10px;border:1px solid #e9ecef;">
            <div class="alert-box"><?= htmlspecialchars($error) ?></div>
            <p style="margin:0;"><a href="/cek-pesanan.php" style="color:var(--danger);font-weight:bold;">🔍 Cek Pesanan</a></p>
        </div>
    <?php endif; ?>

    <?php if ($order):
        // 🔥 Hitung total yang sudah terverifikasi
        $totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
        $totalPaidStmt->execute([$order['id']]);
        $totalPaid = floatval($totalPaidStmt->fetch()['total']);
        $sisa = $order['total'] - $totalPaid;
        $persen = $order['total'] > 0 ? round(($totalPaid / $order['total']) * 100) : 0;

        // 🔥 Bank tujuan dari settings (bank1..bank3)
        $bankList = [];
        for ($i = 1; $i <= 3; $i++) {
            $bn = getSetting("bank{$i}_name");
            $ba = getSetting("bank{$i}_account");
            $bh = getSetting("bank{$i}_name_holder");
            if ($bn && $ba) {
                $bankList[] = ['bank' => $bn, 'account' => $ba, 'holder' => $bh ?: '-'];
            }
        }
        if (empty($bankList)) {
            $bankList = [
                ['bank' => 'BCA', 'account' => '1234567890', 'holder' => 'Percetakan Rainbow'],
                ['bank' => 'Mandiri', 'account' => '9876543210', 'holder' => 'Percetakan Rainbow'],
                ['bank' => 'BNI', 'account' => '5555555555', 'holder' => 'Percetakan Rainbow'],
            ];
        }

        $qrisStatis = getSetting('qris_image');
        $qrisName = getSetting('qris_name');
        $canBayar = ($sisa > 0);
    ?>
        <div class="pp-summary">
            <div class="grid">
                <div class="item" style="grid-column:1/-1;border-bottom:1px solid #dee2e6;padding-bottom:8px;margin-bottom:4px;">
                    <div class="label">Kode Pesanan</div>
                    <div class="value" style="font-size:17px;"><?= htmlspecialchars($order['order_code']) ?></div>
                </div>
                <div class="item">
                    <div class="label">Total Pesanan</div>
                    <div class="value"><?= formatRupiah($order['total']) ?></div>
                </div>
                <div class="item">
                    <div class="label">Sudah Dibayar</div>
                    <div class="value success"><?= formatRupiah($totalPaid) ?></div>
                </div>
                <div class="item">
                    <div class="label">Sisa Tagihan</div>
                    <div class="value <?= $sisa > 0 ? 'danger' : 'success' ?>"><?= formatRupiah($sisa) ?></div>
                </div>
            </div>
            <?php if ($order['total'] > 0): ?>
                <div style="margin-top:12px;background:#ecf0f1;border-radius:10px;height:6px;overflow:hidden;">
                    <div style="width:<?= $persen ?>%;height:100%;background:linear-gradient(90deg,var(--danger),var(--success));"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#6c757d;margin-top:3px;">
                    <span><?= $persen ?>% dibayar</span>
                    <span><?= $sisa > 0 ? 'Sisa ' . formatRupiah($sisa) : '✅ Lunas' ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canBayar): ?>

            <!-- 🔥 QRIS -->
            <div class="pp-card">
                <h3>📱 Scan QRIS</h3>
                <?php if ($qrisStatis): ?>
                    <div class="pp-qris">
                        <img src="<?= htmlspecialchars('/uploads/' . ltrim($qrisStatis, '/')) ?>" alt="QRIS">
                        <?php if ($qrisName): ?>
                            <p style="margin:0;font-size:13px;color:#666;">a.n. <strong><?= htmlspecialchars($qrisName) ?></strong></p>
                        <?php endif; ?>
                        <p style="margin:6px 0 0;font-size:12px;color:var(--danger);">Bayar tepat <strong><?= formatRupiah($sisa) ?></strong> lalu upload bukti pembayaran.</p>
                    </div>
                <?php elseif (!empty($order['qris_content'])): ?>
                    <div class="pp-qris">
                        <img src="<?= qris_png_datauri($order['qris_content']) ?>" alt="QRIS">
                        <?php if (!empty($order['qris_nmid'])): ?>
                            <p style="margin:0;font-size:12px;color:#666;">NMID: <strong><?= htmlspecialchars($order['qris_nmid']) ?></strong></p>
                        <?php endif; ?>
                        <?php if (!empty($order['qris_invid'])): ?>
                            <p style="margin:0;font-size:12px;color:#666;">INV: <strong><?= htmlspecialchars($order['qris_invid']) ?></strong></p>
                        <?php endif; ?>
                        <p style="margin:6px 0 0;font-size:12px;color:var(--danger);">QRIS berlaku <?= htmlspecialchars($order['qris_expiry'] ? 's/d ' . $order['qris_expiry'] : '30 menit') ?>.</p>
                    </div>
                <?php else: ?>
                    <p style="font-size:13px;color:#6c757d;">QRIS belum dikonfigurasi. Silakan gunakan transfer bank di bawah ini.</p>
                <?php endif; ?>
            </div>

            <!-- 🔥 BANK -->
            <div class="pp-card">
                <h3>🏦 Transfer Bank</h3>
                <?php foreach ($bankList as $bank): ?>
                    <div class="bank-card">
                        <div class="bank-name"><?= htmlspecialchars($bank['bank']) ?></div>
                        <div class="bank-detail">No. Rekening: <strong><?= htmlspecialchars($bank['account']) ?></strong></div>
                        <div class="bank-detail">a.n. <?= htmlspecialchars($bank['holder']) ?></div>
                    </div>
                <?php endforeach; ?>
                <p style="font-size:12px;color:#6c757d;margin:8px 0 0;">
                    Transfer tepat <strong><?= formatRupiah($sisa) ?></strong> dan cantumkan nama pesanan
                    <strong><?= htmlspecialchars($order['order_code']) ?></strong> pada keterangan/berita transfer.
                </p>
            </div>

            <!-- 🔥 AKSI -->
            <div class="pp-actions">
                <a href="/payment/confirm.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-warning">
                    ✅ Upload Bukti Pembayaran
                </a>
                <a href="/cek-pesanan.php" class="btn btn-outline">
                    🔍 Cek Status Pesanan
                </a>
            </div>
            <p style="font-size:12px;color:#6c757d;text-align:center;margin-top:10px;">
                Pembayaran diverifikasi admin maksimal 1x24 jam. Buka link ini hanya sampai sisa tagihan lunas.
            </p>

        <?php else: ?>
            <!-- 🔥 SUDAH LUNAS -->
            <div class="pp-card" style="text-align:center;">
                <h3 style="color:var(--success);">✅ Pesanan LUNAS</h3>
                <p style="font-size:14px;color:#555;">Terima kasih, seluruh pembayaran pesanan <?= htmlspecialchars($order['order_code']) ?> sudah diterima.</p>
                <div class="pp-actions">
                    <a href="/invoice.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-primary" target="_blank">🧾 Lihat Invoice</a>
                    <a href="/cek-pesanan.php" class="btn btn-outline">🔍 Cek Status Pesanan</a>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif (!$error): ?>
        <div style="background:#fff;padding:25px;border-radius:10px;border:1px solid #e9ecef;text-align:center;color:#666;">
            Buka link Payment Point dari WhatsApp / email Anda.
            <p style="margin:12px 0 0;"><a href="/cek-pesanan.php" style="color:var(--danger);font-weight:bold;">🔍 Cek Pesanan</a></p>
        </div>
    <?php endif; ?>

    <p class="date-line">— PERCETAKAN RAINBOW —</p>
</div>

<?php include 'includes/footer.php'; ?>