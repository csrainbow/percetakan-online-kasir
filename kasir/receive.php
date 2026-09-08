<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$path = $_POST['path'] ?? '';
$b64 = $_POST['d'] ?? '';
if (empty($path) || empty($b64)) { echo json_encode(['error' => 'missing']); exit; }
$data = base64_decode($b64);
if ($data === false) { echo json_encode(['error' => 'decode']); exit; }
$dst = '/var/www/kasir/' . ltrim($path, '/');
$dir = dirname($dst);
if (!is_dir($dir)) mkdir($dir, 0777, true);
file_put_contents($dst, $data);
echo json_encode(['ok' => true, 'path' => $path, 'size' => strlen($data)]);
