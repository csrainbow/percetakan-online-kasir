<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'Kasir Percetakan');
define('DB_PATH', __DIR__ . '/data/kasir.db');
define('NOTA_SECRET', '0f6a1a3d4c34c8fceb18b655f21fb5a6');

function nota_token($ref, $id) {
    return substr(hash('sha256', $ref . ':' . $id . ':' . NOTA_SECRET), 0, 12);
}

// Kode unik 3-sifer per nota (pesanan/penjualan) untuk mengenali pembayaran
// saat pelanggan transfer via QRIS statis / bank. Nilai bayar = tagihan + kode unik.
function nota_kode_unik($ref, $id) {
    $h = hash('sha256', 'kode-unik:' . $ref . ':' . (int)$id . ':' . NOTA_SECRET);
    return str_pad((int)hexdec(substr($h, 0, 6)) % 1000, 3, '0', STR_PAD_LEFT);
}

function is_superadmin() {
    return ($_SESSION['role'] ?? '') === 'superadmin';
}

function scope_user_id() {
    if (!is_superadmin()) {
        return (int)($_SESSION['user_id'] ?? 0);
    }
    return (int)($_SESSION['scope_user_id'] ?? 0);
}

function scope_sql($alias = '') {
    $u = scope_user_id();
    if ($u <= 0) {
        return '1=1';
    }
    $a = $alias ? $alias . '.' : '';
    return $a . 'user_id = ' . $u;
}

