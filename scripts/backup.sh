#!/usr/bin/env bash
# Sauvegarde : dump Postgres + tar des fichiers d'archive.
set -euo pipefail
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT_DIR"

STAMP="$(date +%Y-%m-%d_%H%M%S)"
BACKUP_DIR="${ROOT_DIR}/backups/${STAMP}"
mkdir -p "$BACKUP_DIR"

echo "→ dump Postgres"
docker compose exec -T postgres pg_dump -U "${DB_USERNAME:-archivage}" "${DB_DATABASE:-archivage}" | gzip > "${BACKUP_DIR}/db.sql.gz"

echo "→ tar archives"
tar -czf "${BACKUP_DIR}/archives.tar.gz" -C "${ROOT_DIR}/backend/storage/app" archives

echo "→ bundle final"
tar -cf "${ROOT_DIR}/backups/${STAMP}.tar" -C "${ROOT_DIR}/backups" "${STAMP}"
rm -rf "${BACKUP_DIR}"

echo "✓ sauvegarde : backups/${STAMP}.tar"
