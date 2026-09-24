<?php
// 🔥 Cron email: proses antrean email_queue di latar belakang.
// Email tidak boleh dikirim sinkron dari request web (Server PHP satu-worker +
// SMTP lambat = seluruh website beku sehingga Cloudflare memutus dengan 524).
//
// Pasang di crontab (tiap 1 menit):
//   * * * * * www-data cd /var/www/percetakan-online && /usr/bin/php cron-email.php >/dev/null 2>&1
//
// Email gagal otomatis dicoba ulang, gagal permanen setelah 3 percobaan.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Akses hanya dari CLI.');
}

require_once __DIR__ . '/config.php';

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// 🔥 Lock agar tidak ada dua instance cron yang berjalan bersamaan
// (email bisa butuh >1 menit; crontab tetap menembak tiap menit).
$lockPath = $logDir . '/email-cron.lock';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

if (function_exists('email_queue_table')) {
    email_queue_table();
}

$batch = $db->query(
    "SELECT id, mail_to, subject, body, content_type, attempts FROM email_queue WHERE status='pending' ORDER BY id ASC LIMIT 5"
)->fetchAll();

$log = [];
foreach ($batch as $row) {
    $id = (int)$row['id'];
    if ((int)$row['attempts'] >= 3) {
        $db->prepare("UPDATE email_queue SET status='failed' WHERE id=?")->execute([$id]);
        continue;
    }
    $ok = _sendEmailRaw($row['mail_to'], $row['subject'], $row['body'], $row['content_type'] ?: 'text/plain');
    if ($ok) {
        $db->prepare("UPDATE email_queue SET status='sent', sent_at=datetime('now','localtime') WHERE id=?")->execute([$id]);
        $log[] = "sent #{$id} -> {$row['mail_to']}";
    } else {
        $db->prepare("UPDATE email_queue SET attempts=attempts+1 WHERE id=?")->execute([$id]);
        $log[] = "fail #{$id} -> {$row['mail_to']} (ke-" . ((int)$row['attempts'] + 1) . ")";
    }
}

flock($lock, LOCK_UN);
fclose($lock);

if ($log) {
    file_put_contents($logDir . '/email_cron.log', '[' . date('Y-m-d H:i:s') . "] " . implode(' | ', $log) . PHP_EOL, FILE_APPEND);
}