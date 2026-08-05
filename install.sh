#!/usr/bin/env bash
# ============================================================
# install.sh — Instalasi Aplikasi Percetakan Online + Kasir
# ------------------------------------------------------------
# Cara cepat (metode curl):
#   curl -fsSL https://raw.githubusercontent.com/csrainbow/percetakan-online-kasir/main/install.sh | sudo bash
#
# Dengan pengaturan khusus:
#   sudo ADMIN_PASSWORD=rahasia KASIR_PORT=8081 TOKO_PORT=8000 \
#     bash -c "$(curl -fsSL https://raw.githubusercontent.com/csrainbow/percetakan-online-kasir/main/install.sh)"
# ============================================================
set -euo pipefail

# ---------- Konfigurasi (override via env) ----------
csrainbow="${csrainbow:-csrainbow}"
REPO_NAME="${REPO_NAME:-percetakan-online-kasir}"
BRANCH="${BRANCH:-main}"
INSTALL_BASE="${INSTALL_BASE:-/var/www}"
TOKO_DIR="${TOKO_DIR:-$INSTALL_BASE/percetakan-online}"
KASIR_DIR="${KASIR_DIR:-$INSTALL_BASE/kasir}"
KASIR_PORT="${KASIR_PORT:-8081}"
TOKO_PORT="${TOKO_PORT:-8000}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
NOTA_SECRET="${NOTA_SECRET:-}"
SKIP_SERVICES="${SKIP_SERVICES:-0}"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info(){ echo -e "${GREEN}[INFO]${NC} $*"; }
warn(){ echo -e "${YELLOW}[WARN]${NC} $*"; }
err(){ echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

[ "$(id -u)" -eq 0 ] || err "Jalankan sebagai root: sudo bash install.sh"

# ---------- 1. Ambil source ----------
if [ -n "${LOCAL_SRC:-}" ]; then
  SRC="$LOCAL_SRC"
  info "Gunakan source lokal: $SRC"
else
  TMP=$(mktemp -d)
  trap 'rm -rf "$TMP"' EXIT
  TARBALL="https://codeload.github.com/${csrainbow}/${REPO_NAME}/tar.gz/refs/heads/${BRANCH}"
  info "Download ${TARBALL}"
  curl -fsSL "$TARBALL" -o "$TMP/src.tgz" || err "Gagal download repo (cek internet)"
  tar -xzf "$TMP/src.tgz" -C "$TMP"
  SRC=$(find "$TMP" -maxdepth 1 -type d -name "*${REPO_NAME}*" | head -1)
  [ -n "$SRC" ] || err "Folder repo tidak ditemukan di archive"
  info "Source: $SRC"
fi

# ---------- 2. Instal paket ----------
if [ "$SKIP_SERVICES" != "1" ]; then
  info "Instal paket php-cli, php-fpm, php-sqlite3, php-curl, php-mbstring, php-gd, nginx ..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y -qq php-cli php-fpm php-sqlite3 php-curl php-mbstring php-gd nginx curl
else
  info "SKIP_SERVICES=1 -> lewati instalasi paket & service"
fi

# ---------- 3. Salin aplikasi ----------
info "Salin aplikasi ke ${TOKO_DIR} dan ${KASIR_DIR}"
mkdir -p "$TOKO_DIR" "$KASIR_DIR"
cp -r "$SRC/toko-online/." "$TOKO_DIR/"
cp -r "$SRC/kasir/." "$KASIR_DIR/"
rm -f "$TOKO_DIR/database.sqlite" "$KASIR_DIR/data/kasir.db"

# ---------- 4. Konfigurasi ----------
info "Konfigurasi NOTA_SECRET kasir & password admin toko online"
if [ -z "$NOTA_SECRET" ]; then
  NOTA_SECRET=$(php -r 'echo bin2hex(random_bytes(16));' 2>/dev/null || openssl rand -hex 16)
fi
sed -i "s|define('NOTA_SECRET', '.*');|define('NOTA_SECRET', '$NOTA_SECRET');|" "$KASIR_DIR/config.php"
TOKO_HASH=$(ADMIN_PW="$ADMIN_PASSWORD" php -r 'echo password_hash(getenv("ADMIN_PW"), PASSWORD_DEFAULT);')
sed -i "s|define('ADMIN_PASSWORD_HASH', '.*');|define('ADMIN_PASSWORD_HASH', '$TOKO_HASH');|" "$TOKO_DIR/config.php"

# ---------- 5. Folder runtime & hak akses ----------
info "Buat folder runtime & symlink"
mkdir -p "$TOKO_DIR/logs" "$TOKO_DIR/uploads/designs" "$TOKO_DIR/uploads/proofs" "$KASIR_DIR/data"
chmod 0775 "$KASIR_DIR/data"
chown -R www-data:www-data "$KASIR_DIR/data" 2>/dev/null || true
ln -sfn "$KASIR_DIR" "$TOKO_DIR/kasir"

if [ "$SKIP_SERVICES" = "1" ]; then
  info "Selesai (mode uji). File di ${TOKO_DIR} dan ${KASIR_DIR}"
  echo "NOTA_SECRET=$NOTA_SECRET"
  exit 0
fi

# ---------- 6. Service toko online (php -S + systemd) ----------
info "Pasang systemd service percetakan-online (port ${TOKO_PORT})"
cat > /etc/systemd/system/percetakan-online.service <<EOF
[Unit]
Description=Percetakan Online PHP Server
After=network.target

[Service]
ExecStart=/usr/bin/php -S 0.0.0.0:${TOKO_PORT} -t ${TOKO_DIR} ${TOKO_DIR}/router.php
Restart=always

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now percetakan-online

# ---------- 7. Nginx kasir ----------
SOCK=""
for s in /run/php/php8.3-fpm.sock /run/php/php-fpm.sock /run/php/php8.2-fpm.sock /run/php/php8.1-fpm.sock; do
  [ -S "$s" ] && SOCK="$s" && break
done
[ -z "$SOCK" ] && SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || echo /run/php/php-fpm.sock)

info "Pasang nginx kasir (port ${KASIR_PORT}, socket ${SOCK})"
cat > /etc/nginx/sites-available/percetakan-online-kasir <<EOF
server {
    listen ${KASIR_PORT};
    server_name _;

    root ${KASIR_DIR};
    index index.php;

    location ^~ /data/ { deny all; }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${SOCK};
    }
}
EOF
ln -sf /etc/nginx/sites-available/percetakan-online-kasir /etc/nginx/sites-enabled/
nginx -t || err "nginx -t gagal"
systemctl enable nginx >/dev/null 2>&1 || true
nginx -s reload || systemctl restart nginx

echo ""
echo "=============================================="
info "INSTALASI SELESAI"
echo "----------------------------------------------"
echo "  Kasir        : http://<IP>:$KASIR_PORT"
echo "    login      : admin / admin123 (ganti segera)"
echo "  Toko online  : http://<IP>:$TOKO_PORT"
echo "    login admin: admin / $ADMIN_PASSWORD"
echo "  NOTA_SECRET  : $NOTA_SECRET (simpan baik-baik)"
echo "  DB kasir     : $KASIR_DIR/data/kasir.db (auto-create)"
echo "  DB toko      : $TOKO_DIR/database.sqlite (auto-create)"
echo "=============================================="
