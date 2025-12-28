#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker/compose.staging.yml}"
ENV_FILE="${ENV_FILE:-.env}"

if ! command -v docker >/dev/null 2>&1; then
  echo "[migrate] docker is required" >&2
  exit 1
fi

docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" exec -T app php artisan migrate --force
