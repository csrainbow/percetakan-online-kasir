<?php
/**
 * init.php - Inisialisasi Database
 * 
 * Cara penggunaan:
 *   1. Akses file ini dari browser
 *   2. Masukkan password jika diperlukan
 *   3. Lihat hasil inisialisasi
 * 
 * PERINGATAN: Hapus file ini setelah selesai!
 */

// 🔥 ============================================
// 🔥 KONFIGURASI KEAMANAN
// 🔥 ============================================

$setupPassword = 'S@idah182';
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];

$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
$passwordProvided = $_GET['password'] ?? '';
$isAuthorized = in_array($clientIP, $allowedIPs) || $passwordProvided === $setupPassword;

// 🔥 Jika tidak authorized, tampilkan halaman login
if (!$isAuthorized) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Database - Percetakan Ikky Share</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .login-box {
                background: #fff;
                padding: 35px 30px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                max-width: 400px;
                width: 100%;
                text-align: center;
            }
            .login-box .logo {
                font-size: 28px;
                font-weight: 700;
                color: #111111;
                margin-bottom: 5px;
            }
            .login-box .logo span { color: #e53935; }
            .login-box .subtitle {
                color: #6c757d;
                font-size: 14px;
                margin-bottom: 25px;
            }
            .login-box input {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                font-size: 14px;
                transition: border-color 0.3s;
                margin-bottom: 12px;
            }
            .login-box input:focus {
                border-color: #e53935;
                outline: none;
            }
            .login-box button {
                width: 100%;
                padding: 12px;
                background: #111111;
                color: #fff;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.3s;
            }
            .login-box button:hover { background: #000000; }
            .login-box .error {
                color: #d32f2f;
                font-size: 13px;
                margin-top: 10px;
            }
            .login-box .lock-icon { font-size: 48px; display: block; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <span class="lock-icon">🔒</span>
            <div class="logo">🌈 <span>Rainbow</span> Printing</div>
            <div class="subtitle">Akses Setup Database</div>
            <form method="GET">
                <input type="password" name="password" placeholder="Masukkan password" required autofocus>
                <button type="submit">Login</button>
            </form>
            <?php if (isset($_GET['error'])): ?>
                <div class="error">❌ Password salah!</div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 🔥 🔥 PROSES INISIALISASI 🔥 🔥

// 🔥 Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// 🔥 Buat folder logs jika belum ada
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// 🔥 Fungsi log
function logSetup($message) {
    $logFile = __DIR__ . '/logs/setup.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// 🔥 Mulai timer
$startTime = microtime(true);
$messages = [];
$errors = [];
$success = true;

// 🔥 Cek apakah database sudah ada
$dbExists = file_exists(DB_PATH);
logSetup("Setup started - Database exists: " . ($dbExists ? 'Yes' : 'No'));

// 🔥 Jika parameter reset=1, hapus database
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    if (file_exists(DB_PATH)) {
        unlink(DB_PATH);
        $messages[] = "🗑️ Database lama dihapus";
        logSetup("Database deleted");
        $dbExists = false;
    }
}

// 🔥 Backup database jika sudah ada dan tidak dalam mode reset
if ($dbExists && !isset($_GET['reset'])) {
    $backupDir = __DIR__ . '/backup';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $backupFile = $backupDir . '/database_' . date('Ymd_His') . '.sqlite';
    if (copy(DB_PATH, $backupFile)) {
        $messages[] = "💾 Database backup: " . basename($backupFile);
        logSetup("Backup created: " . $backupFile);
    }
}

// 🔥 Jalankan inisialisasi
try {
    initDatabase();
    $messages[] = "✅ Database berhasil diinisialisasi";
    logSetup("Database initialized successfully");
    
    // 🔥 Cek tabel yang berhasil dibuat
    $tables = ['products', 'categories', 'orders', 'order_items', 'payments', 'customers', 'settings', 'content_pages', 'cart', 'product_images', 'product_materials'];
    $createdTables = [];
    $missingTables = [];
    
    foreach ($tables as $table) {
        try {
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            if ($result->fetch()) {
                $createdTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        } catch (Exception $e) {
            $missingTables[] = $table;
        }
    }
    
    // 🔥 Cek data
    $productCount = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'] ?? 0;
    $categoryCount = $db->query("SELECT COUNT(*) as c FROM categories")->fetch()['c'] ?? 0;
    $settingCount = $db->query("SELECT COUNT(*) as c FROM settings")->fetch()['c'] ?? 0;
    $orderCount = $db->query("SELECT COUNT(*) as c FROM orders")->fetch()['c'] ?? 0;
    $customerCount = $db->query("SELECT COUNT(*) as c FROM customers")->fetch()['c'] ?? 0;
    
    $messages[] = "📊 Tabel: " . count($createdTables) . " dari " . count($tables) . " berhasil dibuat";
    $messages[] = "📦 Produk: $productCount data";
    $messages[] = "📂 Kategori: $categoryCount data";
    $messages[] = "⚙️ Pengaturan: $settingCount data";
    $messages[] = "👤 Customer: $customerCount data";
    $messages[] = "📋 Pesanan: $orderCount data";
    
    if (!empty($missingTables)) {
        $errors[] = "⚠️ Tabel tidak ditemukan: " . implode(', ', $missingTables);
        $success = false;
    }
    
} catch (Exception $e) {
    $errors[] = "❌ Error: " . $e->getMessage();
    logSetup("Error: " . $e->getMessage());
    $success = false;
}

$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 2);

logSetup("Setup completed - Success: " . ($success ? 'Yes' : 'No') . " - Duration: " . $duration . "ms");

// 🔥 🔥 TAMPILKAN HASIL 🔥 🔥
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Database - Percetakan Ikky Share</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: #fff;
            padding: 40px 35px;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            max-width: 650px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        .logo h1 {
            font-size: 24px;
            color: #111111;
        }
        .logo h1 span { color: #e53935; }
        .logo p {
            color: #6c757d;
            font-size: 14px;
        }
        .status {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status .icon { font-size: 28px; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        .info-item {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            text-align: center;
        }
        .info-item .label {
            font-size: 10px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-item .value {
            font-size: 18px;
            font-weight: 700;
            color: #111111;
        }
        .info-item .value.success { color: #27ae60; }
        .info-item .value.error { color: #d32f2f; }
        .info-item .value.warning { color: #e53935; }
        .messages {
            margin: 15px 0;
            padding: 0;
            list-style: none;
            font-size: 14px;
        }
        .messages li {
            padding: 5px 0;
            color: #555;
            border-bottom: 1px solid #f1f3f5;
        }
        .messages li:last-child { border-bottom: none; }
        .warning-box {
            background: #fef9e7;
            border: 1px solid #e53935;
            padding: 12px 16px;
            border-radius: 6px;
            color: #856404;
            font-size: 13px;
            margin: 15px 0;
        }
        .warning-box strong { color: #b7950b; }
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
            text-align: center;
            flex: 1;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #111111;
            color: #fff;
        }
        .btn-primary:hover { background: #000000; }
        .btn-success {
            background: #27ae60;
            color: #fff;
        }
        .btn-success:hover { background: #1e8449; }
        .btn-warning {
            background: #e53935;
            color: #fff;
        }
        .btn-warning:hover { background: #c62828; }
        .btn-danger {
            background: #d32f2f;
            color: #fff;
        }
        .btn-danger:hover { background: #b71c1c; }
        .btn-outline {
            background: transparent;
            color: #111111;
            border: 1px solid #111111;
        }
        .btn-outline:hover { background: #f8f9fa; }
        @media (max-width: 480px) {
            .container { padding: 25px 20px; }
            .info-grid { grid-template-columns: repeat(2, 1fr); }
            .btn-group { flex-direction: column; }
            .btn { flex: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- LOGO -->
        <div class="logo">
            <h1>🌈 <span>Rainbow</span> Printing</h1>
            <p>Setup Database</p>
        </div>

        <!-- STATUS -->
        <?php if ($success): ?>
            <div class="status success">
                <span class="icon">✅</span>
                <div>
                    <strong>Database berhasil diinisialisasi!</strong>
                    <br><small>Durasi: <?= $duration ?> ms</small>
                </div>
            </div>
        <?php else: ?>
            <div class="status error">
                <span class="icon">❌</span>
                <div>
                    <strong>Terjadi kesalahan!</strong>
                    <br><small>Periksa pesan error di bawah</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- INFO -->
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Database</div>
                <div class="value <?= $dbExists ? 'success' : 'warning' ?>">
                    <?= $dbExists ? '✅ Ada' : '🆕 Baru' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="label">Tabel</div>
                <div class="value <?= empty($missingTables) ? 'success' : 'error' ?>">
                    <?= count($createdTables) ?> / <?= count($tables) ?>
                </div>
            </div>
            <div class="info-item">
                <div class="label">Produk</div>
                <div class="value"><?= $productCount ?? 0 ?></div>
            </div>
        </div>

        <!-- MESSAGES -->
        <ul class="messages">
            <?php foreach ($messages as $msg): ?>
                <li><?= $msg ?></li>
            <?php endforeach; ?>
            <?php foreach ($errors as $err): ?>
                <li style="color:#d32f2f;"><?= $err ?></li>
            <?php endforeach; ?>
        </ul>

        <!-- PERINGATAN -->
        <div class="warning-box">
            <strong>⚠️ Peringatan Keamanan!</strong><br>
            File <strong>init.php</strong> sebaiknya <strong>dihapus</strong> setelah selesai untuk mencegah akses yang tidak sah.
        </div>

        <!-- BUTTONS -->
        <div class="btn-group">
            <a href="index.php" class="btn btn-primary">🏠 Ke Beranda</a>
            <a href="admin/dashboard.php" class="btn btn-success">📊 Admin Panel</a>
            <?php if (!$success): ?>
                <a href="?password=<?= urlencode($setupPassword) ?>" class="btn btn-warning">🔄 Coba Lagi</a>
            <?php endif; ?>
            <a href="?password=<?= urlencode($setupPassword) ?>&reset=1" class="btn btn-danger" 
               onclick="return confirm('⚠️ Yakin ingin mereset database? Semua data akan hilang!')">
                🔄 Reset Database
            </a>
        </div>
    </div>
</body>
</html>