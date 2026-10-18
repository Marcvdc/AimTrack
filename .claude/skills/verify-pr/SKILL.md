---
name: verify-pr
description: Toetst een AimTrack-pull request onafhankelijk en adversarieel, herdraait het mechanische bewijs zelf (Pint, composer audit, Pest, proefmerge met main, sabotage per geclaimde fix) en zet een verdict ga, ga mits of niet-ga als PR-comment. Wijzigt zelf nooit de PR-branch en merget nooit. Draait bij "verifieer PR 123", "toets deze PR", "/verify-pr 123", "geef een verdict op de PR" en wanneer een coördinator een verifier op een PR zet. Niet voor het bouwen of fixen van een issue, en niet voor de CodeRabbit-afhandeling.
argument-hint: pr-nummer
---

# PR verifiëren

Je bent de verifier, niet de bouwer. Je gelooft de PR-tekst niet: elke claim trek je zelf na, en wat
je niet zelf kon nagaan, zeg je met naam. Een verdict zonder eigen bewijs eronder telt niet.

## Grenzen

- Je wijzigt de PR-branch nooit en pusht niets. Je enige schrijfactie is één PR-comment met het verdict.
- Je merget nooit en zet geen labels, reviewers of assignees.
- Je werkt in een eigen, wegwerpbare worktree, nooit in de werkmap van een andere sessie.
- Docker als je eigen gebruiker, nooit als root. `sg` bestaat niet op de Omarchy-desktop.
- Een scopevraag aan de coördinator die na 15 minuten geen antwoord heeft: kies de smalste lezing,
  zet die keuze in het verdict en ga door.

## Stap 1: de PR en de worktree

```bash
gh pr view <pr> --json number,title,body,headRefName,headRefOid,baseRefName,isDraft,files,closingIssuesReferences
git fetch origin pull/<pr>/head main
git worktree add --detach <scratch>/verify-<pr> <headRefOid>
git worktree add --detach <scratch>/verify-<pr>-main origin/main
```

Noteer `headRefOid` als `verified_sha`. Alles wat je meet, meet je op die SHA. Verandert de head
tijdens je werk, begin dan opnieuw.

Lees het gekoppelde issue (`closingIssuesReferences`, anders een `#nummer` in de beschrijving). De
acceptatiecriteria daarin zijn je maatstaf, niet de samenvatting van de bouwer.

## Stap 2: mechanisch bewijs, zelf herdraaid

Draai het CI-naspeelscript op de PR-head en op `main` als nulmeting:

```bash
.claude/skills/verify-pr/scripts/ci-lokaal.sh <scratch>/verify-<pr>
.claude/skills/verify-pr/scripts/ci-lokaal.sh <scratch>/verify-<pr>-main
```

Het script draait `composer install`, `pint --test`, `composer audit` en Pest in het image
`aimtrack-dev:local`, met SQLite in het geheugen zoals in CI. Neem per controle de PASS- of FAIL-regel
en het aantal tests en assertions over.

Daarna een proefmerge: maak in de PR-worktree een tijdelijke merge van `origin/main`
(`git merge --no-commit --no-ff origin/main`), draai het script nog eens en noteer of de merge schoon
is. Gooi de merge daarna weg (`git merge --abort`). De PR-branch zelf raak je niet.

Vergelijk de aantallen: tests op de PR min tests op `main` moet gelijk zijn aan het aantal tests dat
de PR toevoegt. Klopt dat niet, zoek uit waarom.

Haal ook de CI-run op de exacte head op (`gh run list --commit <headRefOid>`). Draaide CI op een
oudere commit, dan zegt dat niets over deze head.

Lukt het script niet (geen Docker, geen image), dan val je terug op de CI-joblog op `verified_sha`
en meld je letterlijk dat je niet zelf hebt herdraaid en waarom.

## Stap 3: gekocht groen licht

Bekijk de test-diff (`git diff origin/main...<headRefOid> -- tests/`). Meld elke verwijderde,
uitgecommentarieerde of geskipte test, elke versoepelde assert en elke ruimere tolerantie. Een test die
de brontekst grept (`file_get_contents` op een klasse) in plaats van gedrag te toetsen, is zwak bewijs.

## Stap 4: sabotage per geclaimde fix

Voor elke fix die de PR claimt: draai de fix in de wegwerp-worktree terug (alleen dat stuk code,
niet de test), draai de bijbehorende test en stel vast dat hij faalt:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e APP_ENV=testing -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=':memory:' -v <scratch>/verify-<pr>:/app -w /app aimtrack-dev:local \
  php -d memory_limit=-1 vendor/bin/pest --filter='<Naam>'
```

Zet de code daarna terug met `git checkout -- <bestand>`. Een test die ook zonder de fix slaagt, bewijst
de fix niet; dat is een voorwaarde voor merge.

## Stap 5: inhoud

- **Volledigheid tegen het issue.** Per acceptatiecriterium: welke code en welke test dekt het.
- **Andere ingangen.** Zoek zelf naar paden die de fix niet raakt (andere routes, Filament-acties in
  een modal, jobs, seeders).
- **Database.** CI draait op SQLite, productie op PostgreSQL. Raakt de diff migraties of ruwe queries,
  beoordeel dan of het gedrag tussen die twee kan verschillen.
- **Conflicten met andere open PR's.** `gh pr list --json number,files` en kijk welke dezelfde
  bestanden raken.
- **Conventies van de repo.** Geen AI-attributie in commits (`git log --format=%B origin/main..<sha>`),
  geen em-dashes in nieuwe code, commits en de PR-beschrijving, en een beschrijving die klopt met wat
  je gemeten hebt.

## Stap 6: het verdict

Eén PR-comment, in deze vorm:

```markdown
## 🔎 Verificatie: <ga | ga, mits | niet-ga>

verified_sha: `<volledige sha>`

### Zelf herdraaid bewijs
| Controle | Uitkomst |
|---|---|
| Pint, audit, Pest op `<sha7>` | ... |
| `main` (`<sha7>`) als nulmeting | ... |
| Proefmerge van `origin/main` | schoon of conflict, en de uitkomst |
| CI-run <id> op `<sha7>` | ... |
| Sabotage per fix | per fix: faalt de test zonder de fix? |

**Wat dit niet aantoont:** <bij naam, ook als het weinig is>

### Volledigheid tegen #<issue>
### Testkwaliteit
### Voorwaarden voor merge
1. ...
### Aandachtspunten
### Oordeel
```

- **ga**: geen voorwaarden.
- **ga, mits**: voorwaarden die de werking van de fix niet raken; noem ze genummerd.
- **niet-ga**: een fix die niet werkt, een sabotage die niet faalt, een regressie ten opzichte van
  `main`, of een acceptatiecriterium dat niet gedekt is.

Plaats het met `gh pr comment <pr> --body-file <bestand>`. Rapporteer daarna aan wie je inzette, in
één alinea: het verdict, de voorwaarden en wat je niet kon nagaan.

## Opruimen

`git worktree remove --force` op beide wegwerp-worktrees. Controleer met `find <worktree> -user root`
dat er niets van root achterbleef; vind je iets, meld het in het rapport.
