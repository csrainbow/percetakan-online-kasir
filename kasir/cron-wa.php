<?php
// Cron WhatsApp — proses antrean wa_queue satu per satu.
// Jalankan tiap 1 menit dari crontab: * * * * * php /var/www/kasir/kasir/cron-wa.php
// Rate limit: max 1 pesan / 20 detik, retry 3x, gagal permanen setelah 3x.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Akses hanya dari CLI.');
}

require_once __DIR__ . '/config.php';

$lockPath = __DIR__ . '/data/wa-cron.lock';
if (!is_dir(dirname($lockPath))) {
    exit(0);
}
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // instance lain sedang jalan
}

// Pengaman: bila gateway belum connect, JANGAN menyentuh antrean.
// Pesan tetap 'tunggu' dan otomatis terkirim begitu gateway connect lagi
// (percobaan tidak ikut terbakar selama gateway mati).
$st = wa_gateway_status(3);
if (empty($st['connected'])) {
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$log = [];
$batch = DB::run(
    "SELECT id, tujuan, pesan, image_url FROM wa_queue WHERE status = 'tunggu' ORDER BY id ASC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($batch as $row) {
    $id = (int)$row['id'];
    $to = wa_norm_nomor($row['tujuan']);
    $pesan = wa_potongan_pesan($row['pesan']);
    $imageUrl = (string)$row['image_url'];

    $ok = false;
    $msg = '';
    $resp = wa_gateway_send($to, $pesan, $imageUrl !== '' ? $imageUrl : '');

    if ($resp[0]) {
        $ok = true;
        $msg = 'OK';
        log_aktivitas('WA terkirim via gateway', "id=$id to=$to");
    } else {
        $msg = $resp[1];
        log_aktivitas('WA gagal kirim', "id=$id to=$to err=$msg");
    }

    $percobaan = DB::one("SELECT percobaan FROM wa_queue WHERE id = ?", [$id]);
    $percobaan = $percobaan ? (int)$percobaan['percobaan'] : 0;

    if ($ok) {
        DB::run(
            "UPDATE wa_queue SET status='terkirim', dikirim_pada=datetime('now','localtime'), galat='' WHERE id=?",
            [$id]
        );
    } else {
        if ($percobaan >= 3) {
            DB::run(
                "UPDATE wa_queue SET status='gagal', dikirim_pada=datetime('now','localtime'), galat=? WHERE id=?",
                [$msg, $id]
            );
        } else {
            DB::run(
                "UPDATE wa_queue SET percobaan=?, galat=? WHERE id=?",
                [$percobaan + 1, $msg, $id]
            );
        }
    }

    // Rate limit: tunggu 20 detik antar pesan
    sleep(20);
}

flock($lock, LOCK_UN);
fclose($lock);
