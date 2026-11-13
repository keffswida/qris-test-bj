#!/usr/bin/env bash
# ============================================================
# Deploy script — Bank Jatim SNAP API Tester ke VPS
#
# Penggunaan:
#   ./deploy.sh user@vps-ip [/path/tujuan]
#
# Contoh:
#   ./deploy.sh root@80.43.0.158 /opt/qris-test-bj
#
# Yang dilakukan:
#   1. Upload project (rsync; .git, log, key, dan PDF dikecualikan)
#   2. Jalankan docker compose up -d --build di VPS
#   3. Tampilkan status container
#
# Syarat di VPS: Docker Engine + Docker Compose Plugin, dan file .env
# sudah terisi (ter-upload dari lokal; pastikan isinya untuk VPS).
# ============================================================
set -euo pipefail

TARGET="${1:-}"
REMOTE_DIR="${2:-/opt/qris-test-bj}"

if [[ -z "$TARGET" ]]; then
    echo "Pemakaian: $0 user@vps-ip [/path/tujuan]"
    exit 1
fi

echo "== Upload project ke ${TARGET}:${REMOTE_DIR} =="
rsync -avz --delete \
    --exclude '.git' \
    --exclude 'storage/logs/*.log' \
    --exclude 'storage/keys/*' \
    --exclude '*.pdf' \
    --exclude '.DS_Store' \
    ./ "${TARGET}:${REMOTE_DIR}/"

echo "== Build & jalankan container =="
ssh "${TARGET}" "cd ${REMOTE_DIR} && docker compose up -d --build"

echo "== Status container =="
ssh "${TARGET}" "cd ${REMOTE_DIR} && docker compose ps"

echo "== Cek akses lokal (dari VPS) =="
ssh "${TARGET}" "curl -s -o /dev/null -w 'dashboard: HTTP %{http_code}\n' http://127.0.0.1:8080/ || true"

echo "Selesai. Dashboard: https://qrisbj.lksbarokah255.my.id"
