# Quickstart

Korte gids om AimTrack lokaal te starten en de eerste sessie te registreren.

## Vereisten
- Docker + Docker Compose
- (Optioneel) AI-provider key in `.env` (`OPENAI_API_KEY` of ander driver-specific secret)

## Installatie (lokaal)
1. Clone repo en kopieer `.env.example` naar `.env`. Vul `APP_KEY` later met `php artisan key:generate`.
   Deze stack zet `SESSION_SECURE_COOKIE` standaard aan, zodat de sessiecookie de `Secure`-vlag krijgt. Op `http://localhost` is dat geen probleem: de browser bewaart een Secure-cookie daar gewoon. Open je de app over plain HTTP via een andere host, zoals een LAN-IP, zet dan in die `.env` `SESSION_SECURE_COOKIE=false` en draai `docker compose up -d` om de nieuwe waarde op te pakken.
2. Zet in de `.env` de regel `STACK_FORCE_HTTPS=false`. De stack forceert https standaard, omdat productie hem draait; zonder die regel stuurt Livewire het inlogformulier naar een `https://`-adres en strandt inloggen over `http://` op een SSL-fout. `APP_FORCE_HTTPS=false` uit `.env.example` helpt hier niet, de stack zet die variabele zelf.
3. Start containers: `docker compose up -d`.
4. Installeer dependencies: `docker compose exec app composer install`.
5. Genereer app key: `docker compose exec app php artisan key:generate`.
6. Run migraties (en seeders indien aanwezig): `docker compose exec app php artisan migrate --seed`.
7. Open de app op `http://localhost:18080` en login met het aangemaakte account (of registreer via standaard Laravel-auth).

## Eerste sessie loggen
1. Ga in Filament naar **Sessies** → **Nieuwe sessie**.
2. Vul datum, baan/vereniging, locatie en optioneel notities/handmatige reflectie in.
3. Voeg één of meer **Wapen × afstand** entries toe (wapen, afstand, aantal schoten, munitietype, score/groepering).
4. Upload eventuele bijlagen (foto/kaart/PDF).
5. Opslaan. De sessie verschijnt in het overzicht.

## AI-reflectie starten
- Open de sessiedetail en klik **Genereer AI-reflectie**. De queue-job wordt gestart; status is zichtbaar in de tabel/detail.

## Export maken
- Ga naar **Export** in het menu, kies periode en (optioneel) wapens, selecteer CSV of PDF en klik **Download**. Lees de disclaimer in de download.

## Problemen oplossen
- Controleer `docker compose logs app` bij errors.
- Queue niet actief? Start `docker compose exec queue php artisan queue:work`.
- Geen AI-output? Check `.env` voor AI-key/base URL en bekijk `ai_reflections` statusvelden.
