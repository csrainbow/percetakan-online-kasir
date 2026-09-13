<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Kebijakan Privasi';

$waNumber = getSetting('whatsapp_number') ?: '6282252569185';
$adminEmail = getSetting('admin_email') ?: 'csrainbowprinting@gmail.com';
include __DIR__ . '/includes/header.php';
?>
<style>
.page-body .note {
    background: #f7f9fc;
    border-left: 4px solid #00c2d1;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    color: #555;
}
.page-body h2 { display: flex; align-items: center; gap: 10px; }
.page-body h2 i { color: var(--info); }
.page-body ul { padding-left: 22px; margin: 8px 0 16px; }
.page-body li { margin-bottom: 6px; font-size: 14px; color: #444; line-height: 1.7; }
</style>

<div class="page-content">
    <h1>Kebijakan Privasi</h1>
    <div class="page-body">
        <p><strong>Percetakan Rainbow</strong> sangat menghargai privasi Anda. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, melindungi, dan membagikan data pribadi Anda saat menggunakan website dan layanan kami.</p>

        <h2><i class="fas fa-database"></i> Data yang Kami Kumpulkan</h2>
        <p>Saat Anda melakukan pemesanan atau menggunakan layanan kami, kami dapat mengumpulkan data berikut:</p>
        <ul>
            <li><strong>Data identitas:</strong> nama lengkap, nomor telepon/WhatsApp, email, dan alamat pengiriman.</li>
            <li><strong>Data pesanan:</strong> produk, ukuran, bahan, jumlah, total harga, dan riwayat transaksi.</li>
            <li><strong>File desain:</strong> file desain (PDF, AI, CDR, PNG, JPG, dll.) yang Anda unggah untuk diproses.</li>
            <li><strong>Data komunikasi:</strong> catatan percakapan dan konsultasi melalui WhatsApp, email, atau telepon.</li>
            <li><strong>Data perangkat:</strong> data teknis seperti IP address, jenis browser, dan halaman yang dikunjungi, hanya untuk keperluan statistik dan keamanan website.</li>
        </ul>

        <h2><i class="fas fa-cogs"></i> Penggunaan Data</h2>
        <p>Data Anda kami gunakan untuk:</p>
        <ul>
            <li>Memproses, memverifikasi, dan mengelola pesanan Anda.</li>
            <li>Menghitung harga dan membuat invoice/struk pembayaran.</li>
            <li>Mengirim notifikasi status pesanan dan konfirmasi via WhatsApp/email.</li>
            <li>Menghubungkan pesanan Anda dengan layanan pengiriman/ekspedisi.</li>
            <li>Memberikan layanan pelanggan dan menindaklanjuti keluhan.</li>
            <li>Meningkatkan kualitas produk dan layanan kami.</li>
        </ul>

        <h2><i class="fas fa-shield-alt"></i> Perlindungan &amp; Penyimpanan Data</h2>
        <ul>
            <li>Data pribadi Anda disimpan di server yang aman dengan akses terbatas.</li>
            <li>Kata sandi akun disimpan dalam bentuk terenkripsi (hash).</li>
            <li>Kami hanya mengakses data Anda seperlunya untuk keperluan operasional.</li>
            <li>File desain yang Anda unggah hanya digunakan untuk keperluan pengerjaan pesanan Anda.</li>
        </ul>

        <h2><i class="fas fa-share-alt"></i> Pembagian Data</h2>
        <p>Kami <strong>tidak menjual, menyewakan, atau memperdagangkan</strong> data pribadi Anda kepada pihak mana pun. Data hanya dapat dibagikan dalam kondisi berikut:</p>
        <ul>
            <li><strong>Layanan pengiriman:</strong> alamat dan nomor telepon diteruskan ke ekspedisi semata-mata untuk mengirim pesanan Anda.</li>
            <li><strong>Kewajiban hukum:</strong> jika diminta oleh instansi berwenang sesuai peraturan perundang-undangan yang berlaku.</li>
        </ul>

        <h2><i class="fas fa-user-check"></i> Hak Anda</h2>
        <p>Anda berhak untuk:</p>
        <ul>
            <li>Meminta akses terhadap data pribadi yang kami simpan.</li>
            <li>Meminta koreksi jika data Anda tidak akurat.</li>
            <li>Meminta penghapusan data, kecuali data yang wajib kami simpan karena alasan akuntansi atau hukum (misalnya riwayat transaksi).</li>
            <li>Menarik persetujuan penggunaan data untuk keperluan komunikasi pemasaran.</li>
        </ul>

        <h2><i class="fas fa-clock"></i> Lama Penyimpanan</h2>
        <p>Data pesanan dan transaksi disimpan selama diperlukan untuk keperluan layanan, akuntansi, dan perpajakan sesuai peraturan yang berlaku. Data yang tidak lagi diperlukan akan dihapus atau dianonimkan.</p>

        <h2><i class="fas fa-cookie-bite"></i> Cookies</h2>
        <p>Website kami menggunakan cookie untuk menyimpan keranjang belanja dan sesi login agar pengalaman berbelanja Anda lebih mudah. Kami tidak menggunakan cookie untuk pelacakan pihak ketiga.</p>

        <h2><i class="fas fa-sync-alt"></i> Perubahan Kebijakan</h2>
        <p>Kami dapat memperbarui kebijakan privasi ini sewaktu-waktu. Perubahan akan diumumkan melalui halaman ini, dan kebijakan terbaru berlaku segera setelah diunggah.</p>

        <h2><i class="fas fa-envelope"></i> Kontak</h2>
        <p>Jika Anda memiliki pertanyaan, keluhan, atau permintaan seputar kebijakan privasi, silakan hubungi:</p>
        <p>
            <strong>Percetakan Rainbow</strong><br>
            Email: <a href="mailto:<?= htmlspecialchars($adminEmail) ?>"><?= htmlspecialchars($adminEmail) ?></a><br>
            WhatsApp: <a href="https://wa.me/<?= htmlspecialchars($waNumber) ?>" target="_blank"><?= htmlspecialchars($waNumber) ?></a>
        </p>

        <p class="note">Dengan menggunakan website dan layanan Percetakan Rainbow, Anda dianggap telah membaca dan menyetujui kebijakan privasi ini.</p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>