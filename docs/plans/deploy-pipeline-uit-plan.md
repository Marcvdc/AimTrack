# Automatische deploys naar de oude Pi uitzetten

Status: APPROVED (Marc, 2026-10-12; open vragen 1 en 2 volgens voorstel)
Auteur: Claude (sessie in Marcvdc/homelab)
Datum: 2026-10-12
Aanleiding: uitrol van #157 en #176 op VM 202, zie Marcvdc/homelab#8 en Marcvdc/homelab#1

## 1. Context & Aanleiding

AimTrack draait sinds 2026-10-11 op VM 202 in de homelab. Een update gaat daar met de hand:
`git pull`, `docker compose build`, `up -d` (runbook `docs/runbooks/aimtrack.md` in
Marcvdc/homelab). De oude host, de Raspberry Pi, is gecompromitteerd geweest (Marcvdc/homelab#1);
AimTrack staat daar sinds 2026-10-12 stil.

Deze repo heeft nog twee pipelines die naar die Pi deployen:

1. **`deploy-production` in `.github/workflows/ci.yml`**, bij elke push naar `main`. Bouwt een
   multi-arch image, pusht naar GHCR, zet een WireGuard-tunnel op, rsynct `docker/` en `scripts/`
   naar `SSH_HOST` en draait daar `scripts/remote_deploy.sh`. Het environment `production` heeft
   een verplichte reviewer (Marc) en een wachttijd van 15 minuten. Bij de merges van #157 en #176
   op 2026-10-12 stonden er twee deploys klaar; beide met de hand afgewezen.
2. **`.github/workflows/deploy-staging.yml`**, bij elke push naar `staging`. Zelfde route. Het
   environment `staging` heeft **alleen een wachttijd, geen reviewer**: een push naar de branch
   `staging` (die bestaat, `0a37408`) deployt zonder dat iemand op een knop drukt.

Gemeten op 2026-10-12 (namen en `updated_at`, geen waarden):

| Environment | Secrets | Laatst gezet |
|---|---|---|
| `production` | `APP_URL`, `DEPLOY_PATH`, `GHCR_TOKEN`, `GHCR_USERNAME`, `SSH_HOST`, `SSH_KEY`, `SSH_PORT`, `SSH_USER` | 2025-12-28 |
| `staging` | dezelfde, plus `WG_CONFIG` | 2025-12-28/29 |

`production` heeft geen `WG_CONFIG` en geen `ENV_FILE_B64` (meer). Een productiedeploy zou dus
waarschijnlijk falen bij de tunnel, maar dat is geen beveiliging om op te leunen: de staging-route
heeft de tunnel wel. Alle waarden dateren van vóór of tijdens de compromittering (sinds
2026-04-21) en wijzen naar de Pi; `SSH_KEY` en `WG_CONFIG` zijn sleutels waarvan de tegenhanger
op de Pi stond. Ze gelden als gelekt.

**Belangrijk voor de aanpak.** Bij een `push` draait GitHub de workflow uit de branch waarop
gepusht wordt, niet die uit `main`. De branch `staging` bevat zijn eigen `deploy-staging.yml`
(en een oude `deploy-production.yml`). Het bestand uit `main` halen dicht de staging-route dus
**niet**. Dat doen alleen het uitschakelen van de workflow in GitHub (stap 0) en het verwijderen
van de environment-secrets (stap 4). Die twee stappen zijn daarom geen nazorg maar de eigenlijke
maatregel; de wijziging in de repo zorgt dat `main` en de documentatie kloppen.

## 2. Doelen

1. Geen enkele push of merge in deze repo start nog een deploy naar een host.
2. De documentatie beschrijft de deploy zoals die nu werkt: met de hand op de homelab-VM.
3. De secrets die bij de oude pipelines horen, verdwijnen uit GitHub.

## 3. Scope & Grenzen

**In scope**
- `.github/workflows/ci.yml`: de job `deploy-production` eruit. `lint`, `test`, `audit` en
  `build` blijven ongewijzigd.
- `.github/workflows/deploy-staging.yml`: verwijderen.
- Documentatie: `README.md` (secties staging- en productiedeploy), `docs/CI-CD-PLAN.md`,
  `docs/operations.md` (deployparagraaf) en `docs/PROD_HARDENING.md` (de verwijzing naar
  `ENV_FILE_B64`). Ze verwijzen voortaan naar het runbook in Marcvdc/homelab.
- Een Pest-test die faalt zodra er weer een deploy-job of -workflow in `.github/workflows/`
  verschijnt.

**Uit scope**
- `scripts/remote_deploy.sh`, `scripts/rollback.sh`, `docker/compose.staging.yml` en
  `tests/Unit/DeployRollbackScriptsTest.php`. Die blijven staan: ze zijn getest, raken niets
  zolang geen workflow ze aanroept, en zijn bruikbaar als er later een pipeline naar VM 202
  komt. Bovenin beide scripts komt één regel dat geen workflow ze meer aanroept. Zie open vraag 1.
- `docker/compose.prod.yml`: de homelab draait een eigen compose (lokale build), maar deze blijft
  de referentie waar die van afwijkt.
- Een nieuwe pipeline naar VM 202. Dat is een eigen besluit, met een eigen plan.
- De secrets zelf: die verwijdert Marc in GitHub, na de merge (zie 5, stap 4).

## 4. Constraints & Assumpties

- `main` heeft geen branch protection en geen verplichte status checks (`gh api
  .../branches/main/protection` geeft 404). Het weghalen van de job breekt dus geen merge-regel.
- De GHCR-images (`ghcr.io/marcvdc/aimtrack:prod-latest`, `staging-latest`) blijven bestaan. Ze
  worden nergens meer gepulld; opruimen is optioneel.
- De repo-secret `ANTHROPIC_API_KEY` (2026-03-23) hoort bij `claude.yml`, niet bij een deploy, en
  valt buiten dit plan.

## 5. Aanpak

0. **Direct, los van de merge (Marc):** `gh workflow disable deploy-staging.yml -R Marcvdc/AimTrack`.
   Dit dicht het pad zonder reviewer, ook voor de branch `staging`, die zijn eigen kopie van de
   workflow heeft. Terug: `gh workflow enable ...`.
1. Job `deploy-production` uit `ci.yml` halen, `deploy-staging.yml` verwijderen.
2. Test `tests/Unit/GeenDeployWorkflowTest.php`: leest elke `.github/workflows/*.yml` en faalt
   op `environment: production`, `environment: staging`, `remote_deploy.sh`, `wg-quick` of een
   verwijzing naar `secrets.SSH_KEY`.
3. Documentatie bijwerken volgens 3, en de kopregel in beide scripts.
4. **Na de merge (Marc, in GitHub):** Settings, Environments: `production` en `staging`
   verwijderen. Daarmee gaan alle environment-secrets in één keer weg.
   - De PAT achter `GHCR_TOKEN` intrekken op github.com/settings/tokens, als het een PAT is (de
     workflows gebruikten hem niet; zij loggen in met `GITHUB_TOKEN`).
   - `SSH_KEY` en `WG_CONFIG`: de tegenhangers verdwijnen met het platgooien van de Pi
     (Marcvdc/homelab#1) en de verse WireGuard-install (Marcvdc/homelab#6). Vermelden op die
     issues, niet hier oplossen.
   - Terug: environments opnieuw aanmaken kan, maar de oude waarden zijn weg. Dat is de bedoeling.
   - Een workflow op een oude branch die naar een verwijderd environment verwijst, maakt het
     opnieuw aan, maar dan zonder secrets: de tunnel en de SSH-stap falen.

## 6. Acceptatiecriteria

1. `ci.yml` bevat geen job met `environment:`; `.github/workflows/deploy-staging.yml` bestaat niet.
2. CI op de PR-branch is groen: lint, test, audit en build.
3. `git grep -n -E 'deploy-production|deploy-staging|ENV_FILE_B64|WG_CONFIG'` toont alleen nog
   deze planfile, de regels die uitleggen dat de pipeline weg is, de bewaarde
   `scripts/remote_deploy.sh` en het oorspronkelijke ontwerp in `docs/CI-CD-PLAN.md` (onder een
   kop die zegt dat het uitgezet is).
4. De nieuwe test faalt als je de oude job terugzet (sabotage, niet gecommit).
5. Na stap 4: `gh api repos/Marcvdc/AimTrack/environments` toont geen `production` en `staging`.

## 7. Tests

- `GeenDeployWorkflowTest`: hierboven. Sabotage: de verwijderde job tijdelijk terugzetten, test
  moet rood.
- Bestaande suite (`php artisan test`) en `pint --test` ongewijzigd groen.

## 8. Risico's

- **Een latere pipeline naar VM 202 botst met de nieuwe test.** Bedoeld: die moet dan bewust de
  test aanpassen, in een eigen plan.
- **Dependabot of een andere bot opent een PR op `staging`.** Met de workflow weg gebeurt er niets.
- **Iemand rekent op `prod-latest` uit GHCR.** Niet bekend; de homelab bouwt lokaal.

## 9. Documentatie

`README.md`, `docs/CI-CD-PLAN.md`, `docs/operations.md`, `docs/PROD_HARDENING.md` volgens 3.
Registry in `.ai/guidelines/parallel-worktrees.md` bij setup en cleanup.

## Open vragen voor Marc

1. Scripts laten staan (voorstel) of ook `remote_deploy.sh`, `rollback.sh`,
   `compose.staging.yml` en hun test verwijderen? Weghalen geeft een kleinere repo zonder dode
   code; laten staan scheelt werk als er een pipeline naar VM 202 komt.
2. De branch `staging` verwijderen? Voorstel: laten staan, apart besluiten. Let op: hij heeft
   zijn eigen kopie van de workflows (zie 1); met stap 0 en 4 doet hij niets meer.
