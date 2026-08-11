<?php
session_start();
date_default_timezone_set('Asia/Makassar');

define('APP_NAME', 'Kasir Percetakan');
define('DB_PATH', __DIR__ . '/data/kasir.db');
define('NOTA_SECRET', 'kasir-percetakan-rainbow-2026');

function nota_token($ref, $id) {
    return substr(hash('sha256', $ref . ':' . $id . ':' . NOTA_SECRET), 0, 12);
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

function wa_send($to, $message, $imageUrl = '') {
    if (!setting('wa_enabled') || !setting('wa_token')) {
        return false;
    }
    $provider = setting('wa_provider', 'fonnte');
    $to = preg_replace('/\D+/', '', (string)$to);
    if ($to === '') {
        return false;
    }
    if ($provider === 'wablas') {
        $url = 'https://patp.wablas.com/api/send-message';
        if (substr($to, 0, 1) === '0') {
            $to = '62' . substr($to, 1);
        }
        $payload = json_encode(['phone' => $to, 'message' => $message, 'token' => setting('wa_token')]);
        $headers = ['Content-Type: application/json'];
    } else {
        $url = 'https://api.fonnte.com/send';
        $body = ['target' => $to, 'message' => $message, 'countryCode' => '62'];
        if ($imageUrl !== '') {
            $body['url'] = $imageUrl;
        }
        $payload = json_encode($body);
        $headers = ['Content-Type: application/json', 'Authorization: ' . setting('wa_token')];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ok = false;
    if ($err === '' && is_string($res) && $res !== '') {
        $j = json_decode($res, true);
        if (is_array($j)) {
            $ok = $j['status'] === true || $j['status'] === 'true' || $j['status'] === 1 || $j['status'] === '1';
        }
    }
    if ($code !== 200 || !$ok) {
        log_aktivitas('WA notif gagal', $provider . ' | code ' . $code . ' | ' . ($err !== '' ? $err : mb_substr((string)$res, 0, 120)));
    }
    return $ok;
}

function barcode_src($data) {
    return 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode((string)$data)
        . '&code=Code128&format=png&dpi=200&modulewidth=1&caption=false&backgroundcolor=FFFFFF';
}

function nota_publik_url($ref, $id, $t = 'a5') {
    $k = nota_token($ref, $id);
    return rtrim(setting('url_publik', 'https://rainbowprinting.web.id/kasir'), '/')
        . '/nota-publik.php?ref=' . $ref . '&id=' . $id . '&t=' . $t . '&k=' . $k;
}

function wa_pelanggan($ps, $event, $extra = '') {
    if (empty($ps['telepon'])) {
        return false;
    }
    $name = $ps['pelanggan'] ?? '';
    $code = $ps['no_pesanan'] ?? '';
    $total = (float)($ps['total'] ?? 0);
    $link = nota_publik_url('pesanan', (int)$ps['id'], 'struk');
    $linkNota = nota_publik_url('pesanan', (int)$ps['id']);
    $waAdmin = setting('wa_admin_number', '') !== '' ? setting('wa_admin_number') : setting('telp');
    $msgs = [
        'baru'   => "🖨️ *PESANAN DITERIMA*\n\nHalo $name, pesanan *$code* sebesar " . rp($total) . " sudah kami terima.\n\nStatus pesanan Anda: *BELUM LUNAS* — mohon segera melakukan pembayaran.\n\nSetelah transfer, mohon konfirmasi dengan mengirimkan *bukti transfer* ke WhatsApp admin: $waAdmin\n\n📄 *Struk:* $link\n*Keterangan:* Belum lunas — silakan bayar\n\nTerima kasih 🙏",
        'dp'     => "💰 *PEMBAYARAN DP DITERIMA*\n\nHalo $name, pembayaran DP pesanan *$code* sudah kami terima.\n\nStatus pesanan bisa dicek di: $link\n\nTerima kasih 🙏",
        'lunas'  => "✅ *PEMBAYARAN LUNAS*\n\nHalo $name, pembayaran pesanan *$code* sebesar " . rp($total) . " sudah kami terima.\n\nPesanan Anda akan segera kami kerjakan.\n\n📄 *Struk:* $link\n\nTerima kasih 🙏",
        'selesai' => "🎉 *PESANAN SELESAI*\n\nHalo $name, pesanan *$code* sudah selesai dan siap untuk diambil / dikirim.\n\nBerikut struk dengan *barcode nota A5* untuk diunduh:\n$link\n\nTerima kasih sudah mempercayakan kami 🙏",
        'batal'  => "ℹ️ *PESANAN DIBATALKAN*\n\nHalo $name, pesanan *$code* telah dibatalkan. Jika ada kendala, silakan hubungi kami kembali.\n\nTerima kasih 🙏",
    ];
    $message = $msgs[$event] ?? '';
    if ($message === '') {
        return false;
    }
    if ($extra !== '') {
        $message .= "\n\n" . $extra;
    }
    $message .= "\n\n— " . setting('nama_toko', 'PERCETAKAN RAINBOW');
    $imageUrl = '';
    if ($event === 'selesai') {
        $imageUrl = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($linkNota)
            . '&code=MobileQRCode&format=png&dpi=300&modulewidth=4&caption=false&backgroundcolor=FFFFFF';
    }
    return wa_send($ps['telepon'], $message, $imageUrl);
}
