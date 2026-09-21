<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/qris.php';
require_once __DIR__ . '/includes/payment_autocheck.php';

$orderCode = $_GET['order'] ?? '';
if (empty($orderCode)) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

// 🔥 HITUNG TOTAL PEMBAYARAN YANG SUDAH TERVERIFIKASI
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = max(0, $order['total'] - $totalPaid);
$persentaseDibayar = $order['total'] > 0 ? round(($totalPaid / $order['total']) * 100) : 0;

// 🔥 CEK STATUS PEMBAYARAN
$isPaid = $order['payment_status'] === 'paid';
$isUnpaid = $order['payment_status'] === 'unpaid';
$isPendingVerification = $order['payment_status'] === 'pending_verification';

// 🔥 CEK METODE PEMBAYARAN
$methodLabels = [
    'transfer' => 'Transfer Bank (Cek Manual)',
    'cod' => 'Bayar di Tempat (COD)',
    'qris' => 'QRIS (Cek Manual)',
];
$methodLabel = $methodLabels[$order['payment_method']] ?? ucfirst($order['payment_method']);

// 🔥 CEK APAKAH CUSTOMER LOGIN
$isLoggedIn = isset($_SESSION['customer_id']);

$pageTitle = 'Pesanan Berhasil - Percetakan Rainbow';
include 'includes/header.php';
?>

<?php if (setting('meta_pixel_id') !== ''): ?>
<!-- 🔥 Meta Pixel: Purchase (pelacakan konversi iklan) -->
<script>
fbq('track', 'Purchase', {
    value: <?= (float)$order['total'] ?>,
    currency: 'IDR',
    content_ids: [<?= (int)$order['id'] ?>],
    content_type: 'product',
    order_code: <?= json_encode((string)$order['order_code']) ?>
});
</script>
<?php endif; ?>

<style>
/* ============================================
   ORDER SUCCESS STYLES
   ============================================ */
.order-success {
    max-width: 600px;
    margin: 20px auto;
    text-align: center;
    background: #fff;
    padding: 40px 35px;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}
.success-icon {
    font-size: 64px;
    margin-bottom: 15px;
}
.order-success h1 {
    font-size: 26px;
    color: #111111;
    margin-bottom: 8px;
}
.order-success .subtitle {
    color: #6c757d;
    font-size: 15px;
    margin-bottom: 20px;
}

/* 🔥 PAYMENT SUMMARY */
.payment-summary {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 8px;
    margin: 15px 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    border-left: 4px solid var(--danger);
}
.payment-summary .item {
    text-align: center;
}
.payment-summary .item .label {
    font-size: 10px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.payment-summary .item .value {
    font-size: 16px;
    font-weight: bold;
    color: #111111;
}
.payment-summary .item .value.success { color: var(--success); }
.payment-summary .item .value.danger { color: var(--danger); }
.payment-summary .item .value.warning { color: var(--danger); }

/* 🔥 PROGRESS BAR */
.progress-container {
    margin: 12px 0;
}
.progress-bar {
    background: #ecf0f1;
    border-radius: 10px;
    height: 8px;
    overflow: hidden;
}
.progress-bar .fill {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, var(--danger), var(--success));
    transition: width 0.5s ease;
}
.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #6c757d;
    margin-top: 4px;
}

/* 🔥 ORDER DETAIL */
.order-detail-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: left;
    margin: 15px 0;
}
.order-detail-card p {
    margin: 6px 0;
    font-size: 14px;
}
.order-detail-card strong {
    color: #111111;
}

