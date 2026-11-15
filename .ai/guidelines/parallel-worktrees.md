# Parallelle ontwikkeling via Git Worktrees

## Wanneer

Gebruik een aparte worktree zodra je een feature wilt ontwikkelen terwijl een andere stack (`aimtrack_dev` of een andere worktree) actief blijft. Denk aan:

- Parallelle Jira-tickets / feature branches
- Migratie-trajecten die de hoofd-dev niet mogen blokkeren
- Browser testen van een nieuwe feature naast een werkende baseline

Niet gebruiken voor: hotfix op de huidige branch, kleine refactor in de huidige stack.

## Branch-model: alles op `main`

Worktrees en PR's baseren zich op `main`, en feature-PR's worden naar `main` gericht. CI (lint, Pest, build) draait op elke PR naar `main`. De branch `develop` bestaat nog op origin, maar loopt ver achter en wordt niet meer gebruikt als integratiebranch. Baseer er niets op.

## Hoofdrepo-discipline

De hoofd-clone (de gewone checkout van de repo, branch `main`) is **alleen voor main-sync**. Geen feature-werk, geen scratch-files, geen plan-bestanden in `.ai/plans/`. Reden:

- Untracked files in de hoofdrepo blokkeren `git pull --ff-only` zodra origin een file met dezelfde naam introduceert ("would be overwritten by merge"). Dit is precies hoe een gemergde feature-PR (die het bijbehorende plan tracked maakt) een schijnbaar onschuldige lokale plan-file in een blocker verandert.
- De hoofdrepo werkt als sync-knooppunt voor alle worktrees: vies werk kruipt door op elke nieuwe worktree die je vanaf `main` afsplitst.

## Vóór je `worktree-setup.sh` aanroept

1. **Lokale `main` bijwerken:** `git -C <hoofdrepo> pull --ff-only`. Het script splitst de nieuwe branch af van de **lokale** basis-branch; staat die achter, dan mist je nieuwe worktree net-gemergde features (incl. de migraties/services waar je plan op leunt).
2. **Hoofdrepo schoon:** `git status` in de hoofdrepo moet leeg zijn. Verplaats lopende plan-files of scratch-werk eerst naar de worktree waar ze bij horen.
3. **Bevestig de afhankelijkheden:** leunt je feature op een nog niet-gemergde branch, geef die branch dan als 3e argument mee aan het script (zie hieronder). Het script controleert met `git rev-parse --verify`, dus een lokale branch, `origin/<branch>` of een SHA werkt.

## Plan-files horen in de worktree

Schrijf `.ai/plans/<TICKET>.md` **in de worktree** (`../aimtrack-<naam>/.ai/plans/<TICKET>.md`), niet in de hoofdrepo. Het plan reist dan mee op de feature-branch, wordt onderdeel van de PR-diff, en blokkeert geen pulls op main.

Als je tijdens het scopen al een DRAFT plan in de hoofdrepo hebt geschreven (bijv. omdat de worktree er nog niet was): verplaats hem naar de juiste worktree zodra die bestaat, vóór de eerste commit. Laat de hoofdrepo daarna leeg achter (`git status` clean).

## Hoe: via het setup script

```bash
./scripts/worktree-setup.sh <feature-naam> [offset] [base-branch]
```

De basis is standaard `main`. Het script:

1. Maakt `../aimtrack-<feature>` aan met branch `feature/<feature>`, afgesplitst van de lokale basis-branch.
2. Kopieert `.env.local` naar de worktree (met terugval op `.env.local.example`, daarna `.env.example`) en zet daarin `APP_URL` op de poort van de worktree (toegevoegd als die regel ontbreekt). `WEB_PORT` wordt alleen aangepast als die regel er al staat.
3. Maakt een **aparte** `.env` in de worktree met de docker-compose overrides (`COMPOSE_PROJECT_NAME=aimtrack_<feature>` en de poorten).
4. Print de URL's en de cleanup-commando's.

### Poorten

Het script telt de regels van `git worktree list` (hoofdclone en alle andere worktrees meegeteld, ook die van andere tools) en gebruikt dat getal als offset, tenzij je er zelf een meegeeft. Daarbij geldt:

