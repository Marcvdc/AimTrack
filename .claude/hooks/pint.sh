#!/usr/bin/env bash
# PostToolUse-hook (Edit/Write): draait Pint op een gewijzigd PHP-bestand, zodat stijlfouten niet
# pas in CI of bij de review opduiken.
#
# Er staat geen PHP op de host. Pint draait daarom in Docker:
#   1. in de draaiende app-container die deze worktree op /var/www/html mount, anders
#   2. in een wegwerpcontainer van het lokale php:8.4-fpm-bookworm-image (wordt niet gepulld).
# Altijd als de eigen gebruiker, nooit als root, zodat er geen bestanden van root achterblijven.
# Lukt geen van beide, dan meldt de hook dat en blokkeert hij niets.
set -uo pipefail

command -v jq >/dev/null || exit 0
file="$(jq -r '.tool_input.file_path // ""')"
[[ $file == *.php ]] || exit 0
[[ -f $file ]] || exit 0

root="$(git -C "$(dirname "$file")" rev-parse --show-toplevel 2>/dev/null)" || exit 0
rel="${file#"$root"/}"
user="$(id -u):$(id -g)"

if [[ ! -x $root/vendor/bin/pint ]]; then
  echo "Pint niet gedraaid op $rel: vendor/bin/pint ontbreekt in deze worktree (composer install nodig)." >&2
  exit 0
fi
command -v docker >/dev/null || { echo "Pint niet gedraaid op $rel: docker ontbreekt." >&2; exit 0; }

container=""
for c in $(docker ps -q 2>/dev/null); do
  if docker inspect "$c" --format '{{range .Mounts}}{{.Source}}={{.Destination}} {{end}}' 2>/dev/null \
    | tr ' ' '\n' | grep -qxF "$root=/var/www/html"; then
    container="$c"
    break
  fi
done

if [[ -n $container ]]; then
  out="$(docker exec -u "$user" -w /var/www/html "$container" vendor/bin/pint "$rel" 2>&1)"
elif docker image inspect php:8.4-fpm-bookworm >/dev/null 2>&1; then
  out="$(docker run --rm -u "$user" -v "$root":/app -w /app php:8.4-fpm-bookworm php vendor/bin/pint "$rel" 2>&1)"
else
  echo "Pint niet gedraaid op $rel: geen draaiende app-container voor deze worktree en geen lokaal php:8.4-image." >&2
  exit 0
fi
rc=$?

if [[ $rc -ne 0 ]]; then
  printf 'Pint faalde op %s (exit %s):\n%s\n' "$rel" "$rc" "$out" >&2
  exit 2
fi
exit 0
