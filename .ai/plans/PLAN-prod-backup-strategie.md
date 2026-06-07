---
status: APPROVED
created: 2026-05-14
approved: 2026-05-14
author: Claude (Opus 4.7)
github_issue: Marcvdc/AimTrack#83
labels: enhancement, devops
---

# PLAN: Productie backup-strategie borgen in repo (#83)

## Status: APPROVED

## Samenvatting

De productie-backupinfrastructuur (DB-dump + storage-rsync + maandelijkse hersteltest) draait nu vanuit handmatig aangemaakte scripts in `/home/madmin/` op de prod-host. Deze scripts staan niet in git en gaan verloren bij een server-rebuild. Dit PLAN borgt de scripts in de repo, fixt een padbug in de bestaande dev-backup-runner, documenteert de productie-crontab en eigenaarschap, en splitst offsite-backup (rclone → Backblaze B2) af naar een follow-up issue.

## Motivatie

- **Risico:** server-rebuild of disk-failure → backup-strategie weg, herstel onmogelijk te reproduceren.
- **Bewezen werkend:** hersteltest 2026-05-14 → 22 tabellen, 1 user, 7 sessies, 142 schoten, 3 wapens — GESLAAGD. We borgen wat al draait, geen nieuw ontwerp.
- **Bugfix:** `docker/backup/run-backup.sh` verwijst naar `/app/scripts/...` maar de container mount op `/var/www/html` — runner zou nooit werken in de huidige image.

## Acceptatiecriteria (AC)

1. `scripts/backup-aimtrack.sh` bestaat in de repo, identiek aan de prod-versie uit het issue, executable (`chmod +x`).
2. `scripts/restore-test-aimtrack.sh` bestaat in de repo, identiek aan de prod-versie uit het issue, executable.
3. `docker/backup/run-backup.sh` verwijst naar `/var/www/html/scripts/backup-dev-db.sh` (was `/app/scripts/...`) en de sudo-vereiste bij `app_aimtrack` eigenaarschap is gedocumenteerd.
4. `docs/BACKUPS.md` bevat de productie-procedure: crontab-regels (03:00 dagelijks backup, 04:00 op 1e v/d maand hersteltest), eigenaarschap (`madmin`), rechten (`chmod +x`), log-locatie (`/home/madmin/aimtrack-backups/logs/cron.log`), sudo-note voor `run-backup.sh`, hersteltest-bewijs (datum + counts).
5. `docs/BACKUPS.md` bevat een check dat productie `.env` `APP_ENV=production` heeft (niet `local`). `.env.example` blijft staan op `production`.
6. Offsite-backup (rclone → Backblaze B2) is afgesplitst naar een nieuw GitHub-issue, gelabeld `enhancement`/`devops`/`backlog`, met de drie sub-tasks uit issue #83 sectie 4.
7. Pint loopt schoon (`vendor/bin/pint --dirty` na de wijzigingen).
8. `php artisan test --compact` blijft groen (geen functionele code-paden gewijzigd; we voegen bash-scripts en docs toe).

## Buiten scope

- Daadwerkelijk uitvoeren van offsite-config (rclone install, B2 keys) — dat hoort op de prod-server en in een follow-up ticket.
- Wijzigingen aan `scripts/backup-dev-db.sh` zelf — die werkt en wordt door de runner aangeroepen; alleen het pad in de runner is fout.
- Migratie van crontab van host naar een container/systemd-timer — keep what works.
- Een ADR — dit is een ops-borging van bestaande, werkende infra, geen nieuwe architectuurkeuze.

## Afhankelijkheden & risico's

| Risico | Impact | Mitigatie |
|---|---|---|
| Script-inhoud wijkt af van wat op prod draait | Hoog | 1-op-1 overnemen uit issue #83 (door eigenaar bevestigd op 2026-05-14). |
| Iemand kopieert script naar prod zonder rechten te zetten | Medium | `chmod +x` in repo + docs-sectie "Installatie op prod-host". |
| `run-backup.sh` padfix breekt dev-backup voor mensen die de oude image draaien | Laag | Image mount-pad is `/var/www/html` (zie `docker/compose.dev.yml`). Oude `/app/` werkt nu al niet, dus geen regressie. |
| Toegevoegde scripts vragen om Pest-coverage (STOP-conditie 9) | Medium | Scripts zijn bash, geen PHP. Pest dekt geen bash. We documenteren dat dekking via runtime (cron-log + maandelijkse hersteltest) plaatsvindt. Vragen aan reviewer of dit OK is. |

## Architectuur

### Wat verdwijnt

- `/app/scripts/backup-dev-db.sh` als pad-string in `docker/backup/run-backup.sh` (was bug).

### Wat blijft

