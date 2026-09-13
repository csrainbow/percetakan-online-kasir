<?php
// ============================================
// CRON PEMBAYARAN — cek & konfirmasi pembayaran otomatis
//
// Dipanggil berkala (tiap 2-3 menit):
//   CLI : php cron-pembayaran.php            (dipercaya)
//   HTTP: https://rainbowprinting.web.id/cron-pembayaran.php?token=<API_KEY>
//
// Yang dilakukan: mencocokkan notifikasi mutasi (payment_hits status 'new')
// dengan nominal unik order -> menandai LUNAS otomatis + notifikasi email admin.
// ============================================

require_once __DIR__ . '/config.php';

$isCli = (PHP_SAPI === 'cli');
$asJson = ($isCli === false);

if ($isCli) {
    // Countdown-helper: log ke stdout
} else {
    require_once __DIR__ . '/includes/ownapi.php';
    if (!ownapi_auth()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }
}

require_once __DIR__ . '/includes/payment_autocheck.php';

$start = microtime(true);

$res = pm_run('cron');

$summary = $res['summary'];
$logRows = $res['log'];

if ($isCli) {
    $lines = [];
    $lines[] = sprintf('[%s] payment-cron done in %.2fs', date('Y-m-d H:i:s'), microtime(true) - $start);
    if (!empty($res['locked'])) {
        $lines[] = 'SKIP: proses lain sedang berjalan (terkunci).';
    } else {
        $lines[] = sprintf(
            'summary processed=%d matched=%d unmatched=%d ambiguous=%d duplicate=%d',
            $summary['processed'],
            $summary['matched'],
            $summary['unmatched'],
            $summary['ambiguous'],
            $summary['duplicate']
        );
        foreach ($logRows as $l) {
            $lines[] = '  ' . $l;
        }
    }
    echo implode(PHP_EOL, $lines) . PHP_EOL;
    exit;
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'summary' => $summary, 'locked' => !empty($res['locked']), 'log' => $logRows]);
exit;