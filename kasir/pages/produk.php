<?php
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['simpan_produk'])) {
        $id = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode'] ?? '');
        if ($kode === '') {
            $kode = next_number('PRD', 'produk');
            $coba = 0;
            while (DB::one('SELECT id FROM produk WHERE kode = ?', [$kode]) && $coba < 100) {
                $coba++;
                $kode = 'PRD-' . date('ymd') . '-' . str_pad((int)DB::one('SELECT COALESCE(MAX(id),0) m FROM produk')['m'] + $coba, 4, '0', STR_PAD_LEFT);
            }
        }
        $nama = trim($_POST['nama'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $kategori = (int)($_POST['kategori_id'] ?? 0);
        $satuan = trim($_POST['satuan'] ?? 'pcs');
        $harga_beli = (float)($_POST['harga_beli'] ?? 0);
        $harga_jual = (float)($_POST['harga_jual'] ?? 0);
        $stok = (float)($_POST['stok'] ?? 0);
        $stok_min = (float)($_POST['stok_min'] ?? 0);

        if ($nama === '' || $harga_jual < 0) {
            flash_set('error', 'Nama produk dan harga jual wajib diisi.');
        } elseif ($kode !== '' && DB::one('SELECT id FROM produk WHERE kode = ? AND id != ?', [$kode, $id])) {
            flash_set('error', 'Kode produk sudah dipakai.');
        } elseif ($barcode !== '' && DB::one('SELECT id FROM produk WHERE barcode = ? AND id != ?', [$barcode, $id])) {
            flash_set('error', 'Barcode sudah terdaftar di produk lain.');
        } elseif ($id > 0) {
            DB::run('UPDATE produk SET kode=?, nama=?, barcode=?, kategori_id=?, satuan=?, harga_beli=?, harga_jual=?, stok=?, stok_min=? WHERE id=?',
                [$kode, $nama, $barcode, $kategori ?: null, $satuan, $harga_beli, $harga_jual, $stok, $stok_min, $id]);
            flash_set('success', 'Produk diperbarui.');
        } else {
            DB::run('INSERT INTO produk (kode, nama, barcode, kategori_id, satuan, harga_beli, harga_jual, stok, stok_min) VALUES (?,?,?,?,?,?,?,?,?)',
                [$kode, $nama, $barcode, $kategori ?: null, $satuan, $harga_beli, $harga_jual, $stok, $stok_min]);
            flash_set('success', 'Produk ditambahkan.');
        }
        header('Location: index.php?p=produk');
        exit;
    }

    if (!empty($_POST['hapus_produk'])) {
        DB::run('DELETE FROM produk WHERE id = ?', [(int)$_POST['hapus_produk']]);
        flash_set('success', 'Produk dihapus.');
        header('Location: index.php?p=produk');
        exit;
    }

    if (!empty($_POST['tambah_kategori'])) {
        $nama = trim($_POST['nama_kategori'] ?? '');
        if ($nama === '') {
            flash_set('error', 'Nama kategori kosong.');
        } elseif (DB::one('SELECT id FROM kategori WHERE nama = ?', [$nama])) {
            flash_set('error', 'Kategori sudah ada.');
        } else {
            DB::run('INSERT INTO kategori (nama) VALUES (?)', [$nama]);
            flash_set('success', 'Kategori ditambahkan.');
        }
        header('Location: index.php?p=produk');
        exit;
    }

    if (!empty($_POST['hapus_kategori'])) {
        DB::run('DELETE FROM kategori WHERE id = ?', [(int)$_POST['hapus_kategori']]);
        flash_set('success', 'Kategori dihapus.');
        header('Location: index.php?p=produk');
        exit;
    }
}

$produk = DB::q('SELECT p.*, k.nama AS kategori FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id ORDER BY p.nama');
$kategori = DB::q('SELECT * FROM kategori ORDER BY nama');
$edit = null;
if (!empty($_GET['edit'])) {
    $edit = DB::one('SELECT * FROM produk WHERE id = ?', [(int)$_GET['edit']]);
}

$judul = 'Produk & Stok';
require __DIR__ . '/../layout/header.php';
?>

<h2>Produk & Stok</h2>

<div class="grid2">
    <div class="panel">
        <h3><?= $edit ? 'Edit Produk' : 'Tambah Produk' ?></h3>
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <div class="form-row">
                <label>Kode
                    <input type="text" name="kode" value="<?= e($edit['kode'] ?? '') ?>" placeholder="otomatis jika kosong">
                </label>
                <label>Kategori
                    <select name="kategori_id">
                        <option value="0">- Pilih -</option>
                        <?php foreach ($kategori as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= ($edit && (int)$edit['kategori_id'] === (int)$k['id']) ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Barcode
                <input type="text" name="barcode" id="barcodeField" value="<?= e($edit['barcode'] ?? '') ?>" placeholder="scan atau ketik nomor barcode produk">
                <button type="button" class="btn kecil" id="btnScanBarcode">Scan Barcode</button>
            </label>
            <label>Nama Produk
                <input type="text" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required>
            </label>
            <div class="form-row">
                <label>Satuan
                    <input type="text" name="satuan" value="<?= e($edit['satuan'] ?? 'pcs') ?>" required>
                </label>
                <label>Harga Beli
                    <input type="number" name="harga_beli" min="0" step="0.01" value="<?= e($edit['harga_beli'] ?? 0) ?>">
                </label>
                <label>Harga Jual
                    <input type="number" name="harga_jual" min="0" step="0.01" value="<?= e($edit['harga_jual'] ?? 0) ?>" required>
                </label>
                <label>Stok
                    <input type="number" name="stok" min="0" step="0.01" value="<?= e($edit['stok'] ?? 0) ?>">
                </label>
                <label>Stok Min
                    <input type="number" name="stok_min" min="0" step="0.01" value="<?= e($edit['stok_min'] ?? 0) ?>">
                </label>
            </div>
            <button type="submit" class="btn" name="simpan_produk" value="1"><?= $edit ? 'Simpan Perubahan' : 'Tambah Produk' ?></button>
            <?php if ($edit): ?>
                <a href="index.php?p=produk" class="btn btn-abu">Batal</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="panel">
        <h3>Kategori</h3>
        <form method="post" class="form-row">
            <input type="text" name="nama_kategori" placeholder="Nama kategori baru" required>
            <button type="submit" class="btn" name="tambah_kategori" value="1">Tambah</button>
        </form>
        <table>
            <tbody>
            <?php foreach ($kategori as $k): ?>
                <tr>
                    <td><?= e($k['nama']) ?></td>
                    <td class="kanan">
                        <form method="post" class="confirm inline" data-confirm="Hapus kategori '<?= e($k['nama']) ?>'? Produk di dalamnya ikut tanpa kategori.">
                            <input type="hidden" name="hapus_kategori" value="<?= $k['id'] ?>">
                            <button type="submit" class="btn-link bahaya">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <h3>Daftar Produk</h3>
    <input type="text" id="filterTabel" placeholder="Cari produk..." autocomplete="off">
    <table id="tabel" class="filterable">
        <thead>
        <tr>
            <th data-k="Kode">Kode</th>
            <th data-k="Nama">Nama</th>
            <th data-k="Barcode">Barcode</th>
            <th data-k="Kategori">Kategori</th>
            <th data-k="Satuan">Satuan</th>
            <th data-k="Beli">H. Beli</th>
            <th data-k="Jual">H. Jual</th>
            <th data-k="Stok">Stok</th>
            <th data-k="Aksi">Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$produk): ?>
            <tr><td colspan="9" class="muted">Belum ada produk.</td></tr>
        <?php endif; ?>
        <?php foreach ($produk as $p): ?>
            <tr data-s="<?= e(strtolower($p['kode'] . ' ' . $p['nama'] . ' ' . $p['barcode'] . ' ' . $p['kategori'] . ' ' . $p['satuan'])) ?>">
                <td><?= e($p['kode']) ?></td>
                <td><?= e($p['nama']) ?></td>
                <td><?= e($p['barcode']) ?></td>
                <td><?= e($p['kategori'] ?? '-') ?></td>
                <td><?= e($p['satuan']) ?></td>
                <td><?= rp($p['harga_beli']) ?></td>
                <td><?= rp($p['harga_jual']) ?></td>
                <td><span class="badge <?= $p['stok'] <= $p['stok_min'] ? 'warn' : 'ok' ?>"><?= qty($p['stok']) ?></span></td>
                <td>
                    <a class="btn-link" href="label.php?ids=<?= (int)$p['id'] ?>">Label</a>
                    <a class="btn-link" href="index.php?p=produk&edit=<?= $p['id'] ?>">Edit</a>
                    <form method="post" class="confirm inline" data-confirm="Hapus produk '<?= e($p['nama']) ?>'?">
                        <input type="hidden" name="hapus_produk" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-link bahaya">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
window.PRODUK_BARCODES = <?= json_encode(array_values(array_filter(array_map(function ($p) { return $p['barcode']; }, $produk)))) ?>;
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
