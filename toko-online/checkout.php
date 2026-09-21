<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/qris.php';
require_once __DIR__ . '/includes/payment_autocheck.php';

$pageTitle = 'Checkout';

// 🔥 AMBIL DATA BANK DARI SETTINGS
$banks = [
    ['name' => getSetting('bank1_name'), 'account' => getSetting('bank1_account'), 'holder' => getSetting('bank1_name_holder')],
    ['name' => getSetting('bank2_name'), 'account' => getSetting('bank2_account'), 'holder' => getSetting('bank2_name_holder')],
    ['name' => getSetting('bank3_name'), 'account' => getSetting('bank3_account'), 'holder' => getSetting('bank3_name_holder')],
];

// 🔥 FILTER BANK YANG VALID
$banks = array_filter($banks, function($b) {
    return !empty($b['name']) && !empty($b['account']);
});

$qrisName = getSetting('qris_name');
$qrisImage = getSetting('qris_image');
$storeName = getSetting('store_name') ?: 'Percetakan Rainbow';

// 🔥 AMBIL DATA CUSTOMER JIKA LOGIN
$customerData = ['name' => '', 'phone' => '', 'address' => '', 'email' => ''];
if (isset($_SESSION['customer_id'])) {
    $stmt = $db->prepare("SELECT name, phone, address, email FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $data = $stmt->fetch();
    if ($data) $customerData = array_merge($customerData, $data);
}

include 'includes/header.php';
?>

<style>
/* ============================================
   CHECKOUT STYLES
   ============================================ */
.checkout-container {
    max-width: 1000px;
    margin: 0 auto;
}
.checkout-container h1 {
    font-size: 24px;
    color: #111111;
    margin-bottom: 5px;
}
.checkout-container .subtitle {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 20px;
}

/* 🔥 CHECKOUT LAYOUT */
.checkout-layout {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .checkout-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

.checkout-form,
.checkout-summary {
    background: #fff;
    padding: 25px 30px;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
}

.checkout-form h2,
.checkout-summary h2 {
    font-size: 18px;
    color: #111111;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--danger);
}

/* 🔥 FORM */
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #111111;
    margin-bottom: 5px;
}
.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.3s;
}
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: var(--danger);
    outline: none;
    box-shadow: 0 0 0 3px rgba(243,156,18,0.15);
}
.form-group .helper-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* 🔥 PAYMENT METHOD INFO */
.payment-info {
    display: none;
    margin-top: 15px;
    padding: 15px 18px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    background: #f8f9fa;
}
.payment-info.show {
    display: block;
}
.payment-info h4 {
    font-size: 14px;
    margin-bottom: 10px;
    color: #111111;
}
.bank-list {
    display: grid;
    gap: 8px;
}
.bank-item {
    padding: 10px 14px;
    background: #fff;
    border-radius: 6px;
    border: 1px solid #ddd;
}
.bank-item .bank-name {
    font-weight: 600;
    color: #111111;
}
.bank-item .bank-detail {
    font-size: 13px;
    color: #6c757d;
}

/* 🔥 QRIS */
.qris-image {
    max-width: 200px;
    border-radius: 8px;
    margin: 10px auto;
    display: block;
    border: 2px solid #e9ecef;
    padding: 8px;
    background: #fff;
}

/* 🔥 USER LOGIN BADGE */
.user-badge {
    padding: 10px 15px;
    background: #e8f5e9;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #2e7d32;
    border-left: 4px solid var(--success);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.user-badge .logout-link {
    color: var(--danger);
    font-size: 12px;
    text-decoration: none;
}
.user-badge .logout-link:hover {
    text-decoration: underline;
}

/* 🔥 SUMMARY */
.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px solid #f1f3f5;
}
.summary-total {
    display: flex;
    justify-content: space-between;
    padding: 12px 0 0;
    font-size: 18px;
    font-weight: 700;
    border-top: 2px solid #111111;
    margin-top: 8px;
}
.summary-empty {
    text-align: center;
    padding: 30px 0;
    color: #6c757d;
}
.summary-empty .icon {
    font-size: 40px;
    display: block;
    margin-bottom: 10px;
}

