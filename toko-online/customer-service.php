<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Kontak & Layanan Pelanggan';

$storeName   = getSetting('store_name') ?: 'Percetakan Rainbow';
$storeAddress = getSetting('store_address') ?: '';
$storePhone  = getSetting('store_phone') ?: '';
$waNumber    = getSetting('whatsapp_number') ?: '';
$adminEmail  = getSetting('admin_email') ?: '';
$waLink = $waNumber ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $waNumber) : '#';

$bankList = [];
for ($i = 1; $i <= 3; $i++) {
    $b = getSetting("bank{$i}_name");
    $a = getSetting("bank{$i}_account");
    $h = getSetting("bank{$i}_name_holder");
    if ($b && $a) $bankList[] = ['bank' => $b, 'account' => $a, 'holder' => $h ?: '-'];
}
include __DIR__ . '/includes/header.php';
?>
<style>
.cs-hero { text-align: center; margin-bottom: 30px; }
.cs-hero h1 { font-size: 24px; color: var(--primary); }
.cs-hero p { color: #6c757d; }
.cs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-bottom: 26px; }
.cs-card {
    background: #fff; border: 1px solid #e6e9f0; border-radius: 14px;
    padding: 18px; box-shadow: 0 4px 14px rgba(0,0,0,0.05);
}
.cs-card .cs-icon {
    width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%); color: var(--dark); font-size: 17px; margin-bottom: 10px;
}
.cs-card h3 { font-size: 14px; color: #111; margin-bottom: 6px; }
.cs-card p { font-size: 13px; color: #555; line-height: 1.6; margin: 0; }
.cs-card a { color: var(--primary); font-weight: 600; text-decoration: none; }
.cs-card a:hover { text-decoration: underline; }
.cs-steps { display: grid; gap: 10px; margin-bottom: 26px; }
.cs-step {
    background: #f8fafc; border: 1px solid #eef1f6; border-radius: 12px; padding: 14px 16px;
    display: flex; gap: 12px; align-items: flex-start;
}
.cs-step .num {
    flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
    background: var(--primary); color: var(--accent1); font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; margin-top: 2px;
}
.cs-step .txt { font-size: 13px; color: #444; line-height: 1.7; }
.cs-step .txt strong { color: var(--primary); }
.cs-wa {
    text-align: center; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 14px; padding: 22px 18px; margin-top: 26px; color: #fff;
}
.cs-wa h3 { margin: 0 0 6px; color: var(--accent1); }
.cs-wa p { font-size: 13px; margin: 0 0 14px; opacity: .85; }
.cs-wa .wa-btn {
    display: inline-block; background: #25d366; color: #fff; padding: 11px 22px; border-radius: 30px;
    font-weight: 700; font-size: 14px; text-decoration: none; transition: transform .2s;
}
.cs-wa .wa-btn:hover { transform: translateY(-2px); }
.cs-bank-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px dashed #e6e9f0; font-size: 13px; }
.cs-bank-row:last-child { border-bottom: none; }
.cs-bank-name { font-weight: 700; min-width: 70px; }
</style>
<div class="page-content">
    <div class="cs-hero">
        <h1>📞 Kontak & Layanan Pelanggan</h1>
        <p>Butuh bantuan, revisi pesanan, atau info pengiriman? Hubungi kami — kami siap membantu.</p>
    </div>

    <div class="cs-grid">
        <div class="cs-card">
            <div class="cs-icon"><i class="fas fa-map-marker-alt"></i></div>
            <h3>📍 Alamat Toko</h3>
            <p><?= htmlspecialchars($storeAddress ?: '-') ?></p>
        </div>
        <div class="cs-card">
            <div class="cs-icon"><i class="fas fa-phone-alt"></i></div>
            <h3>📱 Telepon / WhatsApp</h3>
            <p><a href="<?= htmlspecialchars($waLink) ?>"><?= htmlspecialchars($storePhone ?: '-') ?></a></p>
            <p>Senin–Sabtu, 08.00–17.00. Di luar jam kerja pesan tetap dibalas esok hari.</p>
        </div>
        <div class="cs-card">
            <div class="cs-icon"><i class="fas fa-envelope"></i></div>
            <h3>✉️ Email</h3>
            <p><a href="mailto:<?= htmlspecialchars($adminEmail) ?>"><?= htmlspecialchars($adminEmail ?: '-') ?></a></p>
        </div>
        <div class="cs-card">
            <div class="cs-icon"><i class="fas fa-clock"></i></div>
            <h3>🕗 Waktu Layanan</h3>
            <p>Senin–Sabtu: 08.00–17.00 WITA<br>Ahad & hari libur: via WhatsApp (respon seadanya)</p>
        </div>
    </div>

    <h2 style="font-size:18px;color:var(--primary);margin-bottom:12px;">🛟 Bantuan Pesanan</h2>
    <div class="cs-steps">
        <div class="cs-step">
            <div class="num">1</div>
            <div class="txt"><strong>Cek status pesanan</strong> — buka halaman <a href="/cek-pesanan.php">Cek Pesanan</a>, masukkan kode pesanan Anda untuk melihat status produksi & pembayaran secara real-time.</div>
        </div>
        <div class="cs-step">
            <div class="num">2</div>
            <div class="txt"><strong>Pembayaran</strong> — transfer sesuai <strong>nominal unik</strong> (total + kode 3 digit) pada metode yang Anda pilih; pembayaran terkonfirmasi <em>LUNAS</em> otomatis bila nominalnya cocok.</div>
        </div>
        <div class="cs-step">
            <div class="num">3</div>
            <div class="txt"><strong>Revisi / desain</strong> — hubungi kami via WhatsApp sebelum produksi dimulai. Perubahan setelah cetak berjalan dapat dikenakan biaya tambahan (lihat <a href="/terms-of-service.php">Syarat & Ketentuan</a>).</div>
        </div>
        <div class="cs-step">
            <div class="num">4</div>
            <div class="txt"><strong>Pengiriman</strong> — ambil langsung di toko atau via ekspedisi. Jika barang Anda kirim, simpan bukti terima & foto kemasan untuk proteksi klaim.</div>
        </div>
    </div>

    <?php if ($bankList): ?>
    <h2 style="font-size:18px;color:var(--primary);margin-bottom:12px;">🏦 Rekening Pembayaran</h2>
    <div class="cs-card" style="padding:14px 18px;">
        <?php foreach ($bankList as $b): ?>
        <div class="cs-bank-row">
            <span class="cs-bank-name"><?= htmlspecialchars($b['bank']) ?></span>
            <span><strong><?= htmlspecialchars($b['account']) ?></strong></span>
            <span style="color:#777;margin-left:auto;"><em>a.n. <?= htmlspecialchars($b['holder']) ?></em></span>
        </div>
        <?php endforeach; ?>
        <p style="font-size:12px;color:#999;margin:10px 0 0;">QRIS juga tersedia — scan kode QR saat checkout.</p>
    </div>
    <?php endif; ?>

    <div class="cs-wa">
        <h3>💬 Chat Langsung</h3>
        <p>Pertanyaan cepat, revisi, atau follow-up pesanan — balas langsung di WhatsApp.</p>
        <a class="wa-btn" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
        </a>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>