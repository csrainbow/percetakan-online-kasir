<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Panduan Pemesanan - Cara Order di Percetakan Rainbow';

$waNumber = getSetting('whatsapp_number') ?: '6282252569185';
include __DIR__ . '/includes/header.php';
?>
<style>
.panduan-hero {
    text-align: center;
    margin-bottom: 34px;
    padding: 34px 20px 28px;
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 60%, var(--primary-light) 100%);
    border-radius: 18px;
    color: #fff;
}
.panduan-hero h1 { font-size: 26px; margin-bottom: 8px; }
.panduan-hero p { opacity: 0.9; font-size: 14px; }

.steps { display: flex; flex-direction: column; gap: 14px; }
.step {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    border: 1px solid #e6e9f0;
    border-radius: 14px;
    background: #fff;
    padding: 18px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s;
}
.step:hover { box-shadow: 0 10px 28px rgba(0,194,209,0.18); }
.step .step-num {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    color: var(--dark);
    font-weight: 700;
    font-size: 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.step .step-body { flex: 1; }
.step .step-body h3 { font-size: 16px; margin-bottom: 6px; color: #111; }
.step .step-body p { font-size: 14px; color: #555; line-height: 1.7; }
.step .step-body ul { margin: 6px 0 0; padding-left: 20px; }
.step .step-body li { font-size: 13.5px; color: #555; line-height: 1.7; margin-bottom: 3px; }

.info-box {
    margin-top: 26px;
    border: 1px solid #e6e9f0;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
}
.info-box .info-head {
    padding: 14px 18px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f0f1f5;
}
.info-box .info-head i { color: var(--accent2); }
.info-box .info-body { padding: 16px 18px; font-size: 14px; color: #444; line-height: 1.7; }
.info-box .info-body p { margin-bottom: 8px; }
.info-box .info-body ul { margin: 6px 0 10px; padding-left: 20px; }
.info-box .info-body li { margin-bottom: 5px; }

.panduan-cta {
    margin-top: 30px;
    text-align: center;
    padding: 22px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 16px;
    color: #fff;
}
.panduan-cta p { margin-bottom: 14px; opacity: 0.9; font-size: 14px; }
.panduan-cta a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    color: var(--dark);
    font-weight: 700;
    padding: 11px 24px;
    border-radius: 50px;
    text-decoration: none;
    font-size: 14px;
    transition: transform 0.25s, box-shadow 0.25s;
}
.panduan-cta a:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(255,0,170,0.35); }
</style>

<div class="page-content">
    <div class="panduan-hero">
        <h1>📖 Panduan Pemesanan</h1>
        <p>Ikuti langkah-langkah mudah berikut untuk memesan produk cetak di Percetakan Rainbow.</p>
    </div>

    <div class="steps">
        <div class="step">
            <span class="step-num">1</span>
            <div class="step-body">
                <h3>Pilih Produk</h3>
                <p>Buka halaman <a href="/products.php"><strong>Produk</strong></a> lalu pilih produk yang Anda butuhkan (banner, stiker, undangan, kartu nama, brosur, dan lainnya). Klik <strong>Detail</strong> untuk melihat informasi lengkap produk.</p>
            </div>
        </div>

        <div class="step">
            <span class="step-num">2</span>
            <div class="step-body">
                <h3>Atur Ukuran, Bahan &amp; Jumlah</h3>
                <p>Sesuaikan ukuran, jenis bahan, dan jumlah dengan kebutuhan Anda:</p>
                <ul>
                    <li>Untuk produk <strong>custom ukuran</strong> (banner, stiker ukuran besar), masukkan lebar dan tinggi — harga dihitung otomatis per meter persegi (m²).</li>
                    <li>Untuk produk <strong>satuan</strong> (kartu nama, brosur, undangan), pilih bahan dan jumlah lembar/box.</li>
                    <li>Total harga langsung terlihat sebelum Anda klik <strong>Tambah ke Keranjang</strong>.</li>
                </ul>
            </div>
        </div>

        <div class="step">
            <span class="step-num">3</span>
            <div class="step-body">
                <h3>Tambah ke Keranjang</h3>
                <p>Klik <strong>Tambah ke Keranjang</strong> untuk menyimpan pesanan Anda. Bisa tambah lebih dari satu produk sekaligus. Anda bisa meninjau dan mengubah jumlah lewat halaman <a href="/cart.php"><strong>Keranjang</strong></a>.</p>
            </div>
        </div>

        <div class="step">
            <span class="step-num">4</span>
            <div class="step-body">
                <h3>Checkout &amp; Isi Data Pemesanan</h3>
                <p>Klik <strong>Checkout</strong> lalu lengkapi:</p>
                <ul>
                    <li><strong>Nama &amp; kontak</strong> (nomor WhatsApp aktif untuk konfirmasi).</li>
                    <li><strong>Alamat pengiriman</strong> lengkap beserta kode pos.</li>
                    <li><strong>Catatan pesanan</strong> jika ada permintaan khusus.</li>
                </ul>
            </div>
        </div>

        <div class="step">
            <span class="step-num">5</span>
            <div class="step-body">
                <h3>Unggah Desain</h3>
                <p>Unggah desain Anda dengan format <strong>PDF, AI, CDR, PNG, atau JPG</strong> beresolusi tinggi saat checkout. Belum punya desain? Konsultasikan kebutuhan Anda lewat WhatsApp.</p>
            </div>
        </div>

        <div class="step">
            <span class="step-num">6</span>
            <div class="step-body">
                <h3>Pilih Metode Pembayaran</h3>
                <p>Bayar pesanan Anda dengan salah satu metode:</p>
                <ul>
                    <li><strong>QRIS</strong> — scan QR dari aplikasi m-banking atau e-wallet mana pun (otomatis &amp; paling cepat terverifikasi).</li>
                    <li><strong>Transfer Bank</strong> — BCA dan Mandiri atas nama Percetakan Rainbow.</li>
                </ul>
                <p>Semua pembayaran dilakukan sebagai <strong>pelunasan penuh</strong> sesuai total pesanan — desain/produksi baru berjalan setelah pembayaran diterima oleh kami.</p>
            </div>
        </div>

        <div class="step">
            <span class="step-num">7</span>
            <div class="step-body">
                <h3>Pesanan Diproses &amp; Dikonfirmasi</h3>
                <p>Setelah pembayaran terverifikasi, tim kami segera memproses pesanan. Desain selalu <strong>dicek (proof)</strong> dan dikonfirmasi via WhatsApp sebelum masuk produksi. Estimasi pengerjaan umumnya <strong>2&ndash;5 hari kerja</strong>.</p>
            </div>
        </div>

        <div class="step">
            <span class="step-num">8</span>
            <div class="step-body">
                <h3>Pesanan Selesai &amp; Dikirim</h3>
                <p>Pesanan siap lalu dikirim ke alamat Anda (seluruh Indonesia via ekspedisi; area Samarinda bisa diantar langsung). Anda bisa <a href="/cek-pesanan.php"><strong>cek status pesanan</strong></a> kapan saja menggunakan kode pesanan dan nomor telepon yang terdaftar.</p>
            </div>
        </div>
    </div>

    <div class="info-box">
        <div class="info-head"><i class="fas fa-lightbulb"></i> Tips &amp; Catatan Penting</div>
        <div class="info-body">
            <ul>
                <li>Simpan <strong>kode pesanan</strong> dan screenshot pembayaran sebagai bukti.</li>
                <li>File desain disarankan minimal resolusi <strong>300 DPI</strong> agar hasil cetak tajam.</li>
                <li>Untuk bahan/budget custom silakan konsultasi dulu — <strong>konsultasi gratis</strong> tanpa kewajiban memesan.</li>
                <li>Revisi desain bisa dilakukan <strong>sebelum produksi</strong> tanpa biaya tambahan.</li>
            </ul>
        </div>
    </div>

    <div class="panduan-cta">
        <p>Sudah siap memesan? Mulai sekarang dan bayar dengan mudah.</p>
        <a href="/products.php"><i class="fas fa-shopping-bag"></i> Mulai Pesan</a>
        <a href="https://wa.me/<?= htmlspecialchars($waNumber) ?>" target="_blank" style="margin-left:10px;background:#25D366;color:#fff;"><i class="fab fa-whatsapp"></i> Konsultasi WhatsApp</a>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>