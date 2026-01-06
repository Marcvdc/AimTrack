#!/usr/bin/env bash
set -euo pipefail

# Creates a timestamped PostgreSQL dump from the local dev stack and
# prunes backups older than RETENTION_DAYS (default: 7).
#
# Optional environment overrides:
#   COMPOSE_FILE   (default: docker/compose.dev.yml)
#   ENV_FILE       (default: .env.local)
#   DB_SERVICE     (default: db)
#   BACKUP_DIR     (default: <repo>/backups)
#   BACKUP_PREFIX  (default: db)
#   RETENTION_DAYS (default: 7)

SCRIPT_DIR="$(cd -- "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"

COMPOSE_FILE="${COMPOSE_FILE:-docker/compose.dev.yml}"
ENV_FILE="${ENV_FILE:-.env.local}"
DB_SERVICE="${DB_SERVICE:-db}"
BACKUP_DIR="${BACKUP_DIR:-${PROJECT_ROOT}/backups}"
BACKUP_PREFIX="${BACKUP_PREFIX:-db}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

timestamp="$(date +%Y%m%d-%H%M%S)"
backup_path="${BACKUP_DIR}/${BACKUP_PREFIX}-${timestamp}.sql"

mkdir -p "${BACKUP_DIR}"

echo "[backup] Dumping database to ${backup_path}"
docker compose \
  -f "${PROJECT_ROOT}/${COMPOSE_FILE}" \
  --env-file "${PROJECT_ROOT}/${ENV_FILE}" \
  exec -T "${DB_SERVICE}" \
  sh -c 'pg_dump --no-owner --no-privileges -U "${DB_USERNAME:-aimtrack}" -d "${DB_DATABASE:-aimtrack}"' \
  > "${backup_path}"

echo "[backup] Compressing ${backup_path}"
gzip -f "${backup_path}"
backup_path="${backup_path}.gz"

echo "[backup] Retaining files newer than ${RETENTION_DAYS} day(s)"
find "${BACKUP_DIR}" -maxdepth 1 -type f -name "${BACKUP_PREFIX}-*.sql.gz" -mtime +$((RETENTION_DAYS - 1)) -print -delete || true

echo "[backup] Done -> ${backup_path}"
