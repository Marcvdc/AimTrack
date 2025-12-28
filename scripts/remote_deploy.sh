#!/usr/bin/env bash
set -euo pipefail

if [ -z "${DEPLOY_PATH:-}" ]; then
  echo "[deploy] DEPLOY_PATH is not set" >&2
  exit 1
fi

REGISTRY_IMAGE="${REGISTRY_IMAGE:-}"
IMAGE_TAG="${IMAGE_TAG:-}"
LATEST_TAG="${LATEST_TAG:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker/compose.staging.yml}"
ENV_FILE="${ENV_FILE:-.env}"
APP_URL="${APP_URL:-}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-}"

STATE_DIR=".deploy"
mkdir -p "${STATE_DIR}"
PREVIOUS_TAG_FILE="${STATE_DIR}/last_successful_tag"
PREVIOUS_TAG=""
if [ -f "${PREVIOUS_TAG_FILE}" ]; then
  PREVIOUS_TAG="$(cat "${PREVIOUS_TAG_FILE}")"
fi

if [ -z "${REGISTRY_IMAGE}" ] || [ -z "${IMAGE_TAG}" ]; then
  echo "[deploy] REGISTRY_IMAGE and IMAGE_TAG are required" >&2
  exit 1
fi

mkdir -p "${DEPLOY_PATH}"
cd "${DEPLOY_PATH}"

if [ -n "${ENV_FILE_B64:-}" ]; then
  echo "[deploy] Writing ${ENV_FILE} from ENV_FILE_B64"
  umask 077
  echo "${ENV_FILE_B64}" | base64 --decode > "${ENV_FILE}"
fi

echo "[deploy] Docker login"
echo "${GHCR_TOKEN:?}" | docker login ghcr.io -u "${GHCR_USERNAME:?}" --password-stdin

export REGISTRY_IMAGE IMAGE_TAG LATEST_TAG

echo "[deploy] Pulling images via ${COMPOSE_FILE}"
docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" pull

echo "[deploy] Starting stack"
docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" up -d --remove-orphans

if [ "${RUN_MIGRATIONS}" = "true" ]; then
  echo "[deploy] Running database migrations"
  COMPOSE_FILE="${COMPOSE_FILE}" ENV_FILE="${ENV_FILE}" bash scripts/migrate.sh
else
  echo "[deploy] Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS})"
fi

TARGET_URL="${HEALTHCHECK_URL:-${APP_URL%/}/health}"
if [ -n "${TARGET_URL}" ]; then
  echo "[deploy] Running healthcheck against ${TARGET_URL}"
  APP_URL="${TARGET_URL}" bash scripts/healthcheck.sh
else
  echo "[deploy] No healthcheck URL provided"
fi

echo "${IMAGE_TAG}" > "${PREVIOUS_TAG_FILE}"
if [ -n "${PREVIOUS_TAG}" ]; then
  echo "[deploy] Updated last_successful_tag (previous=${PREVIOUS_TAG}, current=${IMAGE_TAG})"
else
  echo "[deploy] Recorded first successful tag (${IMAGE_TAG})"
fi

echo "[deploy] Deploy finished"
