<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'FAQ - Pertanyaan Umum';

$waNumber = getSetting('whatsapp_number') ?: '6282252569185';
$storePhone = getSetting('store_phone') ?: '';
$storeAddress = getSetting('store_address') ?: '';
include __DIR__ . '/includes/header.php';
?>
<style>
.faq-hero {
    text-align: center;
    margin-bottom: 30px;
}
.faq-hero p { color: #6c757d; }
.faq-list { display: flex; flex-direction: column; gap: 12px; }
.faq-item {
    border: 1px solid #e6e9f0;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s;
}
.faq-item:hover { box-shadow: 0 10px 28px rgba(0,194,209,0.18); }
.faq-item summary {
    cursor: pointer;
    list-style: none;
    padding: 16px 18px;
    font-size: 15px;
    font-weight: 600;
    color: #111;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary .q-icon {
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 9px;
    background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%);
    color: var(--dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
}
.faq-item summary .q-text { flex: 1; }
.faq-item summary .chev { color: #aab; font-size: 14px; transition: transform 0.3s; }
.faq-item[open] summary .chev { transform: rotate(180deg); }
.faq-item[open] summary { border-bottom: 1px solid #f0f1f5; }
.faq-item .a-body { padding: 16px 18px; font-size: 14px; color: #444; line-height: 1.7; }
.faq-item .a-body p { margin-bottom: 10px; }
.faq-item .a-body ul { margin: 6px 0 12px; padding-left: 20px; }
.faq-item .a-body li { margin-bottom: 5px; }
.faq-cta {
    margin-top: 30px;
    text-align: center;
    padding: 22px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 16px;
    color: #fff;
}
.faq-cta p { margin-bottom: 14px; opacity: 0.9; font-size: 14px; }
.faq-cta a {
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
.faq-cta a:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(255,0,170,0.35); }
</style>

<div class="page-content">
    <div class="faq-hero">
        <h1>Pertanyaan Umum (FAQ)</h1>
        <p>Jawaban atas pertanyaan yang sering ditanyakan pelanggan Percetakan Rainbow.</p>
    </div>

    <div class="faq-list">
        <details class="faq-item">
            <summary><span class="q-icon">1</span><span class="q-text">Bagaimana cara memesan?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Cara memesan di Percetakan Rainbow cukup mudah:</p>
                <ul>
                    <li>Pilih produk yang diinginkan (banner, stiker, undangan, dan lainnya).</li>
                    <li>Atur <strong>ukuran, bahan, dan jumlah</strong> sesuai kebutuhan, lalu klik <strong>Tambah ke Keranjang</strong>.</li>
                    <li>Lanjut ke <strong>Checkout</strong> dan isi data pemesanan Anda.</li>
                    <li>Unggah desain jika Anda sudah punya (opsional).</li>
                    <li>Pilih metode pembayaran lalu lakukan pembayaran (pelunasan penuh).</li>
                    <li>Pesanan otomatis diproses setelah pembayaran terverifikasi.</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">2</span><span class="q-text">Produk dan layanan apa saja yang tersedia?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Kami melayani berbagai kebutuhan cetak dan desain:</p>
                <ul>
                    <li><strong>Cetak Digital:</strong> banner/spanduk (In-Fus, Flexi, Luster, Albatex), stiker (Chromo, Vinyl, Cutting Sticker), poster, dan foto.</li>
                    <li><strong>Cetak Offset:</strong> brosur, kartu nama, undangan, kalender, dan kemasan.</li>
                    <li><strong>UV Printer:</strong> cetak di media akrilik, kaca, kayu, dan merchandise.</li>
                    <li><strong>Sablon:</strong> kaos, totebag, dan merchandise custom.</li>
                    <li><strong>Desain Grafis:</strong> konsultasi logo, konten media sosial, dan desain kemasan.</li>
                    <li>Semua ukuran dan bahan bisa <strong>custom</strong> sesuai kebutuhan Anda.</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">3</span><span class="q-text">Bagaimana harga produk custom dihitung?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Untuk produk besar seperti banner, harga dihitung otomatis berdasarkan <strong>ukuran dan jenis bahan</strong> (per meter persegi/m²) yang Anda pilih saat checkout. Sedangkan produk satuan seperti kartu nama, brosur, atau stiker cutting dihitung per satuan/lembar. Jumlah harga akan langsung terlihat di halaman produk sebelum Anda checkout.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">4</span><span class="q-text">Berapa lama proses pengerjaan?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Estimasi pengerjaan umumnya <strong>2-5 hari kerja</strong> tergantung jenis produk, jumlah, dan antrean produksi. Estimasi dihitung mulai dari pembayaran terverifikasi. Untuk pesanan kecil (undangan atau kartu nama) biasanya lebih cepat.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">5</span><span class="q-text">Apakah harus punya desain sendiri?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p><strong>Tidak wajib.</strong> Anda bisa mengunggah desain sendiri saat checkout dengan format PDF, AI, CDR, PNG, atau JPG beresolusi tinggi. Jika belum punya desain, konsultasikan kebutuhan Anda lewat WhatsApp sebelum memesan.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">6</span><span class="q-text">Apakah desain bisa direvisi?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Bisa. Revisi desain dilakukan <strong>sebelum tahap produksi/cetak dimulai</strong> tanpa biaya tambahan. Setelah proses cetak berjalan, perubahan desain dikenakan biaya tambahan karena bahan sudah terpakai.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">7</span><span class="q-text">Metode pembayaran apa saja yang tersedia?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Kami menerima pembayaran melalui:</p>
                <ul>
                    <li><strong>Transfer Bank:</strong> BCA dan Mandiri (atas nama <?= htmlspecialchars(getSetting('bank1_name_holder') ?: 'Percetakan Rainbow') ?>).</li>
                    <li><strong>QRIS:</strong> scan QR dari aplikasi m-banking atau e-wallet mana pun.</li>
                </ul>
                <p>Semua pembayaran dilakukan sebagai <strong>pelunasan penuh</strong> sesuai total pesanan sebelum tahap produksi dimulai.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">8</span><span class="q-text">Bagaimana cara cek status pesanan?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Masuk ke halaman <a href="/cek-pesanan.php"><strong>Cek Pesanan</strong></a>, lalu masukkan <strong>kode pesanan</strong> dan <strong>nomor telepon</strong> yang dipakai saat memesan. Selain itu, kami mengirim notifikasi status pesanan secara otomatis melalui WhatsApp/email.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">9</span><span class="q-text">Apakah tersedia layanan pengiriman?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Ya, kami melayani pengiriman <strong>ke seluruh Indonesia</strong> melalui jasa ekspedisi (JNE, J&T, SiCepat, dan lainnya). Untuk area <strong>Samarinda</strong> tersedia pengiriman langsung/diantar. Biaya ongkir menyesuaikan ekspedisi dan alamat tujuan.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">10</span><span class="q-text">Bagaimana jika hasil cetak kurang sesuai?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Sebelum produksi, tim kami selalu melakukan <strong>pengecekan dan konfirmasi desain (proof)</strong> melalui WhatsApp. Jika terdapat kesalahan dari pihak kami (salah desain, salah bahan/ukuran, atau hasil cacat), kami akan <strong>cetak ulang atau revisi tanpa biaya tambahan</strong>.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary><span class="q-icon">11</span><span class="q-text">Bisakah konsultasi dulu sebelum memesan?</span><span class="chev">▾</span></summary>
            <div class="a-body">
                <p>Tentu. Silakan hubungi kami melalui WhatsApp di <a href="https://wa.me/<?= htmlspecialchars($waNumber) ?>" target="_blank"><?= $storePhone ? '0' . substr($waNumber, 2) : htmlspecialchars($waNumber) ?></a> atau kunjungi toko kami secara langsung. Konsultasi gratis tanpa kewajiban memesan.</p>
            </div>
        </details>
    </div>

    <div class="faq-cta">
        <p>Masih ada pertanyaan lain? Tim kami siap membantu.</p>
        <a href="https://wa.me/<?= htmlspecialchars($waNumber) ?>" target="_blank"><i class="fab fa-whatsapp"></i> Chat WhatsApp Sekarang</a>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>