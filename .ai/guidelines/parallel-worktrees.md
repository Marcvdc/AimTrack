# Parallelle ontwikkeling via Git Worktrees

## Wanneer

Gebruik een aparte worktree zodra je een feature wilt ontwikkelen terwijl een andere stack (`aimtrack_dev` of een andere worktree) actief blijft. Denk aan:

- Parallelle Jira-tickets / feature branches
- Migratie-trajecten die de hoofd-dev niet mogen blokkeren
- Browser testen van een nieuwe feature naast een werkende baseline

Niet gebruiken voor: hotfix op de huidige branch, kleine refactor in de huidige stack.

## Branch-model: `main` / `develop`

- **`main`** — productie-trunk. Komt **alleen** binnen via een PR vanuit `develop` (of een losse hotfix-branch). Geen directe feature-merges meer op `main`.
- **`develop`** — integratie- en testbranch: de "test-versie van `main`". Alle feature-worktrees worden hiervan afgesplitst en hier weer in gemerged. CI (lint + Pest + build) draait automatisch op elke PR naar `develop` **en** op elke push naar `develop`. Zodra `develop` stabiel is → PR `develop` → `main`.

Concreet: je baseert een worktree op `develop` (niet op `main`) en richt de feature-PR op `develop`. `main` blijft daarmee altijd een gemergde, groene baseline.

## Hoofdrepo-discipline

De hoofd-clone (`/home/brandnetel/projects/aimtrack`, branch `main`) is **alleen voor main-sync**. Geen feature-werk, geen scratch-files, geen plan-bestanden in `.ai/plans/`. Reden:

- Untracked files in de hoofdrepo blokkeren `git pull --ff-only` zodra origin een file met dezelfde naam introduceert ("would be overwritten by merge"). Dit is precies hoe een gemergde feature-PR (die het bijbehorende plan tracked maakt) een schijnbaar onschuldige lokale plan-file in een blocker verandert.
- De hoofdrepo werkt als sync-knooppunt voor alle worktrees: vies werk kruipt door op elke nieuwe worktree die je vanaf `main` afsplitst.

## Vóór je `worktree-setup.sh` aanroept

1. **Lokale basis-branch bijwerken:** werk de branch bij waarvan je afsplitst. Voor de geïntegreerde flow is dat `develop`: `git -C <hoofdrepo> fetch origin develop:develop` (fast-forwardt de lokale `develop`-ref zonder checkout — de hoofdrepo blijft op `main`). Voor een op `main` gebaseerde worktree: `git -C <hoofdrepo> pull --ff-only`. Het script splitst de nieuwe branch af van de **lokale** basis-branch; staat die achter, dan mist je nieuwe worktree net-gemergde features (incl. de migraties/services waar je plan op leunt).
2. **Hoofdrepo schoon:** `git status` in de hoofdrepo moet leeg zijn. Verplaats lopende plan-files of scratch-werk eerst naar de worktree waar ze bij horen.
3. **Bevestig de afhankelijkheden:** als je nieuwe feature op een nog niet-gemergde branch leunt, splits dan vanaf die branch (`git worktree add -b <naam> ../aimtrack-<naam> <basis-branch>`) en niet via het script — het script forceert `main` als basis.

## Plan-files horen in de worktree

Schrijf `.ai/plans/<TICKET>.md` **in de worktree** (`../aimtrack-<naam>/.ai/plans/<TICKET>.md`), niet in de hoofdrepo. Het plan reist dan mee op de feature-branch, wordt onderdeel van de PR-diff, en blokkeert geen pulls op main.

Als je tijdens het scopen al een DRAFT plan in de hoofdrepo hebt geschreven (bijv. omdat de worktree er nog niet was): verplaats hem naar de juiste worktree zodra die bestaat, vóór de eerste commit. Laat de hoofdrepo daarna leeg achter (`git status` clean).

## Hoe — altijd via het setup script

```bash
./scripts/worktree-setup.sh <feature-naam> [offset] [base-branch]
```

Voor de geïntegreerde flow baseer je op `develop` (3e arg; laat de offset leeg voor auto):

```bash
./scripts/worktree-setup.sh <feature-naam> "" develop
```

Het script:
1. Maakt `../aimtrack-<feature>` aan met branch `feature/<feature>` (default basis `main`, geef `develop` mee voor de integratie-flow)
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
- **Niet** plan-files of feature-werk in de hoofdrepo plaatsen — zie "Hoofdrepo-discipline" en "Plan-files horen in de worktree".
- **Niet** `worktree-setup.sh` aanroepen met een achterlopende lokale `main` — pull eerst, anders splits je van een verouderde basis en mis je gemergde dependencies.

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

Alleen worktrees die op dit moment daadwerkelijk bestaan (`git worktree list`). Afgeronde
worktrees horen hier niet meer in — zie *Cleanup* hierboven.

| Feature | Branch | Pad | Web | DB | Mailpit | Python | Status |
|---|---|---|---|---|---|---|---|
| _hoofd-dev_ | main | `AimTrack/` | 8080 | 5432 | 8025 | 8000 | actief (alleen main-sync) |
| 106-visual | feature/ai-verenigingen | `aimtrack-106-visual/` | 19091 | 15441 | 19036 | — | actief (issue #95 fase 2 · visuele controle) |
| 55-vision-direct | feature/55-vision-direct | `aimtrack-55-vision-direct/` | 19082 | 15434 | 19027 | 19002 | actief (issue #55 · PR #114) |
| prod-storage | feature/prod-storage | `aimtrack-prod-storage/` | 19090 | 15442 | 19035 | 19010 | actief (productie-503 `storage_unwritable`) |

Update deze tabel bij setup en cleanup — zo weet iedereen direct welke poort bij welke stack hoort.

**Poorten**: `worktree-setup.sh` kiest de default-offset zelf, op basis van de hoogste
`WEB_PORT` die al aan een zuster-worktree is toegewezen. Breek een stack daarom af
(`docker compose ... down -v`) vóór `git worktree remove`: het script leest de nog
aanwezige `.env`-bestanden, dus een verwijderde worktree geeft zijn poort weer vrij.
De precieze grenzen van die logica staan in de comment bij `OFFSET` in het script zelf —
bewust op één plek, zodat deze tabel niet uit de pas kan lopen.

## Cross-machine borging

Dit guideline bestand is in git, dus elke developer en elke Claude sessie volgt dezelfde aanpak. MEMORY.md is **niet** geschikt — die is per-machine en zou niet meereizen.
