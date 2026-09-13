<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/payment_autocheck.php';
require_once __DIR__ . '/../includes/ownapi.php';

if (!isAdmin()) redirect('/admin/index.php');

$message = '';
$error = '';
$summary = null;
$logLines = [];

// 🔥 🔥 PROSES 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'paste_sync') {
        $text = trim($_POST['mutasi_text'] ?? '');
        if ($text === '') {
            $error = '❌ Tempel teks mutasi/notifikasi terlebih dahulu.';
        } else {
            $hits = pm_parse_text($text);
            if (empty($hits)) {
                $error = '❌ Tidak ada nominal transfer terdeteksi dari teks tersebut.';
            } else {
                $res = pm_run('manual', $hits);
                if (!empty($res['locked'])) {
                    $error = '⏳ Proses lain sedang berjalan. Coba lagi sebentar lagi.';
                } else {
                    $summary = $res['summary'];
                    $logLines = $res['log'];
                    $message = '✅ ' . count($hits) . ' mutasi diproses. Cocok & lunas otomatis: ' . ($summary['matched'] ?? 0) . '.';
                }
            }
        }
    } elseif ($action === 'process_new') {
        $res = pm_run('manual');
        if (!empty($res['locked'])) {
            $error = '⏳ Proses lain sedang berjalan. Coba lagi sebentar lagi.';
        } else {
            $summary = $res['summary'];
            $logLines = $res['log'];
            $message = '✅ Proses selesai. Lunas otomatis: ' . ($summary['matched'] ?? 0) . ' dari ' . ($summary['processed'] ?? 0) . ' notifikasi.';
        }
    } elseif ($action === 'regenerate_key') {
        $nk = ownapi_regenerate_key();
        $message = '🔑 API Key baru dibuat: ' . $nk;
    }
}

$apiKey = ownapi_get_key();
$baseUrl = getSetting('site_url') ?: 'https://rainbowprinting.web.id';
$endpoint = $baseUrl . '/api/payment-inbox.php';

$pageTitle = 'Cek Pembayaran Otomatis';
include '../includes/header.php';
?>

