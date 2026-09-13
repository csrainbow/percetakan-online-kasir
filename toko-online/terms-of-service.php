<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Syarat & Ketentuan';
include __DIR__ . '/includes/header.php';
?>
<style>
.page-body h2 { font-size: 17px; margin: 28px 0 10px; color: var(--primary); display: flex; align-items: center; gap: 8px; }
.page-body h2 i { color: #00c2d1; }
.page-body p, .page-body li { font-size: 14px; color: #444; line-height: 1.8; }
.page-body ul { padding-left: 22px; margin: 8px 0 16px; }
.page-body li { margin-bottom: 6px; }
.page-body .updated { color: #999; font-size: 12px; }
</style>
<div class="page-content">
    <h1>📜 Syarat & Ketentuan</h1>
    <p class="updated">Terakhir diperbarui: <?= date('d M Y') ?></p>

    <div class="page-body">
        <p>Dengan memesan produk/jasa di Percetakan Rainbow, Anda dianggap telah membaca, memahami, dan menyetujui seluruh syarat & ketentuan berikut. Mohon baca dengan teliti sebelum melakukan pemesanan.</p>

        <h2><i class="fas fa-shopping-cart"></i> 1. Pemesanan</h2>
        <p>Pesanan dianggap sah setelah data pemesanan diterima dan (bila wajib) bukti/desain file telah diunggah. Kode pesanan yang kami berikan harus disimpan sebagai bukti komunikasi pesanan.</p>
        <ul>
            <li>Setiap pesanan memiliki kode unik untuk pengecekan status di halaman <a href="/cek-pesanan.php">Cek Pesanan</a>.</li>
            <li>Spesifikasi (ukuran, bahan, layanan cetak/desain) yang diisi saat checkout menjadi dasar penawaran harga.</li>
            <li>Pemesanan dianggap batal otomatis jika pembayaran tidak diterima sesuai metode & DP yang berlaku.</li>
        </ul>

        <h2><i class="fas fa-calculator"></i> 2. Harga & Penawaran</h2>
        <p>Harga tercantum dalam Rupiah (Rp) dan dihitung di awal sesuai ukuran, bahan, dan layanan yang dipilih. Harga belum termasuk biaya pengiriman ke luar kota/kecamatan, kecuali dinyatakan lain.</p>
        <ul>
            <li>Untuk kebutuhan khusus (jumlah besar, ukuran non-standar, atau finishing tambahan seperti laminasi, potong khusus), harga final dapat disesuaikan dan dikonfirmasi terlebih dahulu.</li>
            <li>Perbedaan warna minor akibat perangkat monitor atau profil warna tidak termasuk cacat produksi selama masih dalam toleransi standar mesin cetak.</li>
            <li>Kami berhak menolak pesanan yang dokumen/hal kontennya melanggar hukum atau hak pihak lain.</li>
        </ul>

        <h2><i class="fas fa-money-bill-wave"></i> 3. Pembayaran</h2>
        <p>Pembayaran dilakukan melalui transfer bank atau QRIS sesuai metode yang tersedia:</p>
        <ul>
            <li><strong>DP 50%</strong> dari total pesanan untuk memulai produksi; sisa pelunasan dibayarkan sebelum barang dikirim/diambil.</li>
            <li><strong>Transfer penuh</strong> sekaligus juga dapat dilakukan dan langsung mempercepat proses produksi.</li>
            <li>Setiap pesanan mendapat <strong>nominal unik</strong> (total + kode, contoh Rp 1.000.437). Bayar sesuai nominal tersebut agar pembayaran otomatis terkonfirmasi (LUNAS).</li>
            <li>Pembayaran dikonfirmasi setelah dana benar-benar masuk ke rekening kami. Simpan bukti transfer untuk rekonsiliasi bila diperlukan.</li>
        </ul>

        <h2><i class="fas fa-file-image"></i> 4. File Desain & Revisi</h2>
        <ul>
            <li>File desain yang diunggah harus beresolusi sesuai (minimum 300 DPI untuk hasil optimal). Kerusakan/pecah pada hasil cetak akibat file berkualitas rendah menjadi tanggung jawab pemesan.</li>
            <li>Jika memilih <strong>Jasa Desain</strong>, revisi wajib maksimal sesuai kesepakatan. Perubahan desain setelah produksi dimulai dapat dikenakan biaya tambahan.</li>
            <li>Kami tidak mengunggah kembali file desain tanpa izin kecuali untuk keperluan produksi. Beri tahu kami bila desain akhir ingin disimpan sebagai arsip.</li>
            <li>Dokumen mencetak yang pernah dipesan tetap menjadi milik pemesan; kami menyimpan salinan hanya untuk keperluan operasional dan arsip internal.</li>
        </ul>

        <h2><i class="fas fa-truck"></i> 5. Produksi & Pengiriman</h2>
        <ul>
            <li>Estimasi pengerjaan 2–5 hari kerja setelah pembayaran/desain final diterima, bergantung pada jenis cetak dan antrean produksi.</li>
            <li>Waktu pengerjaan dapat lebih lama untuk proyek besar; kami informasikan perkiraan jadwal sebelum produksi dimulai.</li>
            <li>Pengambilan langsung (ambil di tempat) dan pengiriman via ekspedisi tersedia. Ongkos kirim ditanggung pemesan sesuai tarif ekspedisi.</li>
            <li>Kerusakan atau kehilangan dalam pengiriman menjadi tanggung jawab pihak ekspedisi; lapor segera dengan foto/video bukti saat barang diterima.</li>
        </ul>

        <h2><i class="fas fa-undo"></i> 6. Pembatalan & Refund</h2>
        <ul>
            <li>Pembatalan dapat dilakukan sebelum proses produksi dimulai. Uang yang sudah dibayarkan dikembalikan setelah dikurangi biaya desain yang telah dikerjakan dan biaya administrasi.</li>
            <li>Setelah produksi dimulai, pembatalan tidak dapat dilakukan; hasil cetak tetap menjadi milik pemesan sesuai pembayaran yang diterima.</li>
            <li>Refund diproses maksimal 14 hari kerja setelah kesepakatan, via transfer ke rekening yang digunakan pemesan.</li>
        </ul>

        <h2><i class="fas fa-headset"></i> 7. Layanan Pelanggan</h2>
        <p>Pertanyaan, keluhan, atau permintaan perubahan pesanan dapat disampaikan melalui:</p>
        <ul>
            <li>Halaman <a href="/customer-service.php">Kontak & Layanan Pelanggan</a></li>
            <li>Telepon/WhatsApp: <?= htmlspecialchars(getSetting('store_phone') ?: '-') ?></li>
            <li>Email: <?= htmlspecialchars(getSetting('admin_email') ?: '-') ?></li>
        </ul>

        <h2><i class="fas fa-gavel"></i> 8. Perubahan Syarat</h2>
        <p>Kami berhak memperbarui syarat & ketentuan ini sewaktu-waktu. Perubahan berlaku sejak dipublikasikan di halaman ini dan berlaku untuk pemesanan berikutnya.</p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>