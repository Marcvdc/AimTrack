#!/usr/bin/env bash
# AimTrack production backup — database (pg_dump) + storage (rsync)
# Rotatie: 7 dagelijks, 4 wekelijks, 3 maandelijks
# Gebruik: ./backup-aimtrack.sh [--dry-run]
set -euo pipefail

BACKUP_ROOT="/home/madmin/aimtrack-backups"
DB_DIR="${BACKUP_ROOT}/db"
STORAGE_DIR="${BACKUP_ROOT}/storage"
LOG_DIR="${BACKUP_ROOT}/logs"
LOG_FILE="${LOG_DIR}/backup-$(date +%Y%m%d).log"

APP_STORAGE_SRC="/var/www/AimTrack/storage/app"
DB_CONTAINER="aimtrack_db"
DB_USER="postgres"
DB_NAME="aimtrack"

RETAIN_DAILY=7
RETAIN_WEEKLY=4
RETAIN_MONTHLY=3

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
fi

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"; }
die() { log "FOUT: $*"; exit 1; }

run() {
    if [[ "${DRY_RUN}" -eq 1 ]]; then
        log "[DRY-RUN] $*"
    else
        "$@"
    fi
}

rotate_old() {
    local dir="$1" pattern="$2" keep="$3"
    local count
    count=$(find "${dir}" -maxdepth 1 -name "${pattern}" -type f | wc -l)
    if (( count > keep )); then
        find "${dir}" -maxdepth 1 -name "${pattern}" -type f \
            | sort | head -n $(( count - keep )) \
            | while IFS= read -r f; do
                log "  Verwijder oud: ${f}"
                run rm -f "${f}"
            done
    fi
}

docker inspect "${DB_CONTAINER}" --format '{{.State.Running}}' 2>/dev/null | grep -q true \
    || die "Container ${DB_CONTAINER} draait niet."

mkdir -p "${DB_DIR}" "${STORAGE_DIR}" "${LOG_DIR}"

log "=== AimTrack backup gestart ==="
[[ "${DRY_RUN}" -eq 1 ]] && log "*** DRY-RUN modus — geen bestanden worden aangemaakt ***"

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
DAY_OF_WEEK="$(date +%u)"
DAY_OF_MONTH="$(date +%d)"

DAILY_FILE="${DB_DIR}/daily-${TIMESTAMP}.sql.gz"
log "Database dump → ${DAILY_FILE}"

if [[ "${DRY_RUN}" -eq 0 ]]; then
    docker exec "${DB_CONTAINER}" \
        pg_dump -U "${DB_USER}" --no-owner --no-privileges "${DB_NAME}" \
        | gzip -9 > "${DAILY_FILE}"
    log "  Grootte: $(du -sh "${DAILY_FILE}" | cut -f1)"
fi

if [[ "${DAY_OF_WEEK}" == "1" ]]; then
    WEEKLY_FILE="${DB_DIR}/weekly-$(date +%Y-W%V).sql.gz"
    log "Wekelijkse kopie → ${WEEKLY_FILE}"
    run cp "${DAILY_FILE}" "${WEEKLY_FILE}"
    rotate_old "${DB_DIR}" "weekly-*.sql.gz" "${RETAIN_WEEKLY}"
fi

if [[ "${DAY_OF_MONTH}" == "01" ]]; then
    MONTHLY_FILE="${DB_DIR}/monthly-$(date +%Y-%m).sql.gz"
    log "Maandelijkse kopie → ${MONTHLY_FILE}"
    run cp "${DAILY_FILE}" "${MONTHLY_FILE}"
    rotate_old "${DB_DIR}" "monthly-*.sql.gz" "${RETAIN_MONTHLY}"
fi

rotate_old "${DB_DIR}" "daily-*.sql.gz" "${RETAIN_DAILY}"

log "Storage rsync: ${APP_STORAGE_SRC} → ${STORAGE_DIR}"
if [[ -d "${APP_STORAGE_SRC}" ]]; then
    if [[ "${DRY_RUN}" -eq 0 ]]; then
        rsync -a --delete "${APP_STORAGE_SRC}/" "${STORAGE_DIR}/"
        log "  Rsync klaar ($(du -sh "${STORAGE_DIR}" | cut -f1) totaal)"
    else
        log "[DRY-RUN] rsync -a --delete ${APP_STORAGE_SRC}/ ${STORAGE_DIR}/"
    fi
else
    log "  WAARSCHUWING: ${APP_STORAGE_SRC} niet gevonden, storage backup overgeslagen."
fi

log "=== Backup succesvol afgerond ==="
log "DB bestanden:"
ls -lh "${DB_DIR}"/*.sql.gz 2>/dev/null | awk '{print "  " $5 "  " $9}' | tee -a "${LOG_FILE}" || true
