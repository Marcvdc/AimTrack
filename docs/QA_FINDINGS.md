# QA Smoke Findings (lokaal)

## Context
- Doel: smoke-test van core flows (user → wapen → sessie → AI → export → bijlagen → queue).
- Status: geblokkeerd vóór UI-tests door dependency issues tijdens setup.

## Bevindingen
1. **Composer install faalt tegen Laravel 12**  
   - `pestphp/pest-plugin-laravel` vereist Laravel ^10/^11 en blokkeert install.  
   - `barryvdh/laravel-dompdf` locked tot Laravel ^11.  
   - Gevolg: geen vendor map, artisan/Filament niet uitvoerbaar → alle handmatige flows geblokkeerd.  
   - Log: zie composer update foutmelding (dependency conflict).  
   - Impact: Blocker voor alle smoke-tests; moet eerst dependency matrix naar Laravel 12 geüpdatet worden (wachten op compat releases of alternatieve pakketten kiezen).

2. **Geen composer.lock aanwezig**  
   - Install draait als `composer update` en haalt laatste versies; zonder lock is CI niet deterministisch.  
   - Bij huidige conflicts bemoeilijkt dit debugging; lockfile moet toegevoegd zodra dependency-issues zijn opgelost.

3. **Smoke-scenario's niet uitgevoerd**  
   - Door bovenstaande install-blocker zijn de gevraagde handmatige testen (user/wapen/sessie/AI/export/bijlagen/queue) niet uitvoerbaar in deze run.  
   - Zodra dependencies gefikst zijn: QA-plan in `docs/QA_PLAN.md` kan direct gevolgd worden voor een volledige smoke-pass.

## Aanpak na fix
- Dependencies alignen met Laravel 12 (check beschikbaarheid van Pest Laravel plugin en Dompdf voor 12.x of pin tijdelijk naar Laravel 11 als tussenstap).  
- Lockfile genereren, `.env` opzetten (SQLite/Pg), `php artisan migrate --seed`, queue worker starten.  
- Daarna QA-plan uitvoeren en resultaten loggen (verwacht binnen 1 run mogelijk als dependencies staan).
