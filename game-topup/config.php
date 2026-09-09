<?php
/**
 * Game Top-Up - Konfigurasi
 * Percetakan Online + Kasir / monorepo
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Makassar');

// ==================== DIGIFLAZZ ====================
// AMAN: kredensial dibaca dari config.local.php (di .gitignore) atau env vars.
// Salin config.local.example.php -> config.local.php lalu isi kredensial Anda.
$__dgfUser  = getenv('DGF_USERNAME') ?: '';
$__dgfKey   = getenv('DGF_APIKEY')  ?: '';
$__mtServer = getenv('MT_SERVER_KEY') ?: '';
$__mtClient = getenv('MT_CLIENT_KEY') ?: '';

$__localCfg = __DIR__ . '/config.local.php';
if (file_exists($__localCfg)) {
    include $__localCfg;
    $__dgfUser = defined('DGF_USERNAME') ? DGF_USERNAME : $__dgfUser;
    $__dgfKey  = defined('DGF_APIKEY')   ? DGF_APIKEY   : $__dgfKey;
    $__mtServer = defined('MT_SERVER_KEY') ? MT_SERVER_KEY : $__mtServer;
    $__mtClient = defined('MT_CLIENT_KEY') ? MT_CLIENT_KEY : $__mtClient;
}

define('DGF_USERNAME', $__dgfUser);
define('DGF_APIKEY', $__dgfKey);
define('DGF_BASE', 'https://api.digiflazz.com/v1');

// ==================== MIDTRANS (opsional, sandbox default) ====================
define('MT_SERVER_KEY', $__mtServer);
define('MT_CLIENT_KEY', $__mtClient);
define('MIDTRANS_IS_PRODUCTION', filter_var(getenv('MIDTRANS_IS_PRODUCTION') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// ==================== DATABASE ====================
define('DB_PATH', __DIR__ . '/data/topup.db');

// ==================== APPLICATION ====================
define('BASE_URL', 'http://localhost:8082');
define('SITE_NAME', 'TopUp Games');

// Inisialisasi PDO SQLite
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(dirname(DB_PATH))) @mkdir(dirname(DB_PATH), 0755, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        migrate($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        brand TEXT NOT NULL,        -- nama game / provider
        category TEXT DEFAULT 'game',
        status INTEGER DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game_id INTEGER,
        code TEXT NOT NULL UNIQUE,   -- buyer_sku_code Digiflazz
        name TEXT,
        price INTEGER,               -- harga jual anda
        buy_price INTEGER DEFAULT 0,
        stock INTEGER DEFAULT 99999,
        status INTEGER DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ref_id TEXT UNIQUE,
        product_code TEXT,
        product_name TEXT,
        customer_no TEXT,
        player_id TEXT,
        zone_id TEXT DEFAULT '',
        amount INTEGER,              -- harga jual (yang dibayar user)
        profit INTEGER DEFAULT 0,
        payment_method TEXT DEFAULT 'midtrans',
        payment_status TEXT DEFAULT 'pending',  -- pending/waiting/paid/expired
        order_status TEXT DEFAULT 'waiting',    -- waiting/success/failed
        snap_token TEXT DEFAULT '',
        sn TEXT DEFAULT '',
        raw TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    // Seed pricelist cache kosong (diisi oleh cron/manual sync)
    $pdo->exec("INSERT OR IGNORE INTO settings (key,value) VALUES ('pricelist_updated','')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key,value) VALUES ('games_json','')");
}

/** simple render JSON */
function j($data, $http = 200): void {
    http_response_code($http);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
