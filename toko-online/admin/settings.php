<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

$message = '';
$error = '';

// 🔥 🔥 PROSES POST 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowedKeys = [
        'store_name', 'store_address', 'store_phone', 'admin_email',
        'sendgrid_api_key',
        'bank1_name', 'bank1_account', 'bank1_name_holder',
        'bank2_name', 'bank2_account', 'bank2_name_holder',
        'bank3_name', 'bank3_account', 'bank3_name_holder',
        'qris_name', 'qris_merchant_id',
        'qris_fee_percent',
        'qris_api_mid', 'qris_api_nmid', 'qris_api_apikey',
        'midtrans_server_key', 'midtrans_client_key',
        'midtrans_is_production',
        'invoice_template', 'invoice_footer', 'printer_options',
        'logo_nota_size',
        'whatsapp_number', 'footer_text',
        'meta_pixel_id', 'meta_fb_verify'
    ];
    
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        
        // 🔥 SAVE TEXT FIELDS
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                $stmt->execute([$key, $value]);
            }
        }
        
        // 🔥 UPLOAD QRIS IMAGE
        if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['qris_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $filename = 'qris_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['qris_image']['tmp_name'], $uploadDir . $filename)) {
                    // Hapus QRIS lama
                    $oldQris = $db->query("SELECT value FROM settings WHERE key='qris_image'")->fetch();
                    if ($oldQris && $oldQris['value']) {
                        $oldPath = $uploadDir . $oldQris['value'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    
                    $stmt->execute(['qris_image', $filename]);
                } else {
                    $error = "❌ Gagal upload gambar QRIS!";
                }
            } else {
                $error = "❌ Format gambar QRIS tidak didukung! (JPG, PNG, GIF, WEBP)";
            }
        }
        
        // 🔥 UPLOAD LOGO NOTA (dipakai di invoice A5, seperti kasir)
        if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $filename = 'logo-nota.' . $ext;
                if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $uploadDir . $filename)) {
                    // Hapus logo lama jika beda
                    $oldLogo = $db->query("SELECT value FROM settings WHERE key='logo_image'")->fetch();
                    if ($oldLogo && $oldLogo['value'] && strpos($oldLogo['value'], 'uploads/') === 0) {
                        $oldPath = $uploadDir . basename($oldLogo['value']);
                        if ($oldPath !== $uploadDir . $filename && file_exists($oldPath)) unlink($oldPath);
                    }
                    
                    $stmt->execute(['logo_image', 'uploads/' . $filename]);
                } else {
                    $error = "❌ Gagal upload logo!";
                }
            } else {
                $error = "❌ Format logo tidak didukung! (JPG, PNG, GIF, WEBP)";
            }
        }
        
        if (empty($error)) {
            $message = "✅ Pengaturan berhasil disimpan!";
        }
    } catch (Exception $e) {
        $error = "❌ Gagal menyimpan: " . $e->getMessage();
    }
}

// 🔥 🔥 AMBIL SETTINGS 🔥 🔥
$settings = [];
$rows = $db->query("SELECT * FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

// 🔥 Ingat tab aktif setelah save (supaya pengguna tetap di tab yang sama)
$activeTab = 'tab-toko';
if (isset($_POST['active_tab'])) {
    $t = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_POST['active_tab']);
    if (in_array($t, ['tab-toko', 'tab-bank', 'tab-qris', 'tab-qris-dinamis', 'tab-midtrans', 'tab-invoice'], true)) {
        $activeTab = $t;
    }
}

