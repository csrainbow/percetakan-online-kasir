#!/usr/bin/env bash
# ============================================================
# uninstall.sh — Hapus Aplikasi Percetakan Online + Kasir
# Data database & upload dibackup ke /root/backup-percetakan-*
# ============================================================
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "[ERROR] Jalankan sebagai root: sudo bash uninstall.sh"; exit 1; }

TOKO_DIR="${TOKO_DIR:-/var/www/percetakan-online}"
KASIR_DIR="${KASIR_DIR:-/var/www/kasir}"
TS=$(date +%Y%m%d-%H%M%S)
BACKUP="/root/backup-percetakan-${TS}"

echo "[INFO] Backup data -> ${BACKUP}"
mkdir -p "$BACKUP"
[ -f "$TOKO_DIR/database.sqlite" ] && cp -a "$TOKO_DIR/database.sqlite" "$BACKUP/database-toko-online.sqlite"
[ -f "$KASIR_DIR/data/kasir.db" ] && cp -a "$KASIR_DIR/data/kasir.db" "$BACKUP/kasir.db"
[ -d "$TOKO_DIR/uploads" ] && cp -a "$TOKO_DIR/uploads" "$BACKUP/uploads"
[ -f "$TOKO_DIR/config.php" ] && cp -a "$TOKO_DIR/config.php" "$BACKUP/config-toko-online.php"
[ -f "$KASIR_DIR/config.php" ] && cp -a "$KASIR_DIR/config.php" "$BACKUP/config-kasir.php"

echo "[INFO] Matikan service & hapus konfigurasi nginx"
systemctl stop percetakan-online 2>/dev/null || true
systemctl disable percetakan-online 2>/dev/null || true
rm -f /etc/systemd/system/percetakan-online.service
rm -f /etc/nginx/sites-enabled/percetakan-online-kasir /etc/nginx/sites-available/percetakan-online-kasir
systemctl daemon-reload
nginx -s reload 2>/dev/null || true

echo "[INFO] Hapus folder aplikasi"
rm -rf "$TOKO_DIR" "$KASIR_DIR"

echo ""
echo "=============================================="
echo "[INFO] SELESAI"
echo "  Data tersimpan di: ${BACKUP}"
echo "  Paket PHP/Nginx tidak dihapus (mungkin dipakai aplikasi lain)."
echo "  Hapus manual: apt-get purge php-cli php-fpm php-sqlite3 nginx"
echo "=============================================="
