#!/usr/bin/env bash
set -euo pipefail

# scripts/worktree-setup.sh
# Maakt een Git worktree + geïsoleerde Docker stack voor parallelle ontwikkeling.
#
# Gebruik: ./scripts/worktree-setup.sh <feature-naam> [offset] [base-branch]
#   <feature-naam>: lowercase, bv. "copilot" of "auth-rewrite"
#   [offset]      : optioneel, integer (default: high-water-mark — hoogste reeds
#                   toegewezen WEB_PORT + 1, zodat verwijderde worktrees geen poort
#                   van een nog-actieve stack teruggeven)
#   [base-branch] : optioneel, basis-branch voor de worktree (default: main)
#
# Belangrijk:
# - Docker Compose leest GEEN .env.local. Voor compose-variabelen wordt een
#   APARTE .env in de worktree project root aangemaakt. Compose moet altijd
#   draaien met de --env-file flag, anders worden de overrides genegeerd:
#       docker compose --env-file .env -f docker/compose.dev.yml up -d
# - Poorten starten op 19000+ om conflicten met andere lokale projecten
#   (mototrax, openjarvis, etc.) te vermijden.

NAME="${1:-}"
EXPLICIT_OFFSET="${2:-}"
BASE_BRANCH="${3:-main}"

if [[ -z "$NAME" ]]; then
  echo "Gebruik: $0 <feature-naam> [offset] [base-branch]" >&2
  exit 1
fi

if [[ ! "$NAME" =~ ^[a-z0-9][a-z0-9_-]*$ ]]; then
  echo "Feature-naam mag alleen lowercase letters, cijfers, '-' en '_' bevatten." >&2
  exit 1
fi

if [[ -n "$EXPLICIT_OFFSET" && ! "$EXPLICIT_OFFSET" =~ ^[0-9]+$ ]]; then
  echo "Offset (2e argument) moet een niet-negatief geheel getal zijn (of leeg voor automatisch)." >&2
  exit 1
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

WORKTREE_PATH="$REPO_ROOT/../aimtrack-${NAME}"
BRANCH="feature/${NAME}"
PROJECT_NAME="aimtrack_${NAME}"

if [[ -e "$WORKTREE_PATH" ]]; then
  echo "Pad bestaat al: $WORKTREE_PATH" >&2
  exit 1
fi

