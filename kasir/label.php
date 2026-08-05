<?php
require_once __DIR__ . '/config.php';
require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ids = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? ''))));
$mode = $_GET['m'] ?? 'a4-24';
if (!in_array($mode, ['a4-24', 'a4-12', 'thermal'])) {
    $mode = 'a4-24';
}
$qty = max(1, min(500, (int)($_GET['qty'] ?? 1)));
$size = $_GET['size'] ?? '48x40';
if (!preg_match('/^\d{2,3}x\d{2,3}$/', $size)) {
    $size = '58x40';
}
$sizeKey = preg_replace('/\W/', '', $size);
[$lw, $lh] = array_map('intval', explode('x', $size));
$lw = min($lw, 48);
$showKode = false;
$isWebview = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], '; wv') !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lbl_layout'])) {
    $raw = $_POST['lbl_layout'];
    if ($raw === 'RESET') {
        set_setting('lbl_layout_' . $sizeKey, '');
    } else {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $clean = [];
            foreach (['toko', 'nama', 'harga', 'bc', 'kode'] as $el) {
                if (!isset($json[$el]) || !is_array($json[$el])) { continue; }
                $e2 = [];
                foreach (['x', 'y', 'w', 'h', 'fs'] as $k2) {
                    $e2[$k2] = max(0, round((float)($json[$el][$k2] ?? 0), 2));
                }
                $e2['hide'] = !empty($json[$el]['hide']) ? 1 : 0;
                $clean[$el] = $e2;
            }
            $txt = [];
            foreach (['toko', 'nama', 'harga', 'kode'] as $tk) {
                $v = trim(strip_tags((string)($json['txt'][$tk] ?? '')));
                if ($v !== '') { $txt[$tk] = mb_substr($v, 0, 80); }
            }
            if ($txt) { $clean['txt'] = $txt; }
            set_setting('lbl_layout_' . $sizeKey, json_encode($clean));
        }
    }
    header('Location: label.php?' . ($_SERVER['QUERY_STRING'] ?? ''));
    exit;
}
$lblLayout = json_decode(setting('lbl_layout_' . $sizeKey, ''), true);
if (!is_array($lblLayout)) { $lblLayout = []; }
$lblTxt = $lblLayout['txt'] ?? [];

$produk = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $produk = DB::q("SELECT id, kode, nama, barcode, harga_jual, satuan FROM produk WHERE id IN ($ph) ORDER BY id", $ids);
}
if (!$produk) {
    $judul = 'Label Harga';
    require __DIR__ . '/layout/header.php';
    echo '<h2>Label Harga Barcode</h2><p class="muted">Tidak ada produk dipilih. Pilih produk dari menu <a href="index.php?p=produk">Produk &amp; Stok</a> lalu klik "Label".</p>';
    require __DIR__ . '/layout/footer.php';
    exit;
}
$namaToko = setting('nama_toko', APP_NAME);
$judul = 'Label Harga';
require __DIR__ . '/layout/header.php';
?>
<h2>Label Harga Barcode</h2>

