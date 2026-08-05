<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hapus_penjualan'])) {
    $id = (int)$_POST['hapus_penjualan'];
    $back = $_POST['back'] ?? 'penjualan';
    $penj = DB::one('SELECT * FROM penjualan WHERE id = ? AND ' . scope_sql('penjualan'), [$id]);
    if ($penj) {
        $items = DB::q('SELECT * FROM penjualan_item WHERE penjualan_id = ?', [$id]);
        foreach ($items as $i) {
            if ($i['produk_id']) {
                DB::run('UPDATE produk SET stok = stok + ? WHERE id = ?', [$i['qty'], $i['produk_id']]);
            }
        }
        $pesananTerkait = DB::q("SELECT DISTINCT ref_id FROM pembayaran WHERE ref_type = 'pesanan' AND keterangan LIKE ?", ['%' . $penj['no_invoice'] . '%']);
        foreach ($pesananTerkait as $pt) {
            DB::run("DELETE FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ? AND keterangan LIKE ?", [$pt['ref_id'], '%' . $penj['no_invoice'] . '%']);
            $sisaBayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ?", [$pt['ref_id']])['t'];
            if ($sisaBayar <= 0) {
                DB::run('UPDATE pesanan SET deleted = 1 WHERE id = ?', [$pt['ref_id']]);
            }
        }
        DB::run('DELETE FROM penjualan_item WHERE penjualan_id = ?', [$id]);
        DB::run('DELETE FROM penjualan WHERE id = ?', [$id]);
        log_aktivitas('Hapus transaksi', $penj['no_invoice']);
        flash_set('success', 'Transaksi dihapus, stok dikembalikan.');
    } else {
        flash_set('error', 'Transaksi tidak ditemukan.');
    }
    header('Location: index.php?p=' . ($back === 'dashboard' ? 'dashboard' : 'penjualan'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['simpan_edit_penjualan'])) {
    $id = (int)($_POST['id'] ?? 0);
    $back = $_POST['back'] ?? 'penjualan';
    $penj = DB::one('SELECT * FROM penjualan WHERE id = ? AND ' . scope_sql('penjualan'), [$id]);
    if (!$penj) {
        flash_set('error', 'Transaksi tidak ditemukan.');
        header('Location: index.php?p=penjualan');
        exit;
    }
    $items = json_decode($_POST['items'] ?? '[]', true);
    $metode = trim($_POST['metode'] ?? 'Tunai');
    $ket = trim($_POST['keterangan'] ?? '');
    $err = null;
    if (!$items) {
        $err = 'Item transaksi kosong.';
    }
    $old = DB::q('SELECT * FROM penjualan_item WHERE penjualan_id = ?', [$id]);
    $oldQtyByProduct = [];
    foreach ($old as $o) {
        $oldQtyByProduct[(int)$o['produk_id']] = ($oldQtyByProduct[(int)$o['produk_id']] ?? 0) + (float)$o['qty'];
    }
    $newQtyByProduct = [];
    $lines = [];
    $total = 0.0;
    if (!$err) {
        foreach ($items as $it) {
            if (!is_array($it)) {
                $err = 'Data item tidak valid.';
                break;
            }
            $pid = (int)($it['produk_id'] ?? 0);
            $p = DB::one('SELECT * FROM produk WHERE id = ?', [$pid]);
            if (!$p) {
                $err = 'Produk tidak ditemukan.';
                break;
            }
            $q = (float)($it['qty'] ?? 0);
            $h = (float)($it['harga'] ?? 0);
            if ($q <= 0 || $h < 0) {
                $err = 'Qty/harga tidak valid.';
                break;
            }
            $newQtyByProduct[$pid] = ($newQtyByProduct[$pid] ?? 0) + $q;
            $sub = $h * $q;
            $total += $sub;
            $lines[] = ['produk_id' => $pid, 'nama' => $p['nama'], 'harga' => $h, 'qty' => $q, 'subtotal' => $sub];
        }
    }
    if (!$err) {
        foreach ($newQtyByProduct as $pid => $need) {
            $stok = (float)DB::one('SELECT stok FROM produk WHERE id = ?', [$pid])['stok'];
            $avail = $stok + ($oldQtyByProduct[$pid] ?? 0);
            if ($need > $avail + 0.001) {
                $err = 'Stok tidak cukup: butuh ' . qty($need) . ', tersedia ' . qty($avail) . '.';
                break;
            }
        }
    }
    if ($err || $total <= 0) {
        flash_set('error', $err ?: 'Total transaksi 0.');
        header('Location: edit-penjualan.php?id=' . $id);
        exit;
    }
    foreach ($old as $o) {
        if ($o['produk_id']) {
            DB::run('UPDATE produk SET stok = stok + ? WHERE id = ?', [$o['qty'], $o['produk_id']]);
        }
    }
    DB::run('DELETE FROM penjualan_item WHERE penjualan_id = ?', [$id]);
    foreach ($lines as $l) {
        DB::run('INSERT INTO penjualan_item (penjualan_id, produk_id, nama, harga, qty, subtotal) VALUES (?,?,?,?,?,?)',
            [$id, $l['produk_id'], $l['nama'], $l['harga'], $l['qty'], $l['subtotal']]);
        DB::run('UPDATE produk SET stok = stok - ? WHERE id = ?', [$l['qty'], $l['produk_id']]);
    }
    $bayar = (float)$penj['bayar'];
    $kembalian = $bayar - $total;
    if ($kembalian < 0) {
        $bayar = $total;
        $kembalian = 0;
    }
    DB::run('UPDATE penjualan SET total = ?, bayar = ?, kembalian = ?, metode = ?, keterangan = ? WHERE id = ?',
        [$total, $bayar, $kembalian, $metode, $ket, $id]);
    log_aktivitas('Edit transaksi', $penj['no_invoice'] . ' total ' . $total);
    flash_set('success', 'Transaksi diperbarui.');
    header('Location: index.php?p=' . ($back === 'dashboard' ? 'dashboard' : 'penjualan'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['konfirmasi_penjualan'])) {
    $id = (int)$_POST['konfirmasi_penjualan'];
    $penj = DB::one('SELECT * FROM penjualan WHERE id = ? AND ' . scope_sql('penjualan'), [$id]);
    if ($penj && $penj['status'] === 'Menunggu QRIS') {
        DB::run("UPDATE penjualan SET status = 'Lunas' WHERE id = ?", [$id]);
        log_aktivitas('Konfirmasi QRIS', $penj['no_invoice'] . ' | ' . $penj['total']);
        flash_set('success', 'Pembayaran QRIS dikonfirmasi. Transaksi sah.');
    } else {
        flash_set('error', 'Transaksi tidak ditemukan.');
    }
    header('Location: index.php?p=penjualan');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['simpan'])) {
    $items = json_decode($_POST['items'] ?? '[]', true);
    $bayar = (float)($_POST['bayar'] ?? 0);
    $metode = trim($_POST['metode'] ?? 'Tunai');
    $ket = trim($_POST['keterangan'] ?? '');

    $lines = [];
    $total = 0.0;
    $err = null;

    if (!$items) {
        $err = 'Keranjang kosong.';
    }
    foreach ($items as $it) {
        if (!is_array($it)) {
            $err = 'Data keranjang tidak valid.';
            break;
        }
        $p = DB::one('SELECT * FROM produk WHERE id = ?', [(int)$it['id']]);
        if (!$p) {
            $err = 'Produk tidak ditemukan.';
            break;
        }
        $q = (float)$it['qty'];
        if ($q <= 0) {
            $err = 'Qty tidak valid.';
            break;
        }
        if ($p['stok'] < $q) {
            $err = "Stok '{$p['nama']}' tidak cukup (sisa " . qty($p['stok']) . ' ' . $p['satuan'] . ').';
            break;
        }
        $sub = $p['harga_jual'] * $q;
        $total += $sub;
        $lines[] = ['produk_id' => (int)$p['id'], 'nama' => $p['nama'], 'harga' => (float)$p['harga_jual'], 'qty' => $q, 'subtotal' => $sub];
    }

    if ($err) {
        flash_set('error', $err);
        header('Location: index.php?p=penjualan');
        exit;
    }
    if ($total <= 0) {
        flash_set('error', 'Total transaksi 0.');
        header('Location: index.php?p=penjualan');
        exit;
    }
    if ($bayar < $total) {
        flash_set('error', 'Uang bayar kurang dari total.');
        header('Location: index.php?p=penjualan');
        exit;
    }

    $no = next_number('PNL', 'penjualan');
    $statusBayar = $metode === 'QRIS' ? 'Menunggu QRIS' : 'Lunas';
    DB::run('INSERT INTO penjualan (no_invoice, tgl, total, bayar, kembalian, metode, user_id, keterangan, status) VALUES (?,?,?,?,?,?,?,?,?)',
        [$no, date('Y-m-d H:i:s'), $total, $bayar, $bayar - $total, $metode, $_SESSION['user_id'], $ket, $statusBayar]);
    $pid = DB::lastId();
    log_aktivitas('Transaksi baru', $no . ' | total ' . $total . ($statusBayar === 'Menunggu QRIS' ? ' | MENUNGGU KONFIRMASI QRIS' : ''));
    foreach ($lines as $l) {
        DB::run('INSERT INTO penjualan_item (penjualan_id, produk_id, nama, harga, qty, subtotal) VALUES (?,?,?,?,?,?)',
            [$pid, $l['produk_id'], $l['nama'], $l['harga'], $l['qty'], $l['subtotal']]);
        DB::run('UPDATE produk SET stok = stok - ? WHERE id = ?', [$l['qty'], $l['produk_id']]);
    }

    $pesananId = (int)($_POST['pesanan_id'] ?? 0);
    if ($pesananId > 0) {
        $ps = DB::one('SELECT * FROM pesanan WHERE id = ? AND status IN (?, ?) AND ' . scope_sql('pesanan'), [$pesananId, 'DP', 'Lunas']);
        if ($ps) {
            $sudahBayar = (float)DB::one("SELECT COALESCE(SUM(jumlah),0) t FROM pembayaran WHERE ref_type='pesanan' AND ref_id = ?", [$pesananId])['t'];
            $sisaPs = $ps['total'] - $sudahBayar;
            $dibayar = min($total, max(0, $sisaPs));
            if ($dibayar > 0) {
                DB::run('INSERT INTO pembayaran (ref_type, ref_id, tgl, jumlah, metode, keterangan, status, user_id) VALUES (?,?,?,?,?,?,?,?)',
                    ['pesanan', $pesananId, date('Y-m-d H:i:s'), $dibayar, $metode, 'Pembayaran pesanan via kasir ' . $no, $metode === 'QRIS' ? 'Menunggu QRIS' : 'Lunas', $_SESSION['user_id']]);
                $sisaBaru = max(0, $sisaPs - $dibayar);
                DB::run('UPDATE pesanan SET sisa = ?, status = ? WHERE id = ?', [$sisaBaru, $sisaBaru <= 0 ? 'Lunas' : 'DP', $pesananId]);
            }
        }
    }

    header('Location: struk.php?id=' . $pid . '&auto=1');
    exit;
}
$produk = DB::q('SELECT p.id, p.kode, p.nama, p.barcode, p.satuan, p.harga_jual, p.stok, p.stok_min, k.nama AS kategori
                 FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id ORDER BY p.nama');

$fFrom = trim($_GET['from'] ?? '');
$fTo = trim($_GET['to'] ?? '');
$fQ = trim($_GET['q'] ?? '');
$whereTr = 'WHERE ' . scope_sql('penjualan');
$argsTr = [];
if ($fFrom !== '') {
    $whereTr .= ' AND date(tgl) >= ?';
    $argsTr[] = $fFrom;
}
if ($fTo !== '') {
    $whereTr .= ' AND date(tgl) <= ?';
    $argsTr[] = $fTo;
}
if ($fQ !== '') {
    $whereTr .= ' AND (no_invoice LIKE ? OR keterangan LIKE ?)';
    $argsTr[] = "%$fQ%";
    $argsTr[] = "%$fQ%";
}
$filterAktif = ($fFrom !== '' || $fTo !== '' || $fQ !== '');
$transaksi = DB::q("SELECT * FROM penjualan $whereTr ORDER BY id DESC LIMIT " . ($filterAktif ? 500 : 10), $argsTr);

$pesananTerkait = DB::q('SELECT id, no_pesanan, pelanggan, sisa FROM pesanan WHERE sisa > 0 AND status != ? AND ' . scope_sql('pesanan') . ' ORDER BY id DESC', ['Batal']);

$judul = 'Kasir';
require __DIR__ . '/../layout/header.php';
?>
<script>
window.PRODUK = <?= json_encode(array_map(function ($p) {
    return ['id' => (int)$p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'barcode' => $p['barcode'], 'satuan' => $p['satuan'],
        'kategori' => $p['kategori'] ?? '', 'harga' => (float)$p['harga_jual'], 'stok' => (float)$p['stok']];
}, $produk)) ?>;
</script>
<?php $qris = setting('qris_image'); ?>

<h2>Kasir / Penjualan Cepat</h2>
<div class="grid-kasir">
    <div class="panel">
        <h3>Pilih Produk</h3>
        <input type="text" id="cariProduk" placeholder="Cari nama / kode / barcode..." autocomplete="off">
        <button type="button" class="btn besar" id="btnScanKasir">Scan Barcode Produk</button>
        <ul id="hasilCari" class="hasil-cari"></ul>
        <?php if ($qris): ?>
            <button type="button" class="btn" id="btnQris">Tampilkan QRIS Pembayaran</button>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Keranjang</h3>
        <table>
            <thead><tr><th>Nama</th><th>Harga</th><th>Qty / Ukuran</th><th>Subtotal</th><th></th></tr></thead>
            <tbody id="cartBody"></tbody>
        </table>
        <p id="cartEmpty" class="muted">Keranjang kosong.</p>

        <div class="baris-total">Total: <b id="lblTotal">Rp 0</b></div>

        <form method="post" id="formKasir">
            <input type="hidden" name="items" id="itemsJson">
            <div class="form-row">
                <label>Uang Bayar
                    <input type="number" name="bayar" id="bayar" min="0" step="0.01" required>
                </label>
                <label>Metode
                    <select name="metode">
                        <option>Tunai</option>
                        <option>QRIS</option>
                        <option>Transfer</option>
                    </select>
                </label>
            </div>
            <label>Keterangan
                <input type="text" name="keterangan" placeholder="opsional">
            </label>
            <label>Dibayar untuk Pesanan (opsional)
                <select name="pesanan_id">
                    <option value="">- Tidak ada -</option>
                    <?php foreach ($pesananTerkait as $pt): ?>
                        <option value="<?= $pt['id'] ?>"><?= e($pt['no_pesanan']) ?> - <?= e($pt['pelanggan']) ?> (sisa <?= rp($pt['sisa']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="muted kecil">Pilih pesanan bila transaksi ini pelunasan untuk pesanan — otomatis dicatat sebagai pembayaran pesanan.</p>
            <div class="baris-total">Kembalian: <b id="lblKembali">Rp 0</b></div>
            <button type="submit" class="btn besar" name="simpan" value="1">Simpan Transaksi</button>
        </form>
    </div>
</div>

<?php if ($qris): ?>
<div id="modalQris" class="modal hidden">
    <div class="modal-box">
        <h3>QRIS Pembayaran</h3>
        <img src="<?= e($qris) ?>" alt="QRIS">
        <button type="button" class="btn" id="btnTutupQris">Tutup</button>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Transaksi Terakhir</h3>
    <form method="get" class="form-row">
        <input type="hidden" name="p" value="penjualan">
        <input type="date" name="from" value="<?= e($fFrom) ?>" title="Dari tanggal">
        <input type="date" name="to" value="<?= e($fTo) ?>" title="Sampai tanggal">
        <input type="text" name="q" value="<?= e($fQ) ?>" placeholder="Cari no invoice / keterangan...">
        <button type="submit" class="btn">Filter</button>
        <?php if ($filterAktif): ?>
            <a class="btn abu" href="index.php?p=penjualan">Bersihkan</a>
        <?php endif; ?>
    </form>
    <?php if (!$transaksi): ?>
        <p class="muted">Belum ada transaksi.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>No Invoice</th><th>Tanggal</th><th>Total</th><th>Metode</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($transaksi as $tr): ?>
                <tr>
                    <td>
                        <?= e($tr['no_invoice']) ?>
                        <?php if ($tr['status'] === 'Menunggu QRIS'): ?>
                            <span class="badge warn">Menunggu QRIS</span>
                        <?php endif; ?>
                    </td>
                    <td><?= tgl($tr['tgl']) ?></td>
                    <td><?= rp($tr['total']) ?></td>
                    <td><?= e($tr['metode']) ?></td>
                    <td>
                        <?php if ($tr['status'] === 'Menunggu QRIS'): ?>
                            <form method="post" class="confirm inline" data-confirm="Pastikan dana QRIS sudah masuk di GoPay Merchant, lalu konfirmasi transaksi <?= e($tr['no_invoice']) ?>?">
                                <input type="hidden" name="konfirmasi_penjualan" value="<?= $tr['id'] ?>">
                                <button type="submit" class="btn kecil ok">Konfirmasi Dana Masuk</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn kecil" href="struk.php?id=<?= $tr['id'] ?>">Cetak Struk</a>
                        <a class="btn kecil" href="nota.php?ref=penjualan&id=<?= $tr['id'] ?>&t=a5">Cetak Nota</a>
                        <a class="btn kecil" href="edit-penjualan.php?id=<?= $tr['id'] ?>">Edit</a>
                        <form method="post" class="confirm inline" data-confirm="Hapus transaksi <?= e($tr['no_invoice']) ?>? Stok akan dikembalikan.">
                            <input type="hidden" name="hapus_penjualan" value="<?= $tr['id'] ?>">
                            <button type="submit" class="btn kecil bahaya">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
