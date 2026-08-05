function qs(s, root) { return (root || document).querySelector(s); }

function qsa(s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); }

function rp(n) {
    return 'Rp ' + (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function () {
    qsa('form.confirm').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!window.confirm(f.dataset.confirm || 'Yakin?')) ev.preventDefault();
        });
    });

    var filter = qs('#filterTabel');
    if (filter) {
        filter.addEventListener('input', function () {
            var q = filter.value.toLowerCase();
            qsa('#tabel tr[data-s]').forEach(function (tr) {
                tr.style.display = tr.dataset.s.indexOf(q) > -1 ? '' : 'none';
            });
        });
    }

    initKasir();
    initQris();
    initScanner();
    initPesanan();
});

var scannerInstance = null;

function scanFormats() {
    if (!window.Html5Qrcode) return null;
    var F = Html5QrcodeSupportedFormats;
    return [F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.CODE_93, F.CODABAR, F.ITF, F.QR_CODE, F.DATA_MATRIX, F.AZTEC, F.PDF_417];
}

function openScanner(onResult) {
    if (window.AndroidScanner && typeof window.AndroidScanner.scan === 'function') {
        window.onBarcodeResult = function (code) {
            window.onBarcodeResult = null;
            onResult(code);
        };
        window.AndroidScanner.scan();
        return;
    }

    ensureScanModal();
    var modal = qs('#scanModal');
    var region = qs('#scanRegion');
    var status = qs('#scanStatus');
    region.innerHTML = '';
    status.textContent = 'Menyiapkan kamera...';
    modal.classList.remove('hidden');

    if (!window.Html5Qrcode) {
        status.textContent = 'Library scanner tidak termuat.';
        return;
    }

    scannerInstance = new Html5Qrcode('scanRegion');
    scannerInstance.start(
        { facingMode: 'environment' },
        {
            fps: 10,
            qrbox: { width: 240, height: 240 },
            formatsToSupport: scanFormats(),
            showTorchButtonIfSupported: true
        },
        function (text) {
            stopScanner();
            modal.classList.add('hidden');
            onResult(text);
        },
        function () {}
    ).catch(function (err) {
        status.textContent = 'Kamera tidak dapat diakses: ' + err;
    });
}

function stopScanner() {
    if (scannerInstance) {
        try {
            scannerInstance.stop().then(function () { scannerInstance.clear(); });
        } catch (e) {}
        scannerInstance = null;
    }
}

function ensureScanModal() {
    if (qs('#scanModal')) return;
    var modal = document.createElement('div');
    modal.id = 'scanModal';
    modal.className = 'modal hidden';
    modal.innerHTML =
        '<div class="modal-box">' +
        '<h3>Scan Barcode</h3>' +
        '<div id="scanRegion" class="scan-region"></div>' +
        '<p id="scanStatus" class="muted kecil"></p>' +
        '<button type="button" class="btn abu" id="btnTutupScan">Tutup</button>' +
        '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (ev) {
        if (ev.target === modal) {
            stopScanner();
            modal.classList.add('hidden');
        }
    });
    qs('#btnTutupScan').addEventListener('click', function () {
        stopScanner();
        modal.classList.add('hidden');
    });
}

function initScanner() {
    var btnKasir = qs('#btnScanKasir');
    if (btnKasir) {
        btnKasir.addEventListener('click', function () {
            openScanner(function (code) {
                code = String(code).trim();
                var produk = window.PRODUK || [];
                var found = null;
                produk.forEach(function (p) {
                    if (p.barcode && String(p.barcode).trim() === code) found = p;
                });
                if (!found) {
                    produk.forEach(function (p) {
                        if (p.kode && String(p.kode).trim() === code) found = p;
                    });
                }
                if (found) {
                    if (typeof window.addItemKasir === 'function') {
                        window.addItemKasir(found);
                    }
                } else {
                    window.alert('Barcode ' + code + ' tidak ditemukan. Tambahkan di menu Produk & Stok.');
                }
            });
        });
    }

    var btnBarcode = qs('#btnScanBarcode');
    if (btnBarcode) {
        btnBarcode.addEventListener('click', function () {
            openScanner(function (code) {
                var field = qs('#barcodeField');
                if (!field) return;
                field.value = String(code).trim();
                var ada = (window.PRODUK_BARCODES || []).filter(function (b) {
                    return b && b.trim() === String(code).trim();
                });
                if (ada.length) {
                    window.alert('Barcode ini sudah dipakai produk lain. Periksa daftar produk di bawah.');
                    var filter = qs('#filterTabel');
                    if (filter) {
                        filter.value = String(code).trim();
                        filter.dispatchEvent(new Event('input'));
                    }
                }
            });
        });
    }
}

