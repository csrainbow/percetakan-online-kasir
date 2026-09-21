<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

$id = $_GET['id'] ?? 0;
$stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) redirect('/admin/orders.php');

$items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $db->prepare("SELECT * FROM payments WHERE order_id=? ORDER BY created_at DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

// Hitung total pembayaran yang sudah terverifikasi
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$id]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = max(0, $order['total'] - $totalPaid);
$persentaseDibayar = $order['total'] > 0 ? min(100, round(($totalPaid / $order['total']) * 100)) : 0;

// 🔥 CEK INVOICE STATUS (hanya jika sudah LUNAS dan sudah diproses/dicetak/selesai)
$canPublishInvoice = $order['payment_status'] === 'paid' && in_array($order['status'], ['processed','printing','done']);

// 🔥 CEK STATUS PEMBAYARAN UNTUK TOMBOL
$isPaid = $order['payment_status'] === 'paid';
$isPendingVerification = $order['payment_status'] === 'pending_verification';
$isUnpaid = $order['payment_status'] === 'unpaid';

// 🔥 PROSES SIMPAN PRINTER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_printer'])) {
    $printerType = trim($_POST['printer_type'] ?? '');
    $db->prepare("UPDATE orders SET printer_type=? WHERE id=?")->execute([$printerType, $id]);
    $_SESSION['success'] = "✅ Tipe printer berhasil disimpan!";
    echo '<script>location.href="order-detail.php?id=' . $id . '";</script>';
    exit;
}

// 🔥 KIRIM NOTIFIKASI KE CUSTOMER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $message = trim($_POST['notification_message'] ?? '');
    $sendWhatsApp = isset($_POST['send_whatsapp']) ? true : false;
    
    if (!empty($message)) {
        $customerEmail = $db->prepare("SELECT email FROM customers WHERE id=?");
        $customerEmail->execute([$order['customer_id']]);
        $customer = $customerEmail->fetch();
        
        if ($customer && $customer['email']) {
            $subject = '📧 Notifikasi Pesanan - ' . $order['order_code'];
            $fullMessage = "Halo " . $order['customer_name'] . ",\n\n";
            $fullMessage .= $message . "\n\n";
            $fullMessage .= "Pesanan: " . $order['order_code'] . "\n";
            $fullMessage .= "Status: " . ucfirst($order['status']) . "\n\n";
            $fullMessage .= "Terima kasih,\nPercetakan Rainbow";
            
            $emailSent = sendEmail($customer['email'], $subject, $fullMessage);
            
            if ($emailSent) {
                $_SESSION['success'] = "✅ Email notifikasi berhasil dikirim ke customer!";
            } else {
                $_SESSION['error'] = "⚠️ Gagal mengirim email. Coba lagi.";
            }
            
            // 🔥 Kirim WhatsApp jika dicentang (via WA Gateway otomatis)
            if ($sendWhatsApp) {
                $waSent = false;
                if ($order['customer_phone'] && function_exists('wa_web_send')) {
                    $waSent = wa_web_send($order['customer_phone'], str_replace(["\n\n", "\n"], ["\n", "\n"], $fullMessage));
                }
                if ($waSent) {
                    $_SESSION['success'] = "✅ Email & WhatsApp berhasil dikirim ke customer!";
                } else {
                    $_SESSION['success'] = "✅ Email berhasil dikirim! (WA masuk antrean bila gateway aktif)";
                }
            }
        } else {
            $_SESSION['error'] = "❌ Email customer tidak ditemukan!";
        }
        echo '<script>location.href="order-detail.php?id=' . $id . '";</script>';
        exit;
    }
}

$pageTitle = 'Detail Pesanan - ' . $order['order_code'];
include '../includes/header.php';
?>

