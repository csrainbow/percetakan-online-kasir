<?php
// Halaman manajemen WA Gateway (Baileys self-hosted). Superadmin only.
require_once __DIR__ . '/config.php';
require_login();
if (!is_superadmin()) {
    flash_set('error', 'Hanya super admin.');
    header('Location: index.php?p=pengaturan');
    exit;
}
$judul = 'WA Gateway';
$page = 'wa-gateway';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['simpan_gw'])) {
        set_setting('wa_gw_base', rtrim(trim($_POST['wa_gw_base'] ?? 'http://127.0.0.1:3001'), '/'));
        set_setting('wa_gw_key', trim($_POST['wa_gw_key'] ?? ''));
        set_setting('wa_gw_enabled', !empty($_POST['wa_gw_enabled']) ? '1' : '');
        set_setting('wa_gw_cache', '');
        flash_set('success', 'Pengaturan gateway disimpan.');
        header('Location: index.php?p=wa-gateway');
        exit;
    }
    if (!empty($_POST['test_gw'])) {
        $tujuan = trim($_POST['test_gw_tujuan'] ?? '');
        if ($tujuan === '') {
            $tujuan = setting('wa_admin_number', '');
        }
        if ($tujuan === '') {
            flash_set('error', 'Isi nomor tujuan test.');
        } else {
            $pesan = "🔔 *TEST WA GATEWAY (Baileys)*\n\nJika Anda menerima pesan ini, gateway self-hosted berfungsi. Waktu: " . date('d/m/Y H:i:s');
            [$ok, $why] = wa_gateway_send($tujuan, $pesan);
            flash_set($ok ? 'success' : 'error', $ok
                ? 'Pesan test via gateway terkirim ke ' . $tujuan . '.'
                : 'Test gateway GAGAL: ' . $why);
        }
        header('Location: index.php?p=wa-gateway');
        exit;
    }
    if (!empty($_POST['logout_gw'])) {
        $base = rtrim(setting('wa_gw_base', 'http://127.0.0.1:3001'), '/');
        $key = setting('wa_gw_key', '');
        $ch = curl_init($base . '/logout');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $key !== '' ? ['X-Api-Key: ' . $key] : []),
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
        flash_set('success', 'Perintah logout dikirim. Scan ulang QR untuk menautkan kembali.');
        header('Location: index.php?p=wa-gateway');
        exit;
    }
}

$gwBase = setting('wa_gw_base', 'http://127.0.0.1:3001');
$gwKey = setting('wa_gw_key', '');
$gwEnabled = setting('wa_gw_enabled', '1') === '1';
// Halaman ini selalu butuh status fresh (untuk QR & tombol), bukan cache.
$gw = wa_gateway_status_cached(true);
$qrSrc = $gw['hasQr'] ? 'wa-gw-qr.php?t=' . time() : '';

require __DIR__ . '/../layout/header.php';
?>
<div class="panel">
    <h2>WA Gateway (Baileys — Self Hosted)</h2>
    <p class="muted">Pengiriman utama notifikasi kasir. Bila gateway tidak connect, otomatis fallback ke provider (Fonnte/Wablas/Meta) sesuai Pengaturan.</p>
    <table>
        <tr><th style="width:220px">Status gateway</th><td>
            <?php if ($gw['connected']): ?>
                <span class="badge ok">● CONNECTED</span>
            <?php elseif ($gw['ok']): ?>
                <span class="badge warn">● <?= e($gw['status']) ?></span>
            <?php else: ?>
                <span class="badge err">● OFFLINE (service mati / base salah)</span>
            <?php endif; ?>
        </td></tr>
        <?php if (!empty($gw['me'])): ?>
        <tr><th>Nomor tertaut</th><td><?= e(($gw['me']['name'] ?? '') . ' ' . ($gw['me']['id'] ?? '')) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($gw['raw'])): ?>
        <tr><th>Terkirim / gagal</th><td><?= (int)($gw['raw']['sent'] ?? 0) ?> / <?= (int)($gw['raw']['failed'] ?? 0) ?></td></tr>
        <?php endif; ?>
        <tr><th>Mode kirim</th><td><?= $gwEnabled ? 'Gateway utama + fallback provider' : 'Provider saja (gateway nonaktif)' ?></td></tr>
    </table>
    <?php if ($gw['hasQr']): ?>
        <h3>Scan untuk menautkan</h3>
        <p><img src="<?= e($qrSrc) ?>" alt="QR WhatsApp" style="width:300px;height:300px;border:1px solid #ddd;border-radius:8px"></p>
        <p class="muted">Buka WhatsApp di HP → Setelan → Perangkat Tertaut → Tautkan Perangkat → scan QR ini. Halaman ini auto-refresh tiap 10 detik selama belum connect.</p>
        <script>setTimeout(function(){ location.reload(); }, 10000);</script>
    <?php elseif ($gw['connected']): ?>
        <p class="muted">✅ Perangkat tertaut. Tidak perlu scan ulang kecuali logout dari HP.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Konfigurasi Gateway</h3>
    <form method="post">
        <label>Aktifkan gateway sebagai jalur utama
            <input type="checkbox" name="wa_gw_enabled" value="1" <?= $gwEnabled ? 'checked' : '' ?>>
        </label>
        <label>Base URL gateway (internal)
            <input type="text" name="wa_gw_base" value="<?= e($gwBase) ?>" placeholder="http://127.0.0.1:3001">
        </label>
        <label>API Key gateway (isi sesuai WA_GATEWAY_KEY di /opt/wa-gateway/.env)
            <input type="text" name="wa_gw_key" value="<?= e($gwKey) ?>" placeholder="hex 64 karakter" autocomplete="off">
        </label>
        <button type="submit" class="btn" name="simpan_gw" value="1">Simpan</button>
    </form>
</div>

<div class="panel">
    <h3>Test Kirim via Gateway</h3>
    <form method="post" class="form-row">
        <input type="text" name="test_gw_tujuan" placeholder="Nomor tujuan, mis. 0822xxxx (kosong = nomor admin)">
        <button type="submit" class="btn" name="test_gw" value="1">Kirim Test</button>
    </form>
    <form method="post" onsubmit="return confirm('Putuskan session WA di server? Harus scan ulang setelah ini.');">
        <button type="submit" class="btn bahaya" name="logout_gw" value="1">Logout / Putus Session</button>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