$pageTitle = 'Pengaturan';
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
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    padding: 20px 15px;
    border-radius: 14px;
    flex-shrink: 0;
    position: sticky;
    top: 80px;
    height: fit-content;
    border: 1px solid rgba(45,212,191,0.1);
}
.admin-sidebar h2 {
    color: var(--accent1);
    font-size: 16px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.admin-sidebar ul {
    list-style: none;
    padding: 0;
}
.admin-sidebar ul li { margin-bottom: 4px; }
.admin-sidebar ul li a {
    display: block;
    padding: 8px 12px;
    color: #bdc3c7;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}
.admin-sidebar ul li a:hover { background: rgba(45,212,191,0.08); color: #fff; }
.admin-sidebar ul li a.active {
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    color: var(--dark);
    font-weight: 600;
    box-shadow: 0 6px 18px rgba(45,212,191,0.25);
}

.admin-main { flex: 1; min-width: 0; }
.admin-main h1 { font-size: 24px; color: var(--primary); margin-bottom: 20px; }

.alert {
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 15px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.btn {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.3s;
}
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #1e8449; }

.qris-badge {
    display: inline-block;
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}
.qris-badge.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.qris-badge.warn { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

/* 🔥 SETTINGS FORM */
.settings-form {
    background: #fff;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.04);
}

.settings-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--light);
}
.settings-section:last-child { border-bottom: none; margin-bottom: 0; }

.settings-section h2 {
    font-size: 18px;
    color: var(--primary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.settings-section h2 .badge {
    font-size: 11px;
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    color: var(--dark);
    padding: 2px 10px;
    border-radius: 20px;
}

.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 14px;
    color: var(--primary);
}
.form-group .helper-text {
    font-size: 12px;
    color: #999;
    margin-top: 4px;
}
.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    border-color: #00c2d1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,194,209,0.12);
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* 🔥 QRIS PREVIEW */
.qris-preview {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.qris-preview img {
    max-width: 150px;
    max-height: 150px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 8px;
    background: #fff;
}
.qris-preview .qris-info {
    font-size: 13px;
    color: #6c757d;
}
.qris-preview .qris-info strong { color: var(--primary); }

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
    .form-row { grid-template-columns: 1fr; }
}

/* 🔥 TAB NAVIGATION */
.tab-nav {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    background: #fff;
    padding: 6px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.04);
}
.tab-nav .tab-btn {
    padding: 8px 18px;
    border: none;
    background: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.3s;
    color: #6c757d;
}
.tab-nav .tab-btn:hover { background: var(--light); color: var(--primary); }
.tab-nav .tab-btn.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: #fff;
}
.tab-section {
    display: none;
}
.tab-section.active {
    display: block;
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="settings.php" class="active">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <h1>⚙️ Pengaturan</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="settings-form" id="settingsForm" onsubmit="return saveUI(this)">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?= htmlspecialchars($activeTab) ?>">
            
            <!-- 🔥 TAB NAVIGATION -->
            <div class="tab-nav">
                <button type="button" class="tab-btn<?= $activeTab === 'tab-toko' ? ' active' : '' ?>" data-tab="tab-toko">🏪 Toko</button>
                <button type="button" class="tab-btn<?= $activeTab === 'tab-bank' ? ' active' : '' ?>" data-tab="tab-bank">🏦 Bank</button>
                <button type="button" class="tab-btn<?= $activeTab === 'tab-qris' ? ' active' : '' ?>" data-tab="tab-qris">📱 QRIS</button>
                <button type="button" class="tab-btn<?= $activeTab === 'tab-qris-dinamis' ? ' active' : '' ?>" data-tab="tab-qris-dinamis">⚡ QRIS Dinamis</button>
                <button type="button" class="tab-btn<?= $activeTab === 'tab-midtrans' ? ' active' : '' ?>" data-tab="tab-midtrans">💳 Midtrans</button>
                <button type="button" class="tab-btn<?= $activeTab === 'tab-invoice' ? ' active' : '' ?>" data-tab="tab-invoice">🧾 Invoice</button>
            </div>

            <!-- 🔥 TAB 1: TOKO -->
            <div class="tab-section<?= $activeTab === 'tab-toko' ? ' active' : '' ?>" id="tab-toko">
                <div class="settings-section">
                    <h2>🏪 Informasi Toko</h2>
                    
                    <div class="form-group">
                        <label>Nama Toko</label>
                        <input type="text" name="store_name" value="<?= htmlspecialchars($settings['store_name'] ?? 'Percetakan Rainbow') ?>" placeholder="Nama toko Anda">
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="store_address" rows="2"><?= htmlspecialchars($settings['store_address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>No. Telepon/WA</label>
                            <input type="text" name="store_phone" value="<?= htmlspecialchars($settings['store_phone'] ?? '') ?>" placeholder="08123456789">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp Number (untuk tombol kontak)</label>
                            <input type="text" name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>" placeholder="628123456789">
                            <div class="helper-text">Gunakan format internasional tanpa + (contoh: 628123456789)</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Admin</label>
                        <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" placeholder="admin@email.com">
                        <div class="helper-text">Email untuk menerima notifikasi upload file desain dari customer</div>
                    </div>
                    <div class="form-group">
                        <label>Footer Text</label>
                        <input type="text" name="footer_text" value="<?= htmlspecialchars($settings['footer_text'] ?? '') ?>" placeholder="Teks footer website">
                        <div class="helper-text">Teks yang muncul di bagian bawah setiap halaman</div>
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 2: BANK -->
            <div class="tab-section<?= $activeTab === 'tab-bank' ? ' active' : '' ?>" id="tab-bank">
                <div class="settings-section">
                    <h2>🏦 Rekening Bank 1</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="bank1_name" value="<?= htmlspecialchars($settings['bank1_name'] ?? '') ?>" placeholder="BRI">
                        </div>
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" name="bank1_account" value="<?= htmlspecialchars($settings['bank1_account'] ?? '') ?>" placeholder="1234567890">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="bank1_name_holder" value="<?= htmlspecialchars($settings['bank1_name_holder'] ?? '') ?>" placeholder="Nama Pemilik Rekening">
                    </div>
                </div>

                <div class="settings-section">
                    <h2>🏦 Rekening Bank 2</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="bank2_name" value="<?= htmlspecialchars($settings['bank2_name'] ?? '') ?>" placeholder="BCA">
                        </div>
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" name="bank2_account" value="<?= htmlspecialchars($settings['bank2_account'] ?? '') ?>" placeholder="9876543210">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="bank2_name_holder" value="<?= htmlspecialchars($settings['bank2_name_holder'] ?? '') ?>" placeholder="Nama Pemilik Rekening">
                    </div>
                </div>

                <div class="settings-section">
                    <h2>🏦 Rekening Bank 3 <span class="badge">Opsional</span></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="bank3_name" value="<?= htmlspecialchars($settings['bank3_name'] ?? '') ?>" placeholder="Mandiri">
                        </div>
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" name="bank3_account" value="<?= htmlspecialchars($settings['bank3_account'] ?? '') ?>" placeholder="5555555555">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="bank3_name_holder" value="<?= htmlspecialchars($settings['bank3_name_holder'] ?? '') ?>" placeholder="Nama Pemilik Rekening">
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 3: QRIS STATIS -->
            <div class="tab-section<?= $activeTab === 'tab-qris' ? ' active' : '' ?>" id="tab-qris">
                <div class="settings-section">
                    <h2>📱 QRIS Statis</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Upload QR code untuk pembayaran QRIS. Dipakai jika QRIS Dinamis belum aktif.</p>
                    <div class="form-group">
                        <label>Nama Merchant/Pemilik</label>
                        <input type="text" name="qris_name" value="<?= htmlspecialchars($settings['qris_name'] ?? '') ?>" placeholder="Nama Kamu">
                    </div>
                    <div class="form-group">
                        <label>Biaya QRIS Statis</label>
                        <input type="hidden" name="qris_fee_percent" value="0">
                        <div class="helper-text">Tidak ada biaya penyedia layanan. Pelanggan membayar total pesanan.</div>
                    </div>
                    <div class="form-group">
                        <label>Merchant ID <span class="badge" style="font-size:10px;">Opsional</span></label>
                        <input type="text" name="qris_merchant_id" value="<?= htmlspecialchars($settings['qris_merchant_id'] ?? '') ?>" placeholder="QRIS Merchant ID">
                    </div>
                    <div class="form-group">
                        <label>Gambar QR Code</label>
                        <input type="file" name="qris_image" accept="image/*" id="qrisInput">
                        <div class="helper-text">Format: JPG, PNG, GIF, WEBP. Kosongkan jika tidak ingin mengganti.</div>

                        <?php if (!empty($settings['qris_image'])): ?>
                        <div class="qris-preview" id="qrisPreview">
                            <img src="/uploads/<?= htmlspecialchars($settings['qris_image']) ?>" alt="QRIS" id="qrisPreviewImg">
                            <div class="qris-info">
                                <strong>QRIS saat ini</strong><br>
                                <span style="font-size:12px;color:#999;">Klik "Choose File" untuk mengganti</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 3b: QRIS DINAMIS -->
            <div class="tab-section<?= $activeTab === 'tab-qris-dinamis' ? ' active' : '' ?>" id="tab-qris-dinamis">
                <div class="settings-section">
                    <h2>⚡ QRIS Dinamis — InterActive</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Konfigurasi QRIS Dinamis (berlaku 30 menit, API live). Isi APIKEY dari email aktivasi.</p>
                    <?php if (getSetting('qris_api_apikey') && getSetting('qris_api_mid')): ?>
                        <p class="qris-badge ok">✅ API AKTIF — QRIS Dinamis berjalan</p>
                    <?php else: ?>
                        <p class="qris-badge warn">⚠️ API BELUM AKTIF — QRIS statis dipakai</p>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>mID</label>
                        <input type="text" name="qris_api_mid" value="<?= htmlspecialchars($settings['qris_api_mid'] ?? '') ?>" placeholder="cth: 127683506">
                    </div>
                    <div class="form-group">
                        <label>NMID (ditampilkan di bawah QR)</label>
                        <input type="text" name="qris_api_nmid" value="<?= htmlspecialchars($settings['qris_api_nmid'] ?? '') ?>" placeholder="cth: ID1026589862154">
                    </div>
                    <div class="form-group">
                        <label>APIKEY</label>
                        <input type="password" name="qris_api_apikey" value="<?= htmlspecialchars($settings['qris_api_apikey'] ?? '') ?>" placeholder="APIKEY dari email aktivasi">
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 4: MIDTRANS -->
            <div class="tab-section<?= $activeTab === 'tab-midtrans' ? ' active' : '' ?>" id="tab-midtrans">
                <div class="settings-section">
                    <h2>💳 Midtrans <span class="badge">Opsional</span></h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Konfigurasi untuk pembayaran via Midtrans. Kosongkan jika tidak menggunakan.</p>

                    <div class="form-group">
                        <label>Mode Midtrans</label>
                        <select name="midtrans_is_production" id="midtransMode">
                            <option value="1" <?= ($settings['midtrans_is_production'] ?? '1') === '1' ? 'selected' : '' ?>>Production (pembayaran asli, key dimulai "Mid-")</option>
                            <option value="0" <?= ($settings['midtrans_is_production'] ?? '1') !== '1' ? 'selected' : '' ?>>Sandbox (uji coba, key dimulai "SB-Mid-")</option>
                        </select>
                        <div class="helper-text">Pilih "Sandbox" saat testing. Merchant ID & key bisa dilihat di dashboard Midtrans → Settings → Access Keys.</div>
                    </div>
                    <div class="form-group">
                        <label>Server Key</label>
                        <input type="text" name="midtrans_server_key" value="<?= htmlspecialchars($settings['midtrans_server_key'] ?? '') ?>" placeholder="<?= ($settings['midtrans_is_production'] ?? '1') === '1' ? 'Mid-server-xxxx' : 'SB-Mid-server-xxxx' ?>">
                        <div class="helper-text">Dapatkan dari dashboard Midtrans</div>
                    </div>
                    <div class="form-group">
                        <label>Client Key</label>
                        <input type="text" name="midtrans_client_key" value="<?= htmlspecialchars($settings['midtrans_client_key'] ?? '') ?>" placeholder="<?= ($settings['midtrans_is_production'] ?? '1') === '1' ? 'Mid-client-xxxx' : 'SB-Mid-client-xxxx' ?>">
                        <div class="helper-text">Dapatkan dari dashboard Midtrans</div>
                    </div>
                    <div class="form-group">
                        <label>SendGrid API Key <span class="badge" style="font-size:10px;">Opsional</span></label>
                        <input type="password" name="sendgrid_api_key" value="<?= htmlspecialchars($settings['sendgrid_api_key'] ?? '') ?>" placeholder="SG.xxxx">
                        <div class="helper-text">Untuk kirim email notifikasi via SendGrid. Daftar di <a href="https://sendgrid.com" target="_blank">sendgrid.com</a></div>
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 5: INVOICE -->
            <div class="tab-section<?= $activeTab === 'tab-invoice' ? ' active' : '' ?>" id="tab-invoice">
                <div class="settings-section">
                    <h2>🖼️ Logo Nota (A5 / Invoice)</h2>
                    <div class="form-group">
                        <label>Gambar Logo</label>
                        <input type="file" name="logo_image" accept=".png,.jpg,.jpeg,.webp" id="logoInput">
                        <div class="helper-text">Format: JPG, PNG, WEBP (maks 2 MB). Kosongkan jika tidak ingin mengganti. Belum diunggah = memakai logo bawaan <code>/logo.png</code>.</div>

                        <?php 
                        $logoVal = $settings['logo_image'] ?? '';
                        if ($logoVal): 
                            $logoSrc = (strpos($logoVal, 'data:') === 0 || strpos($logoVal, 'http') === 0) ? $logoVal : BASE_URL . ltrim($logoVal, '/');
                        ?>
                        <div class="qris-preview" id="logoPreview">
                            <img src="<?= htmlspecialchars($logoSrc) ?>" alt="Logo Nota" id="logoPreviewImg">
                            <div class="qris-info">
                                <strong>Logo saat ini</strong><br>
                                <span style="font-size:12px;color:#999;">Klik "Choose File" untuk mengganti</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="qris-preview" id="logoPreview">
                            <img src="<?= BASE_URL ?>logo.png?v=2" alt="Logo Nota (bawaan)">
                            <div class="qris-info">
                                <strong>Logo bawaan aktif</strong><br>
                                <span style="font-size:12px;color:#999;">Unggah logo Anda untuk dipakai di invoice A5.</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Lebar Logo di Invoice (mm)</label>
                        <input type="number" name="logo_nota_size" min="10" max="45" step="1" value="<?= htmlspecialchars($settings['logo_nota_size'] ?? '24') ?>" style="width:120px;">
                        <div class="helper-text">Min 10 mm – maks 45 mm (default 24 mm)</div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>🧾 Invoice & Cetakan</h2>
                    
                    <div class="form-group">
                        <label>Tampilan Invoice</label>
                        <select name="invoice_template">
                            <option value="classic" <?= ($settings['invoice_template'] ?? '') === 'classic' ? 'selected' : '' ?>>📄 Classic</option>
                            <option value="modern" <?= ($settings['invoice_template'] ?? '') === 'modern' ? 'selected' : '' ?>>🎨 Modern</option>
                            <option value="professional" <?= ($settings['invoice_template'] ?? '') === 'professional' ? 'selected' : '' ?>>💼 Professional</option>
                        </select>
                        <div class="helper-text">Pilih tampilan/style invoice yang dicetak</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Opsi Tipe Printer</label>
                        <input type="text" name="printer_options" value="<?= htmlspecialchars($settings['printer_options'] ?? 'In-Fus/Solvent,Digital Printing,Offset,UV Printer,Sablon') ?>" placeholder="In-Fus/Solvent,Digital Printing,Offset">
                        <div class="helper-text">Tipe printer yang tersedia, dipisahkan dengan koma</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Teks Footer Invoice</label>
                        <textarea name="invoice_footer" rows="2"><?= htmlspecialchars($settings['invoice_footer'] ?? 'Terima kasih telah berbelanja di Percetakan Rainbow') ?></textarea>
                        <div class="helper-text">Teks yang muncul di bagian bawah invoice</div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>📘 Integrasi Facebook / Meta</h2>
                    <p style="font-size:12px;color:#888;margin-bottom:15px;">Diperlukan untuk katalog produk & pelacakan iklan di Facebook / Instagram.</p>

                    <div class="form-group">
                        <label>Meta Pixel ID</label>
                        <input type="text" name="meta_pixel_id" value="<?= htmlspecialchars($settings['meta_pixel_id'] ?? '') ?>" placeholder="cth: 1234567890123456">
                        <div class="helper-text">Dari Facebook Business Manager → Events Manager → Pixels. Kosongkan jika belum membuat Pixel.</div>
                    </div>

                    <div class="form-group">
                        <label>Meta Domain Verification Token</label>
                        <input type="text" name="meta_fb_verify" value="<?= htmlspecialchars($settings['meta_fb_verify'] ?? '') ?>" placeholder="cth: abc123def456">
                        <div class="helper-text">Dari Facebook Business Manager → Brand Safety → Domains → meta tag content. Kosongkan jika belum verifikasi.</div>
                    </div>
                </div>

            </div>

            <!-- 🔥 SUBMIT -->
            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" onclick="return confirmSave()">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
                <button type="reset" class="btn btn-outline" style="background:#fff;color:var(--primary);border:1px solid var(--primary);padding:8px 16px;border-radius:6px;cursor:pointer;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </main>
</div>

<script>
// 🔥 TAB NAVIGATION
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Hapus active dari semua tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
        
        // Aktifkan tab yang dipilih
        this.classList.add('active');
        const target = document.getElementById(this.dataset.tab);
        if (target) target.classList.add('active');

        // 🔥 Simpan tab aktif supaya tetap di tab yang sama setelah save
        const input = document.getElementById('activeTabInput');
        if (input) input.value = this.dataset.tab;
    });
});

// 🔥 Umpan balik saat tombol Simpan ditekan
function saveUI(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';
    }
    return true;
}