# OFFSET bepaalt alle poorten. Zonder expliciete offset: high-water-mark — de hoogste
# WEB_PORT die al aan een zuster-worktree (../aimtrack-*/.env) is toegewezen bepaalt de
# volgende offset. Zo krijgt een nieuwe worktree nooit de poort van een nog-actieve
# stack terug, ook niet nadat een lager genummerde worktree is verwijderd (de oude
# 'git worktree list | wc -l'-telling deed dat wél: verwijder een middelste worktree en
# de volgende kreeg dezelfde poort als een nog-draaiende stack).
if [[ -n "$EXPLICIT_OFFSET" ]]; then
  # 10# forceert base-10, zodat "08"/"09" niet als (ongeldig) octaal worden gelezen.
  OFFSET=$((10#$EXPLICIT_OFFSET))
else
  MAX_WEB_PORT=19080
  for envfile in "$REPO_ROOT"/../aimtrack-*/.env; do
    [[ -f "$envfile" ]] || continue
    existing="$(grep -E '^WEB_PORT=' "$envfile" 2>/dev/null | tail -1 | cut -d= -f2 | tr -d '[:space:]' || true)"
    if [[ "$existing" =~ ^[0-9]+$ ]] && (( existing > MAX_WEB_PORT )); then
      MAX_WEB_PORT="$existing"
    fi
  done
  OFFSET=$((MAX_WEB_PORT - 19080 + 1))
fi

# Poort-range vanaf 19000 om conflicten met andere lokale stacks te vermijden.
WEB_PORT=$((19080 + OFFSET))
DB_FORWARD_PORT=$((15432 + OFFSET))
PYTHON_SERVICE_PORT=$((19000 + OFFSET))
MAILPIT_HTTP_PORT=$((19025 + OFFSET))
MAILPIT_SMTP_PORT=$((11025 + OFFSET))

if ! git rev-parse --verify --quiet "$BASE_BRANCH" >/dev/null; then
  echo "Basis-branch '$BASE_BRANCH' bestaat niet lokaal. Run 'git fetch' of geef een geldige branch op als 3e argument." >&2
  exit 1
fi

echo "==> Worktree aanmaken: $WORKTREE_PATH (branch: $BRANCH, basis: $BASE_BRANCH)"
git worktree add -b "$BRANCH" "$WORKTREE_PATH" "$BASE_BRANCH"

# Kopieer .env.local zodat Laravel zelf z'n env heeft (DB_PASSWORD, APP_KEY, etc.)
for candidate in .env.local .env.example; do
  if [[ -f "$REPO_ROOT/$candidate" ]]; then
    cp "$REPO_ROOT/$candidate" "$WORKTREE_PATH/.env.local"
    echo "==> .env.local gekopieerd uit $candidate"
    break
  fi
done

# Stem APP_URL/WEB_PORT in de gekopieerde .env.local af op de worktree-poort.
# Zonder dit blijft APP_URL op de hoofd-dev poort (8080) staan, waardoor
# route()-gegenereerde URL's (o.a. de Copilot stream-fetch) cross-origin naar
# de verkeerde stack wijzen → 401 / kapotte features.
if [[ -f "$WORKTREE_PATH/.env.local" ]]; then
  if grep -q '^APP_URL=' "$WORKTREE_PATH/.env.local"; then
    sed -i -E "s#^APP_URL=.*#APP_URL=http://localhost:${WEB_PORT}#" "$WORKTREE_PATH/.env.local"
  else
    echo "APP_URL=http://localhost:${WEB_PORT}" >> "$WORKTREE_PATH/.env.local"
  fi
  if grep -q '^WEB_PORT=' "$WORKTREE_PATH/.env.local"; then
    sed -i -E "s#^WEB_PORT=.*#WEB_PORT=${WEB_PORT}#" "$WORKTREE_PATH/.env.local"
  fi
  echo "==> APP_URL/WEB_PORT in .env.local afgestemd op poort ${WEB_PORT}"
fi

# Maak .env in de worktree root met ALLEEN docker compose overrides.
# Deze .env wordt door 'docker compose --env-file .env' gelezen.
cat > "$WORKTREE_PATH/.env" <<EOF
# Docker Compose variabele overrides (auto-gegenereerd door scripts/worktree-setup.sh)
# Lezen via: docker compose --env-file .env -f docker/compose.dev.yml ...
COMPOSE_PROJECT_NAME=${PROJECT_NAME}
WEB_PORT=${WEB_PORT}
DB_FORWARD_PORT=${DB_FORWARD_PORT}
PYTHON_SERVICE_PORT=${PYTHON_SERVICE_PORT}
MAILPIT_HTTP_PORT=${MAILPIT_HTTP_PORT}
MAILPIT_SMTP_PORT=${MAILPIT_SMTP_PORT}
EOF

cat <<EOF

==> Worktree klaar: $WORKTREE_PATH

LET OP: Gebruik altijd '--env-file .env' bij docker compose commands.
Zonder die flag worden defaults gebruikt en mounten containers de hoofd-dev stack.

Volgende stappen:
  cd $WORKTREE_PATH
  docker compose --env-file .env -f docker/compose.dev.yml up -d
  docker compose --env-file .env -f docker/compose.dev.yml exec app php artisan migrate --seed

Toegangs-URL's:
  Web:        http://localhost:${WEB_PORT}
  Mailpit:    http://localhost:${MAILPIT_HTTP_PORT}
  Postgres:   localhost:${DB_FORWARD_PORT}
  Python svc: http://localhost:${PYTHON_SERVICE_PORT}

Project name: ${PROJECT_NAME}

Vergeet niet de registry in .ai/guidelines/parallel-worktrees.md bij te werken.

Cleanup later:
  cd $WORKTREE_PATH && docker compose --env-file .env -f docker/compose.dev.yml down -v
  cd $REPO_ROOT && git worktree remove $WORKTREE_PATH
EOF