<style>
.admin-layout {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.admin-sidebar {
    width: 220px;
    background: var(--primary);
    padding: 20px 15px;
    border-radius: 8px;
    flex-shrink: 0;
    position: sticky;
    top: 80px;
    height: fit-content;
}
.admin-sidebar h2 {
    color: var(--danger);
    font-size: 16px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.admin-sidebar ul {
    list-style: none;
    padding: 0;
}
.admin-sidebar ul li {
    margin-bottom: 4px;
}
.admin-sidebar ul li a {
    display: block;
    padding: 8px 12px;
    color: #bdc3c7;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s;
}
.admin-sidebar ul li a:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
.admin-sidebar ul li a.active {
    background: var(--danger);
    color: #fff;
}
.admin-main {
    flex: 1;
    min-width: 0;
}
.admin-main h1 {
    font-size: 24px;
    color: var(--primary);
    margin-bottom: 20px;
}
.order-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.card h3 {
    font-size: 16px;
    color: var(--primary);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}
.card p {
    margin: 6px 0;
    font-size: 14px;
}
.status-badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-pending { background: var(--danger); color: #fff; }
.status-desain { background: var(--secondary); color: #fff; }
.status-processed { background: var(--info); color: #fff; }
.status-printing { background: var(--primary); color: #fff; }
.status-done { background: var(--success); color: #fff; }
.status-cancelled { background: var(--danger); color: #fff; }
.status-failed { background: var(--danger); color: #fff; }
.status-unpaid { background: #95a5a6; color: #fff; }
.status-dp { background: var(--danger); color: #fff; }
.status-paid { background: var(--success); color: #fff; }
.status-pending_verification { background: var(--info); color: #fff; }
.status-verified { background: var(--success); color: #fff; }
.status-rejected { background: var(--danger); color: #fff; }
.status-approved { background: var(--success); color: #fff; }

.btn {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.3s;
}
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: #000000; }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #1e8449; }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #b71c1c; }
.btn-warning { background: var(--danger); color: #fff; }
.btn-warning:hover { background: #c62828; }
.btn-outline { background: #fff; color: var(--primary); border: 1px solid var(--primary); }
.btn-outline:hover { background: #f8f9fa; }
.btn-sm { padding: 4px 10px; font-size: 11px; }

.table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.table thead { background: #f8f9fa; }
.table th { padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #dee2e6; }
.table td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; font-size: 14px; }
.table tbody tr:hover { background: #f8f9fa; }
.table tfoot { background: #f8f9fa; font-weight: bold; }

.alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
    .order-detail-grid { grid-template-columns: 1fr; }
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php" class="active">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <!-- 🔥 ALERT MESSAGES -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <h1>📋 Detail Pesanan: <?= htmlspecialchars($order['order_code']) ?></h1>
        
        <!-- 🔥 INFO PEMBAYARAN RINGKAS -->
        <div style="background:<?= $sisaPembayaran > 0 ? '#fef9e7' : '#e8f5e9' ?>;padding:15px;border-radius:8px;border:1px solid <?= $sisaPembayaran > 0 ? 'var(--danger)' : 'var(--success)' ?>;margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
                <div>
                    <strong style="color:#555;">Total Pesanan</strong><br>
                    <span style="font-size:20px;font-weight:bold;"><?= formatRupiah($order['total']) ?></span>
                </div>
                <div>
                    <strong style="color:#555;">Sudah Dibayar</strong><br>
                    <span style="font-size:20px;font-weight:bold;color:var(--success);"><?= formatRupiah($totalPaid) ?></span>
                </div>
                <div>
                    <strong style="color:#555;">Sisa Pembayaran</strong><br>
                    <span style="font-size:20px;font-weight:bold;color:<?= $sisaPembayaran > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= formatRupiah($sisaPembayaran) ?>
                    </span>
                </div>
                <div>
                    <strong style="color:#555;">Status</strong><br>
                    <span style="font-size:16px;font-weight:bold;color:<?= $sisaPembayaran > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= $sisaPembayaran > 0 ? 'Pembayaran Sebagian' : '✅ LUNAS' ?>
                    </span>
                </div>
            </div>
            <?php if ($sisaPembayaran > 0): ?>
                <div style="margin-top:10px;background:#fff;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="width:<?= $persentaseDibayar ?>%;height:100%;background:linear-gradient(90deg,var(--danger),var(--danger));"></div>
                </div>
                <small style="color:#999;"><?= $persentaseDibayar ?>% dari total sudah dibayar</small>
            <?php endif; ?>
        </div>
        
        <!-- 🔥 ORDER & CUSTOMER INFO -->
        <div class="order-detail-grid">
            <div class="card">
                <h3>👤 Data Pembeli</h3>
                <p><strong>Nama:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                <p><strong>WhatsApp:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                <p><strong>Alamat:</strong> <?= nl2br(htmlspecialchars($order['customer_address'])) ?></p>
                <p><strong>Catatan:</strong> <?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
            <div class="card">
                <h3>📊 Status</h3>
                <p><strong>Pesanan:</strong> <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
                <p><strong>Pembayaran:</strong> <span class="status-badge status-<?= $order['payment_status'] ?>">
                    <?php
                    $pl = ['unpaid'=>'Belum','pending_verification'=>'Verifikasi','paid'=>'Lunas'];
                    echo $pl[$order['payment_status']] ?? ucfirst($order['payment_status']);
                    ?>
                </span></p>
                <p><strong>Metode:</strong> <?php
                $ml = ['transfer'=>'Transfer Bank','cod'=>'COD','qris'=>'QRIS (Cek Manual)','qris_dinamis'=>'QRIS (Cek Otomatis / API)','midtrans'=>'Midtrans'];
                echo $ml[$order['payment_method']] ?? ucfirst($order['payment_method']);
                ?></p>
                <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                
                <!-- 🔥 TIPE PRINTER -->
                <form method="POST" style="margin-top:10px;padding-top:10px;border-top:1px solid #eee;">
                    <p style="font-size:13px;margin-bottom:5px;"><strong>🖨️ Tipe Printer:</strong></p>
                    <div style="display:flex;gap:6px;">
                        <select name="printer_type" style="flex:1;padding:6px;font-size:13px;border:1px solid #ddd;border-radius:4px;">
                            <option value="">- Pilih -</option>
                            <?php
                            $printerOpts = getSetting('printer_options') ?: 'In-Fus/Solvent,Digital Printing,Offset,UV Printer,Sablon';
                            foreach (explode(',', $printerOpts) as $opt):
                                $opt = trim($opt);
                                $val = strtolower(str_replace([' ','/'], ['-','-'], $opt));
                            ?>
                            <option value="<?= $val ?>" <?= $order['printer_type'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="save_printer" value="1" class="btn btn-primary btn-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 🔥 BUKTI PEMBAYARAN -->
        <?php if (!empty($payments)): ?>
        <h2 style="margin-top:20px;">💰 Bukti Pembayaran</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;margin-bottom:20px;">
            <?php 
            $runningTotal = 0;
            foreach ($payments as $p): 
                $runningTotal += floatval($p['amount']);
                $paymentTypeLabel = '✅ Pelunasan';
            ?>
            <div class="card" style="position:relative;padding-top:25px;">
                <?php if ($p['status'] === 'verified' || $p['status'] === 'approved' || $p['status'] === 'paid'): ?>
                    <span style="position:absolute;top:-8px;right:10px;background:var(--success);color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">✅ LUNAS</span>
                <?php elseif ($p['status'] === 'rejected'): ?>
                    <span style="position:absolute;top:-8px;right:10px;background:var(--danger);color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">❌ DITOLAK</span>
                <?php else: ?>
                    <span style="position:absolute;top:-8px;right:10px;background:#95a5a6;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">⏳ <?= ucfirst($p['status']) ?></span>
                <?php endif; ?>
                
                <?php if ($paymentTypeLabel): ?>
                    <div style="margin-bottom:6px;font-size:12px;color:#666;"><?= $paymentTypeLabel ?></div>
                <?php endif; ?>
                
                <?php $proofFile = !empty($p['proof_image']) ? basename($p['proof_image']) : ''; ?>
                <?php if ($proofFile !== '' && is_file(__DIR__ . '/../uploads/proofs/' . $proofFile)): ?>
                    <a href="/admin/proof-stream.php?f=<?= urlencode($proofFile) ?>" target="_blank">
                        <img src="/admin/proof-stream.php?f=<?= urlencode($proofFile) ?>" style="width:100%;border-radius:6px;margin-bottom:8px;border:1px solid #eee;">
                    </a>
                <?php else: ?>
                    <div style="margin-bottom:8px;padding:10px;background:#f8f9fa;border:1px dashed #dee2e6;border-radius:6px;font-size:12px;color:#999;text-align:center;">📄 Tanpa bukti (input manual / otomatis)</div>
                <?php endif; ?>
                <p><strong><?= htmlspecialchars($p['bank_name']) ?></strong> — <?= htmlspecialchars($p['account_number']) ?></p>
                <p>a.n. <?= htmlspecialchars($p['account_name']) ?></p>
                <p><strong>Jumlah:</strong> <?= formatRupiah($p['amount']) ?></p>
                
                <?php if ($p['status'] === 'verified' || $p['status'] === 'approved' || $p['status'] === 'paid'): ?>
                    <p style="font-size:12px;color:var(--success);margin-top:-5px;">
                        <strong>✅ Lunas</strong> — <?= min(100, round(($runningTotal/$order['total'])*100)) ?>% dari total
                    </p>
                <?php endif; ?>
                
                <p>Status: <span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></p>
                
                <?php if ($p['status'] === 'pending'): ?>
                <form method="POST" action="orders.php" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="return_to" value="order-detail.php?id=<?= $order['id'] ?>">
                    <button type="submit" name="verify_payment" value="1" class="btn btn-success btn-sm">✅ Verifikasi (Lunas)</button>
                    <button type="submit" name="reject_payment" value="1" class="btn btn-danger btn-sm">✕ Tolak</button>
                </form>
                <?php endif; ?>
                <p style="font-size:12px;color:#999;margin-top:5px;"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 🔥 ITEM PESANAN -->
        <h2>📦 Item Pesanan</h2>
        <table class="table">
            <thead><tr><th>Produk</th><th>Bahan</th><th>Ukuran</th><th>Layanan Desain</th><th>Jumlah</th><th>Harga</th><th>Subtotal</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php 
                        $varData = !empty($item['variants']) ? json_decode($item['variants'], true) : [];
                        if (!empty($varData)): 
                            foreach ($varData as $vr): ?>
                                <br><small style="color:var(--danger);">+ <?= htmlspecialchars($vr['name']) ?> <?= formatRupiah($vr['price']) ?></small>
                        <?php endforeach; endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['material_name']) ?: '-' ?></td>
                    <td><?= ($item['width'] && $item['height']) ? intval($item['width']) . '×' . intval($item['height']) . ' cm' : '-' ?></td>
                    <td>
                        <?php if ($item['design_service'] === 'upload'): ?>
                            <span style="display:inline-block;padding:3px 10px;background:var(--info);color:#fff;border-radius:4px;font-size:12px;">Upload File</span>
                            <?php if ($item['design_file']): ?>
                                <br><a href="/uploads/designs/<?= htmlspecialchars($item['design_file']) ?>" target="_blank" style="font-size:11px;text-decoration:underline;">📎 <?= htmlspecialchars($item['design_original_name'] ?: 'Lihat File') ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#999;font-size:12px;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= formatRupiah($item['price']) ?></td>
                    <td><?= formatRupiah($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if ((float)($order['biaya_layanan'] ?? 0) > 0): ?>
                    <tr><th colspan="5" style="text-align:right;">Biaya Layanan QRIS</th><th colspan="2"><?= formatRupiah((float)$order['biaya_layanan']) ?></th></tr>
                <?php endif; ?>
                <tr><th colspan="5" style="text-align:right;">Total</th><th colspan="2"><?= formatRupiah($order['total']) ?></th></tr>
            </tfoot>
        </table>
        
        <!-- 🔥 INVOICE STATUS -->
        <div style="margin-top:20px;">
            <?php if ($canPublishInvoice): ?>
                <div style="padding:15px;background:#e8f5e9;border-radius:8px;border:1px solid var(--success);">
                    <h3 style="margin:0 0 10px;color:#1e8e49;">✅ Syarat Terbitkan Invoice Terpenuhi</h3>
                    <p style="margin:0;font-size:13px;color:var(--success);">
                        Pembayaran sudah <strong>Lunas</strong>. Invoice dapat diterbitkan.
                    </p>
                    <a href="/invoice.php?order=<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-success" style="margin-top:10px;">🧾 Terbitkan / Lihat Invoice</a>
                </div>
            <?php elseif (!$isPaid && $order['status'] !== 'done'): ?>
                <div style="padding:15px;background:#fef9e7;border-radius:8px;border:1px solid var(--danger);">
                    <h3 style="margin:0 0 10px;color:#b7950b;">⏳ Belum Bisa Terbitkan Invoice</h3>
                    <p style="margin:0;font-size:13px;color:#b7950b;">
                        Pembayaran harus <strong>Lunas</strong> dulu (status: <?= ucfirst($order['payment_status']) ?>).
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 🔥 FORM KIRIM NOTIFIKASI -->
        <div style="margin-top:20px;padding:15px;background:#e8f0fe;border-radius:8px;border:1px solid #4a90d9;">
            <h3 style="margin:0 0 10px;color:var(--primary);">📧 Kirim Notifikasi ke Customer</h3>
            <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <input type="hidden" name="send_notification" value="1">
                <div style="flex:1;min-width:200px;">
                    <textarea name="notification_message" rows="2" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" placeholder="Tulis pesan notifikasi...">Pesanan Anda sedang diproses. Terima kasih telah berbelanja di Percetakan Rainbow.</textarea>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <label style="font-size:13px;cursor:pointer;">
                        <input type="checkbox" name="send_whatsapp" value="1"> 📱 Juga via WhatsApp
                    </label>
                    <button type="submit" class="btn btn-primary">📧 Kirim</button>
                </div>
            </form>
        </div>
        
        <!-- 🔥 ACTION BUTTONS -->
        <p style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=Halo%20<?= urlencode($order['customer_name']) ?>%2C%20pesanan%20<?= $order['order_code'] ?>%20kami%20proses" target="_blank" class="btn btn-success">
                📱 Hubungi Pembeli
            </a>
            <a href="orders.php" class="btn btn-outline">← Kembali ke Daftar Pesanan</a>
        </p>
    </main>
</div>
<?php include '../includes/footer.php'; ?>