#!/usr/bin/env bash
set -euo pipefail

# Deployt de stack op de doelhost en rolt bij een falende migratie of healthcheck
# automatisch terug naar de laatst geslaagde image-tag.
#
# Exitcodes:
#   0  deploy (of rollback) geslaagd
#   1  configuratiefout, de stack is niet aangeraakt
#   20 deploy mislukt, teruggerold naar de vorige tag
#   21 deploy mislukt en de rollback is niet gelukt, de host heeft aandacht nodig

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

EXIT_ROLLED_BACK=20
EXIT_ROLLBACK_FAILED=21

log() {
  echo "[deploy] $*"
}

fail() {
  echo "[deploy] $*" >&2
}

if [ -z "${DEPLOY_PATH:-}" ]; then
  fail "DEPLOY_PATH is not set"
  exit 1
fi

REGISTRY_IMAGE="${REGISTRY_IMAGE:-}"
IMAGE_TAG="${IMAGE_TAG:-}"
ROLLBACK_TO="${ROLLBACK_TO:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker/compose.staging.yml}"
ENV_FILE="${ENV_FILE:-.env}"
APP_URL="${APP_URL:-}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-}"
AUTO_ROLLBACK="${AUTO_ROLLBACK:-true}"

if [ -n "${ROLLBACK_TO}" ]; then
  # Een rollback zet alleen de code terug; het schema blijft vooruit staan.
  RUN_MIGRATIONS="${RUN_MIGRATIONS:-false}"
  TARGET_TAG="${ROLLBACK_TO}"
else
  RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
  TARGET_TAG="${IMAGE_TAG}"
fi

if [ -z "${REGISTRY_IMAGE}" ] || [ -z "${TARGET_TAG}" ]; then
  fail "REGISTRY_IMAGE and IMAGE_TAG are required"
  exit 1
fi

mkdir -p "${DEPLOY_PATH}"
cd "${DEPLOY_PATH}"
DEPLOY_PATH="$(pwd)"

# Het statusbestand hangt bewust niet aan de cwd van de aanroeper.
STATE_DIR="${DEPLOY_PATH}/.deploy"
mkdir -p "${STATE_DIR}"
CURRENT_TAG_FILE="${STATE_DIR}/last_successful_tag"
PREVIOUS_TAG_FILE="${STATE_DIR}/previous_successful_tag"
HISTORY_FILE="${STATE_DIR}/deploy_history"
REGISTRY_IMAGE_FILE="${STATE_DIR}/registry_image"
COMPOSE_FILE_STATE="${STATE_DIR}/compose_file"

read_state() {
  if [ -f "$1" ]; then
    tr -d '[:space:]' < "$1"
  fi
}

record_history() {
  printf '%s\t%s\t%s\t%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$1" "$2" "$3" >> "${HISTORY_FILE}"
}

LAST_SUCCESSFUL_TAG="$(read_state "${CURRENT_TAG_FILE}")"

# Zodat scripts/rollback.sh op de host genoeg heeft aan een kaal commando.
printf '%s\n' "${REGISTRY_IMAGE}" > "${REGISTRY_IMAGE_FILE}"
printf '%s\n' "${COMPOSE_FILE}" > "${COMPOSE_FILE_STATE}"

if [ -n "${ENV_FILE_B64:-}" ]; then
  log "Writing ${ENV_FILE} from ENV_FILE_B64"
  umask 077
  echo "${ENV_FILE_B64}" | base64 --decode > "${ENV_FILE}"
fi

if [ ! -f "${ENV_FILE}" ]; then
  fail "${ENV_FILE} not found in ${DEPLOY_PATH} and ENV_FILE_B64 was not provided"
  fail "Provide the ENV_FILE_B64 secret or place a ${ENV_FILE} on the host before deploying."
  exit 1
fi

if [ -n "${GHCR_TOKEN:-}" ] && [ -n "${GHCR_USERNAME:-}" ]; then
  log "Docker login to ghcr.io as ${GHCR_USERNAME}"
  echo "${GHCR_TOKEN}" | docker login ghcr.io -u "${GHCR_USERNAME}" --password-stdin
else
  log "No GHCR credentials provided, relying on the existing docker login on this host"
fi

TARGET_URL="${HEALTHCHECK_URL}"
if [ -z "${TARGET_URL}" ] && [ -n "${APP_URL}" ]; then
  TARGET_URL="${APP_URL%/}/health"
fi

compose() {
  docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" "$@"
}

start_stack() {
  local tag="$1"
  local pull_required="$2"

  export REGISTRY_IMAGE
  export IMAGE_TAG="${tag}"

  log "Pulling ${REGISTRY_IMAGE}:${tag} via ${COMPOSE_FILE}"
  if ! compose pull; then
    if [ "${pull_required}" = "true" ]; then
      fail "Could not pull ${REGISTRY_IMAGE}:${tag}"
      return 1
    fi
    log "Pull failed, falling back to the image already present on this host"
  fi

  log "Starting stack on ${tag}"
  compose up -d --remove-orphans
}

