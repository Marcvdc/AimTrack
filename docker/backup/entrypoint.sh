#!/usr/bin/env bash
set -euo pipefail

# Backup-container entrypoint voor het Debian-image (php:8.4-fpm-bookworm).
#
# Op bookworm is /bin/sh = dash en die kent `set -o pipefail` niet; daarom draait
# dit script bewust onder bash. De image installeert Debian's Vixie-cron (zie
# Dockerfile): de daemon start met `cron` en leest cron-jobs uit /etc/cron.d/.
# De BusyBox/Alpine-conventies (een aparte crond-daemon en een crontab onder
# /etc/crontabs/) bestaan op dit image niet.
#
# Cron geeft de container-environment niet door aan de job. De backup-runner
# heeft echter DB-credentials en pad-variabelen nodig. Daarom snapshotten we de
# relevante variabelen als VAR=value-regels boven in het cron.d-bestand, zodat de
# job met dezelfde configuratie draait als de container.

CRON_SCHEDULE="${BACKUP_SCHEDULE:-0 3 * * *}"
CRON_LOG="${BACKUP_LOG:-/backups/cron.log}"
CRON_FILE="/etc/cron.d/aimtrack-backup"
BACKUP_RUNNER_SOURCE="${BACKUP_RUNNER_SOURCE:-/var/www/html/docker/backup/run-backup.sh}"
BACKUP_RUNNER_TARGET="/usr/local/bin/run-backup"

if [ ! -f "${BACKUP_RUNNER_SOURCE}" ]; then
  echo "[backup-entrypoint] Runner script ontbreekt op ${BACKUP_RUNNER_SOURCE}" >&2
  exit 1
fi

cp "${BACKUP_RUNNER_SOURCE}" "${BACKUP_RUNNER_TARGET}"
chmod +x "${BACKUP_RUNNER_TARGET}"

mkdir -p "$(dirname "${CRON_LOG}")"
touch "${CRON_LOG}"

cron_env() {
  local name="$1"
  local value="${!name:-}"
  if [ -n "${value}" ]; then
    printf '%s=%s\n' "${name}" "${value}"
  fi
}

{
  echo "SHELL=/bin/bash"
  echo "PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin"
  for var in \
    BACKUP_MODE BACKUP_SCRIPT BACKUP_DIR BACKUP_PREFIX RETENTION_DAYS \
    BACKUP_DRY_RUN DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
    cron_env "${var}"
  done
  echo "${CRON_SCHEDULE} root ${BACKUP_RUNNER_TARGET} >> ${CRON_LOG} 2>&1"
} >"${CRON_FILE}"

chmod 0644 "${CRON_FILE}"

exec cron -f -L 2
