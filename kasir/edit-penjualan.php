<?php
require_once __DIR__ . '/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$penj = DB::one('SELECT * FROM penjualan WHERE id = ?', [$id]);
if (!$penj || (scope_user_id() !== 0 && (int)$penj['user_id'] !== scope_user_id())) {
    flash_set('error', 'Transaksi tidak ditemukan.');
    header('Location: index.php?p=penjualan');
    exit;
}
$items = DB::q('SELECT * FROM penjualan_item WHERE penjualan_id = ? ORDER BY id', [$id]);
$produk = DB::q('SELECT p.id, p.kode, p.nama, p.satuan, p.harga_jual, p.stok FROM produk p ORDER BY p.nama');

$page = 'penjualan';
$judul = 'Edit Transaksi ' . $penj['no_invoice'];
require __DIR__ . '/layout/header.php';
?>
<script>
window.EDIT_PRODUK = <?= json_encode(array_map(function ($p) {
    return ['id' => (int)$p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'satuan' => $p['satuan'],
        'harga' => (float)$p['harga_jual'], 'stok' => (float)$p['stok']];
}, $produk)) ?>;
window.EDIT_ITEMS = <?= json_encode(array_map(function ($i) {
    return ['id' => (int)$i['produk_id'], 'nama' => $i['nama'], 'harga' => (float)$i['harga'], 'qty' => (float)$i['qty']];
}, $items)) ?>;
</script>
<h2>Edit Transaksi <?= e($penj['no_invoice']) ?></h2>

<form method="post" action="index.php?p=penjualan" id="formEdit">
    <input type="hidden" name="simpan_edit_penjualan" value="1">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="back" value="<?= e($_GET['back'] ?? 'penjualan') ?>">
    <input type="hidden" name="items" id="itemsJson">

    <div class="panel">
        <h3>Item Transaksi</h3>
        <div class="form-row">
            <select id="tambahProduk" style="flex:1;">
                <option value="">- Tambah produk -</option>
                <?php foreach ($produk as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['nama']) ?> (<?= rp($p['harga_jual']) ?>/<?= e($p['satuan']) ?>, stok <?= qty($p['stok']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="number" id="tambahQty" value="1" min="0.01" step="any" style="width:100px;">
            <button type="button" class="btn" id="btnTambah">Tambah</button>
        </div>
        <table>
            <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
            <tbody id="tblItems"></tbody>
        </table>
        <div class="baris-total">Total: <b id="lblTotal">Rp 0</b></div>
    </div>

    <div class="panel">
        <h3>Info Transaksi</h3>
        <div class="form-row">
            <label>Metode
                <select name="metode">
                    <?php foreach (['Tunai', 'QRIS', 'Transfer'] as $m): ?>
                        <option <?= $penj['metode'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Keterangan
                <input type="text" name="keterangan" value="<?= e($penj['keterangan']) ?>" placeholder="opsional">
            </label>
        </div>
        <p class="muted kecil">Uang bayar lama: <?= rp($penj['bayar']) ?>. Bila total baru lebih besar dari uang bayar, uang bayar otomatis disamakan.</p>
        <div class="form-row">
            <a class="btn" href="index.php?p=<?= e($_GET['back'] ?? 'penjualan') ?>">Batal</a>
            <button type="submit" class="btn ok">Simpan Perubahan</button>
        </div>
    </div>
</form>

<script>
(function () {
    var rows = window.EDIT_ITEMS.map(function (i) {
        return { id: i.id, nama: i.nama, harga: i.harga, qty: i.qty };
    });
    var tbody = document.getElementById('tblItems');
    var lblTotal = document.getElementById('lblTotal');
    var itemsJson = document.getElementById('itemsJson');

    function rupiah(n) {
        return 'Rp ' + Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function render() {
        tbody.innerHTML = '';
        rows.forEach(function (r, idx) {
            var tr = document.createElement('tr');
            var sub = r.harga * r.qty;
            tr.innerHTML = '<td>' + (r.nama ? r.nama : '-') + '</td>' +
                '<td><input type="number" step="any" min="0" value="' + r.harga + '" data-i="' + idx + '" class="fHarga"></td>' +
                '<td><input type="number" step="any" min="0.01" value="' + r.qty + '" data-i="' + idx + '" class="fQty"></td>' +
                '<td class="kanan">' + rupiah(sub) + '</td>' +
                '<td><button type="button" class="btn kecil bahaya btnHapus" data-i="' + idx + '">Hapus</button></td>';
            tbody.appendChild(tr);
        });
        var total = rows.reduce(function (t, r) { return t + r.harga * r.qty; }, 0);
        lblTotal.textContent = rupiah(total);
    }

    tbody.addEventListener('input', function (e) {
        var i = parseInt(e.target.getAttribute('data-i'), 10);
        if (isNaN(i)) return;
        if (e.target.classList.contains('fHarga')) rows[i].harga = parseFloat(e.target.value) || 0;
        if (e.target.classList.contains('fQty')) rows[i].qty = parseFloat(e.target.value) || 0;
        render();
    });

    tbody.addEventListener('click', function (e) {
        var i = parseInt(e.target.getAttribute('data-i'), 10);
        if (isNaN(i) || !e.target.classList.contains('btnHapus')) return;
        rows.splice(i, 1);
        render();
    });

    document.getElementById('btnTambah').addEventListener('click', function () {
        var sel = document.getElementById('tambahProduk');
        var qty = parseFloat(document.getElementById('tambahQty').value) || 1;
        var p = window.EDIT_PRODUK.find(function (x) { return x.id === parseInt(sel.value, 10); });
        if (!p) return;
        var exist = rows.find(function (r) { return r.id === p.id; });
        if (exist) { exist.qty += qty; }
        else { rows.push({ id: p.id, nama: p.nama, harga: p.harga, qty: qty }); }
        render();
    });

    document.getElementById('formEdit').addEventListener('submit', function () {
        itemsJson.value = JSON.stringify(rows.map(function (r) {
            return { produk_id: r.id, nama: r.nama, harga: r.harga, qty: r.qty };
        }));
    });

    render();
})();
</script>
<?php require __DIR__ . '/layout/footer.php'; ?>
