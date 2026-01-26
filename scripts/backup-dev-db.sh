#!/usr/bin/env bash
set -euo pipefail

# Creates a timestamped PostgreSQL dump from the local dev stack and
# prunes backups older than RETENTION_DAYS (default: 7).
#
# Optional environment overrides:
#   BACKUP_MODE    (compose|direct, default: compose)
#   COMPOSE_FILE   (default: docker/compose.dev.yml)
#   ENV_FILE       (default: .env.local)
#   DB_SERVICE     (default: db)
#   BACKUP_DIR     (default: <repo>/backups)
#   BACKUP_PREFIX  (default: db)
#   RETENTION_DAYS (default: 7)
#   DB_HOST        (direct mode default: localhost)
#   DB_PORT        (default: 5432)
#   DB_DATABASE    (default: aimtrack)
#   DB_USERNAME    (default: aimtrack)
#   DB_PASSWORD    (default: aimtrack)
#   BACKUP_DRY_RUN (set to 1 to skip pg_dump and create placeholder output)

SCRIPT_DIR="$(cd -- "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"

BACKUP_MODE="${BACKUP_MODE:-compose}"
COMPOSE_FILE="${COMPOSE_FILE:-docker/compose.dev.yml}"
ENV_FILE="${ENV_FILE:-.env.local}"
DB_SERVICE="${DB_SERVICE:-db}"
BACKUP_DIR="${BACKUP_DIR:-${PROJECT_ROOT}/backups}"
BACKUP_PREFIX="${BACKUP_PREFIX:-db}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-aimtrack}"
DB_DATABASE="${DB_DATABASE:-aimtrack}"
DB_PASSWORD="${DB_PASSWORD:-aimtrack}"
BACKUP_DRY_RUN="${BACKUP_DRY_RUN:-0}"

timestamp="$(date +%Y%m%d-%H%M%S)"
backup_path="${BACKUP_DIR}/${BACKUP_PREFIX}-${timestamp}.sql"

mkdir -p "${BACKUP_DIR}"

if [ "${BACKUP_DRY_RUN}" = "1" ]; then
  echo "[backup] DRY-RUN enabled; writing placeholder dump to ${backup_path}"
  echo "dry-run placeholder $(date --iso-8601=seconds)" > "${backup_path}"
elif [ "${BACKUP_MODE}" = "compose" ]; then
  echo "[backup] Dumping database via docker compose to ${backup_path}"
  docker compose \
    -f "${PROJECT_ROOT}/${COMPOSE_FILE}" \
    --env-file "${PROJECT_ROOT}/${ENV_FILE}" \
    exec -T "${DB_SERVICE}" \
    sh -c 'pg_dump --no-owner --no-privileges -U "${DB_USERNAME:-aimtrack}" -d "${DB_DATABASE:-aimtrack}"' \
    > "${backup_path}"
else
  echo "[backup] Dumping database via direct pg_dump (${DB_HOST}:${DB_PORT}/${DB_DATABASE})"
  export PGPASSWORD="${DB_PASSWORD}"
  pg_dump \
    --no-owner \
    --no-privileges \
    --host "${DB_HOST}" \
    --port "${DB_PORT}" \
    --username "${DB_USERNAME}" \
    --dbname "${DB_DATABASE}" \
    > "${backup_path}"
fi

echo "[backup] Compressing ${backup_path}"
gzip -f "${backup_path}"
backup_path="${backup_path}.gz"

echo "[backup] Retaining files newer than ${RETENTION_DAYS} day(s)"
find "${BACKUP_DIR}" -maxdepth 1 -type f -name "${BACKUP_PREFIX}-*.sql.gz" -mtime +$((RETENTION_DAYS - 1)) -print -delete || true

echo "[backup] Done -> ${backup_path}"
