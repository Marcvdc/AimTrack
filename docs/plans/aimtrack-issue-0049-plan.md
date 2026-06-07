# Export HTTPS Enforcement Plan
Status: APPROVED (user-confirmed)  
Auteur: Cascade AI  
Datum: 2026-01-26  
Issue: GH-49

## 1. Context & Aanleiding
- Gebruikers downloaden exports via `/exports/sessions/download`. In productie draait de site onder HTTPS (`https://aimtrack.nl`).
- Tijdens downloads wordt de request geredirect naar een HTTP-url, wat mixed-content warnings veroorzaakt en sommige browsers blokkeert volledige downloads.
- De vermoedelijke oorzaken: onjuiste `APP_URL` of `config('app.url')`, ontbrekende proxy headers (X-Forwarded-Proto) of het ontbreken van een gedwongen HTTPS-scheme in URL-generation.

## 2. Doelen
1. Export-downloads (CSV/PDF) moeten volledig via HTTPS verlopen zonder mixed-content waarschuwingen.
2. Applicatie moet consistent juiste scheme gebruiken, ongeacht proxy/load balancer configuratie.
3. Logging en documentatie moeten vastleggen welke wijzigingen nodig zijn (app config, infra hints) zodat regressies voorkomen worden.

## 3. Scope & Grenzen
**In scope**
- Analyse van huidige URL-generatie voor exportroutes (Filament pagina, route helper, redirects).
- Configuratie-aanpassing binnen Laravel (bv. `AppServiceProvider` of middleware) om HTTPS af te dwingen op URL+asset generation.
- Controle van relevante `.env.example` hints zodat deployments de juiste `APP_URL` gebruiken.
- Documentatie-updates (docs/AimTrack/index.md Recent + eventuele constraints) en tests voor route-beveiliging (bv. feature test die assert dat redirect scheme HTTPS is wanneer HTTPS wordt gesimuleerd).

**Uit scope**
- Wijzigingen aan hosting infrastructuur zelf (nginx/Cloudflare), behalve documentatie van vereiste headers.
- Herbouw van exportfunctionaliteit zelf (data, templates) zolang mixed-content is opgelost.
- Nieuwe feature flags of toggles buiten HTTPS enforcing.

## 4. Constraints & Assumpties
- Applicatie draait achter een reverse proxy die `X-Forwarded-Proto` verstuurt; Laravel moet de `TrustProxies` config correct gebruiken.
- Geen plain HTTP verkeer toegestaan; fallback naar HTTP is ongewenst.
- Geen wijziging aan bestaand downloadpad/naamgeving (gebruikers verwachten `/exports/sessions/download`).
- Houd rekening met multi-environment setup (local dev kan HTTP blijven).

## 5. Aanpak (Stappen)
1. **Analyse huidige configuratie**
   - Controleer `App\Http\Middleware\TrustProxies` en `config/trustedproxy.php` (indien aanwezig) om te zien of `X-Forwarded-Proto` wordt vertrouwd.
   - Inspecteer `APP_URL` in `.env.example` en daadwerkelijke `.env` (indien beschikbaar) om te bepalen of HTTPS is ingesteld.
   - Trace Filament export flow (`ExportSessionsPage`, route definition) om te bevestigen hoe redirect occurs.
2. **HTTPS afdwingen in Laravel**
   - Kies best passende remedie: (a) zorg dat `TrustProxies` accepteert proto header + `APP_URL` = https, en/of (b) voeg `URL::forceScheme('https')` conditioneel toe voor productie.
   - Introduceer een expliciete configuratie (`config('app.force_https')` met `.env` sleutel, bv. `APP_FORCE_HTTPS=true`) zodat we het gedrag eenvoudig kunnen schakelen per omgeving.
   - Voeg test/detectie toe om te voorkomen dat lokale dev wordt beïnvloed (bv. force scheme enkel als `config('app.force_https')` of environment flag true is).
3. **Tests & Validatie**
   - Schrijf Pest feature test (`tests/Feature/Export/ExportSessionsHttpsTest.php`) die een HTTPS request naar export route simuleert met `X-Forwarded-Proto=https` en assert dat response een 200-download response blijft zonder downgrade.
   - Test fallback scenario (HTTP request lokaal) blijft werken (zelfde testbestand, aparte testcase) zodat lokale ontwikkelaars geen regressie ervaren.
4. **Documentatie & Operationalisatie**
   - Werk docs/AimTrack/index.md (Recent) + evt. constraints of deployment notes bij met instructies om `APP_URL`, `APP_FORCE_HTTPS` en proxy headers correct te configureren.
   - Voeg in `.env.example` en `.env.local.example` een voorbeeld toe voor `APP_FORCE_HTTPS` met toelichting (prod=true, local=false).
   - Log wijziging in `.ai-logs/GH-49/` indien extra STOP-condities ontstaan.

## 8. Deliverables & Traceability
- Geüpdatete configuratie (TrustProxies/AppServiceProvider) met toggle-bare HTTPS forcing.
- Nieuwe Pest feature tests voor export-downloads onder HTTPS en HTTP.
- Documentatie-updates (`docs/AimTrack/index.md`, `.env.example`) met duidelijke instructies.
- Eventuele ondersteunende logging/notes in `.ai-logs/GH-49/` indien STOP-condities optreden.

## 6. Acceptatiecriteria
1. Een feature test toont dat een HTTPS-aanvraag naar `/exports/sessions/download` niet wordt omgeleid naar HTTP en levert een 200-downloadresponse met correcte headers.
2. Configuratie (TrustProxies en/of force scheme) garandeert dat `route()` en `redirect()` helpers in productie altijd HTTPS-urls genereren zonder lokale ontwikkelomgeving te breken.
3. Documentatie vermeldt de noodzakelijke `APP_URL` en proxy header vereisten; `.env.example` bevat HTTPS-voorbeelden voor productie.
4. Pint en relevante tests (minimaal het nieuwe export HTTPS testbestand) draaien succesvol.

## 7. Open Vragen / Afhankelijkheden
- **Infra:** Site draait via een Raspberry Pi met nginx reverse proxy (geen Cloudflare/CDN). `TrustProxies` moet dus X-Forwarded-* van nginx accepteren.
- **Globale HTTPS:** Reeds geconfigureerd; probleem lijkt specifiek op exportdownload-flow (waarschijnlijk verkeerde `APP_URL` of gemiste proto-header). Focus blijft op het herstellen van die flow zonder bestaande globale redirect te wijzigen.