<style>
.admin-layout { display: flex; gap: 20px; margin-top: 20px; }
.admin-sidebar {
    width: 220px; background: linear-gradient(180deg, var(--primary) 0%, #0a1018 100%);
    padding: 20px 15px; border-radius: 14px; flex-shrink: 0; position: sticky; top: 80px;
    height: fit-content; border: 1px solid rgba(45,212,191,0.1);
}
.admin-sidebar h2 { color: var(--accent1); font-size: 16px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.admin-sidebar ul { list-style: none; padding: 0; }
.admin-sidebar ul li { margin-bottom: 4px; }
.admin-sidebar ul li a { display: block; padding: 8px 12px; color: #bdc3c7; text-decoration: none; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
.admin-sidebar ul li a:hover { background: rgba(45,212,191,0.08); color: #fff; }
.admin-sidebar ul li a.active { background: linear-gradient(135deg, var(--accent1) 0%, var(--accent2) 100%); color: var(--dark); font-weight: 600; box-shadow: 0 6px 18px rgba(45,212,191,0.25); }
.admin-main { flex: 1; min-width: 0; }
.admin-main h1 { font-size: 24px; color: var(--primary); margin-bottom: 5px; }
.admin-main .subtitle { color: #6c757d; font-size: 13px; margin-bottom: 20px; }
.alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 15px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.card { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; margin-bottom: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
.card h2 { font-size: 16px; color: var(--primary); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #f0f1f5; }
.card p { font-size: 13px; color: #555; margin: 6px 0; }
.btn { display: inline-block; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
.btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: #fff; }
.btn-primary:hover { box-shadow: 0 6px 18px rgba(13,27,42,0.35); }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
textarea.form-control { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; font-family: inherit; min-height: 120px; }
input.api-box { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; font-family: monospace; background: #f8f9fa; color: #333; }
.hits-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.hits-table th, .hits-table td { padding: 8px 10px; border-bottom: 1px solid #eef0f4; text-align: left; }
.hits-table th { background: #f8f9fb; color: #555; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; }
.badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-new { background: #fff3cd; color: #856404; }
.badge-matched { background: #d4edda; color: #155724; }
.badge-unmatched { background: #f8d7da; color: #721c24; }
.badge-ambiguous { background: #ffe5d0; color: #8a4d00; }
.badge-duplicate { background: #e2e3e5; color: #383d41; }
.status-ok { color: var(--success); font-weight: 600; }
.status-no { color: #856404; }
.howto { background: #f7f9fc; border-left: 4px solid #00c2d1; padding: 12px 15px; border-radius: 8px; font-size: 13px; color: #444; }
summary-help { display:block; }
@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="sync-payment.php" class="active">Cek Pembayaran</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <h1>🔁 Cek Pembayaran Otomatis</h1>
        <p class="subtitle">Sistem nominal unik + API key sendiri — cocokkan transfer/QRIS yang masuk, tandai LUNAS otomatis.</p>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($summary): ?>
        <div class="card">
            <h2>📊 Hasil Pemrosesan</h2>
            <p><span class="status-ok">✅ Lunas otomatis: <?= (int)$summary['matched'] ?></span> ·
               Belum cocok: <?= (int)$summary['unmatched'] ?> · Ambigu: <?= (int)$summary['ambiguous'] ?> · Duplikat: <?= (int)$summary['duplicate'] ?></p>
            <?php if ($logLines): ?>
            <ul style="font-size:13px;color:#155724;padding-left:20px;">
                <?php foreach ($logLines as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>🔑 API Key (milik kita sendiri)</h2>
            <p>Endpoint push notifikasi pembayaran — bisa dipasang pada forwarder SMS/email mutasi, skrip bank, atau aplikasi lain.</p>
            <p style="margin-bottom:4px;">Endpoint: <code style="background:#eef1f6;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($endpoint) ?></code></p>
            <label>API Key</label>
            <input type="text" class="api-box" value="<?= htmlspecialchars($apiKey) ?>" readonly onclick="this.select()">
            <p class="subtitle" style="margin-top:6px;">Kirim dengan header: <code><?= 'Authorization: Bearer ' . htmlspecialchars($apiKey) ?></code></p>
            <form method="POST" style="margin-top:8px;" onsubmit="return confirm('Buat API key baru? Key lama otomatis nonaktif.')">
                <input type="hidden" name="action" value="regenerate_key">
                <button type="submit" class="btn btn-danger btn-sm">🔄 Regenerate API Key</button>
            </form>
        </div>

        <div class="card">
            <h2>📋 Sync Cepat — Tempel Mutasi e-Banking / Notif QRIS</h2>
            <p>Tidak pakai IMAP: buka m-banking / aplikasi QRIS, salin (copy) teks notifikasi atau riwayat mutasi masuk, tempel di bawah, sistem langsung cocokkan nominal unik dan menandai pesanan LUNAS.</p>
            <div class="howto">
                <strong>Contoh format yang dikenali:</strong><br>
                <code>10/09 10:00 BCA 158317 TRF DARI Bpk ACHE<br>
                10/09 10:05 Mandiri — 148.200 — Transfer<br>
                Rp 1.158.317 · Ref 123456789 · 10-Sep-2026<br>
                Pembayaran QRIS Rp 750.000 berhasil. Ref 8837462510 · 10/09/2026</code>
            </div>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="action" value="paste_sync">
                <textarea name="mutasi_text" class="form-control" placeholder="Tempel teks mutasi / notifikasi QRIS di sini..."></textarea>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">🔍 Cocokkan & Konfirmasi</button>
                    <button type="submit" class="btn btn-primary btn-sm" form="formProcess">⚙️ Proses Notifikasi Baru</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>💳 QRIS Static — Alur Forward Notifikasi</h2>
            <p>QRIS static (QR cetak/tempel) tidak punya API status resmi. Notifikasi datang ke aplikasi merchant (MyProfit), SMS, atau email. Cukup <strong>forward/salin teksnya</strong> ke kotak tempel di atas — nominal unik yang memberitahu pesanan mana yang harus dikonfirmasi.</p>
            <ol style="font-size:13px;color:#444;line-height:1.9;margin:0;padding-left:20px;">
                <li><strong>Wajib nominal unik:</strong> saat checkout, pelanggan QRIS diminta membayar <em>total + kode</em> (mis. Rp 750.000 → Rp 750.318).</li>
                <li>Customer scan QR, lalu ketik nominal unik itu & bayar.</li>
                <li>Notifikasi QRIS masuk (MyProfit/SMS/email) — buka, pilih "Copy", tempel di kotak <strong>Sync Cepat</strong>.</li>
                <li>Tekan <strong>Cocokkan & Konfirmasi</strong> → nominal cocok → pesanan <strong>LUNAS otomatis</strong>.</li>
            </ol>
            <p style="font-size:12.5px;color:#6c757d;margin-top:10px;">
                ⚡ Kalau pelanggan lupa menambahkan kode unik dan transfer pas total (tanpa kode), sistem tetap cocokkan — konfirmasi mengikuti nominal total.
            </p>
            <p style="font-size:12.5px;color:#6c757d;margin:8px 0 0;">
                🔑 Automatis penuh: pasang notifikasi/skrip bank/gateway untuk mengirim teks ke
                <code><?= htmlspecialchars($endpoint) ?></code> dengan header
                <code><?= 'Authorization: Bearer ' . htmlspecialchars($apiKey) ?></code> — cron tiap 2 menit lalu memprosesnya sendiri.
            </p>
        </div>

        <form method="POST" id="formProcess" style="margin:0;">
            <input type="hidden" name="action" value="process_new">
        </form>

        <div class="card">
            <h2>🕐 Riwayat Notifikasi Pembayaran (terakhir)</h2>
            <?php
            $hitsRows = $db->query("SELECT h.*, o.order_code FROM payment_hits h LEFT JOIN orders o ON o.id = h.matched_order_id ORDER BY h.id DESC LIMIT 50")->fetchAll();
            ?>
            <?php if (empty($hitsRows)): ?>
                <p class="subtitle">Belum ada notifikasi. Kirim via API atau tempel mutasi di atas.</p>
            <?php else: ?>
            <table class="hits-table">
                <thead><tr><th>ID</th><th>Tanggal</th><th>Nominal</th><th>Bank</th><th>Deskripsi</th><th>Ref</th><th>Pesan</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($hitsRows as $hr): ?>
                    <tr>
                        <td><?= (int)$hr['id'] ?></td>
                        <td><?= htmlspecialchars($hr['txdate'] ?: '-') ?></td>
                        <td><strong>Rp <?= number_format((int)$hr['amount'], 0, ',', '.') ?></strong></td>
                        <td><?= htmlspecialchars($hr['bank_name'] ?: '-') ?></td>
                        <td style="max-width:220px;overflow-wrap:anywhere;"><?= htmlspecialchars(mb_substr($hr['description'], 0, 50)) ?></td>
                        <td><?= htmlspecialchars($hr['refno'] ?: '-') ?></td>
                        <td>
                            <?= $hr['matched_order_id'] > 0
                                ? '<a href="order-detail.php?id=' . (int)$hr['matched_order_id'] . '">' . htmlspecialchars($hr['order_code'] ?? '') . '</a>'
                                : htmlspecialchars(mb_substr($hr['note'] ?? '', 0, 40)) ?>
                        </td>
                        <td><span class="badge badge-<?= htmlspecialchars($hr['status']) ?>"><?= htmlspecialchars($hr['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>