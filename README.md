# AimTrack

AimTrack is een moderne, self-hosted schietlog voor sportschutters. Registreer sessies (met wapens/afstanden), voeg bijlagen toe, krijg AI-reflecties en wapen-trends, stel vragen aan een AI-coach en genereer CSV/PDF-exports voor o.a. WM-4-achtige aanvragen. Gebouwd met Laravel 12, Filament 4 en Livewire 3.

## Kernfeatures
- **Sessies**: datum, baan/vereniging, locatie, tijden, notities, attachments en meerdere wapen × afstand entries.
- **Reflectie**: handmatige reflectie + AI-reflectie (samenvatting, went_well, needs_improvement, next_focus).
- **Wapenbeheer**: wapens met type/kaliber/serienummer/opslag; inzicht in gebruik per sessie.
- **AI-coach**: vrije vragen op basis van eigen sessies/wapens.
- **Export**: CSV/PDF per periode + wapenselectie met NL-disclaimer.
- **Infra**: Docker Compose (nginx, php-fpm, db, queue) en queue-jobs voor AI-taken.

## Stack
- PHP 8.4/8.5, Laravel 12
- Filament 4 + Livewire 3
- Database: PostgreSQL (default) of MySQL
- Queue: database driver (queue-worker container)

## Snel starten (Docker)
1. Kopieer `.env.example` naar `.env` en vul waarden in.
2. Start stack: `docker compose up -d`.
3. Installeer dependencies: `docker compose exec app composer install`.
4. Genereer key: `docker compose exec app php artisan key:generate`.
5. Migreer (en seed): `docker compose exec app php artisan migrate --seed`.
6. Open `http://localhost:8080` en log in (standaard Laravel-auth). Gebruik Filament om sessies/wapens te beheren.

## AI-configuratie
- Zet `AI_DRIVER`, `AI_MODEL`, `OPENAI_API_KEY` en `OPENAI_BASE_URL` (of andere provider variabelen) in `.env`.
- AI-calls verlopen via queue-jobs (`GenerateSessionReflectionJob`, `GenerateWeaponInsightJob`). Zorg dat de `queue` container draait.
- Prompts bevatten NL-disclaimers; AI-output is adviserend en vervangt geen veiligheidsregels of instructeurs.

## Export
Gebruik de Filament **Export**-pagina om CSV of PDF te downloaden. Periode is verplicht; wapenselectie optioneel. Download bevat een disclaimer dat de gebruiker zelf verantwoordelijk blijft voor actuele WM-4-eisen.

## Documentatie
- Techniek: `docs/architecture.md`, `docs/ai.md`, `docs/export.md`, `docs/infra.md`, `docs/operations.md`, `docs/security.md`
- Gebruiker: `docs/user/quickstart.md`, `docs/user/sessions.md`, `docs/user/weapons.md`, `docs/user/export.md`, `docs/user/ai-coach.md`
- Overige plannen: `docs/PLAN.md`, `docs/BACKUPS.md`, `docs/PROD_HARDENING.md`

## Ontwikkelen
- Lint: `composer lint` (Laravel Pint).
- Tests: `composer test` (Pest/phpunit).
- Houd NL-labels aan in Filament en gebruik queue voor AI-taken.

## Licentie
MIT – zie [LICENSE](LICENSE).
