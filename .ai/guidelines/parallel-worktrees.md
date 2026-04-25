# Parallelle ontwikkeling via Git Worktrees

## Wanneer

Gebruik een aparte worktree zodra je een feature wilt ontwikkelen terwijl een andere stack (`aimtrack_dev` of een andere worktree) actief blijft. Denk aan:

- Parallelle Jira-tickets / feature branches
- Migratie-trajecten die de hoofd-dev niet mogen blokkeren
- Browser testen van een nieuwe feature naast een werkende baseline

Niet gebruiken voor: hotfix op de huidige branch, kleine refactor in de huidige stack.

## Hoe — altijd via het setup script

```bash
./scripts/worktree-setup.sh <feature-naam> [offset]
```

Het script:
1. Maakt `../aimtrack-<feature>` aan met branch `feature/<feature>`
2. Kopieert `.env.local` (Laravel-config) naar de worktree
3. Maakt een **aparte** `.env` in de worktree project root met de docker-compose overrides (`COMPOSE_PROJECT_NAME`, poorten)
4. Print de URL's en cleanup-instructies

Vervolgens:

```bash
cd ../aimtrack-<feature>
docker compose --env-file .env -f docker/compose.dev.yml up -d
docker compose --env-file .env -f docker/compose.dev.yml exec app php artisan migrate --seed
```

**Cruciaal**: gebruik altijd `--env-file .env`. Docker Compose leest `.env.local` niet, en zonder de flag worden alle compose-variabelen genegeerd. Dat heeft als bijwerking dat de containers de hoofd-dev project name (`aimtrack_dev`) en default poorten (8080, 5432, ...) gebruiken — waardoor je hoofd-dev stack wordt overschreven.

## Verboden

- **Niet** de root `docker-compose.yml` gebruiken voor parallelle stacks — heeft hardcoded `container_name` en geeft conflicten. Altijd `-f docker/compose.dev.yml`.
- **Niet** `--env-file` weglaten in compose commands — zonder die flag mount de worktree per ongeluk de hoofd-dev stack.
- **Niet** handmatig worktrees aanmaken zonder het script — dan loopt de poort-administratie en `.env`-isolatie uit de pas.
- **Niet** dezelfde branch in twee worktrees checkouten (Git verbiedt dit, maar zelf opletten).

## Cleanup

```bash
cd ../aimtrack-<feature>
docker compose --env-file .env -f docker/compose.dev.yml down -v
cd -
git worktree remove ../aimtrack-<feature>
git branch -d feature/<feature>   # alleen na merge
```

Vergeet de registry hieronder niet bij te werken.

## Registry van actieve worktrees

| Feature | Branch | Pad | Web | DB | Mailpit | Python | Status |
|---|---|---|---|---|---|---|---|
| _hoofd-dev_ | _huidige_ | `aimtrack/` | 8080 | 5432 | 8025 | 8000 | actief |
| copilot | feature/copilot | `aimtrack-copilot/` | 19080 | 15433 | 19025 | 19000 | actief (Filament Copilot migratie) |

Update deze tabel bij setup en cleanup — zo weet iedereen direct welke poort bij welke stack hoort.

## Cross-machine borging

Dit guideline bestand is in git, dus elke developer en elke Claude sessie volgt dezelfde aanpak. MEMORY.md is **niet** geschikt — die is per-machine en zou niet meereizen.
