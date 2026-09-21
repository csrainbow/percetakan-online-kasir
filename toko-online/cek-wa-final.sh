#!/bin/bash
# ============================================================
# CEK STATUS WEBHOOK META WA — BERSIH (TOKEN TIDAK DI-PRINT)
# ============================================================
set -uo pipefail
BASE=/var/www/percetakan-online

echo "=== 1) Apakah file api/wa-webhook.php ADA & nilai 8-byte awal (harus 38 33 31 39 37 34 36 = '83193746') ==="
if [ -f "$BASE/api/wa-webhook.php" ]; then
  head -c 8 "$BASE/api/wa-webhook.php" | od -c | head -1
else
  echo "❌ TIDAK ADA di $BASE/api/wa-webhook.php"
fi

echo ""
echo "=== 2) TES HANDSHAKE LOOPBACK (persis Meta) — body harus POLOS ==="
curl -s -w "\n[HTTP %{http_code}]\n" \
  "http://127.0.0.1:8000/api/wa-webhook.php?hub_mode=subscribe&hub_verify_token=rainbowprint-wa-v1&hub_challenge=57193746" | od -c | head -2

echo ""
echo "=== 3) TES HANDSHAKE PUBLIK (rainbowprinting.web.id — yang Meta benar2 hit) ==="
curl -s -w "\n[HTTP %{http_code}]\n" \
  "https://rainbowprinting.web.id/api/wa-webhook.php?hub_mode=subscribe&hub_verify_token=rainbowprint-wa-v1&hub_challenge=57193746" | od -c | head -2

echo ""
echo "=== 4) LOG WEBHOOK — bukti POST event dari Meta masuk (5 baris terakhir, token di-redak) ==="
LOG="$BASE/logs/wa-webhook.log"
if [ -f "$LOG" ]; then
  sed -E 's/(EAA[A-Za-z0-9]{8})[A-Za-z0-9]+/\1[REDACTED]/g' "$LOG" | tail -5
  echo "→ TOTAL baris log : $(wc -l < "$LOG")"
else
  echo "(log belum ada — kemungkinan webhook belum trigger POST)"
fi

echo ""
echo "=== 5) Path file webhook yg BENAR-BENAR di-hit Meta (pastikan ada di /var/www root) ==="
ls -la "$BASE/api/" | grep -i webhook