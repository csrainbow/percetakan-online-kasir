<?php
// ============================================
// CONFIGURASI SESSION & KEAMANAN
// ============================================

// 🔥 Atur session dengan aman
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set 1 jika pakai HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// 🔥 Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ERROR REPORTING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set 0 untuk production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-error.log');

// ============================================
// KONSTANTA
// ============================================

// 🔥 Database
define('DB_PATH', __DIR__ . '/database.sqlite');

// 🔥 URL
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
define('BASE_URL', $baseUrl);

// 🔥 Nama Toko
define('SITE_NAME', 'Percetakan Rainbow');
define('SITE_DESCRIPTION', 'Percetakan online terpercaya di Samarinda. Cetak undangan, stiker, banner, dan kebutuhan percetakan lainnya.');

// 🔥 WhatsApp
define('WHATSAPP_NUMBER', '6281234567890');

// 🔥 WhatsApp Cloud API (Meta)
define('WHATSAPP_PHONE_NUMBER_ID', '1295084437027112');
define('WHATSAPP_BUSINESS_ACCOUNT_ID', '2530101160785700');
define('WHATSAPP_ACCESS_TOKEN', 'EAAZATZAigzK4cBSpHrcW0H6AzZAFtMXZCnsCAZB3tZCHCQZBCnH5xBTZAZAfUChbHqsmKhtlUBcg6K0H5lhP0nf5zqYcGZBs0TA33gmbQIlPruF0zAijZCXdMJxKU8woIIFRZCA1ooTOftWZC3803VpbvSiMxD8PX4FlC5FuJCkTZAiYT5gjFFCno0jIXw3wSev4WDIXB6FVbU4cxBsWcfgmH0ZBiHmFjtHYhPBB65L960HZAQEn7bq9hfAs3GnXZCvu27DMQbZCXskOgLZCb9rmghbp5v7fJEMVO8IlcJzecXZAq8GvzwZDZD');
define('WHATSAPP_VERIFY_TOKEN', 'verify_token_random_' . md5('Percetakan Rainbow WhatsApp API'));

// 🔥 Admin Login
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$/QhFH0S5hbJZbxJiSqbqPuGM0Trmx3mpq3rZLEo8kcQ5uBEnuxuri');

// 🔥 Salt untuk token Payment Point (halaman bayar publik)
define('PAYPOINT_SALT', 'e9f2d81a5c07b4639a1c8e4f20d15b73');

// 🔥 Biaya layanan QRIS statis (Rp 3.000) — ditambahkan otomatis ke total tagihan.
define('QRIS_STATIS_FEE', 3000);

// ============================================
// LOGS FOLDER
// ============================================

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================
// TIMEZONE
// ============================================

date_default_timezone_set('Asia/Makassar'); // WITA

// ============================================
// KONEKSI DATABASE
// ============================================

try {
    // 🔥 Cek apakah file database ada, jika tidak buat
    if (!file_exists(DB_PATH)) {
        file_put_contents(DB_PATH, '');
        chmod(DB_PATH, 0666);
    }
    
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "<br>Path: " . DB_PATH);
}

// ============================================
// 🔥 INCLUDE FUNCTIONS
// ============================================

$functionsFile = __DIR__ . '/includes/functions.php';
if (!file_exists($functionsFile)) {
    die("❌ File functions.php tidak ditemukan di: " . $functionsFile);
}

require_once $functionsFile;

require_once __DIR__ . '/includes/wa_web.php';

// ============================================
// 🔥 INISIALISASI DATABASE (dari functions.php)
// ============================================

if (function_exists('initDatabase')) {
    initDatabase();
} else {
    die("❌ Fungsi initDatabase() tidak ditemukan di functions.php");
}

// ============================================
// 🔥 CEK KONEKSI DATABASE
// ============================================

try {
    $db->query("SELECT 1");
} catch (Exception $e) {
    die("❌ Database tidak bisa diakses: " . $e->getMessage());
}