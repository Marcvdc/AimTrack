#!/usr/bin/env bash
# AimTrack maandelijkse hersteltest
# Gebruik: ./restore-test-aimtrack.sh [--keep]
set -euo pipefail

BACKUP_ROOT="/home/madmin/aimtrack-backups"
DB_DIR="${BACKUP_ROOT}/db"
LOG_DIR="${BACKUP_ROOT}/logs"
LOG_FILE="${LOG_DIR}/restore-test-$(date +%Y%m%d).log"

DB_CONTAINER="aimtrack_db"
DB_USER="postgres"
RESTORE_DB="aimtrack_restore_test"

KEEP_DB=0
[[ "${1:-}" == "--keep" ]] && KEEP_DB=1

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"; }
die() { log "FOUT: $*"; exit 1; }

log "=== AimTrack hersteltest gestart ==="

DUMP_FILE="$(find "${DB_DIR}" -maxdepth 1 -name "daily-*.sql.gz" -type f | sort | tail -n1)"
[[ -z "${DUMP_FILE}" ]] && die "Geen dagelijkse dump gevonden in ${DB_DIR}"
log "Dump: ${DUMP_FILE} ($(du -sh "${DUMP_FILE}" | cut -f1))"

docker exec "${DB_CONTAINER}" \
    psql -U "${DB_USER}" -c "DROP DATABASE IF EXISTS ${RESTORE_DB};" postgres \
    >> "${LOG_FILE}" 2>&1

log "Aanmaken testdatabase: ${RESTORE_DB}"
docker exec "${DB_CONTAINER}" \
    psql -U "${DB_USER}" -c "CREATE DATABASE ${RESTORE_DB};" postgres \
    >> "${LOG_FILE}" 2>&1

log "Herstellen dump..."
gunzip -c "${DUMP_FILE}" \
    | docker exec -i "${DB_CONTAINER}" \
        psql -U "${DB_USER}" -d "${RESTORE_DB}" -q \
    >> "${LOG_FILE}" 2>&1

TABLE_COUNT=$(docker exec "${DB_CONTAINER}" \
    psql -U "${DB_USER}" -d "${RESTORE_DB}" -tAc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")
log "Aantal tabellen in herstelde DB: ${TABLE_COUNT}"

for table in users sessions session_shots weapons; do
    ROW_COUNT=$(docker exec "${DB_CONTAINER}" \
        psql -U "${DB_USER}" -d "${RESTORE_DB}" -tAc \
        "SELECT count(*) FROM ${table};" 2>/dev/null || echo "ONTBREEKT")
    log "  ${table}: ${ROW_COUNT} rijen"
done

if [[ "${TABLE_COUNT}" -lt 5 ]]; then
    log "WAARSCHUWING: Minder dan 5 tabellen gevonden — controleer dump!"
    EXIT_CODE=1
else
    log "Hersteltest GESLAAGD (${TABLE_COUNT} tabellen)"
    EXIT_CODE=0
fi

if [[ "${KEEP_DB}" -eq 0 ]]; then
    log "Verwijderen testdatabase..."
    docker exec "${DB_CONTAINER}" \
        psql -U "${DB_USER}" -c "DROP DATABASE ${RESTORE_DB};" postgres \
        >> "${LOG_FILE}" 2>&1
else
    log "Testdatabase bewaard als: ${RESTORE_DB}"
fi

log "=== Hersteltest afgerond (exitcode: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
