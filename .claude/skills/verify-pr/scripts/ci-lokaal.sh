#!/usr/bin/env bash
# Speelt de CI-jobs Lint, Audit en Tests na in het dev-image, op een worktree.
#
#   ci-lokaal.sh <worktree> [image]     image standaard aimtrack-dev:local
#
# Draait als de eigen gebruiker, nooit als root, zodat er geen bestanden van root achterblijven.
# Raakt de dev-stack niet: geen compose, een wegwerpcontainer met SQLite zoals in CI. Net als
# setup-php in CI zonder geheugenlimiet; het dev-image staat op 128M en dan valt Pest om.
# Print per controle één regel `<controle>: PASS|FAIL ...` en de laatste regels van de uitvoer.
# Exit 0 als alles slaagt, 1 als een controle faalt, 2 bij een verkeerde aanroep of ontbrekend image.
set -uo pipefail

wt="${1:-}"
image="${2:-aimtrack-dev:local}"
[[ -n $wt && -f $wt/composer.json ]] || { echo "gebruik: ci-lokaal.sh <worktree> [image]" >&2; exit 2; }
wt="$(cd "$wt" && pwd)"
docker image inspect "$image" >/dev/null 2>&1 || { echo "image $image ontbreekt; bouw het dev-image eerst" >&2; exit 2; }

run() {
  docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer \
    -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=':memory:' \
    -v "$wt":/app -w /app "$image" sh -c "$1"
}

status=0
controle() { # $1=naam $2=commando
  local out rc
  out="$(run "$2" 2>&1)"; rc=$?
  if [[ $rc -eq 0 ]]; then echo "$1: PASS"; else echo "$1: FAIL (exit $rc)"; status=1; fi
  printf '%s\n' "$out" | tail -n 5 | sed 's/^/    /'
}

controle "composer install" "composer install --no-interaction --no-progress --prefer-dist"
[[ -f $wt/.env ]] || cp "$wt/.env.example" "$wt/.env"
controle "key:generate" "php artisan key:generate --ansi --force"
controle "pint --test" "php -d memory_limit=-1 vendor/bin/pint --test"
controle "composer audit" "composer audit --locked --no-interaction --format=plain"
controle "pest" "php -d memory_limit=-1 vendor/bin/pest"

exit $status