run_migrations() {
  log "Running database migrations"
  COMPOSE_FILE="${COMPOSE_FILE}" ENV_FILE="${ENV_FILE}" bash "${SCRIPT_DIR}/migrate.sh"
}

run_healthcheck() {
  log "Running healthcheck against ${TARGET_URL}"
  APP_URL="${TARGET_URL}" bash "${SCRIPT_DIR}/healthcheck.sh"
}

roll_back_to_previous() {
  local reason="$1"

  fail "Deploy of ${TARGET_TAG} failed: ${reason}"

  if [ "${AUTO_ROLLBACK}" != "true" ]; then
    fail "AUTO_ROLLBACK is disabled, ${TARGET_TAG} stays live"
    return 1
  fi

  if [ -z "${LAST_SUCCESSFUL_TAG}" ]; then
    fail "No previous successful tag recorded in ${CURRENT_TAG_FILE}, cannot roll back automatically"
    fail "Pick a tag by hand: bash scripts/rollback.sh <tag>"
    return 1
  fi

  fail "Rolling back to ${LAST_SUCCESSFUL_TAG}"
  if ! start_stack "${LAST_SUCCESSFUL_TAG}" false; then
    fail "Rollback to ${LAST_SUCCESSFUL_TAG} failed, this host needs manual attention"
    return 1
  fi

  record_history rollback "${LAST_SUCCESSFUL_TAG}" "auto: ${reason}"

  if [ -n "${TARGET_URL}" ] && ! run_healthcheck; then
    fail "Rolled back to ${LAST_SUCCESSFUL_TAG} but the healthcheck still fails"
    return 1
  fi

  fail "Rolled back to ${LAST_SUCCESSFUL_TAG}"
  return 0
}

abort_with_rollback() {
  if roll_back_to_previous "$1"; then
    exit "${EXIT_ROLLED_BACK}"
  fi
  exit "${EXIT_ROLLBACK_FAILED}"
}

if [ -n "${ROLLBACK_TO}" ]; then
  log "Manual rollback to ${ROLLBACK_TO} requested"

  if ! start_stack "${ROLLBACK_TO}" false; then
    fail "Rollback to ${ROLLBACK_TO} failed"
    exit "${EXIT_ROLLBACK_FAILED}"
  fi

  record_history rollback "${ROLLBACK_TO}" manual

  # previous_successful_tag blijft staan: anders zou een tweede rollback
  # terugstuiteren naar de versie waar je net vanaf kwam.
  printf '%s\n' "${ROLLBACK_TO}" > "${CURRENT_TAG_FILE}"

  if [ "${RUN_MIGRATIONS}" = "true" ] && ! run_migrations; then
    fail "Migrations failed after rolling back to ${ROLLBACK_TO}"
    exit "${EXIT_ROLLBACK_FAILED}"
  fi

  if [ -n "${TARGET_URL}" ] && ! run_healthcheck; then
    fail "${ROLLBACK_TO} is running but the healthcheck fails"
    exit "${EXIT_ROLLBACK_FAILED}"
  fi

  log "Rollback to ${ROLLBACK_TO} finished"
  exit 0
fi

if ! start_stack "${IMAGE_TAG}" true; then
  abort_with_rollback "the stack could not be started on ${IMAGE_TAG}"
fi

if [ "${RUN_MIGRATIONS}" = "true" ]; then
  if ! run_migrations; then
    abort_with_rollback "database migrations failed"
  fi
else
  log "Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS})"
fi

if [ -n "${TARGET_URL}" ]; then
  if ! run_healthcheck; then
    abort_with_rollback "the healthcheck against ${TARGET_URL} failed"
  fi
else
  log "No healthcheck URL provided"
fi

if [ -n "${LAST_SUCCESSFUL_TAG}" ] && [ "${LAST_SUCCESSFUL_TAG}" != "${IMAGE_TAG}" ]; then
  printf '%s\n' "${LAST_SUCCESSFUL_TAG}" > "${PREVIOUS_TAG_FILE}"
fi
printf '%s\n' "${IMAGE_TAG}" > "${CURRENT_TAG_FILE}"
record_history deploy "${IMAGE_TAG}" ok

if [ -n "${LAST_SUCCESSFUL_TAG}" ]; then
  log "Updated last_successful_tag (previous=${LAST_SUCCESSFUL_TAG}, current=${IMAGE_TAG})"
else
  log "Recorded first successful tag (${IMAGE_TAG})"
fi

log "Deploy finished"
