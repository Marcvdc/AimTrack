#!/usr/bin/env bash
set -euo pipefail

# Zet de stack terug op een eerdere image-tag.
#
#   bash scripts/rollback.sh              # terug naar de vorige geslaagde tag
#   bash scripts/rollback.sh 4f21ab9      # terug naar een specifieke tag
#   bash scripts/rollback.sh --list       # toon de bekende tags en de historie
#
# REGISTRY_IMAGE en COMPOSE_FILE komen uit .deploy/ als ze niet in de omgeving
# staan, zodat een rollback op de host geen losse variabelen nodig heeft.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_PATH="${DEPLOY_PATH:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
STATE_DIR="${DEPLOY_PATH}/.deploy"

read_state() {
  if [ -f "$1" ]; then
    tr -d '[:space:]' < "$1"
  fi
}

usage() {
  cat <<'USAGE'
Zet de stack terug op een eerdere image-tag.

  bash scripts/rollback.sh              # terug naar de vorige geslaagde tag
  bash scripts/rollback.sh 4f21ab9      # terug naar een specifieke tag
  bash scripts/rollback.sh --list       # toon de bekende tags en de historie

REGISTRY_IMAGE en COMPOSE_FILE komen uit .deploy/ als ze niet in de omgeving
staan, zodat een rollback op de host geen losse variabelen nodig heeft.
USAGE
}

CURRENT_TAG="$(read_state "${STATE_DIR}/last_successful_tag")"
PREVIOUS_TAG="$(read_state "${STATE_DIR}/previous_successful_tag")"
REGISTRY_IMAGE="${REGISTRY_IMAGE:-$(read_state "${STATE_DIR}/registry_image")}"
COMPOSE_FILE="${COMPOSE_FILE:-$(read_state "${STATE_DIR}/compose_file")}"

case "${1:-}" in
  -h|--help)
    usage
    exit 0
    ;;
  --list)
    echo "Deploy path:     ${DEPLOY_PATH}"
    echo "Registry image:  ${REGISTRY_IMAGE:-onbekend}"
    echo "Compose file:    ${COMPOSE_FILE:-onbekend}"
    echo "Nu live:         ${CURRENT_TAG:-onbekend}"
    echo "Vorige tag:      ${PREVIOUS_TAG:-onbekend}"
    echo
    echo "Historie (nieuwste onderaan):"
    if [ -f "${STATE_DIR}/deploy_history" ]; then
      tail -n 20 "${STATE_DIR}/deploy_history"
    else
      echo "  geen historie in ${STATE_DIR}/deploy_history"
    fi
    if [ -n "${REGISTRY_IMAGE}" ] && command -v docker >/dev/null 2>&1; then
      echo
      echo "Lokaal aanwezige images:"
      docker images --format '  {{.Repository}}:{{.Tag}} ({{.CreatedSince}})' "${REGISTRY_IMAGE}"
    fi
    exit 0
    ;;
esac

TARGET_TAG="${1:-${PREVIOUS_TAG}}"

if [ -z "${TARGET_TAG}" ]; then
  echo "[rollback] Geen tag opgegeven en geen vorige tag bekend in ${STATE_DIR}" >&2
  echo "[rollback] Kies er zelf een: bash scripts/rollback.sh --list" >&2
  exit 1
fi

if [ -z "${REGISTRY_IMAGE}" ]; then
  echo "[rollback] REGISTRY_IMAGE is niet bekend, zet 'm in de omgeving" >&2
  exit 1
fi

# Zonder bekend compose-bestand niet doorgaan: een productiehost mag hier nooit
# stilzwijgend op de staging-compose terechtkomen.
if [ -z "${COMPOSE_FILE}" ]; then
  echo "[rollback] COMPOSE_FILE is niet bekend in ${STATE_DIR}, zet 'm in de omgeving" >&2
  echo "[rollback] Bijvoorbeeld: COMPOSE_FILE=docker/compose.prod.yml bash scripts/rollback.sh" >&2
  exit 1
fi

if [ "${TARGET_TAG}" = "${CURRENT_TAG}" ]; then
  echo "[rollback] Let op: ${TARGET_TAG} staat al als live geregistreerd, de stack wordt opnieuw op die tag gezet" >&2
fi

echo "[rollback] Zet ${REGISTRY_IMAGE}:${TARGET_TAG} terug (nu live: ${CURRENT_TAG:-onbekend})"

DEPLOY_PATH="${DEPLOY_PATH}" \
REGISTRY_IMAGE="${REGISTRY_IMAGE}" \
COMPOSE_FILE="${COMPOSE_FILE}" \
ROLLBACK_TO="${TARGET_TAG}" \
  bash "${SCRIPT_DIR}/remote_deploy.sh"