// 🔥 QRIS PREVIEW
document.getElementById('qrisInput')?.addEventListener('change', function(e) {
    const file = this.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        let preview = document.getElementById('qrisPreview');
        if (!preview) {
            preview = document.createElement('div');
            preview.id = 'qrisPreview';
            preview.className = 'qris-preview';
            this.parentElement.appendChild(preview);
        }
        preview.innerHTML = `
            <img src="${e.target.result}" alt="QRIS Preview" style="max-width:150px;max-height:150px;border-radius:8px;border:2px solid var(--success);padding:8px;background:#fff;">
            <div class="qris-info">
                <strong style="color:var(--success);">✅ QRIS baru</strong><br>
                <span style="font-size:12px;color:#999;">File: ${file.name} (${(file.size/1024).toFixed(1)} KB)</span>
            </div>
        `;
    };
    reader.readAsDataURL(file);
});

// 🔥 LOGO PREVIEW
document.getElementById('logoInput')?.addEventListener('change', function(e) {
    const file = this.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('logoPreviewImg');
        if (img) {
            img.src = e.target.result;
            img.style.borderColor = 'var(--success)';
        }
    };
    reader.readAsDataURL(file);
});
function confirmSave() {
    // Validasi form
    const form = document.getElementById('settingsForm');
    const requiredFields = form.querySelectorAll('input[required]');
    for (const field of requiredFields) {
        if (!field.value.trim()) {
            alert('❌ ' + field.previousElementSibling?.textContent + ' harus diisi!');
            field.focus();
            return false;
        }
    }
    
    return confirm('⚠️ Yakin ingin menyimpan semua pengaturan?');
}

// 🔥 AUTO HIDE ALERT + scroll ke alert setelah reload
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    if (alerts.length > 0) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>

<?php include '../includes/footer.php'; ?>