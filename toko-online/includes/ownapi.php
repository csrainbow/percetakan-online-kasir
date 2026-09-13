<?php
// ============================================
// OWN API — API Key milik sendiri (self-hosted)
// Dipakai untuk:
//   - api/payment-inbox.php : penerima notifikasi pembayaran
//   - cron-pembayaran.php   : cek & konfirmasi pembayaran otomatis
// ============================================

function ownapi_get_key() {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'ownapi_token'");
    $stmt->execute();
    $v = $stmt->fetchColumn();
    if ($v === false || $v === null || $v === '') {
        $v = bin2hex(random_bytes(24)); // 48 karakter hex
        $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('ownapi_token', ?)")
           ->execute([$v]);
    }
    return (string)$v;
}

function ownapi_regenerate_key() {
    global $db;
    $v = bin2hex(random_bytes(24));
    $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('ownapi_token', ?)")
       ->execute([$v]);
    return $v;
}

// Cek permintaan ber-API key. Sumber token:
//   Header: Authorization: Bearer <token>
//   Query:  ?token=<token>
//   Form:   token=<token>
//   CLI (php cron) : dipercaya (localhost)
function ownapi_auth() {
    if (PHP_SAPI === 'cli') {
        return true;
    }
    $expected = ownapi_get_key();
    $given = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $a = $_SERVER['HTTP_AUTHORIZATION'];
        if (stripos($a, 'Bearer ') === 0) {
            $given = trim(substr($a, 7));
        }
    }
    if ($given === '') {
        $given = trim($_GET['token'] ?? $_POST['token'] ?? '');
    }
    if ($given === '' || $expected === '') {
        return false;
    }
    return hash_equals($expected, $given);
}

// Ambil payload JSON dari body (raw) atau POST
function ownapi_input() {
    $raw = file_get_contents('php://input');
    if ($raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return $j;
        }
    }
    return $_POST;
}

// Normalisasi nominal "Rp 1.234.567" / "1.234.567" / "158317" -> 1234567 (int)
function ownapi_parse_amount($v) {
    if ($v === null) return 0;
    if (is_numeric($v) && !is_string($v)) return (int)round((float)$v);
    $s = preg_replace('/[^0-9]/', '', (string)$v);
    if ($s === '') return 0;
    return (int)$s;
}

// Keluaran JSON + stop
function ownapi_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}