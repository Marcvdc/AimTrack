#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-}"
TIMEOUT="${TIMEOUT:-300}"
SLEEP="${SLEEP:-5}"

if [ -z "${APP_URL}" ]; then
  echo "[healthcheck] APP_URL is not set" >&2
  exit 1
fi

echo "[healthcheck] Waiting for ${APP_URL} (timeout=${TIMEOUT}s, interval=${SLEEP}s)"
start_time=$(date +%s)

while true; do
  if curl -fsS --max-time 5 "${APP_URL}" >/dev/null 2>&1; then
    echo "[healthcheck] OK"
    exit 0
  fi

  now=$(date +%s)
  elapsed=$((now - start_time))
  if [ "${elapsed}" -ge "${TIMEOUT}" ]; then
    echo "[healthcheck] Timed out after ${elapsed}s" >&2
    exit 1
  fi

  sleep "${SLEEP}"
done