<div class="panel">
    <form method="get" class="form-row">
        <input type="hidden" name="ids" value="<?= e(implode(',', $ids)) ?>">
        <label>Mode
            <select name="m">
                <option value="a4-24" <?= $mode === 'a4-24' ? 'selected' : '' ?>>A4 - 24 label (40x35mm)</option>
                <option value="a4-12" <?= $mode === 'a4-12' ? 'selected' : '' ?>>A4 - 12 label (65x45mm)</option>
                <option value="thermal" <?= $mode === 'thermal' ? 'selected' : '' ?>>Printer Thermal (1 label/lembar)</option>
            </select>
        </label>
        <label>Jumlah per produk
            <input type="number" name="qty" min="1" max="500" value="<?= $qty ?>">
        </label>
        <?php if ($mode === 'thermal'): ?>
            <label>Ukuran Label Thermal
                <select name="size">
                    <?php foreach (['33x15', '40x30', '48x30', '48x40', '50x30', '58x40', '60x40', '70x50'] as $sz): ?>
                        <option value="<?= $sz ?>" <?= $size === $sz ? 'selected' : '' ?>><?= $sz ?> mm</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="muted kecil">Jadi: <?= $lw ?> x <?= $lh ?> mm</span>
        <?php endif; ?>
        <button type="submit" class="btn abu">Terapkan</button>
        <button type="button" onclick="cetakNota()" class="btn">🖨️ Cetak Label</button>
    </form>
    <p class="muted kecil">Produk: <?= implode(', ', array_map(function ($p) { return e($p['nama']); }, $produk)) ?></p>
    <?php if ($mode === 'thermal' && !$isWebview): ?>
    <div class="no-print form-row" id="lbl-edit-bar" style="margin-top:6px">
        <button type="button" class="btn abu" id="btn-edit-layout">✏️ Atur Posisi (Drag &amp; Resize)</button>
        <button type="button" class="btn" id="btn-save-layout" style="display:none">💾 Simpan Layout</button>
        <button type="button" class="btn abu" id="btn-batal-layout" style="display:none">Batal</button>
        <button type="button" class="btn abu" id="btn-reset-layout" style="display:none">↩️ Reset ke Otomatis</button>
        <span class="muted kecil" id="lbl-edit-hint" style="display:none">Tarik objek untuk memindah; kotak biru pojok kanan-bawah untuk ukuran; dobel-klik / ✎ untuk ubah teks; ✕ untuk sembunyikan.</span>
        <span class="form-row" id="lbl-hidden-row" style="display:none"></span>
    </div>
    <form method="post" id="lbl-layout-form">
        <input type="hidden" name="ids" value="<?= e(implode(',', $ids)) ?>">
        <input type="hidden" name="m" value="<?= e($mode) ?>">
        <input type="hidden" name="qty" value="<?= (int)$qty ?>">
        <input type="hidden" name="size" value="<?= e($size) ?>">
        <input type="hidden" name="lbl_layout" id="lbl-layout-data" value="">
    </form>
    <?php endif; ?>
</div>