- `scripts/backup-dev-db.sh` (dev backup, werkt al).
- `docker/backup/run-backup.sh` (alleen pad-string vervangen).

### Wat nieuw wordt toegevoegd

- `scripts/backup-aimtrack.sh` (prod DB-dump + storage-rsync + rotatie).
- `scripts/restore-test-aimtrack.sh` (maandelijkse hersteltest).
- Uitbreiding van `docs/BACKUPS.md` met sectie "Productie installatie & crontab".
- Update van `.ai/guidelines/parallel-worktrees.md` registry (al gedaan in deze worktree-setup).

## Fasering

### Fase 1 — Scripts in repo (SIMPLE)
1. `scripts/backup-aimtrack.sh` — exact zoals in issue, `chmod +x`.
2. `scripts/restore-test-aimtrack.sh` — exact zoals in issue, `chmod +x`.

**Klaar wanneer:** beide bestanden bestaan, executable zijn, `bash -n` valideert syntax zonder fouten.

### Fase 2 — Bugfix runner (SIMPLE)
3. `docker/backup/run-backup.sh`: default voor `BACKUP_SCRIPT` van `/app/scripts/backup-dev-db.sh` → `/var/www/html/scripts/backup-dev-db.sh`.

**Klaar wanneer:** runner verwijst naar correct mount-pad. Geen container nodig om dit te testen — `grep` volstaat.

### Fase 3 — Documentatie (SIMPLE)
4. `docs/BACKUPS.md` uitbreiden met:
   - "Productie installatie" sectie (pad `/home/madmin/`, ownership, `chmod +x`).
   - Crontab-regels (verbatim uit issue).
   - Sudo-note voor `run-backup.sh` wanneer `app_aimtrack` eigenaar van het dev-bestand is.
   - `APP_ENV=production` check.
   - Hersteltest-bewijs 2026-05-14 (1 regel, traceerbaar).

**Klaar wanneer:** alle 5 sub-bullets staan in het bestand, sectie-anchor `## Productie installatie` bestaat.

### Fase 4 — Follow-up issue (SIMPLE)
5. Nieuw GitHub-issue aanmaken voor offsite (rclone+B2) met:
   - Title: `feat: offsite backup naar Backblaze B2 via rclone (follow-up #83)`.
   - Body: 3 sub-tasks uit issue #83 sectie 4 + verwijzing naar dit ticket.
   - Labels: `enhancement`, `devops`, `backlog`.

**Klaar wanneer:** issue staat in GitHub, URL is bekend.

### Fase 5 — Lint + test + commit (SIMPLE)
6. `vendor/bin/pint --dirty`.
7. `php artisan test --compact` (sanity check; geen nieuwe PHP-tests omdat geen PHP raakt aangepast).
8. Commit per fase (script, bugfix, docs, follow-up-link) — wacht expliciet op approval per commit volgens core-workflow.
9. PR openen die issue #83 sluit (Fases 1-3-5 sluiten 4 van de 5 sub-tasks; Fase 4 sluit AC 6).

## Open vragen aan reviewer (Marc)

1. **Coverage-STOP (regel 9):** scripts zijn bash, geen Pest-coverage mogelijk. Akkoord om runtime-bewijs (cron-log + maandelijkse hersteltest) als equivalent te tellen, of moeten we een Pest browser/smoke-test bedenken die het `scripts/`-pad valideert?
2. **`docs/BACKUPS.md` of nieuwe `docs/operations/backups-prod.md`?** `BACKUPS.md` bestaat al, ik stel voor om uit te breiden. OK?
3. **Commit-strategie:** één commit per fase (4 commits) of één gebundelde commit voor issue #83? Issue raakt 4 onafhankelijke artefacten — ik leun naar gebundeld omdat ze samen sluiten.

## STOP-condities (uit core-workflow)

| # | Conditie | Status |
|---|---|---|
| 1 | PLAN bestaat | OK (dit document) |
| 2 | ≥3 AC's + verplichte secties | OK (8 AC's, alle secties) |
| 4 | MCP/JIRA actief | n.v.t. (GitHub-issue, geen Jira) |
| 5 | Linter schoon | uit te voeren in Fase 5 |
| 6 | Code zonder tests/docs | bash-scripts → zie open vraag 1 |
| 7 | Secrets in ENV | geen — scripts bevatten alleen container-/user-namen |
| 9 | Coverage 90% gewijzigd | zie open vraag 1 |
| 10 | PLAN vs JIRA divergent | n.v.t. |
| 11-17 | Architectuur-regels | n.v.t. (alleen bash + docs) |

## Sign-off

- [ ] Marc — review AC's en open vragen, geef APPROVED voor BUILD MODE.
