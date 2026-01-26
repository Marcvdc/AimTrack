# UI Verbetering User – Landing Link Plan
Status: APPROVED (2026-01-26)
Auteur: Cascade AI
Datum: 2026-01-26
Initiatief: "UI-VERBETERING-USER" — Landingpage-knoppen login & profiel

## 1. Context & Aanleiding
- De marketing landingpage (http://localhost:8080/) is momenteel alleen bereikbaar buiten de applicatie.
- Gebruikers willen tijdens login of nadat ze ingelogd zijn kunnen terugkeren naar de landingpage om productinfo te herbekijken zonder URL handmatig in te typen.
- Er ontbreken consistente CTA's binnen het login-scherm (Filament modal) en het profielmenu waardoor navigatie terug naar de landing ontbreekt.

## 2. Doelen
1. Voeg een duidelijke CTA toe op het login-scherm (onderaan de modal) om naar de landingpage te gaan.
2. Voeg een equivalent item toe in het profielmenu voor ingelogde gebruikers zodat zij ook kunnen terugkeren.
3. Zorg voor tests en documentatie waardoor regressies worden voorkomen en het gedrag beschreven is.

## 3. Scope & Grenzen
**In scope**
- Aanpassingen aan login view/modal template (verwacht: `resources/views/auth/login.blade.php` of Filament layout) om CTA met document-icoon te tonen.
- Aanpassingen aan profielmenu component (bijv. `resources/views/partials/profile-menu.blade.php` of Livewire menu) om extra menu-item op te nemen.
- Configuratie van de landing-link (bij voorkeur named route of config entry die naar http://localhost:8080/ verwijst).
- Tests (Pest) die aanwezigheid van CTA's valideren, inclusief accessibility-attributes.
- Documentatie-update in `docs/AimTrack/index.md` (Recent) en evt. aanvullende UI release notes.

**Out of scope**
- Wijzigingen aan de landingpage zelf.
- Extra tracking/telemetry, analytics of feature flags.
- Stylingwijzigingen buiten de twee nieuwe CTA-elementen.

## 4. Constraints & Assumpties
- Bestaande constraints (C-PII-Logging, C-API-Timeouts) blijven gelden; CTA mag geen PII loggen.
- Login CTA zichtbaar voor alle gebruikers (ook niet-ingelogden) omdat het in de modal staat.
- Profielmenu CTA zichtbaar voor alle ingelogde gebruikers/rollen.
- Iconografie "iets met een documentje" → gebruik bestaand iconset (bijv. Heroicon document) om consistent te blijven.
- Link moet open webbrowser redirecten zonder extra bevestiging (gewone anchor/Filament button).
- Geen extra backend logica vereist; enkel redirect naar landing URL.

## 5. Aanpak (Stappen)
1. **Analyse & Routing**
   - Verifieer bestaande routes/config voor landingpage; indien afwezig, maak named route `landing.home` die naar http://localhost:8080/ verwijst (eventueel via config/env).
2. **Login CTA implementatie**
   - Lokaliseer login modal view/component en voeg onderaan een secundaire CTA met document-icoon, tekst en aria-label toe.
   - Zorg dat styling consistent is met bestaande knopvarianten.
3. **Profielmenu item**
   - Voeg een nieuw menu-item (met icon, tekst) toe dat naar dezelfde route verwijst.
   - Respecteer menu-sortering en separators.
4. **Validatie & Documentatie**
   - Schrijf/actualiseer Pest tests die aanwezigheid van de CTA's controleren.
   - Update relevante docs (`docs/AimTrack/index.md` Recent) en vermeld wijziging in release notes indien nodig.

## 6. Acceptatiecriteria
1. **Login CTA:** Het login-scherm toont onderaan de modal een CTA (met document-icoon) die naar de landingpage (http://localhost:8080/ of named route) linkt; test de aanwezigheid/URL via een feature test.
2. **Profielmenu item:** Het profielmenu bevat een nieuw item met document-icoon dat dezelfde landing-link gebruikt; test bevestigt dat het item voor alle ingelogde gebruikers zichtbaar is.
3. **Documentatie & Tests:** Relevante documentatie is bijgewerkt, en Pest-tests voor login/profielmenu-passages slagen (`php artisan test --compact` targetted). Geen lint- of coverage-regressies (<90%) op de gewijzigde onderdelen.

## 7. Afhankelijkheden & Open Vragen
- **Landing route:** gebruiker bevestigde dat de bestaande landing al via een route/controller bereikbaar is; hergebruik die route.
- **Componentlocatie:** loginmodal- en profielmenucomponenten moeten nog door code-inspectie worden geïdentificeerd tijdens de implementatievoorbereiding.