/* 🔥 BUTTONS */
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
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-lg {
    padding: 12px 30px;
    font-size: 16px;
}
.btn-outline {
    background: #fff;
    color: #111111;
    border: 1px solid #111111;
}
.btn-outline:hover {
    background: #f8f9fa;
}

/* 🔥 RESPONSIVE */
@media (max-width: 480px) {
    .checkout-form,
    .checkout-summary {
        padding: 18px 15px;
    }
    .user-badge {
        flex-direction: column;
        text-align: center;
    }
    .bank-list {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="checkout-container">
    <h1>🛒 Checkout</h1>
    <p class="subtitle">Lengkapi data dan pilih metode pembayaran untuk menyelesaikan pesanan.</p>

    <!-- 🔥 USER BADGE -->
    <?php if (isset($_SESSION['customer_id'])): ?>
        <div class="user-badge">
            <span>✅ Checkout sebagai <strong><?= htmlspecialchars($_SESSION['customer_name']) ?></strong> — data otomatis terisi</span>
            <a href="/logout.php" class="logout-link">🚪 Logout</a>
        </div>
    <?php endif; ?>

    <!-- 🔥 EMPTY CART -->
    <div id="checkout-empty" style="display:none;">
        <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:10px;border:1px solid #e9ecef;">
            <div style="font-size:48px;margin-bottom:15px;">🛒</div>
            <h3 style="color:#111111;margin-bottom:8px;">Keranjang Kosong</h3>
            <p style="color:#6c757d;">Belum ada produk di keranjang Anda.</p>
            <a href="products.php" class="btn btn-primary" style="margin-top:15px;">Mulai Belanja</a>
        </div>
    </div>

    <!-- 🔥 FORM CHECKOUT -->
    <form id="checkout-form" onsubmit="submitOrder(event)" style="display:none;">
        <div class="checkout-layout">
            <!-- 🔥 LEFT: FORM -->
            <div class="checkout-form">
                <h2>📋 Data Pembeli</h2>
                
                <div class="form-group">
                    <label for="name">Nama Lengkap *</label>
                    <input type="text" name="name" id="name" required 
                           value="<?= htmlspecialchars($customerData['name']) ?>" 
                           placeholder="Masukkan nama lengkap">
                </div>
                
                <div class="form-group">
                    <label for="phone">Nomor WhatsApp *</label>
                    <input type="tel" name="phone" id="phone" required 
                           value="<?= htmlspecialchars($customerData['phone']) ?>" 
                           placeholder="08123456789">
                    <div class="helper-text">Gunakan format 08123456789 atau 628123456789</div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email (untuk struk pembayaran online)</label>
                    <input type="email" name="email" id="email"
                           value="<?= htmlspecialchars($customerData['email']) ?>"
                           placeholder="nama@email.com">
                    <div class="helper-text">Opsional — untuk bukti pembayaran yang diverifikasi admin.</div>
                </div>

                <div class="form-group">
                    <label for="address">Alamat Lengkap</label>
                    <textarea name="address" id="address" rows="3"
                              placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota"><?= htmlspecialchars($customerData['address']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Catatan Pesanan</label>
                    <textarea name="notes" id="notes" rows="2" 
                              placeholder="Contoh: mau laminasi doff, warna merah, dll"></textarea>
                </div>

                <h2>💳 Metode Pembayaran</h2>
                
                <div class="form-group">
                    <label for="payment_method">Pilih Pembayaran *</label>
                    <select name="payment_method" id="payment_method" required>
                        <option value="">-- Pilih Metode --</option>
                        <?php if (!empty($banks)): ?>
                            <option value="transfer">🏦 Transfer Bank (Cek Manual + Foto Bukti)</option>
                        <?php endif; ?>
                        <?php if ($qrisImage): ?>
                            <option value="qris">📱 QRIS (Cek Manual)</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($banks) && !$qrisImage): ?>
                        <p style="color:var(--danger);font-size:13px;margin-top:6px;">⚠️ Belum ada metode pembayaran aktif. Hubungi admin.</p>
                    <?php endif; ?>
                    <div class="helper-text">Pilih metode pembayaran yang sesuai</div>
                </div>

                <!-- 🔥 BANK INFO -->
                <div class="payment-info" id="transfer-info">
                    <h4>🏦 Transfer Bank (Cek Manual)</h4>
                    <p style="font-size:13px;color:#555;margin-bottom:10px;">Transfer ke salah satu rekening berikut, lalu upload foto bukti transfer:</p>
                    <div class="bank-list">
                        <?php foreach ($banks as $b): ?>
                            <div class="bank-item">
                                <div class="bank-name"><?= htmlspecialchars($b['name']) ?></div>
                                <div class="bank-detail">
                                    <?= htmlspecialchars($b['account']) ?> 
                                    a.n. <?= htmlspecialchars($b['holder']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($banks)): ?>
                            <div class="bank-item">
                                <div class="bank-detail" style="color:var(--danger);">
                                    ⚠️ Belum ada rekening bank yang dikonfigurasi. Hubungi admin.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p style="font-size:13px;color:#6c757d;margin-top:10px;">
                        Setelah transfer, upload bukti pembayaran di halaman berikutnya.
                    </p>
                </div>

                <!-- 🔥 QRIS MANUAL INFO -->
                <div class="payment-info" id="qris-info">
                    <h4>📱 QRIS (Cek Manual)</h4>
                    <?php if ($qrisImage): ?>
                        <img src="/uploads/<?= htmlspecialchars($qrisImage) ?>" alt="QRIS" class="qris-image">
                    <?php else: ?>
                        <p style="color:var(--danger);">⚠️ QRIS belum dikonfigurasi. Hubungi admin.</p>
                    <?php endif; ?>
                    <?php if ($qrisName): ?>
                        <p style="text-align:center;">a.n. <strong><?= htmlspecialchars($qrisName) ?></strong></p>
                    <?php endif; ?>
                    <p style="font-size:13px;color:#b45309;margin-top:10px;text-align:center;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px;">
                        🧾 Pembayaran QRIS dikenakan <strong>Biaya Layanan <?= formatRupiah(qris_statis_fee()) ?></strong> — otomatis ditambahkan ke total tagihan.
                    </p>
<p style="font-size:13px;color:#6c757d;margin-top:10px;text-align:center;">
                        Scan QRIS, lalu upload bukti pembayaran di halaman berikutnya untuk dicek manual oleh admin.
                    </p>
                </div>
            </div>

            <!-- 🔥 RIGHT: SUMMARY -->
            <div class="checkout-summary">
                <h2>🧾 Ringkasan Pesanan</h2>
                <div id="order-summary">
                    <div class="summary-empty">
                        <span class="icon">🛒</span>
                        <p>Keranjang kosong</p>
                    </div>
                </div>
                
                <p style="font-size:12px;color:#6c757d;margin-top:15px;text-align:center;">
                    Dengan memesan, kamu menyetujui 
                    <a href="/terms-of-service.php" target="_blank">syarat & ketentuan</a> kami.
                </p>
                
                <button type="submit" class="btn btn-primary btn-lg" 
                        style="width:100%;margin-top:15px;" id="submitBtn">
                    <i class="fas fa-check"></i> Buat Pesanan
                </button>
            </div>
        </div>
    </form>

    <!-- 🔥 RESULT -->
    <div id="checkout-result" style="display:none;"></div>
</div>

<script>
/**
 * 🔥 PAYMENT METHOD TOGGLE
 */
window.QRIS_FEE_VALUE = <?= (int)qris_statis_fee() ?>;
document.addEventListener('DOMContentLoaded', function() {
    var paymentSelect = document.getElementById('payment_method');
    
    paymentSelect.addEventListener('change', function() {
        // Sembunyikan semua
        document.querySelectorAll('.payment-info').forEach(function(el) {
            el.classList.remove('show');
        });
        
        // Tampilkan yang dipilih
        var targetId = this.value + '-info';
        var target = document.getElementById(targetId);
        if (target) target.classList.add('show');

        // 🔥 Perbarui ringkasan (total tagihan + biaya layanan QRIS)
        if (typeof renderCheckoutSummary === 'function') renderCheckoutSummary();
    });
    
    // 🔥 Trigger initial state
    var event = new Event('change');
    paymentSelect.dispatchEvent(event);
});

/**
 * 🔥 SUBMIT ORDER (Override dari script.js)
 */
async function submitOrder(event) {
    event.preventDefault();
    
    var form = document.getElementById('checkout-form');
    var formData = new FormData(form);
    var items = getCart();
    
    if (items.length === 0) {
        showNotification('Keranjang kosong!', 'error');
        return;
    }
    
    // 🔥 Validasi nomor WhatsApp
    var phone = formData.get('phone');
    if (!validatePhone(phone)) {
        showNotification('Format nomor WhatsApp tidak valid! Gunakan 08123456789 atau 628123456789.', 'error');
        return;
    }
    
    var paymentMethod = formData.get('payment_method');
    
    var data = {
        name: formData.get('name'),
        phone: formData.get('phone'),
        email: formData.get('email'),
        address: formData.get('address'),
        notes: formData.get('notes'),
        payment_method: paymentMethod,
        items: items.map(function(item) {
            return {
                id: item.id,
                qty: item.qty,
                width: item.width || 0,
                height: item.height || 0,
                customSize: item.customSize || false,
                material: item.material || '',
                matPrice: item.pricePerM2 || 0,
                variants: item.variants || '',
                designService: item.designService || '',
                designFile: item.designFile || '',
                designOriginalName: item.designOriginalName || ''
            };
        })
    };
    
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Memproses...';
    
    try {
        var response = await fetch('/api-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        var result = await response.json();
        
        if (result.success) {
            localStorage.removeItem('cart');
            updateCartBadge();
            showNotification('✅ Pesanan berhasil!', 'success');
            setTimeout(function() {
                window.location.href = '/order-success.php?order=' + encodeURIComponent(result.order_code);
            }, 1200);
        } else {
            showNotification(result.message || 'Gagal memproses pesanan', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Buat Pesanan';
        }
    } catch (err) {
        showNotification('Terjadi kesalahan, coba lagi', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Buat Pesanan';
    }
}

/**
 * 🔥 VALIDATE PHONE
 */
function validatePhone(phone) {
    var clean = phone.replace(/[^0-9]/g, '');
    return /^(0|62)\d{8,13}$/.test(clean);
}

/**
 * 🔥 SHOW NOTIFICATION
 */
function showNotification(msg, type) {
    type = type || 'success';
    var existing = document.querySelector('.notif-toast');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notif-toast';
    var bgColor = type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--danger)';
    div.style.cssText = 'position:fixed;top:15px;left:50%;transform:translateX(-50%);background:' + bgColor + ';color:#fff;padding:12px 24px;border-radius:8px;z-index:99999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);text-align:center;max-width:90%;';
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(function() {
        div.style.opacity = '0';
        div.style.transition = '.3s';
    }, 3000);
    setTimeout(function() { div.remove(); }, 3500);
}

/**
 * 🔥 GET CART (dari localStorage)
 */
function getCart() {
    try {
        var cart = localStorage.getItem('cart');
        return cart ? JSON.parse(cart) : [];
    } catch (e) {
        return [];
    }
}

/**
 * 🔥 UPDATE CART BADGE
 */
function updateCartBadge() {
    var cart = getCart();
    var count = cart.reduce(function(total, item) {
        return total + (parseInt(item.qty) || 0);
    }, 0);
    var badges = document.querySelectorAll('.cart-badge');
    badges.forEach(function(badge) {
        badge.textContent = count;
    });
}
</script>

<?php include 'includes/footer.php'; ?>