/* 🔥 STATUS BADGE */
.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-unpaid { background: #95a5a6; color: #fff; }
.status-pending_verification { background: var(--danger); color: #fff; }
.status-dp { background: var(--danger); color: #fff; }
.status-paid { background: var(--success); color: #fff; }

/* 🔥 INFO BOX */
.info-box {
    margin-top: 15px;
    padding: 15px 18px;
    border-radius: 8px;
    text-align: left;
    border-left: 4px solid;
}
.info-box-warning {
    background: #fff3cd;
    border-color: var(--danger);
    color: #856404;
}
.info-box-warning strong { color: #b7950b; }
.info-box-warning a { color: #856404; font-weight: bold; }
.info-box-success {
    background: #d4edda;
    border-color: var(--success);
    color: #155724;
}
.info-box-success strong { color: #1e8449; }

/* 🔥 BUTTONS */
.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
    justify-content: center;
}
.btn {
    display: inline-block;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 14px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: #111111;
    color: #fff;
}
.btn-primary:hover {
    background: #000000;
}
.btn-success {
    background: var(--success);
    color: #fff;
}
.btn-success:hover {
    background: #1e8449;
}
.btn-warning {
    background: var(--danger);
    color: #fff;
}
.btn-warning:hover {
    background: #c62828;
}
.btn-outline {
    background: #fff;
    color: #111111;
    border: 1px solid #111111;
}
.btn-outline:hover {
    background: #f8f9fa;
}
.btn-lg {
    padding: 12px 30px;
    font-size: 16px;
    width: 100%;
    text-align: center;
}

/* 🔥 RESPONSIVE */
@media (max-width: 480px) {
    .order-success {
        padding: 25px 20px;
        margin: 10px;
    }
    .success-icon {
        font-size: 48px;
    }
    .order-success h1 {
        font-size: 20px;
    }
    .payment-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    .btn-group .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="order-success">
    <!-- 🔥 ICON -->
    <div class="success-icon">
        <?php if ($isPaid): ?>
            ✅
        <?php elseif ($isPendingVerification): ?>
            ⏳
        <?php else: ?>
            📋
        <?php endif; ?>
    </div>

    <!-- 🔥 JUDUL -->
    <h1>
        <?php if ($isPaid): ?>
            Pembayaran Berhasil! 🎉
        <?php elseif ($isPendingVerification): ?>
            Menunggu Verifikasi Pembayaran
        <?php else: ?>
            Pesanan Berhasil Dibuat!
        <?php endif; ?>
    </h1>

    <p class="subtitle">
        <?php if ($isPaid): ?>
            Terima kasih! Pembayaran Anda telah kami terima. Pesanan akan segera diproses.
        <?php elseif ($isPendingVerification): ?>
            Bukti pembayaran sedang diverifikasi oleh admin. Proses ini maksimal 1x24 jam.
        <?php else: ?>
            Terima kasih, pesanan Anda telah tercatat. Silakan lanjutkan pembayaran.
        <?php endif; ?>
    </p>

    <!-- 🔥 RINGKASAN PEMBAYARAN -->
    <?php if ($order['total'] > 0): ?>
    <div class="payment-summary">
        <div class="item">
            <div class="label">Total Pesanan</div>
            <div class="value"><?= formatRupiah($order['total']) ?></div>
        </div>
        <div class="item">
            <div class="label">Sudah Dibayar</div>
            <div class="value success"><?= formatRupiah($totalPaid) ?></div>
        </div>
        <div class="item">
            <div class="label">Sisa</div>
            <div class="value <?= $sisaPembayaran > 0 ? 'danger' : 'success' ?>">
                <?= $sisaPembayaran > 0 ? formatRupiah($sisaPembayaran) : '✅ LUNAS' ?>
            </div>
        </div>
    </div>

    <!-- 🔥 PROGRESS BAR -->
    <div class="progress-container">
        <div class="progress-bar">
            <div class="fill" style="width: <?= $persentaseDibayar ?>%;"></div>
        </div>
        <div class="progress-label">
            <span><?= $persentaseDibayar ?>% dibayar</span>
            <span><?= $sisaPembayaran > 0 ? 'Sisa ' . formatRupiah($sisaPembayaran) : '✅ Lunas' ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- 🔥 DETAIL PESANAN -->
    <div class="order-detail-card">
        <p><strong>📋 Kode Pesanan:</strong> <?= htmlspecialchars($order['order_code']) ?></p>
        <p><strong>👤 Nama:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
        <p><strong>📱 WhatsApp:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
        <?php if ((float)($order['biaya_layanan'] ?? 0) > 0): ?>
            <p><strong>🧾 Biaya Layanan QRIS:</strong> <?= formatRupiah((float)$order['biaya_layanan']) ?></p>
        <?php endif; ?>
        <p><strong>💰 Total:</strong> <?= formatRupiah($order['total']) ?></p>
        <p><strong>📊 Status Pesanan:</strong>
            <span class="status-badge status-<?= $order['status'] ?>">
                <?= ucfirst($order['status']) ?>
            </span>
        </p>
        <p><strong>💳 Status Pembayaran:</strong>
            <span class="status-badge status-<?= $order['payment_status'] ?>">
                <?php
                $pl = [
                    'unpaid' => 'Belum Dibayar',
                    'pending_verification' => 'Menunggu Verifikasi',
                    'paid' => '✅ Lunas'
                ];
                echo $pl[$order['payment_status']] ?? ucfirst($order['payment_status']);
                ?>
            </span>
        </p>
        <p><strong>🏦 Metode:</strong> <?= htmlspecialchars($methodLabel) ?></p>
    </div>

    <?php if ($isUnpaid && ($order['payment_method'] ?? '') === 'qris'): ?>
        <?php $qrisImg = getSetting('qris_image'); ?>
        <div class="qris-block" style="margin:15px 0;padding:18px;background:#fff;border:1px solid #e9ecef;border-radius:10px;text-align:center;">
            <p style="font-weight:600;margin-bottom:10px;color:#111111;">📱 Scan QRIS Statis untuk bayar (Cek Manual)</p>
            <?php if ($qrisImg): ?>
                <img src="/uploads/<?= htmlspecialchars($qrisImg) ?>" alt="QRIS" style="max-width:220px;width:100%;display:block;margin:0 auto 8px;border:1px solid #e2e8f0;border-radius:8px;">
            <?php else: ?>
                <p style="color:var(--danger);">⚠️ QRIS belum dikonfigurasi.</p>
            <?php endif; ?>
            <?php if (getSetting('qris_name')): ?>
                <p style="margin:0;font-size:13px;color:#666;">a.n. <strong><?= htmlspecialchars(getSetting('qris_name')) ?></strong></p>
            <?php endif; ?>
            <p style="margin:6px 0 0;font-size:12px;color:var(--danger);">Bayar sesuai total pesanan <?= formatRupiah($order['total']) ?> pada metode yang Anda pilih, lalu upload bukti untuk cek manual.</p>
        </div>
    <?php endif; ?>
    <!-- 🔥 🔥 TOMBOL AKSI 🔥 🔥 -->
    <div class="btn-group">
        <?php if ($isUnpaid): ?>
            <a href="/payment/confirm.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-primary btn-lg">
                💳 Lanjutkan Pembayaran
            </a>
        <?php endif; ?>

        <?php if ($isPaid): ?>
            <a href="/invoice.php?order=<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-success btn-lg">
                🧾 Lihat Invoice
            </a>
        <?php endif; ?>

        <?php if ($isPendingVerification): ?>
            <a href="/customer/order-detail.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-outline btn-lg">
                📋 Cek Status
            </a>
        <?php endif; ?>
    </div>

    <!-- 🔥 INFO UNTUK GUEST -->
    <?php if (!$isLoggedIn): ?>
        <div class="info-box info-box-warning">
            <strong>📌 Simpan Kode Pesanan</strong>
            <p style="margin:5px 0;font-size:13px;">
                Gunakan kode <strong><?= htmlspecialchars($order['order_code']) ?></strong> untuk cek status pesanan kapan saja:
            </p>
            <a href="/cek-pesanan.php" class="btn btn-primary btn-sm" style="display:inline-block;margin-top:5px;">
                🔍 Cek Status Pesanan
            </a>
            <p style="font-size:12px;margin-top:8px;">
                Atau <a href="/register.php">daftar akun</a> untuk pantau semua pesanan dalam satu dashboard.
            </p>
        </div>
    <?php endif; ?>

    <!-- 🔥 TOMBOL LAINNYA -->
    <div class="btn-group" style="margin-top:10px;">
        <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=Halo%20saya%20ingin%20konfirmasi%20pesanan%20<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-success">
            <i class="fab fa-whatsapp"></i> Konfirmasi via WA
        </a>
        <a href="/products.php" class="btn btn-outline">
            🛍️ Belanja Lagi
        </a>
        <?php if ($isLoggedIn): ?>
            <a href="/customer/dashboard.php" class="btn btn-outline">
                📋 Dashboard Saya
            </a>
        <?php endif; ?>
    </div>
<div id="modalQris" class="modal hidden">
        <div class="modal-box">
            <h3>QRIS Pembayaran</h3>
            <div id="qrisBox">
                <p class="muted">Menyiapkan QRIS...</p>
            </div>
            <button type="button" class="btn" id="btnTutupQris">Tutup</button>
        </div>
    </div>
    </div>
/**
 * 🔥 SAVE LAST ORDER
 */
(function() {
    var data = {
        code: '<?= addslashes($order['order_code']) ?>',
        phone: '<?= addslashes($order['customer_phone']) ?>'
    };
    localStorage.setItem('lastOrder', JSON.stringify(data));
})();

/**
 * 🔥 SAVE ORDER KE SESSION (untuk guest)
 */
<?php if (!$isLoggedIn): ?>
    // Simpan ke session via AJAX
    fetch('/api/save-guest-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            order_code: '<?= addslashes($order['order_code']) ?>',
            phone: '<?= addslashes($order['customer_phone']) ?>'
        })
    }).catch(function() {});
<?php endif; ?>

/**
 * 🔥 AUTO HIDE ALERT
 */
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 5000);
    });
});
function tampilQris(orderCode, phone) {
    var modal = document.getElementById('modalQris');
    var box = document.getElementById('qrisBox');
    if (!modal || !box) return;
    modal.classList.remove('hidden');
    box.innerHTML = '<p class="muted">Mengambil QRIS...</p>';
    document.getElementById('btnTutupQris').onclick = function() { modal.classList.add('hidden'); };
    fetch('/qris-status.php?order_code=' + encodeURIComponent(orderCode) + '&phone=' + encodeURIComponent(phone))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) { box.innerHTML = '<p class="bahaya">' + String(d.error || 'Gagal').replace(/</g,'&lt;') + '</p>'; return; }
            var html = '';
            if (d.qris_image) html += '<img class="qris-dinamis" src="' + d.qris_image + '" alt="QRIS">';
            html += '<p class="muted">' + String(d.status_teks || '') + '</p>';
            if (d.nmid) html += '<p class="muted kecil">NMID: <b>' + String(d.nmid) + '</b></p>';
            if (d.invid) html += '<p class="muted kecil">INV: ' + String(d.invid) + '</p>';
            if (d.expiry) html += '<p class="muted kecil">Berlaku s/d <b>' + String(d.expiry) + '</b></p>';
            if (d.paid) {
                html += '<p class="badge ok">' + String(d.status_teks) + '</p>';
                html += '<p><a href="/invoice.php?order=' + encodeURIComponent(orderCode) + '" class="btn btn-success">🧾 Lihat Invoice</a></p>';
            } else if (!d.paid && !d.expired) {
                html += '<p><button type="button" class="btn" onclick="tampilQris(\'' + orderCode + '\',\'' + phone + '\')">🔄 Periksa Lagi</button></p>';
            }
            box.innerHTML = html;
        })
        .catch(function() { box.innerHTML = '<p class="bahaya">Gagal menghubungi server.</p>'; });
}
    document.getElementById('btnTutupQris')?.addEventListener('click', function() { modal.classList.add('hidden'); });
</script>

<?php include 'includes/footer.php'; ?>