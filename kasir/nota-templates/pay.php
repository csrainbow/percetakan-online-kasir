<?php
// ============================================================================
//  PAYMENT POINT — halaman pembayaran publik per pesanan
//  Route: /n.php/{ref}/{id}/pay/{token}
//  - Ringkasan pesanan (produk yang belum dibayarkan)
//  - Pilihan metode pembayaran: QRIS statis & Transfer Bank
//  - Apabila costumer memilih QRIS → tampil QRIS statis + jumlah yang harus
//    dibayar (tagihan + kode unik) + kode unik
// ============================================================================
$ppRef = $ref ?? 'pesanan';
$ppId  = (int)$id;
$ppKode = nota_kode_unik($ppRef, $ppId);
if ($ppRef === 'penjualan') {
    $ppSisa = ((string)($ps['status'] ?? '') === 'Menunggu QRIS') ? max(0, (float)$ps['total']) : 0.0;
} else {
    $ppSisa = max(0, (float)($ps['sisa'] ?? 0));
}
$ppTotal       = $ppSisa > 0 ? $ppSisa + (float)$ppKode : 0.0;
$ppQris        = setting('qris_image');
$ppBankNama    = setting('bank_nama', '');
$ppBankRek     = setting('bank_rekening', '');
$ppBankPemilik = setting('bank_pemilik', '');
$ppTelp        = setting('telp');
$ppWa = '';
if ($ppTelp !== '') {
    $ppWaMsg = 'Halo ' . setting('nama_toko', 'Percetakan Rainbow') . ', saya ' . $ps['pelanggan']
        . '. Saya sudah membayar pesanan ' . $ps['no_pesanan'] . ' sebesar '
        . rp($ppTotal > 0 ? $ppTotal : $ps['total'])
        . ($ppKode !== '' ? ' (kode unik ' . $ppKode . ').' : '.')
        . ' Mohon konfirmasi pembayaran.';
    $ppWa = wa_href($ppTelp, $ppWaMsg);
}
$ppBase = rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/') . '/';
$ppStrukUrl = $ppBase . 'n.php/' . rawurlencode($ppRef) . '/' . $ppId . '/struk/' . nota_token($ppRef, $ppId);
$ppStatus = in_array($ps['status'], ['Selesai', 'Batal']) ? $ps['status'] : ($ps['pembayaran_status'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<base href="<?= e($ppBase) ?>">
<title>Pembayaran <?= e($ps['no_pesanan']) ?> — <?= e(setting('nama_toko')) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:Arial,Helvetica,sans-serif; }
body { background:linear-gradient(160deg,#eef2ff,#f8fafc 60%,#f1f5f9); min-height:100vh; }
.pp-card { max-width:520px; margin:18px auto; background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 8px 24px rgba(15,23,42,.12); overflow:hidden; }
.pp-head { background:linear-gradient(135deg,#0f172a,#1e3a8a); color:#fff; padding:14px 18px; }
.pp-head img { max-height:44px; max-width:120px; object-fit:contain; display:block; margin:0 auto 6px; background:rgba(255,255,255,.14); border-radius:8px; padding:4px 8px; }
.pp-head h2 { font-size:16px; text-align:center; }
.pp-head p { font-size:11px; text-align:center; opacity:.85; }
.pp-badges { display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:6px; }
.pp-badge { background:rgba(255,255,255,.16); padding:2px 10px; border-radius:999px; font-size:10px; }
.pp-sec { padding:12px 16px; }
.pp-sec h3 { font-size:13px; color:#0f172a; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #cbd5e1; padding-bottom:6px; margin-bottom:10px; }
.pp-meta { display:grid; grid-template-columns:auto 1fr; gap:4px 10px; font-size:12px; color:#475569; }
.pp-meta b { text-align:right; color:#0f172a; }
table.pp-items { width:100%; border-collapse:collapse; font-size:12px; }
.pp-items th { text-align:left; color:#64748b; font-size:10px; text-transform:uppercase; padding:4px 6px; border-bottom:1px solid #e2e8f0; }
.pp-items td { padding:5px 6px; border-bottom:1px solid #eef2f7; }
.pp-items .r { text-align:right; }
.pp-totals { width:100%; border-collapse:collapse; margin-top:8px; font-size:12px; }
.pp-totals td { padding:4px 6px; border-bottom:1px solid #e2e8f0; }
.pp-totals .r { text-align:right; }
.pp-totals .sisa td { background:#eef2ff; font-weight:800; font-size:14px; }
.pp-lunas { background:#dcfce7; border:1px solid #86efac; color:#166534; border-radius:10px; padding:12px 14px; font-size:14px; }
.pp-methods { display:flex; gap:10px; }
.pp-method { flex:1; border:2px solid #e2e8f0; border-radius:12px; padding:12px 10px; background:#f8fafc; cursor:pointer; text-align:center; font-size:13px; font-weight:700; color:#475569; transition:all .15s; }
.pp-method .ico { font-size:26px; margin:0 auto 6px; display:block; }
.pp-method.act { border-color:#0f172a; background:#0f172a; color:#fff; box-shadow:0 4px 10px rgba(15,23,42,.18); }
#ppQrisPanel, #ppBankPanel { display:none; }
#ppQrisPanel.act, #ppBankPanel.act { display:block; }
.pp-qris-img { max-width:200px; margin:8px auto; display:block; border:1px solid #cbd5e1; border-radius:10px; }
.pp-amount { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin:10px 0 4px; }
.pp-amount .lb { font-size:12px; color:#475569; }
.pp-amount .val { font-size:22px; font-weight:800; color:#fff; background:#0f172a; padding:4px 16px; border-radius:8px; }
.pp-kode { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:8px 0 4px; }
.pp-kode .lb { font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:.4px; }
.pp-kode .code { font-size:26px; font-weight:900; background:#facc15; color:#0f172a; padding:4px 16px; border-radius:10px; }
.pp-btn { padding:8px 14px; border:1px solid #0f172a; border-radius:8px; background:#0f172a; color:#fff; cursor:pointer; font-size:12px; }
.pp-note { margin:6px 0; font-size:12px; color:#475569; line-height:1.55; }
.pp-wa { display:block; width:100%; text-align:center; background:#25D366; color:#fff; padding:12px 16px; border-radius:10px; text-decoration:none; font-size:14px; font-weight:700; }
.pp-keluar { display:block; width:100%; text-align:center; border:1px solid #cbd5e1; color:#475569; background:#fff; padding:10px 16px; border-radius:10px; text-decoration:none; font-size:13px; margin-top:8px; cursor:pointer; }
.pp-foot { text-align:center; font-size:11px; color:#94a3b8; padding:14px; }
@media print { .pp-no-print { display:none !important; } body { background:#fff; } }
</style>
</head>
<body>
<div class="pp-card">
    <div class="pp-head">
        <?php $pl = setting('logo_struk', 'assets/logo.png'); if ($pl): ?>
            <?php $plv = is_file(__DIR__ . '/../' . $pl) ? @filemtime(__DIR__ . '/../' . $pl) : 0; ?>
            <img src="<?= e($pl) ?>?v=<?= $plv ?>" alt="Logo">
        <?php endif; ?>
        <h2><?= e(setting('nama_toko')) ?></h2>
        <p><?= nl2br(e(setting('alamat'))) ?> · Telp: <?= e(setting('telp')) ?></p>
        <div class="pp-badges">
            <span class="pp-badge"># <?= e($ps['no_pesanan']) ?></span>
            <span class="pp-badge"><?= e($ppStatus) ?></span>
        </div>
    </div>

    <div class="pp-sec">
        <h3>📋 Ringkasan Pesanan</h3>
        <div class="pp-meta">
            <span>Pelanggan</span><b><?= e($ps['pelanggan']) ?></b>
            <span>Tanggal</span><b><?= tgl($ps['tgl']) ?></b>
            <?php if ($ps['telepon']): ?>
                <span>Telepon</span><b><?= e($ps['telepon']) ?></b>
            <?php endif; ?>
            <span>Kasir</span><b><?= e($user['username'] ?? '-') ?></b>
        </div>
    </div>

    <div class="pp-sec">
        <h3>🛍️ Produk yang Belum Dibayarkan</h3>
        <table class="pp-items">
            <thead><tr><th>Produk</th><th style="text-align:center;">Qty</th><th class="r">Harga</th><th class="r">Total</th></tr></thead>
            <tbody>
            <?php $ppNo = 1; foreach ($viewItems as $it): ?>
                <tr>
                    <td><?= e($it['nama']) ?><?php if (!empty($it['note'])): ?><?php foreach ($it['note'] as $nt): ?><br><small style="color:#94a3b8"><?= e($nt) ?></small><?php endforeach; ?><?php endif; ?></td>
                    <td style="text-align:center;"><?= e($it['qty']) ?></td>
                    <td class="r"><?= e($it['harga']) ?></td>
                    <td class="r"><?= e($it['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table class="pp-totals">
            <tr><td>Total Pesanan</td><td class="r"><?= rp($ps['total']) ?></td></tr>
            <tr><td>Sudah Dibayar</td><td class="r"><?= rp($totalBayar) ?></td></tr>
            <tr class="sisa"><td>Sisa Tagihan</td><td class="r"><?= rp($ppSisa) ?></td></tr>
        </table>
    </div>
<?php if ($ppSisa > 0): ?>
    <div class="pp-sec">
        <h3>💳 Pilihan Pembayaran</h3>
        <div class="pp-methods">
            <?php if ($ppQris): ?>
            <div class="pp-method<?= $ppQris ? ' act' : '' ?>" id="ppM-qris" onclick="ppSel('qris')">
                <span class="ico">🟢</span>QRIS
            </div>
            <?php endif; ?>
            <?php if ($ppBankRek): ?>
            <div class="pp-method<?= $ppQris ? '' : ' act' ?>" id="ppM-bank" onclick="ppSel('bank')">
                <span class="ico">🏦</span>Transfer Bank
            </div>
            <?php endif; ?>
        </div>

        <?php if ($ppQris): ?>
        <div id="ppQrisPanel" class="<?= $ppQris ? 'act' : '' ?>">
            <p class="pp-note">Scan <b>QRIS statis</b> dengan aplikasi banka / e-wallet, lalu bayar nilai di bawah.</p>
            <img class="pp-qris-img" src="<?= e($ppQris) ?>" alt="QRIS Statis">
            <div class="pp-amount">
                <span class="lb">Total yang harus dibayar</span>
                <span class="val"><?= rp($ppTotal) ?></span>
            </div>
            <div class="pp-kode">
                <span class="lb">Kode Unik</span>
                <span class="code" id="ppKodeQ"><?= e($ppKode) ?></span>
                <button type="button" class="pp-btn" onclick="ppSalin(this,'<?= e($ppKode) ?>')">Salin Kode</button>
            </div>
            <p class="pp-note">Nilai <b><?= rp($ppTotal) ?></b> = Tagihan <b><?= rp($ppSisa) ?></b> + Kode Unik <b><?= e($ppKode) ?></b>.</p>
            <button type="button" class="pp-btn" onclick="ppSalin(this,<?= (int)$ppTotal ?>)">Salin Nilai</button>
        </div>
        <?php endif; ?>

        <?php if ($ppBankRek): ?>
        <div id="ppBankPanel" class="<?= $ppQris ? '' : 'act' ?>">
            <p class="pp-note">Transfer ke bank di bawah, lalu cantumkan <b>kode unik</b> pada keterangan/berita transfer agar pembayaran mudah direkognisi. Pastikan nomor rekening dicek ulang sebelum transfer.</p>
            <div class="pp-meta" style="grid-template-columns:auto 1fr;">
                <span>Bank</span><b><?= e($ppBankNama ?: '-') ?></b>
                <span>No. Rekening</span><b><?= e($ppBankRek) ?></b>
                <span>Atas Nama</span><b><?= e($ppBankPemilik ?: '-') ?></b>
            </div>
            <div class="pp-amount">
                <span class="lb">Total yang harus dibayar</span>
                <span class="val"><?= rp($ppTotal) ?></span>
            </div>
            <div class="pp-kode">
                <span class="lb">Kode Unik</span>
                <span class="code" id="ppKodeB"><?= e($ppKode) ?></span>
                <button type="button" class="pp-btn" onclick="ppSalin(this,'<?= e($ppKode) ?>')">Salin Kode</button>
            </div>
            <p class="pp-note">Cantumkan kode unik <b><?= e($ppKode) ?></b> pada keterangan/berita transfer agar pembayaran mudah direkognisi.</p>
            <button type="button" class="pp-btn" onclick="ppSalin(this,'<?= e($ppBankRek) ?>')">Salin No. Rekening</button>
        </div>
        <?php endif; ?>

        <?php if (!$ppQris && !$ppBankRek): ?>
            <p class="pp-lunas">Belum ada metode pembayaran dikonfigurasi. Mohon kontak toko via WhatsApp / telepon.</p>
        <?php endif; ?>
    </div>

    <div class="pp-sec pp-no-print">
        <?php if ($ppWa): ?>
        <p class="pp-note">Setelah transfer, klik di bawah untuk konfirmasi:</p>
        <a class="pp-wa" href="<?= e($ppWa) ?>" target="_blank">💬 Konfirmasi via WhatsApp</a>
        <?php endif; ?>
        <button class="pp-keluar" onclick="keluarNota()">✕ Keluar</button>
    </div>
    <?php else: ?>
    <div class="pp-sec">
        <h3>💳 Pembayaran</h3>
        <p class="pp-lunas">✅ Pesanan <b><?= e($ps['no_pesanan']) ?></b> sudah dibayar lunas — tidak ada sisa tagihan. Kode unik referensi: <b><?= e($ppKode) ?></b></p>
        <a class="pp-wa" style="background:#0f172a" href="<?= e($ppStrukUrl) ?>">📄 Buka Halaman Struk</a>
    </div>
    <div class="pp-sec pp-no-print">
        <button class="pp-keluar" onclick="keluarNota()">✕ Keluar</button>
    </div>
    <?php endif; ?>

    <div class="pp-foot pp-no-print">
        <?= e(setting('footer_struk') ?: 'Terima kasih atas kepercayaan Anda.') ?><br>
        <?= e(setting('nama_toko')) ?> · <?= e(setting('telp')) ?>
    </div>
</div>

<script>
function ppSel(m) {
    var q = document.getElementById('ppQrisPanel');
    var b = document.getElementById('ppBankPanel');
    var mq = document.getElementById('ppM-qris');
    var mb = document.getElementById('ppM-bank');
    if (m === 'qris') {
        if (q) q.className = 'act';
        if (b) b.className = '';
        if (mq) mq.className = 'pp-method act';
        if (mb) mb.className = 'pp-method';
    } else {
        if (q) q.className = '';
        if (b) b.className = 'act';
        if (mq) mq.className = 'pp-method';
        if (mb) mb.className = 'pp-method act';
    }
}
function keluarNota() {
    if (history.length > 1) { history.back(); } else { window.close(); }
}
function ppSalin(btn, txt) {
    var ok = false;
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(txt));
            ok = true;
        }
    } catch (e) { ok = false; }
    if (!ok) {
        var ta = document.createElement('textarea');
        ta.value = String(txt);
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); ok = true; } catch (e) { ok = false; }
        document.body.removeChild(ta);
    }
    var old = btn.innerHTML;
    btn.innerHTML = '✅ Salin';
    setTimeout(function () { btn.innerHTML = old; }, 1400);
}
</script>
</body>
</html>