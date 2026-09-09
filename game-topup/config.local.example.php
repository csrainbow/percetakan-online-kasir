<?php
/**
 * SALIN file ini menjadi config.local.php lalu isi kredensial Anda.
 * config.local.php TIDAK di-commit ke git (lihat .gitignore).
 */
define('DGF_USERNAME', 'username_digiflazz_anda');
define('DGF_APIKEY', 'apikey_digiflazz_anda');
define('DGF_TESTING', true); // wajib true saat memakai Development Key; false untuk transaksi riil (Production Key)
// Secret webhook Digiflazz (wajib): pakai string acak; Digiflazz kirim X-Hub-Signature: sha1=HMAC-SHA1(body, secret)
define('DGF_WEBHOOK_SECRET', 'ganti_dengan_string_acak_panjang');
define('MT_SERVER_KEY', 'Mid-server-xxxx');
define('MT_CLIENT_KEY', 'Mid-client-xxxx');
// define('MIDTRANS_IS_PRODUCTION', false); // opsional, default false