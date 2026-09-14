<?php
// ============================================
// PROOF STREAM — bukti pembayaran hanya untuk admin
// Bukti tersimpan di uploads/proofs (diblock router utk publik).
// Akses: admin/proof-stream.php?f=<nama_file>
// ============================================
require_once __DIR__ . '/../config.php';

if (!isAdmin() || isAdmin() !== true) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    exit;
}

$f = basename((string)($_GET['f'] ?? ''));
if ($f === '' || $f === '.' || $f === '..') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo '400 Bad Request';
    exit;
}

$path = __DIR__ . '/../uploads/proofs/' . $f;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    exit;
}

$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
switch ($ext) {
    case 'jpg':
    case 'jpeg': $mime = 'image/jpeg'; break;
    case 'png':  $mime = 'image/png';  break;
    case 'gif':  $mime = 'image/gif';  break;
    case 'webp': $mime = 'image/webp'; break;
    case 'pdf':  $mime = 'application/pdf'; break;
    default:     $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $f . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;