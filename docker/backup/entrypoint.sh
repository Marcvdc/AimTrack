#!/usr/bin/env sh
set -euo pipefail

CRON_SCHEDULE="${BACKUP_SCHEDULE:-0 3 * * *}"
CRON_LOG="${BACKUP_LOG:-/backups/cron.log}"
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

cat <<EOF >/etc/crontabs/root
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
${CRON_SCHEDULE} ${BACKUP_RUNNER_TARGET} >> ${CRON_LOG} 2>&1
EOF

exec crond -f -l 2