require_once __DIR__ . '/db.php';

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function rp($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function qty($n) {
    $n = (float)$n;
    return number_format($n, (fmod($n, 1) == 0) ? 0 : 2, ',', '.');
}

function tgl($t) {
    return $t ? date('d/m/Y H:i', strtotime($t)) : '-';
}

function tglOnly($t) {
    return $t ? date('d/m/Y', strtotime($t)) : '-';
}

function tgl_ind($t) {
    if (!$t) return '-';
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($t);
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function flash_set($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function log_aktivitas($aksi, $detail = '') {
    DB::run('INSERT INTO log_aktivitas (user_id, aksi, detail) VALUES (?, ?, ?)',
        [$_SESSION['user_id'] ?? 0, $aksi, $detail]);
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function setting($key, $default = '') {
    $row = DB::one('SELECT value FROM pengaturan WHERE key = ?', [$key]);
    return ($row && $row['value'] !== '') ? $row['value'] : $default;
}

function set_setting($key, $value) {
    DB::run('INSERT INTO pengaturan (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
}

function pembayaran_status_label($totalBayar, $total, $status = '') {
    if ($status === 'Batal') {
        return 'Batal';
    }
    $dibayar = (float)$totalBayar;
    $totalF = (float)$total;
    if ($dibayar <= 0.01) {
        return 'Belum Bayar';
    }
    if ($dibayar >= $totalF - 0.01) {
        return 'Lunas';
    }
    return 'DP';
}

function next_number($prefix, $table) {
    $row = DB::one("SELECT COALESCE(MAX(id), 0) + 1 AS m FROM $table");
    return $prefix . '-' . date('ymd') . '-' . str_pad($row['m'], 4, '0', STR_PAD_LEFT);
}

function midtrans_is_production() {
    return setting('midtrans_is_production') === '1';
}
function midtrans_server_key() {
    return midtrans_is_production()
        ? setting('midtrans_server_key_production')
        : setting('midtrans_server_key_sandbox');
}
function midtrans_client_key() {
    return midtrans_is_production()
        ? setting('midtrans_client_key_production')
        : setting('midtrans_client_key_sandbox');
}
function midtrans_base_url() {
    return midtrans_is_production()
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com';
}
function midtrans_is_ready() {
    return !empty(midtrans_server_key());
}

function wa_href($phone, $text) {
    $p = preg_replace('/\D+/', '', (string)$phone);
    if ($p === '') {
        return '';
    }
    if (substr($p, 0, 2) === '62') {
        $p = '62' . ltrim(substr($p, 2), '0');
    } elseif (substr($p, 0, 1) === '0') {
        $p = '62' . substr($p, 1);
    }
    return 'https://wa.me/' . $p . '?text=' . rawurlencode($text);
}

function is_telat($ps) {
    return !empty($ps['estimasi'])
        && in_array($ps['status'], ['DP', 'Lunas'])
        && strtotime($ps['estimasi']) < time();
}

function wa_gateway_status($timeout = 5) {
    $base = rtrim(setting('wa_gw_base', 'http://127.0.0.1:3001'), '/');
    $st = ['ok' => false, 'status' => 'unconfigured', 'connected' => false, 'me' => null, 'hasQr' => false, 'base' => $base];
    if ($base === '') {
        return $st;
    }
    $ch = curl_init($base . '/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($res) && $res !== '') {
        $j = json_decode($res, true);
        if (is_array($j)) {
            $st['ok'] = true;
            $st['status'] = (string)($j['status'] ?? 'unknown');
            $st['connected'] = !empty($j['connected']);
            $st['me'] = $j['me'] ?? null;
            $st['hasQr'] = !empty($j['hasQr']);
            $st['raw'] = $j;
        }
    }
    return $st;
}

// Status gateway yang di-cache 60 detik di DB agar cek di setiap halaman tidak
// memperlambat kasir. Paksa refresh dengan $force=true.
function wa_gateway_status_cached($force = false) {
    $ttl = 60;
    try {
        $row = DB::one("SELECT value FROM pengaturan WHERE key = 'wa_gw_cache'");
        $cache = $row ? json_decode($row['value'], true) : null;
        if (!$force && is_array($cache) && !empty($cache['at']) && (time() - (int)$cache['at'] < $ttl) && isset($cache['st'])) {
            return $cache['st'];
        }
    } catch (Throwable $e) {
        $cache = null;
    }
    $st = wa_gateway_status(2);
    try {
        set_setting('wa_gw_cache', json_encode(['at' => time(), 'st' => $st]));
    } catch (Throwable $e) {
        // abaikan bila DB belum siap
    }
    return $st;
}

// Ingatkan admin via antrean WA (tetap jalan walau gateway sedang putus,
// karena cron akan mengirimnya begitu gateway connect lagi).
// Dibatas: maks 1x per 30 menit, hanya bila wa_enabled=1 dan ada nomor admin.
function wa_gateway_alert_admin($status) {
    if (!setting('wa_enabled')) {
        return;
    }
    $admin = trim(setting('wa_admin_number', ''));
    if ($admin === '') {
        return;
    }
    $lastRow = DB::one("SELECT value FROM pengaturan WHERE key = 'wa_gw_alert_at'");
    $last = $lastRow ? (int)$lastRow['value'] : 0;
    if (time() - $last < 1800) {
        return; // masih dalam masa tenang 30 menit
    }
    set_setting('wa_gw_alert_at', (string)time());
    $msg = "⚠️ *WA GATEWAY PUTUS*\n\nGateway WhatsApp kasir status: *$status* pada " . date('d/m/Y H:i') . ".\n"
        . "Pesan pelanggan MENGANTRE dan akan terkirim otomatis setelah gateway connect.\n\n"
        . "Segera tautkan ulang: buka Kasir → *WA Gateway* → scan QR.\n\n— " . setting('nama_toko', 'PERCETAKAN RAINBOW');
    wa_send($admin, $msg);
}

function wa_gateway_send($to, $message, $imageUrl = '', $caption = null) {
    $base = rtrim(setting('wa_gw_base', 'http://127.0.0.1:3001'), '/');
    $key = setting('wa_gw_key', '');
    if ($base === '') {
        return [false, 'gateway belum dikonfigurasi'];
    }
    $to = preg_replace('/\D+/', '', (string)$to);
    if ($to === '') {
        return [false, 'nomor kosong'];
    }
    $body = ['to' => $to, 'message' => (string)$message];
    if ($imageUrl !== '') {
        $body['imageUrl'] = $imageUrl;
        $body['caption'] = $caption === null ? (string)$message : (string)$caption;
    }
    $ch = curl_init($base . '/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array_merge(
            ['Content-Type: application/json'],
            $key !== '' ? ['X-Api-Key: ' . $key] : []
        ),
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err === '' && $code >= 200 && $code < 300 && is_string($res) && $res !== '') {
        $j = json_decode($res, true);
        if (is_array($j) && !empty($j['ok'])) {
            return [true, ''];
        }
        return [false, 'gateway: ' . mb_substr((string)$res, 0, 120)];
    }
    return [false, 'gateway HTTP ' . $code . ($err !== '' ? ' ' . $err : ' ' . mb_substr((string)$res, 0, 80))];
}

// Panjang maksimum 1 pesan WhatsApp (±4000 char). Pesan pelanggan kita
// biasanya < 1000 char; fungsi ini hanya pengaman agar pesan raksasa
// tidak ditolak / memicu flag spam.
function wa_potongan_pesan($message, $maks = 3500) {
    $message = (string)$message;
    if (function_exists('mb_strlen') && mb_strlen($message, 'UTF-8') > $maks) {
        return mb_substr($message, 0, $maks, 'UTF-8') . "\n…(dipotong)";
    }
    if (strlen($message) > $maks) {
        return substr($message, 0, $maks) . "\n...(dipotong)";
    }
    return $message;
}

// Normalisasi nomor ke format 62... (tanpa +, spasi, strip).
function wa_norm_nomor($to) {
    $d = preg_replace('/\D+/', '', (string)$to);
    if ($d === '') {
        return '';
    }
    if (substr($d, 0, 1) === '0') {
        $d = '62' . substr($d, 1);
    } elseif (substr($d, 0, 1) === '8') {
        $d = '62' . $d;
    }
    return $d;
}

// Antrean pesan WA (anti-ban): tanpa provider pihak ke-3.
// wa_send() HANYA menulis ke tabel wa_queue; pengiriman fisik dilakukan
// cron-wa.php 1x/menit, max 1 pesan / 20 detik + retry 3x + gagal permanen.
// Mengembalikan true bila berhasil masuk antrean.
function wa_send($to, $message, $imageUrl = '') {
    if (!setting('wa_enabled')) {
        return false;
    }
    $to = wa_norm_nomor($to);
    if ($to === '') {
        return false;
    }
    $message = wa_potongan_pesan($message);
    try {
        DB::run(
            "INSERT INTO wa_queue (tujuan, pesan, image_url, status, percobaan, dibuat_pada) VALUES (?, ?, ?, 'tunggu', 0, ?)",
            [$to, $message, (string)$imageUrl, date('Y-m-d H:i:s')]
        );
        return true;
    } catch (Throwable $e) {
        log_aktivitas('WA antre gagal', $e->getMessage());
        return false;
    }
}

// Jumlah pesan menunggu di antrean (untuk badge/menu).
function wa_queue_tunggu() {
    try {
        $r = DB::one("SELECT COUNT(*) c FROM wa_queue WHERE status = 'tunggu'");
        return $r ? (int)$r['c'] : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function barcode_src($data) {
    return 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode((string)$data)
        . '&code=Code128&format=png&dpi=200&modulewidth=1&caption=false&backgroundcolor=FFFFFF';
}

function nota_publik_url($ref, $id, $t = 'a5') {
    $k = nota_token($ref, $id);
    return rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/')
        . '/n.php/' . rawurlencode($ref) . '/' . $id . '/' . rawurlencode($t) . '/' . $k;
}

function wa_pelanggan_msg($ps, $event, $extra = '') {
    // ------------------------------------------------------------------
    //  ATURAN LINK WHATSAPP (struk vs payment point berdiri sendiri):
    //  - Belum Bayar ('baru')            → halaman PAYMENT POINT
    //    (rekening + QRIS statis + ringkasan + nilai = sisa + kode unik)
    //  - DP ('dp', masih ada sisa)       → halaman PAYMENT POINT
    //    (nominal otomatis = SISA pembayaran + kode unik)
    //  - Lunas ('lunas')                 → halaman STRUK (bukti lunas)
    //  - Selesai ('selesai') / Batal     → halaman STRUK
    // ------------------------------------------------------------------
    $name = $ps['pelanggan'] ?? '';
    $code = $ps['no_pesanan'] ?? '';
    $total = (float)($ps['total'] ?? 0);
    $dpVal = (float)($ps['dp'] ?? 0);
    $sisaVal = (float)($ps['sisa'] ?? ($total - $dpVal));
    $link = nota_link('pesanan', (int)$ps['id'], 'struk');
    $linkNota = nota_link('pesanan', (int)$ps['id']);
    $linkBayar = nota_link('pesanan', (int)$ps['id'], 'pay');
    $kodeUnik = nota_kode_unik('pesanan', (int)$ps['id']);
    $bayarSisa = max(0, $sisaVal) > 0 ? (float)$sisaVal + (float)$kodeUnik : 0.0;
    $waAdmin = setting('wa_admin_number', '') !== '' ? setting('wa_admin_number') : setting('telp');
    $bankNama = setting('bank_nama', 'Bank Central Asia');
    $bankRek = setting('bank_rekening', '7935405254');
    $bankPemilik = setting('bank_pemilik', 'Nur Ismani');
    $msgs = [
        'baru'   => "🖨️ *PESANAN DITERIMA*\n\nHalo $name, pesanan *$code* sebesar " . rp($total) . " sudah kami terima.\n\nStatus pesanan Anda: *BELUM LUNAS* — silakan segera melakukan pembayaran:\n\n💳 *BAYAR ONLINE (Payment Point):*\n$linkBayar\n\n📱 *QRIS & TRANSFER BANK*\n🏦 Nama Bank: $bankNama\n💳 No. Rekening: $bankRek\n👤 Atas Nama: $bankPemilik\n\n💡 *Nilai bayar:* " . rp($bayarSisa) . " = Tagihan " . rp($sisaVal) . " + Kode Unik $kodeUnik\nCantumkan kode unik *$kodeUnik* pada keterangan/berita transfer agar pembayaran terdeteksi otomatis.\n\nSetelah transfer, kirimkan *screenshot bukti bayar* ke: $waAdmin\n\nTerima kasih 🙏",
        'dp'     => "💰 *PEMBAYARAN DP DITERIMA*\n\nHalo $name, pembayaran DP pesanan *$code* sebesar " . rp($dpVal) . " sudah kami terima.\n\nSisa tagihan: " . rp($sisaVal) . " — mohon segera dilunasi.\n\n💳 *SISA BAYAR (Payment Point):*\n$linkBayar\n\n📱 *QRIS & TRANSFER BANK*\n🏦 Nama Bank: $bankNama\n💳 No. Rekening: $bankRek\n👤 Atas Nama: $bankPemilik\n\n💡 *Nilai bayar:* " . rp($bayarSisa) . " = Tagihan " . rp($sisaVal) . " + Kode Unik $kodeUnik\nCantumkan kode unik *$kodeUnik* pada keterangan/berita transfer agar pembayaran terdeteksi otomatis.\n\nSetelah transfer, kirimkan *screenshot bukti bayar* ke: $waAdmin\n\nTerima kasih 🙏",
        'lunas'  => "âœ… *PEMBAYARAN LUNAS*\n\nHalo $name, pembayaran pesanan *$code* sebesar " . rp($total) . " sudah kami terima.\n\nPesanan akan segera kami proses.\n\nðŸ“„ *Struk:* $link\n\nTerima kasih ðŸ™",
        'selesai' => "ðŸŽ‰ *PESANAN SELESAI*\n\nHalo $name, pesanan *$code* sudah selesai dan siap untuk diambil / dikirim.\n\nBerikut struk dengan *barcode nota A5* untuk diunduh:\n$link\n\nTerima kasih sudah mempercayakan kami ðŸ™",
        'batal'  => "â„¹ï¸ *PESANAN DIBATALKAN*\n\nHalo $name, pesanan *$code* telah dibatalkan. Jika ada kendala, silakan hubungi kami kembali.\n\nTerima kasih ðŸ™",
    ];
    $message = $msgs[$event] ?? '';
    if ($message === '') {
        return '';
    }
    if ($extra !== '') {
        $message .= "\n\n" . $extra;
    }
    $message .= "\n\nâ€” " . setting('nama_toko', 'Percetakan Ikky Share');
    return $message;
}

function wa_pelanggan($ps, $event, $extra = '') {
    if (empty($ps['telepon'])) {
        return false;
    }
    $message = wa_pelanggan_msg($ps, $event, $extra);
    if ($message === '') {
        return false;
    }
    $linkNota = nota_link('pesanan', (int)$ps['id']);
    $imageUrl = '';
    if ($event === 'selesai') {
        $imageUrl = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($linkNota)
            . '&code=MobileQRCode&format=png&dpi=300&modulewidth=4&caption=false&backgroundcolor=FFFFFF';
    }
    return wa_send($ps['telepon'], $message, $imageUrl);
}

require_once __DIR__ . '/qris.php';
require_once __DIR__ . '/link_short.php';
