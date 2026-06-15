#!/usr/bin/env bash
set -euo pipefail

# Draait onder bash: op het Debian-image is /bin/sh = dash, die `set -o pipefail`
# niet kent. Standaard wordt scripts/backup-dev-db.sh in `direct` mode gebruikt;
# dat is een volwaardige pg_dump tegen DB_HOST (zie docs/AimTrack/tech/backups.md).
SCRIPT_PATH="${BACKUP_SCRIPT:-/var/www/html/scripts/backup-dev-db.sh}"

if [ ! -f "${SCRIPT_PATH}" ]; then
  echo "[backup-runner] Script not found at ${SCRIPT_PATH}" >&2
  exit 1
fi

exec env BACKUP_MODE="${BACKUP_MODE:-direct}" "${SCRIPT_PATH}"