<?php if ($mode === 'thermal'): ?>
<style>
    @media print {
        @page { size: 48mm auto; margin: 0; }
        html, body { margin: 0 !important; padding: 0 !important; }
        .foot { display: none !important; }
        body.printing-thermal { background: #fff; }
        .topbar, h2, .panel, .lbl-toolbar, .no-print { display: none !important; }
        .lbl-thermal { border: none; box-shadow: none; margin: 0; padding: 0; }
        .lbl-cell { width: <?= min($lw, 48) ?>mm; height: <?= $lh ?>mm; }
    }
    .lbl-cell { width: <?= $lw ?>mm; height: <?= $lh ?>mm; border: 1px dashed #999; }
    .lbl-thermal .lbl-cell { overflow: hidden; }
<?php if ($lh <= 32): ?>
    .lbl-thermal .lbl-inner { padding: 1mm; }
    .lbl-thermal .lbl-toko { font-size: 7px; }
    .lbl-thermal .lbl-nama { font-size: 9px; line-height: 1.1; }
    .lbl-thermal .lbl-harga { font-size: 13px; }
    .lbl-thermal .lbl-bc { height: 8mm; max-height: 8mm; margin: 0.5mm auto; }
<?php endif; ?>
<?php if ($lblLayout): ?>
    .lbl-thermal.lbl-custom .lbl-inner { position: relative !important; display: block !important; padding: 0 !important; text-align: center; }
<?php foreach (['toko' => 'lbl-toko', 'nama' => 'lbl-nama', 'harga' => 'lbl-harga', 'bc' => 'lbl-bc', 'kode' => 'lbl-kode'] as $lk => $lcls): ?>
<?php $LL = $lblLayout[$lk] ?? null; if ($LL): ?>
    .lbl-thermal.lbl-custom .<?= $lcls ?> { position: absolute !important; left: <?= $LL['x'] ?>mm !important; top: <?= $LL['y'] ?>mm !important; width: <?= $LL['w'] ?>mm !important; height: <?= $LL['h'] ?>mm !important; margin: 0 !important; overflow: hidden; text-align: center; display: <?= !empty($LL['hide']) || ($lk === 'kode' && !$showKode) ? 'none' : 'block' ?> !important; <?= $lk === 'bc' ? 'object-fit: contain;' : 'font-size: ' . $LL['fs'] . 'mm;' ?> }
<?php endif; endforeach; ?>
<?php endif; ?>
    .lbl-thermal.lbl-edit { transform: scale(2.5); transform-origin: top left; }
    .lbl-thermal.lbl-edit .lbl-cell { box-shadow: 0 0 0 2px #2563eb; }
    .lbl-thermal.lbl-edit .lbl-inner { position: relative !important; display: block !important; padding: 0 !important; text-align: center; }
    .lbl-thermal.lbl-edit .lbl-toko, .lbl-thermal.lbl-edit .lbl-nama, .lbl-thermal.lbl-edit .lbl-harga, .lbl-thermal.lbl-edit .lbl-bc, .lbl-thermal.lbl-edit .lbl-kode { position: absolute !important; display: block !important; margin: 0 !important; cursor: move; touch-action: none; box-shadow: 0 0 0 1px #2563eb; background: rgba(37, 99, 235, 0.07); z-index: 3; }
    .lbl-thermal.lbl-edit .lbl-el-handle { position: absolute; width: 9px; height: 9px; background: #2563eb; border: 1px solid #fff; cursor: nwse-resize; z-index: 5; }
    .lbl-thermal.lbl-edit .lbl-el-editbtn, .lbl-thermal.lbl-edit .lbl-el-delbtn { position: absolute; width: 12px; height: 12px; line-height: 11px; text-align: center; font-size: 10px; color: #fff; border: 1px solid #fff; border-radius: 2px; cursor: pointer; z-index: 6; user-select: none; }
    .lbl-thermal.lbl-edit .lbl-el-editbtn { background: #2563eb; }
    .lbl-thermal.lbl-edit .lbl-el-delbtn { background: #dc2626; }
</style>
<div class="lbl-thermal<?= $lblLayout ? ' lbl-custom' : '' ?>" data-w="<?= $lw ?>" data-h="<?= $lh ?>" data-gap="2" data-layout="<?= e(json_encode($lblLayout)) ?>">
    <?php foreach ($produk as $p): for ($i = 0; $i < $qty; $i++): ?>
        <div class="lbl-cell<?= $lh <= 20 ? ' lbl-small' : '' ?><?= $lh <= 20 && strlen($p['barcode'] ?: $p['kode']) > 12 ? ' lbl-long' : '' ?>">
            <div class="lbl-inner">
                <div class="lbl-info">
                    <div class="lbl-toko"><?= e($lblTxt['toko'] ?? '') ?: e($namaToko) ?></div>
                    <div class="lbl-nama"><?= e($lblTxt['nama'] ?? '') ?: e($p['nama']) ?></div>
                    <div class="lbl-harga"><?= e($lblTxt['harga'] ?? '') ?: rp($p['harga_jual']) ?></div>
                </div>
                <img class="lbl-bc" src="<?= e(barcode_src($p['barcode'] ?: $p['kode'])) ?>" alt="barcode">
                <div class="lbl-kode"<?= $showKode ? '' : ' style="display:none"' ?>><?= e($lblTxt['kode'] ?? '') ?: e($p['barcode'] ?: $p['kode']) ?></div>
            </div>
        </div>
    <?php endfor; endforeach; ?>
</div>
<?php else: ?>
<style>
    @media print {
        @page { size: A4 portrait; margin: 5mm; }
        .topbar, h2, .panel, .lbl-toolbar, .no-print, .foot { display: none !important; }
        .lbl-sheet { box-shadow: none; margin: 0; }
        .lbl-cell { break-inside: avoid; }
    }
    .lbl-sheet {
        width: 200mm;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(<?= $mode === 'a4-24' ? 3 : 2 ?>, 1fr);
        gap: 1mm;
        background: #fff;
    }
    .lbl-cell {
        border: 1px dashed #999;
        height: <?= $mode === 'a4-24' ? '34mm' : '45mm' ?>;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
<div class="lbl-sheet">
    <?php for ($i = 0; $i < $qty; $i++): foreach ($produk as $p): ?>
        <div class="lbl-cell">
            <div class="lbl-inner">
                <div class="lbl-info">
                    <div class="lbl-toko"><?= e($namaToko) ?></div>
                    <div class="lbl-nama"><?= e($p['nama']) ?></div>
                    <div class="lbl-harga"><?= rp($p['harga_jual']) ?></div>
                </div>
                <img class="lbl-bc" src="<?= e(barcode_src($p['barcode'] ?: $p['kode'])) ?>" alt="barcode">
                <div class="lbl-kode"<?= $showKode ? '' : ' style="display:none"' ?>><?= e($p['barcode'] ?: $p['kode']) ?></div>
            </div>
        </div>
    <?php endforeach; endfor; ?>
</div>
<?php endif; ?>

<style>
    .lbl-inner { text-align: center; padding: 2mm; width: 100%; }
    .lbl-toko { font-size: 8px; color: #666; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lbl-nama { font-size: 11px; font-weight: bold; line-height: 1.15; margin: 1px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .lbl-harga { font-size: 17px; font-weight: 900; color: #b91c1c; margin: 2px 0; white-space: nowrap; }
    .lbl-bc { width: 48mm; max-width: 90%; height: 14mm; object-fit: contain; margin: 1mm auto; display: block; }
    .lbl-kode { font-size: 8px; color: #444; letter-spacing: 1px; }
    .lbl-cell.thermal .lbl-harga { font-size: 22px; }
    .lbl-cell.lbl-small .lbl-inner { display: flex; flex-direction: row; align-items: center; gap: 1mm; padding: 0.5mm; text-align: left; }
    .lbl-cell.lbl-small .lbl-bc { order: 1; flex: 0 0 auto; width: 14mm; height: 12mm; margin: 0; }
    .lbl-cell.lbl-small .lbl-info { order: 2; flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; }
    .lbl-cell.lbl-small .lbl-toko { font-size: 6px; margin: 0; }
    .lbl-cell.lbl-small .lbl-nama { font-size: 8px; -webkit-line-clamp: 2; margin: 0; }
    .lbl-cell.lbl-small .lbl-harga { font-size: 12px; margin: 0; }
    .lbl-cell.lbl-small .lbl-kode { display: none; }
    .lbl-cell.lbl-small.lbl-long .lbl-inner { flex-direction: column; align-items: center; gap: 0.5mm; padding: 0.5mm; text-align: center; }
    .lbl-cell.lbl-small.lbl-long .lbl-bc { order: 1; flex: none; width: 100%; max-width: 31mm; height: 6.5mm; margin: 0; }
    .lbl-cell.lbl-small.lbl-long .lbl-info { order: 2; flex: none; width: 100%; align-items: center; }
    .lbl-cell.lbl-small.lbl-long .lbl-toko { display: none; }
    .lbl-cell.lbl-small.lbl-long .lbl-nama { font-size: 7px; -webkit-line-clamp: 1; }
    .lbl-cell.lbl-small.lbl-long .lbl-harga { font-size: 9px; }
    .lbl-cell.lbl-small.lbl-long .lbl-kode { display: none; }
</style>
<script src="assets/print.js"></script>
<script>
if (location.search.includes('auto=1')) {
    setTimeout(function () { cetakNota(); }, 400);
}
</script>
<?php if (!$isWebview): ?>
<script src="assets/label_edit.js?v=3"></script>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
