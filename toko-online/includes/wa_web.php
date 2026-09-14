<?php
// ============================================
// WA OTOMATIS WEBSITE — antrean bersama kasir
// Website menulis ke wa_queue kasir dgn sumber='web'
// Cron kasir (/etc/cron.d/kasir) yang mengirim via gateway :3001
// ============================================

if (!function_exists('wa_norm_nomor')) {
    function wa_norm_nomor($to) {
        $d = preg_replace('/\D+/', '', (string)$to);
        if ($d === '') return '';
        if (substr($d, 0, 1) === '0') {
            $d = '62' . substr($d, 1);
        } elseif (substr($d, 0, 1) === '8') {
            $d = '62' . $d;
        }
        return $d;
    }
}

if (!function_exists('wa_potongan_pesan')) {
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
}

if (!function_exists('wa_web_pdo')) {
    function wa_web_pdo() {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;
        $path = '/var/www/kasir/data/kasir.db';
        if (!file_exists($path)) return null;
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } catch (Throwable $e) {
            $pdo = null;
        }
        return $pdo;
    }
}

// setting dari DB kasir
if (!function_exists('wa_web_setting')) {
    function wa_web_setting($key, $default = '') {
        $pdo = wa_web_pdo();
        if (!$pdo) return $default;
        try {
            $st = $pdo->prepare("SELECT value FROM pengaturan WHERE key = ?");
            $st->execute([$key]);
            $v = $st->fetchColumn();
            return ($v === false || $v === null) ? $default : $v;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

// enqueue pesan WA ke antrean kasir (sumber = 'web')
if (!function_exists('wa_web_send')) {
    function wa_web_send($to, $message, $imageUrl = '') {
        $pdo = wa_web_pdo();
        if (!$pdo) return false;
        if (wa_web_setting('wa_enabled', '0') !== '1') return false;
        $to = wa_norm_nomor($to);
        if ($to === '') return false;
        $message = wa_potongan_pesan($message);
        try {
            $st = $pdo->prepare(
                "INSERT INTO wa_queue (tujuan, pesan, image_url, status, percobaan, dibuat_pada, sumber) VALUES (?, ?, ?, 'tunggu', 0, datetime('now','localtime'), 'web')"
            );
            $st->execute([$to, $message, (string)$imageUrl]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

// nomor admin: preferensi FROM kasir (wa_admin_number), fallback whatsapp_number web
if (!function_exists('wa_web_admin_number')) {
    function wa_web_admin_number() {
        $n = wa_web_setting('wa_admin_number', '');
        if ($n !== '') return $n;
        $n = getSetting('whatsapp_number');
        if ($n) return $n;
        return '6285286470224';
    }
}

// notifikasi ke admin web (tanda asal pesanan: sumber='web' + salam web)
if (!function_exists('wa_web_notify_admin')) {
    function wa_web_notify_admin($subject, $lines) {
        $admin = wa_web_admin_number();
        if ($admin === '') return false;
        $msg = $subject . "\n";
        foreach ($lines as $l) { $msg .= $l . "\n"; }
        $msg .= "\n🌐 Dikirim otomatis dari Website.";
        return wa_web_send($admin, $msg);
    }
}
// ============================================
// Payment Point web: halaman bayar publik berisi
// QRIS + rekening + sisa tagihan (pola kasir).
// Token memakai salt PAYPOINT_SALT agar URL tidak
// menampilkan nomor HP / kode order sebagai query.
// ============================================
if (!function_exists('wa_web_pay_token')) {
    function wa_web_pay_token($orderCode, $phone) {
        return substr(hash('sha256', (string)$orderCode . ':' . (string)$phone . ':' . PAYPOINT_SALT), 0, 16);
    }
}

if (!function_exists('wa_web_pay_point_url')) {
    function wa_web_pay_point_url($orderCode, $phone) {
        $base = rtrim(getSetting('site_url') ?: 'https://rainbowprinting.web.id', '/');
        if ($orderCode === '' || $phone === '') {
            return $base . '/cek-pesanan.php';
        }
        $tok = wa_web_pay_token($orderCode, $phone);
        $url = $base . '/pay-point.php?order=' . rawurlencode($orderCode) . '&t=' . $tok;
        if (function_exists('cs_shorten')) {
            return cs_shorten($url);
        }
        return $url;
    }
}

// ============================================
// waOrderStatus: status pesanan -> WA pelanggan
// dipanggil admin/orders.php (via antrean kasir)
// ============================================
if (!function_exists('waOrderStatus')) {
    function waOrderStatus($db, $orderId, $event, $extra = '') {
        $order = $db->prepare("SELECT * FROM orders WHERE id=?");
        $order->execute([$orderId]);
        $order = $order->fetch();
        if (!$order || empty($order['customer_phone'])) {
            return false;
        }
        $name = $order['customer_name'];
        $code = $order['order_code'];
        $total = isset($order['total']) ? (float)$order['total'] : 0;

        // Total terbayar & sisa tagihan
        $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
        $paidStmt->execute([(int)$order['id']]);
        $totalPaid = (float)$paidStmt->fetchColumn();
        $sisa = max(0, $total - $totalPaid);

        $msgs = [
            'paid'      => "\u{2705} *PEMBAYARAN DITERIMA*\n\nHalo $name, pembayaran pesanan *$code* sebesar *" . formatRupiah($total) . "* sudah kami terima.\n\nPesanan Anda akan segera kami kerjakan. Terima kasih \u{1F64F}",
            'dp'        => "\u{1F4B5} *PEMBAYARAN DP DITERIMA*\n\nHalo $name, pembayaran DP pesanan *$code* sebesar *" . formatRupiah($totalPaid) . "* sudah kami terima.\n\nSisa tagihan: *" . formatRupiah($sisa) . "*\n\n\u{1F4B3} *Silakan lunasi melalui Payment Point berikut:*\n" . wa_web_pay_point_url($order['order_code'], $order['customer_phone']) . "\n\n*Nilai bayar:* " . formatRupiah($sisa) . "\nCantumkan nama pesanan *$code* pada keterangan/berita transfer agar pembayaran terdeteksi otomatis.\n\nSetelah transfer, kirimkan *screenshot bukti bayar* ke: " . wa_web_admin_number() . "\n\nTerima kasih \u{1F64F}",
            'processed' => "\u{1F528} *PESANAN DIPROSES*\n\nHalo $name, pesanan *$code* sedang dikerjakan oleh tim kami.\n\nKami akan kabari lagi jika sudah selesai. Terima kasih \u{1F64F}",
            'printing'  => "\u{1F5A8}\u{FE0F} *PESANAN DICETAK*\n\nHalo $name, pesanan *$code* sedang dalam proses cetak.\n\nMohon ditunggu ya \u{1F64F}",
            'done'      => "\u{1F389} *PESANAN SELESAI*\n\nHalo $name, pesanan *$code* sudah selesai dan siap untuk diambil / dikirim.\n\nTerima kasih sudah mempercayakan kami \u{1F64F}",
        ];
        $message = $msgs[$event] ?? '';
        if ($message !== '' && $extra !== '') {
            $message .= "\n\n" . $extra;
        }
        if ($message === '') {
            return false;
        }
        $message .= "\n\n\u{2014} PERCETAKAN RAINBOW";
        return wa_web_send($order['customer_phone'], $message);
    }
}