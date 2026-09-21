#!/bin/bash
# ============================================================
# KIRIM TEMPLATE WA (Cloud API) — ujicoba nyata ke customer
# Token dibaca dari config; TIDAK di-echo (hanya cek panjang).
# ============================================================
set -uo pipefail
BASE=/var/www/percetakan-online
C=/var/www/percetakan-online/config.php

echo "=== 1) Ambil Phone Number ID + token dari config (tanpa echo isi token) ==="
PHONE_ID=$(php -r 'require "/var/www/percetakan-online/config.php"; echo defined("WHATSAPP_PHONE_NUMBER_ID")?WHATSAPP_PHONE_NUMBER_ID:(defined("WA_PHONE_NUMBER_ID")?WA_PHONE_NUMBER_ID:"");' 2>/dev/null)
if [ -z "$PHONE_ID" ]; then
  # fallback: grep definisi apapun yg mirip phone/business/access
  echo "ℹ️ konstanta WHATSAPP_PHONE_NUMBER_ID / WA_PHONE_NUMBER_ID tidak ketemu. Cari bentuk lain:"
  grep -oE "define\(['\"][A-Za-z_]*(PHONE|NUMBER|ACCESS|BUSINESS|TOKEN)[A-Za-z_]*['\"]" "$C" 2>/dev/null
  exit 0
fi
TOKEN=$(php -r 'require "/var/www/percetakan-online/config.php"; echo defined("WHATSAPP_ACCESS_TOKEN")?WHATSAPP_ACCESS_TOKEN:(defined("WHATSAPP_BUSINESS_ACCESS_TOKEN")?WHATSAPP_BUSINESS_ACCESS_TOKEN:"");' 2>/dev/null)
# fallback: coba baca yg namanya mirip token
if [ -z "$TOKEN" ]; then
  TOKEN=$(php -r 'require "/var/www/percetakan-online/config.php"; foreach (get_defined_constants(true)["user"] as $k=>$v) { if (is_string($v) && stripos($v,"EAA")===0) { echo $v; break; } }' 2>/dev/null)
fi

echo "Phone Number ID : $PHONE_ID"
if [ -n "${TOKEN:-}" ]; then echo "Token           : OK (${#TOKEN} karakter, disimpan rapi)"; else echo "Token           : ❌ GAGAL dibaca"; exit 1; fi

echo ""
echo "=== 2) KIRIM template jaspers_market_order_confirmation_v1 → 6282252569185 ==="
RESP=$(curl -s -w "\n[HTTP %{http_code}]\n" \
  -X POST "https://graph.facebook.com/v25.0/$PHONE_ID/messages" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "messaging_product": "whatsapp",
    "to": "6282252569185",
    "type": "template",
    "template": {
      "name": "jaspers_market_order_confirmation_v1",
      "language": { "code": "en_US" },
      "components": [{
        "type": "body",
        "parameters": [
          {"type":"text","text":"John Doe"},
          {"type":"text","text":"123456"},
          {"type":"text","text":"Sep 19, 2026"}
        ]
      }]
    }
  }')
echo "$RESP"

echo ""
echo "=== 3) bukti: cek log webhook (harus ada event sent/delivered) ==="
LOG=/var/www/percetakan-online/logs/wa-webhook.log
[ -f "$LOG" ] && tail -6 "$LOG" | sed -E 's/(EAA[A-Za-z0-9]{8})[A-Za-z0-9]+/\1[REDACTED]/g' || echo "(belum ada log POST)"