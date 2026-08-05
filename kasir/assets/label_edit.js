(function () {
    var therm = document.querySelector('.lbl-thermal');
    if (!therm) return;
    var first = therm.querySelector('.lbl-cell');
    if (!first) return;
    var lw = parseFloat(therm.dataset.w) || 40, lh = parseFloat(therm.dataset.h) || 30;
    var KEYS = ['toko', 'nama', 'harga', 'bc', 'kode'];
    var SEL = { toko: '.lbl-toko', nama: '.lbl-nama', harga: '.lbl-harga', bc: '.lbl-bc', kode: '.lbl-kode' };
    var TEXT = { toko: 1, nama: 1, harga: 1, bc: 0, kode: 1 };
    var saved = {};
    try { saved = JSON.parse(therm.dataset.layout || '{}'); } catch (e) {}
    var hadCustom = therm.classList.contains('lbl-custom');
    var state = {};
    var controls = {};
    var btnEdit = document.getElementById('btn-edit-layout');
    var btnSave = document.getElementById('btn-save-layout');
    var btnReset = document.getElementById('btn-reset-layout');
    var btnBatal = document.getElementById('btn-batal-layout');
    var hint = document.getElementById('lbl-edit-hint');
    var hiddenRow = document.getElementById('lbl-hidden-row');
    if (!btnEdit) return;
    function cellRect() { return first.getBoundingClientRect(); }
    function allE(k) { return therm.querySelectorAll(SEL[k]); }
    function applyInline(k, css) {
        allE(k).forEach(function (el) { for (var p in css) { el.style[p] = css[p]; } });
    }
    function renderEl(k) {
        var st = state[k];
        applyInline(k, {
            left: st.x + 'mm', top: st.y + 'mm',
            width: st.w + 'mm', height: st.h + 'mm',
            fontSize: TEXT[k] ? st.fs + 'mm' : ''
        });
        if (st.hide) { applyInline(k, { display: 'none' }); }
        if (TEXT[k]) {
            allE(k).forEach(function (el) { el.textContent = st.txt; });
        }
        positionControls(k);
    }
    function positionControls(k) {
        var c = controls[k];
        if (!c) return;
        var st = state[k];
        if (st.hide) {
            c.h.style.display = 'none';
            if (c.eb) { c.eb.style.display = 'none'; }
            c.db.style.display = 'none';
            return;
        }
        c.h.style.display = '';
        c.h.style.left = 'calc(' + Math.round((st.x + st.w) * 10) / 10 + 'mm - 2px)';
        c.h.style.top = 'calc(' + Math.round((st.y + st.h) * 10) / 10 + 'mm - 2px)';
        if (c.eb) {
            c.eb.style.display = '';
            c.eb.style.left = 'calc(' + Math.round(st.x * 10) / 10 + 'mm - 2px)';
            c.eb.style.top = 'calc(' + Math.round(st.y * 10) / 10 + 'mm - 2px)';
        }
        c.db.style.display = '';
        c.db.style.left = 'calc(' + Math.round((st.x + st.w) * 10) / 10 + 'mm - 10px)';
        c.db.style.top = 'calc(' + Math.round(st.y * 10) / 10 + 'mm - 2px)';
    }
    function editText(k) {
        var cur = state[k].txt;
        var t = prompt('Ubah teks "' + k + '" (kosongkan untuk kembali ke teks asli):', cur);
        if (t === null) return;
        state[k].txt = t;
        allE(k).forEach(function (el) { el.textContent = t; });
    }
    function setHide(k, v) {
        state[k].hide = v ? 1 : 0;
        applyInline(k, { display: v ? 'none' : '' });
        positionControls(k);
        refreshHiddenRow();
    }
    function refreshHiddenRow() {
        if (!hiddenRow) return;
        hiddenRow.innerHTML = '';
        var any = false;
        KEYS.forEach(function (k) {
            if (state[k] && state[k].hide) {
                any = true;
                var b = document.createElement('button');
                b.type = 'button';
                b.style.cssText = 'font-size:11px;padding:2px 8px;margin-right:4px;';
                b.textContent = '\u21a9\ufe0f ' + k;
                b.addEventListener('click', function () { setHide(k, 0); });
                hiddenRow.appendChild(b);
            }
        });
        hiddenRow.style.display = any ? 'inline-block' : 'none';
    }
    function makeControls(k) {
        if (controls[k]) return;
        var box = first.querySelector('.lbl-inner');
        if (!box) return;
        var h = document.createElement('span');
        h.className = 'lbl-el-handle';
        h.title = 'Ubah ukuran';
        h.addEventListener('pointerdown', function (e) { beginDrag(k, e, 'resize'); });
        box.appendChild(h);
        var eb = null;
        if (TEXT[k]) {
            eb = document.createElement('span');
            eb.className = 'lbl-el-editbtn';
            eb.textContent = '\u270e';
            eb.title = 'Ubah teks';
            eb.addEventListener('click', function (e) { e.stopPropagation(); editText(k); });
            box.appendChild(eb);
        }
        var db = document.createElement('span');
        db.className = 'lbl-el-delbtn';
        db.textContent = '\u2715';
        db.title = 'Sembunyikan objek ini';
        db.addEventListener('click', function (e) { e.stopPropagation(); setHide(k, 1); });
        box.appendChild(db);
        controls[k] = { h: h, eb: eb, db: db };
        positionControls(k);
    }
    function beginDrag(k, e, mode) {
        e.preventDefault();
        var st = state[k];
        var sx = e.clientX, sy = e.clientY;
        var ox = st.x, oy = st.y, ow = st.w, oh = st.h, of = st.fs;
        function onMove(ev) {
            var c = cellRect();
            var dx = (ev.clientX - sx) / c.width * lw, dy = (ev.clientY - sy) / c.height * lh;
            if (mode === 'resize') {
                st.w = Math.max(3, Math.min(lw - st.x, ow + dx));
                st.h = Math.max(3, Math.min(lh - st.y, oh + dy));
                if (TEXT[k]) { st.fs = Math.max(0.5, of * st.h / oh); }
            } else {
                st.x = Math.max(0, Math.min(lw - st.w, ox + dx));
                st.y = Math.max(0, Math.min(lh - st.h, oy + dy));
            }
            renderEl(k);
        }
        function onUp() {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
        }
        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    }
    function enterEdit() {
        therm.classList.remove('lbl-custom');
        therm.classList.add('lbl-edit');
        btnEdit.style.display = 'none';
        btnSave.style.display = 'inline-block';
        btnReset.style.display = 'inline-block';
        btnBatal.style.display = 'inline-block';
        hint.style.display = 'inline';
        KEYS.forEach(function (k) {
            var el = first.querySelector(SEL[k]);
            if (!el) return;
            var r = el.getBoundingClientRect();
            var c = cellRect();
            var fs = parseFloat(getComputedStyle(el).fontSize) || 8;
            var se = saved[k] || {};
            var txt = (saved.txt && saved.txt[k] != null) ? saved.txt[k] : (TEXT[k] ? el.textContent : '');
            state[k] = {
                x: se.x != null ? se.x : (r.left - c.left) / c.width * lw,
                y: se.y != null ? se.y : (r.top - c.top) / c.height * lh,
                w: se.w != null ? se.w : r.width / c.width * lw,
                h: se.h != null ? se.h : r.height / c.height * lh,
                fs: se.fs != null ? se.fs : fs * 25.4 / 96,
                hide: se.hide ? 1 : 0,
                txt: txt
            };
            renderEl(k);
            makeControls(k);
            if (!el.__lblB) {
                el.__lblB = 1;
                el.addEventListener('pointerdown', function (ev) { beginDrag(k, ev, 'move'); });
                el.addEventListener('dblclick', function (ev) { if (TEXT[k]) { editText(k); } });
            }
        });
    }
    function exitEdit() {
        therm.classList.remove('lbl-edit');
        if (hadCustom) { therm.classList.add('lbl-custom'); }
        KEYS.forEach(function (k) { allE(k).forEach(function (el) { el.removeAttribute('style'); }); });
        Object.keys(controls).forEach(function (k) {
            var c = controls[k];
            if (!c) return;
            if (c.h.parentNode) { c.h.parentNode.removeChild(c.h); }
            if (c.eb && c.eb.parentNode) { c.eb.parentNode.removeChild(c.eb); }
            if (c.db.parentNode) { c.db.parentNode.removeChild(c.db); }
        });
        controls = {};
        if (hiddenRow) { hiddenRow.style.display = 'none'; }
        btnEdit.style.display = 'inline-block';
        btnSave.style.display = 'none';
        btnReset.style.display = 'none';
        btnBatal.style.display = 'none';
        hint.style.display = 'none';
    }
    function save() {
        var out = {}, t = {};
        KEYS.forEach(function (k) {
            var st = state[k];
            if (!st) return;
            out[k] = {
                x: Math.round(st.x * 100) / 100,
                y: Math.round(st.y * 100) / 100,
                w: Math.round(st.w * 100) / 100,
                h: Math.round(st.h * 100) / 100,
                fs: Math.round(st.fs * 100) / 100,
                hide: st.hide ? 1 : 0
            };
            if (TEXT[k] && st.txt && st.txt.trim() !== '') { t[k] = st.txt; }
        });
        out.txt = t;
        document.getElementById('lbl-layout-data').value = JSON.stringify(out);
        document.getElementById('lbl-layout-form').submit();
    }
    function reset() {
        if (!confirm('Kembalikan layout label ke otomatis?')) return;
        document.getElementById('lbl-layout-data').value = 'RESET';
        document.getElementById('lbl-layout-form').submit();
    }
    btnEdit.addEventListener('click', enterEdit);
    btnSave.addEventListener('click', save);
    btnReset.addEventListener('click', reset);
    btnBatal.addEventListener('click', exitEdit);
})();
