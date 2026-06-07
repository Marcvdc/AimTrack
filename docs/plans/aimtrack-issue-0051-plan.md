# CI – CIDC Errors Plan
Status: APPROVED (per PO, 2026-01-26)
Auteur: Cascade AI
Datum: 2026-01-26
Issue: GH-51

## 1. Context & Aanleiding
- GitHub Actions CI (lint job) draait `composer install` + `vendor/bin/pint --test` maar faalt tijdens `artisan package:discover` met `InvalidArgumentException: Please provide a valid cache path`.
- `config/view.php` gebruikt `realpath(storage_path('framework/views'))`. Bij een schone checkout bestaat `storage/framework/views` niet, waardoor `realpath` `false` retourneert en de View Compiler geen pad ontvangt.
- Lokale ontwikkelaars hebben de map vaak handmatig staan, waardoor het probleem enkel zichtbaar is in CI of nieuwe omgevingen. Hierdoor blokkeert iedere pull request pipeline.

## 2. Doelen
1. CI-workflow moet zonder handmatige stappen `composer install` en Pint kunnen uitvoeren.
2. View-compiler moet altijd een geldig pad krijgen, ook wanneer `storage/` nog niet geprimed is.
3. Repo/documentatie moet duidelijk maken welke storage-submappen vereist zijn zodat nieuwe omgevingen voorspelbaar opstarten.

## 3. Scope & Grenzen
**In scope**
- Analyse en fix van `config/view.php` default waarde en het automatisch aanmaken van `storage/framework/views` (en gerelateerde) directories.
- Updates aan `.github/workflows/ci.yml` of helper scripts zodat storage directories bestaan vóór artisan-scripts draaien.
- Documentatie van vereiste storage-mappen en eventuele nieuwe ENV hints (bv. `VIEW_COMPILED_PATH`).
- Toevoegen van ontbrekende `.gitignore` placeholders in `storage/framework/*` indien nodig.

**Out of scope**
- Herstructurering van de volledige CI pipeline (docker build/test blijven ongewijzigd).
- Introduceren van alternatieve cache backends of het verplaatsen van view-compiles buiten `storage/`.
- Oplossen van andere Laravel cache/queue directory issues buiten dit issue.

## 4. Constraints & Assumpties
- Bestaande compliance constraints (C-PII-Logging, C-API-Timeouts) blijven van kracht; logging blijft in applicatielogs.
- CI-beeld draait op Ubuntu runners zonder persistent disk; vereiste directories moeten per run gecreëerd worden.
- Wijzigingen mogen lokale dev-ervaring niet verslechteren: ontwikkelaars moeten nog steeds zonder extra scripts `php artisan serve` kunnen starten.
- Geen secrets in workflow of config; eventuele ENV uitbreidingen moeten `.env.example` en `.env.local.example` volgen.

## 5. Aanpak (Stappen)
1. **Analyse & bevestiging**
   - Reproduceer lokaal door `rm -rf storage/framework/views` en `composer install` om stacktrace te bevestigen.
   - Controleer andere storage-subdirs (cache, sessions) om te bepalen of meer placeholders nodig zijn.
2. **Config hardening**
   - Pas `config/view.php` aan zodat `'compiled'` zonder `realpath()` een pad retourneert.
   - Voeg bootstrapping toe (bv. in `AppServiceProvider::boot` of dedicated helper) die ontbrekende directories creëert (`StoragePathInitializer`).
3. **Repo & workflow updates**
   - Commit `.gitignore` placeholders binnen `storage/framework/views` (en andere benodigde paden) zodat directories bestaan na checkout.
   - Voeg in `.github/workflows/ci.yml` een vroege stap toe (`mkdir -p storage/framework/{cache,views}` + `chmod -R 775 storage`) zodat artisan-commando's nooit falen wanneer directories weggegooid worden.
4. **Validatie & tests**
   - Draai lokaal `composer install`, `vendor/bin/pint --test`, en `php artisan package:discover` op een schone workspace.
   - Run gerichte Pest suite (`php artisan test --compact`) voor sanity-check.
5. **Documentatie & communicatie**
   - Update `docs/AimTrack/index.md` (Recent) en eventueel `docs/AimTrack/constraints.md` of `PLAN.md` met vereiste storage-priming stappen.
   - Voeg notities toe aan `.env.example` indien `VIEW_COMPILED_PATH` expliciet configureerbaar moet zijn.

## 6. Acceptatiecriteria
1. Een schone clone + `composer install && vendor/bin/pint --test` slaagt zonder `InvalidArgumentException` over de cache path (bevestigd via lokale run of CI dry-run).
2. `config/view.php` levert altijd een bestaand pad (of creëert het) zonder afhankelijk te zijn van `realpath`, en bevat geen regressies voor bestaande omgevingen.
3. GitHub Actions workflow bevat expliciete stap(pen) die storage directories voorbereiden, en documentatie vermeldt deze vereiste zodat nieuwe omgevingen voorspelbaar werken.
4. Pint en de relevante Pest tests draaien groen na implementatie.

## 7. Open Vragen / Afhankelijkheden
- Moet dezelfde fix ook toegepast worden op andere cache-gerelateerde configs (bv. Twig/mail view caches) of volstaat `views`?
- Heeft hostingomgeving custom `VIEW_COMPILED_PATH` nodig (bijv. bij readonly storage), en zo ja, moeten we een fallback implementeren?
- Zijn er bestaande scripts (bijv. `scripts/healthcheck.sh`) die storage-permissies beheren waarmee we moeten alignen?
