<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nota <?= e($ps['no_pesanan']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial, Helvetica, sans-serif; background:#e8eaed; }
.print-aksi { display:flex;justify-content:flex-end;gap:8px;padding:10px 16px;background:#f8f9fa;border-bottom:1px solid #eee; }
.print-btn,.back-btn { padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px;text-align:center;text-decoration:none;color:#fff; }
.print-btn { background:#2c3e50; }
.print-btn:hover { background:#34495e; }
.print-btn.wa { background:#25D366; }
.print-btn.wa:hover { background:#1ebe5b; }
.back-btn { background:#7f8c8d; }
.back-btn:hover { background:#95a5a6; }
@media print {
    @page { size:A5 landscape; margin:3mm; }
    * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    body { background:#fff; }
    .print-aksi { display:none!important; }
    .invoice-wrapper { border:none;box-shadow:none;margin:0; }
}
</style>
</head>
<body>

<?php
$waHref = '';
if (empty($publik)) {
    if (!empty($ps['telepon'])) {
        $waHref = wa_href($ps['telepon'], "Halo {$ps['pelanggan']}, nota {$ps['no_pesanan']} total " . rp($ps['total']) . " status {$ps['status']}. Mohon konfirmasi pesanan Anda.");
    }
} else {
    $telpToko = setting('telp');
    if ($telpToko !== '') {
        $waHref = wa_href($telpToko, "Halo " . setting('nama_toko') . ", saya {$ps['pelanggan']}. Saya sudah melihat nota {$ps['no_pesanan']} total " . rp($ps['total']) . ". Mohon konfirmasi pesanan saya.");
    }
}
$refNota = $ref ?? 'pesanan';
?>
<div class="print-aksi">
    <?php if (empty($publik)): ?>
    <a href="index.php?p=<?= e($back_page) ?>" class="back-btn">Kembali</a>
    <button onclick="cetakNota()" class="print-btn">🖨️ Cetak / Simpan PDF</button>
    <?php if ($waHref): ?>
        <a href="#" onclick="waKirim(event)" data-ref="<?= e($refNota) ?>" data-id="<?= (int)$ps['id'] ?>" class="print-btn wa">💬 WhatsApp Pelanggan</a>
    <?php endif; ?>
    <?php else: ?>
    <a href="nota-pdf.php?ref=<?= e($ref) ?>&id=<?= (int)$id ?>&k=<?= e($k) ?>" class="print-btn">⬇️ Download PDF</a>
    <?php if ($waHref): ?>
        <a href="<?= e($waHref) ?>" target="_blank" class="print-btn wa">💬 Konfirmasi via WhatsApp</a>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/a5-invoice.php'; ?>

<script src="assets/print.js"></script>
<script>
function waKirim(ev) {
    ev.preventDefault();
    var btn = ev.currentTarget;
    var ref = btn.getAttribute('data-ref');
    var id = btn.getAttribute('data-id');
    var old = btn.textContent;
    btn.textContent = '⏳ Mengirim...';
    var fd = new FormData();
    fd.append('id', id);
    fd.append('ref', ref);
    fetch('wa-send.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) {
                btn.textContent = '✅ Terkirim';
                setTimeout(function () { btn.textContent = old; }, 3000);
            } else if (d.fallback) {
                window.open(d.fallback, '_blank');
                btn.textContent = old;
            } else {
                btn.textContent = old;
                alert(d.msg || 'Gagal mengirim WhatsApp.');
            }
        })
        .catch(function () {
            btn.textContent = old;
            alert('Gagal menghubungi server.');
        });
}
if (location.search.includes('auto=1')) {
    setTimeout(function () { cetakNota(); }, 400);
}
</script>
</body>
</html>