function initKasir() {
    var input = qs('#cariProduk');
    if (!input) return;

    var produk = window.PRODUK || [];
    var cart = [];
    var hasil = qs('#hasilCari');
    var tbody = qs('#cartBody');
    var empty = qs('#cartEmpty');
    var lblTotal = qs('#lblTotal');
    var lblKembali = qs('#lblKembali');
    var bayar = qs('#bayar');
    var form = qs('#formKasir');

    function formatQty(n) {
        return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function total() {
        return cart.reduce(function (s, it) { return s + it.harga * it.qty; }, 0);
    }

    function renderCari(list) {
        hasil.innerHTML = '';
        if (!list.length) return;
        list.forEach(function (p) {
            var li = document.createElement('li');
            var nama = document.createElement('span');
            nama.innerHTML = '<b>' + p.nama + '</b>' + (p.kode ? ' <span class="muted">(' + p.kode + ')</span>' : '');
            var info = document.createElement('span');
            info.className = 'muted';
            info.textContent = rp(p.harga) + ' | stok ' + formatQty(p.stok) + ' ' + p.satuan;
            li.appendChild(nama);
            li.appendChild(info);
            li.addEventListener('click', function () {
                addItem(p);
                input.value = '';
                renderCari([]);
                input.focus();
            });
            hasil.appendChild(li);
        });
    }

    function isM2(p) {
        return p.satuan === 'm2' ||
            (p.kategori && p.kategori.toLowerCase().indexOf('banner') > -1) ||
            (p.kategori && p.kategori.toLowerCase().indexOf('spanduk') > -1);
    }

    function addItem(p) {
        var ada = null;
        cart.forEach(function (it) { if (it.id === p.id) ada = it; });
        if (ada) {
            if (ada.qty + 1 > p.stok) {
                window.alert('Stok ' + p.nama + ' tidak cukup (sisa ' + formatQty(p.stok) + ' ' + p.satuan + ').');
                return;
            }
            ada.qty += 1;
        } else {
            if (p.stok < 1) {
                window.alert('Stok ' + p.nama + ' habis.');
                return;
            }
            if (isM2(p)) {
                cart.push({ id: p.id, nama: p.nama, satuan: p.satuan, harga: p.harga, stok: p.stok, qty: 1, isM2: true, panjang: 1, lebar: 1 });
            } else {
                cart.push({ id: p.id, nama: p.nama, satuan: p.satuan, harga: p.harga, stok: p.stok, qty: 1, isM2: false });
            }
        }
        renderCart();
    }

    window.addItemKasir = addItem;

    function renderCart() {
        tbody.innerHTML = '';
        empty.style.display = cart.length ? 'none' : '';
        cart.forEach(function (it, idx) {
            var tr = document.createElement('tr');
            var td1 = document.createElement('td');
            td1.textContent = it.nama;
            var td2 = document.createElement('td');
            td2.textContent = rp(it.harga) + '/' + it.satuan;
            var td3 = document.createElement('td');
            if (it.isM2) {
                var wrap = document.createElement('div');
                wrap.style.display = 'flex';
                wrap.style.alignItems = 'center';
                wrap.style.gap = '4px';
                var p = document.createElement('input');
                p.type = 'number';
                p.min = '0.01';
                p.step = '0.01';
                p.value = it.panjang;
                p.style.width = '64px';
                p.title = 'Panjang (m)';
                var l = document.createElement('input');
                l.type = 'number';
                l.min = '0.01';
                l.step = '0.01';
                l.value = it.lebar;
                l.style.width = '64px';
                l.title = 'Lebar (m)';
                var br = document.createElement('span');
                br.className = 'muted kecil';
                br.textContent = '= ' + formatQty(it.qty) + ' m2';
                function upd() {
                    it.panjang = parseFloat(p.value) || 0;
                    it.lebar = parseFloat(l.value) || 0;
                    it.qty = it.panjang * it.lebar;
                    if (it.qty > it.stok) {
                        window.alert('Luas melebihi stok (sisa ' + formatQty(it.stok) + ' ' + it.satuan + ').');
                        it.qty = it.stok;
                    }
                    renderCart();
                }
                p.addEventListener('change', upd);
                l.addEventListener('change', upd);
                wrap.appendChild(p);
                wrap.appendChild(l);
                wrap.appendChild(br);
                td3.appendChild(wrap);
            } else {
                var q = document.createElement('input');
                q.type = 'number';
                q.min = '0.01';
                q.step = '1';
                q.value = it.qty;
                q.style.width = '80px';
                q.addEventListener('change', function () {
                    var v = parseFloat(q.value);
                    if (!(v > 0)) v = 1;
                    if (v > it.stok) {
                        window.alert('Stok tidak cukup (sisa ' + formatQty(it.stok) + ' ' + it.satuan + ').');
                        v = it.stok;
                    }
                    it.qty = v;
                    q.value = v;
                    renderCart();
                });
                td3.appendChild(q);
            }
            var td4 = document.createElement('td');
            td4.className = 'kanan';
            td4.textContent = rp(it.harga * it.qty);
            var td5 = document.createElement('td');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-link bahaya';
            btn.textContent = 'Hapus';
            btn.addEventListener('click', function () {
                cart.splice(idx, 1);
                renderCart();
            });
            td5.appendChild(btn);
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            tr.appendChild(td4);
            tr.appendChild(td5);
            tbody.appendChild(tr);
        });
        lblTotal.textContent = rp(total());
        hitungKembali();
    }

    function hitungKembali() {
        var b = parseFloat(bayar.value) || 0;
        var k = b - total();
        lblKembali.textContent = rp(k < 0 ? 0 : k);
    }

    input.addEventListener('input', function () {
        var q = input.value.toLowerCase().trim();
        if (!q) { renderCari([]); return; }
        var list = produk.filter(function (p) {
            return p.nama.toLowerCase().indexOf(q) > -1 ||
                (p.kode && p.kode.toLowerCase().indexOf(q) > -1) ||
                (p.barcode && String(p.barcode).toLowerCase().indexOf(q) > -1);
        });
        renderCari(list.slice(0, 30));
    });

    bayar.addEventListener('input', hitungKembali);

    form.addEventListener('submit', function (ev) {
        if (!cart.length) {
            ev.preventDefault();
            window.alert('Keranjang kosong.');
            return;
        }
        var t = total();
        var b = parseFloat(bayar.value) || 0;
        if (b < t) {
            ev.preventDefault();
            window.alert('Uang bayar kurang dari total (' + rp(t) + ').');
            return;
        }
        qs('#itemsJson').value = JSON.stringify(cart.map(function (it) {
            return { id: it.id, qty: it.qty };
        }));
    });
}

function initQris() {
    var btn = qs('#btnQris');
    var modal = qs('#modalQris');
    if (!btn || !modal) return;
    btn.addEventListener('click', function () { modal.classList.remove('hidden'); });
    qs('#btnTutupQris').addEventListener('click', function () { modal.classList.add('hidden'); });
    modal.addEventListener('click', function (ev) {
        if (ev.target === modal) modal.classList.add('hidden');
    });
}

function initPesanan() {
    var sel = qs('#produkHitung');
    if (!sel) return;

    var produk = window.PESANAN_PRODUK || [];
    var box = qs('#m2Box');
    var panjang = qs('#panjangM');
    var lebar = qs('#lebarM');
    var qty = qs('#qtyPesanan');
    var info = qs('#m2Info');
    var total = qs('#totalPesanan');
    var deskripsi = qs('#deskripsiPesanan');

    function cariProduk() {
        var id = parseInt(sel.value, 10);
        var p = null;
        produk.forEach(function (x) { if (x.id === id) p = x; });
        return p;
    }

    function formatAngka(n) {
        return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function hitung() {
        var p = cariProduk();
        var jumlah = parseFloat(qty.value) || 0;
        if (!p) {
            box.classList.add('hidden');
            info.textContent = '';
            return;
        }
        var isM2 = p.satuan === 'm2' || (p.kategori && p.kategori.toLowerCase().indexOf('banner') > -1) ||
            (p.kategori && p.kategori.toLowerCase().indexOf('spanduk') > -1);
        box.classList.toggle('hidden', !isM2);
        if (isM2) {
            var luas = (parseFloat(panjang.value) || 0) * (parseFloat(lebar.value) || 0);
            var subtotal = jumlah * luas * p.harga;
            info.textContent = formatAngka(jumlah) + ' x ' + formatAngka(luas) + ' m2 x ' + rp(p.harga) + ' = ' + rp(subtotal);
            total.value = subtotal;
        } else {
            var subtotal = jumlah * p.harga;
            info.textContent = formatAngka(jumlah) + ' x ' + rp(p.harga) + ' = ' + rp(subtotal);
            total.value = subtotal;
        }
    }

    sel.addEventListener('change', function () {
        var p = cariProduk();
        if (p && deskripsi.value.trim() === '') {
            deskripsi.value = p.nama;
        }
        hitung();
    });
    panjang.addEventListener('input', hitung);
    lebar.addEventListener('input', hitung);
    qty.addEventListener('input', hitung);

    var keranjang = [];
    var daftar = qs('#daftarItem');
    var infoItem = qs('#itemInfo');
    var btnTambah = qs('#btnTambahItem');
    var form = sel.form;

    function isM2p(p) {
        return p.satuan === 'm2' || (p.kategori && p.kategori.toLowerCase().indexOf('banner') > -1) ||
            (p.kategori && p.kategori.toLowerCase().indexOf('spanduk') > -1);
    }

    function hitungItem(p) {
        var jumlah = parseFloat(qty.value) || 0;
        var luas = isM2p(p) ? (parseFloat(panjang.value) || 0) * (parseFloat(lebar.value) || 0) : 1;
        return { qty: jumlah * luas, subtotal: jumlah * luas * p.harga };
    }

    function renderDaftar() {
        daftar.innerHTML = '';
        var sum = 0;
        keranjang.forEach(function (it, idx) {
            sum += it.subtotal;
            var tr = document.createElement('tr');
            var td1 = document.createElement('td');
            td1.textContent = it.nama;
            var td2 = document.createElement('td');
            td2.style.textAlign = 'center';
            td2.textContent = formatAngka(it.qty) + (it.satuan === 'm2' ? ' m2' : ' ' + it.satuan);
            var td3 = document.createElement('td');
            td3.style.textAlign = 'right';
            td3.textContent = rp(it.harga);
            var td4 = document.createElement('td');
            td4.style.textAlign = 'right';
            td4.textContent = rp(it.subtotal);
            var td5 = document.createElement('td');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn kecil bahaya';
            btn.textContent = 'x';
            btn.addEventListener('click', function () {
                keranjang.splice(idx, 1);
                renderDaftar();
            });
            td5.appendChild(btn);
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            tr.appendChild(td4);
            tr.appendChild(td5);
            daftar.appendChild(tr);
        });
        infoItem.textContent = keranjang.length
            ? keranjang.length + ' item terdaftar. Total otomatis: ' + rp(sum)
            : 'Belum ada item. Pilih produk lalu klik "+ Tambah ke Pesanan" (bisa lebih dari satu).';
        if (keranjang.length) {
            total.value = sum;
        }
    }

    if (btnTambah) {
        btnTambah.addEventListener('click', function () {
            var p = cariProduk();
            var jumlah = parseFloat(qty.value) || 0;
            if (!p || jumlah <= 0) {
                window.alert('Pilih produk dan isi jumlah (qty) terlebih dahulu.');
                return;
            }
            var it = hitungItem(p);
            if (it.qty <= 0) {
                window.alert('Ukuran / qty tidak valid.');
                return;
            }
            keranjang.push({ produk_id: p.id, nama: p.nama, satuan: p.satuan, qty: it.qty, harga: p.harga, subtotal: it.subtotal });
            renderDaftar();
            qty.value = 1;
            if (panjang) panjang.value = 1;
            if (lebar) lebar.value = 1;
            if (deskripsi.value.trim() === '' || deskripsi.value === p.nama) {
                deskripsi.value = p.nama;
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            var inp = qs('#itemsPesanan');
            if (inp) {
                inp.value = JSON.stringify(keranjang);
            }
        });
    }
}
