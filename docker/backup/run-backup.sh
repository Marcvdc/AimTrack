#!/usr/bin/env sh
set -euo pipefail

SCRIPT_PATH="${BACKUP_SCRIPT:-/app/scripts/backup-dev-db.sh}"

if [ ! -f "${SCRIPT_PATH}" ]; then
  echo "[backup-runner] Script not found at ${SCRIPT_PATH}" >&2
  exit 1
fi

exec env BACKUP_MODE="${BACKUP_MODE:-direct}" "${SCRIPT_PATH}"
