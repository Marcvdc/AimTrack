#!/usr/bin/env bash
set -euo pipefail

# scripts/worktree-setup.sh
# Maakt een Git worktree + geïsoleerde Docker stack voor parallelle ontwikkeling.
#
# Gebruik: ./scripts/worktree-setup.sh <feature-naam> [offset] [base-branch]
#   <feature-naam>: lowercase, bv. "copilot" of "auth-rewrite"
#   [offset]      : optioneel, integer (default: aantal bestaande worktrees)
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

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

WORKTREE_PATH="$REPO_ROOT/../aimtrack-${NAME}"
BRANCH="feature/${NAME}"
PROJECT_NAME="aimtrack_${NAME}"

if [[ -e "$WORKTREE_PATH" ]]; then
  echo "Pad bestaat al: $WORKTREE_PATH" >&2
  exit 1
fi

if [[ -n "$EXPLICIT_OFFSET" ]]; then
  OFFSET="$EXPLICIT_OFFSET"
else
  # Bestaande worktrees tellen (inclusief hoofd-dev). Eerste extra worktree → offset 1.
  OFFSET="$(git worktree list | wc -l | tr -d ' ')"
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
