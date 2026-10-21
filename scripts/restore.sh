#!/usr/bin/env bash
# Restauration depuis une archive produite par backup.sh
set -euo pipefail
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT_DIR"

ARCHIVE="${1:-}"
if [ -z "$ARCHIVE" ] || [ ! -f "$ARCHIVE" ]; then
  echo "usage : $0 backups/AAAA-MM-DD_HHMMSS.tar"
  exit 1
fi

WORK="$(mktemp -d)"
tar -xf "$ARCHIVE" -C "$WORK"
STAMP="$(basename "$ARCHIVE" .tar)"

echo "→ restore db"
gunzip -c "${WORK}/${STAMP}/db.sql.gz" | docker compose exec -T postgres psql -U "${DB_USERNAME:-archivage}" -d "${DB_DATABASE:-archivage}" -v ON_ERROR_STOP=1

echo "→ restore archives"
tar -xzf "${WORK}/${STAMP}/archives.tar.gz" -C "${ROOT_DIR}/backend/storage/app"

rm -rf "$WORK"
echo "✓ restauration OK"
