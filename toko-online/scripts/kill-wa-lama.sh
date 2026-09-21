#!/bin/bash
# ============================================================
# MATIKAN FITUR WA LAMA di WEBSITE (web -> wa_queue -> gateway kasir)
# Satu kill-switch: pengaturan wa_enabled='0' di DB kasir.
# wa_web_send() sudah cek wa_web_setting('wa_enabled','0') !== '1' => bal.
# ============================================================
set -uo pipefail

KASIR_DB=/var/www/kasir/data/kasir.db

echo "=== A. Cek isi /etc/cron.d/kasir (apa yg dijalankan) ==="
cat /etc/cron.d/kasir 2>/dev/null

echo ""
echo "=== B. Nilai wa_enabled SEKARANG ==="
if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 -readonly "$KASIR_DB" "SELECT key,value FROM pengaturan WHERE key='wa_enabled';" 2>&1
else
    php -r '$p=new PDO("sqlite:/var/www/kasir/data/kasir.db"); foreach($p->query("SELECT key,value FROM pengaturan WHERE key=\"wa_enabled\"") as $r) echo $r["key"]."=".$r["value"]."\n";' 2>&1
fi

echo ""
echo "=== C. MATIKAN: set wa_enabled=0 ==="
if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 "$KASIR_DB" "UPDATE pengaturan SET value='0' WHERE key='wa_enabled';
INSERT INTO pengaturan(key,value) SELECT 'wa_enabled','0' WHERE NOT EXISTS(SELECT 1 FROM pengaturan WHERE key='wa_enabled');" 2>&1
else
    php -r '
      $p=new PDO("sqlite:/var/www/kasir/data/kasir.db");
      $p->exec("UPDATE pengaturan SET value=\"0\" WHERE key=\"wa_enabled\"");
      $p->exec("INSERT INTO pengaturan(key,value) SELECT \"wa_enabled\",\"0\" WHERE NOT EXISTS(SELECT 1 FROM pengaturan WHERE key=\"wa_enabled\")");
    ' 2>&1
fi

echo ""
echo "=== D. VERIFIKASI setelah dimatikan ==="
if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 -readonly "$KASIR_DB" "SELECT key,value FROM pengaturan WHERE key='wa_enabled';" 2>&1
fi
echo ""
echo "test kirim web (harusnya sekarang menolak/bal):"
php -r '
  require "/var/www/percetakan-online/includes/wa_web.php";
  $r = wa_web_send("6281234567890","TEST-KILL-SWITCH");
  var_export($r); echo "\n(ketika wa_enabled=0 => false = mati ✅)\n";
' 2>&1 | tail -5