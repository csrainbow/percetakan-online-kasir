<?php
$sisaAmount = (float)$ps['sisa'];
?>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
.invoice-wrapper { margin:16px auto;background:#fff;padding:6px 4px;border:1px solid #ccc;box-shadow:0 2px 10px rgba(0,0,0,0.08);width:100%;max-width:none; }
.invoice-head { width:100%;border-collapse:collapse;margin-bottom:6px; }
.invoice-head td { vertical-align:top; }
.invoice-head-left { font-size:8px;color:#555; }
.invoice-head-left strong { font-size:11px;color:#2c3e50; }
.invoice-head-right { text-align:right;font-size:8px;color:#555; }
.invoice-head-right .inv-no { font-size:9px;font-weight:bold;color:#2c3e50;margin-top:2px; }
.invoice-head-right p,.invoice-head-left p { font-size:8px!important;margin:0!important;line-height:1.2!important; }
.invoice-mid { width:100%;border-collapse:collapse;margin-bottom:6px;padding:4px 6px;background:#f8f9fa;border-radius:4px;font-size:8px; }
.invoice-mid td { padding:4px; }
.invoice-table { width:100%;border-collapse:collapse;margin-bottom:4px; }
.invoice-table th { background:#2c3e50;color:#fff;padding:3px 4px;text-align:left;font-size:8px; }
.invoice-table td { padding:2px 4px;border-bottom:1px solid #eee;font-size:8px;line-height:1.2; }
.invoice-table .item-note { font-size:7px;color:#999; }
.invoice-bottom { width:100%;border-collapse:collapse;margin-top:4px;font-size:8px; }
.invoice-bottom td { vertical-align:top;padding:0 4px; }
.invoice-bottom-left { font-size:8px;color:#555; }
.invoice-bottom-left ol { margin:2px 0;padding-left:10px;font-size:7px;line-height:1.3; }
.invoice-bottom-left ol li { margin-bottom:1px; }
.invoice-bottom-right { min-width:200px;font-size:8px; }
.invoice-bottom-right table { width:100%;border-collapse:collapse; }
.invoice-bottom-right td { padding:2px 4px;border-bottom:1px solid #ddd;font-size:8px; }
.invoice-bottom-right .total-row td { font-weight:bold;font-size:10px;border-top:2px solid #2c3e50; }
.invoice-sign { width:100%;border-collapse:collapse;margin-top:8px; }
.invoice-sign td { text-align:center;width:50%;font-size:8px; }
.invoice-sign .sign-space { margin-bottom:18px; }
.invoice-footer2 { margin-top:5px;padding-top:4px;border-top:1px solid #ddd;text-align:center;font-size:7px;color:#999; }
@media print {
    @page { size:A5 landscape; margin:3mm; }
    * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>
<div class="invoice-wrapper">
    <table class="invoice-head">
        <tr>
            <td class="invoice-head-left">
                <?php $logoImg = $invoiceLogo ?? setting('logo_image', 'assets/logo-ikky.jpeg'); ?>
                <?php if ($logoImg): ?>
                    <img src="<?= e($logoImg) ?>" alt="Logo" style="float:left;width:90px;height:auto;margin:0 8px 4px 0;">
                <?php endif; ?>
                <strong><?= e(setting('nama_toko')) ?></strong>
                <p><?= nl2br(e(setting('alamat'))) ?></p>
                <p>Telp: <?= e(setting('telp')) ?></p>
                <div class="inv-no" style="margin-top:8px;">Invoice No : <?= e($ps['no_pesanan']) ?></div>
            </td>
            <td class="invoice-head-right">
                <p><?= e(setting('kota')) ?>, <?= tgl_ind($ps['tgl']) ?></p>
                <p><strong>Kepada Yth,</strong></p>
                <p><?= e($ps['pelanggan']) ?></p>
                <?php if ($ps['telepon']): ?>
                <p><?= e($ps['telepon']) ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="invoice-mid">
        <tr>
            <td><strong>No. Pesanan:</strong> <?= e($ps['no_pesanan']) ?></td>
            <td><strong>Kasir:</strong> <?= e($user['username'] ?? '-') ?></td>
            <td><strong>Status Pembayaran:</strong> <?= $ps['pembayaran_status'] === 'Batal' ? 'BATAL' : ($ps['pembayaran_status'] === 'Lunas' ? 'LUNAS' : ($ps['pembayaran_status'] === 'DP' ? 'DP' : 'BELUM BAYAR')) ?></td>
            <?php if ($sisaAmount > 0): ?>
            <td><strong>Sisa:</strong> <?= rp($sisaAmount) ?></td>
            <?php endif; ?>
        </tr>
    </table>

    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width:30px;text-align:center;">No</th>
                <th>Nama Barang</th>
                <th style="width:35px;text-align:center;">Qty</th>
                <th style="width:80px;text-align:right;">Harga</th>
                <th style="width:80px;text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($viewItems as $it): ?>
            <tr>
                <td style="text-align:center;"><?= $no++ ?></td>
                <td>
                    <?= e($it['nama']) ?>
                    <?php if (!empty($it['note'])): ?>
                        <?php foreach ($it['note'] as $nt): ?>
                            <br><span class="item-note"><?= e($nt) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;"><?= $it['qty'] ?></td>
                <td style="text-align:right;"><?= $it['harga'] ?></td>
                <td style="text-align:right;"><?= $it['total'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="invoice-bottom">
        <tr>
            <td class="invoice-bottom-left">
                <ol style="font-size:7px;line-height:1.4;margin:3px 0;padding-left:12px;">
                    <li>Pekerjaan akan diselesaikan dalam waktu kurang lebih 2 hari.</li>
                    <li>Apabila ada Kesalahan Desain/File setelah di cetak dari Customer bukan Tanggung jawab Kami.</li>
                    <li>Pekerjaan dilaksanakan Setelah ada bukti transfer dan kesepakatan Sebelumnya.</li>
                    <li>Apabila ada hal lain yang kurang berkenan silahkan hubungi No. yang ada di website resmi kami.</li>
                    <li>Terima kasih atas kepercayaan Anda.</li>
                </ol>
            </td>
            <td class="invoice-bottom-right">
                <table>
                    <tr><td>Total Pesanan</td><td style="text-align:right;"><?= rp($ps['total']) ?></td></tr>
                    <tr><td>Sudah Dibayar</td><td style="text-align:right;color:#27ae60;"><?= rp($totalBayar) ?></td></tr>
                    <tr class="total-row">
                        <td><strong>Jumlah Pelunasan</strong></td>
                        <td style="text-align:right;<?= $sisaAmount > 0 ? 'color:#e74c3c;' : 'color:#27ae60;' ?>">
                            <strong><?= rp($sisaAmount) ?></strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="invoice-sign">
        <tr>
            <td>
                <p class="sign-space">Penerima,</p>
                <p style="font-weight:bold;"><?= e($ps['pelanggan']) ?></p>
            </td>
            <td>
                <p class="sign-space">Hormat Kami,</p>
                <p style="font-weight:bold;"><?= e(setting('nama_toko')) ?></p>
            </td>
        </tr>
    </table>

    <div class="invoice-footer2">
        <?= e(setting('footer_struk') ?: 'Terima kasih atas kepercayaan Anda.') ?>
    </div>
</div>