| Dienst | Poort |
|---|---|
| Web | 19080 + offset |
| Postgres (forward) | 15432 + offset |
| Python-service (`PYTHON_SERVICE_PORT`) | 19000 + offset |
| Mailpit HTTP | 19025 + offset |
| Mailpit SMTP | 11025 + offset |

`PYTHON_SERVICE_PORT` wordt wel weggeschreven, maar `docker/compose.dev.yml` heeft geen Python-service; de waarde doet niets.

**Bekende valkuil:** er is geen administratie van uitgegeven poorten. Wordt een worktree verwijderd, dan telt `git worktree list` minder regels en krijgt een nieuwe worktree dezelfde offset als een stack die nog draait. Dat is al meerdere keren gebeurd. Controleer na het aanmaken op dubbele poorten:

```bash
grep -h PORT= ../aimtrack-*/.env | sort | uniq -d
```

Geeft dat uitvoer, verwijder dan de nieuwe worktree en maak hem opnieuw aan met een vrije offset als 2e argument. Een fix met een high-water-mark (#118) is gemerged in `develop` en dus niet op `main` beschikbaar.

### Worktrees van andere tools

Tools als Orca maken hun eigen worktrees aan, op een eigen plek en zonder dit script. Die hebben geen `.env` met poorten en projectnaam. Zonder override is de compose-projectnaam `aimtrack_dev` (regel 1 van `docker/compose.dev.yml`), dus dezelfde containers en hetzelfde volume `db_data_dev` als de hoofd-dev stack. Een `migrate` vanaf een featurebranch draait dan op de hoofd-dev-database, ook als die stack uit staat. Draai in zo'n worktree dus alleen een stack met een eigen `.env` (eigen `COMPOSE_PROJECT_NAME` en poorten, bijvoorbeeld vanuit de uitvoer van het script), nooit met de standaardwaarden. Welke worktrees er zijn, zie je met `git worktree list`.

Vervolgens:

```bash
cd ../aimtrack-<feature>
docker compose --env-file .env -f docker/compose.dev.yml up -d
docker compose --env-file .env -f docker/compose.dev.yml exec app php artisan migrate --seed
```

**Cruciaal**: gebruik altijd `--env-file .env`. Docker Compose leest `.env.local` niet, en zonder de flag worden alle compose-variabelen genegeerd. Dat heeft als bijwerking dat de containers de hoofd-dev project name (`aimtrack_dev`) en default poorten (8080, 5432, ...) gebruiken, waardoor je hoofd-dev stack wordt overschreven.

## Verboden

- **Niet** de root `docker-compose.yml` gebruiken voor parallelle stacks (heeft hardcoded `container_name` en geeft conflicten). Altijd `-f docker/compose.dev.yml`.
- **Niet** `--env-file` weglaten in compose commands: zonder die flag mount de worktree per ongeluk de hoofd-dev stack.
- **Niet** handmatig een worktree met docker-stack aanmaken zonder het script of een eigen `.env`; dan botsen poorten en projectnaam met een andere stack.
- **Niet** dezelfde branch in twee worktrees checkouten (Git verbiedt dit, maar zelf opletten).
- **Niet** plan-files of feature-werk in de hoofdrepo plaatsen (zie "Hoofdrepo-discipline" en "Plan-files horen in de worktree").
- **Niet** `worktree-setup.sh` aanroepen met een achterlopende lokale `main`: pull eerst, anders splits je van een verouderde basis en mis je gemergde dependencies.

## Cleanup

```bash
cd ../aimtrack-<feature>
docker compose --env-file .env -f docker/compose.dev.yml down -v
cd -
git worktree remove ../aimtrack-<feature>
git branch -d feature/<feature>   # alleen na merge
```

## Welke worktrees er zijn

Er is bewust geen registry in dit bestand. Een tabel die bij elke setup en cleanup in een gedeeld bestand wijzigt, veroudert vanzelf en geeft merge-conflicten tussen parallelle PR's. De waarheid is `git worktree list`; poorten en projectnaam staan per worktree in `.env`.

## Cross-machine borging

Dit guideline bestand is in git, dus elke developer en elke Claude sessie volgt dezelfde aanpak. MEMORY.md is **niet** geschikt: die is per-machine en zou niet meereizen.
