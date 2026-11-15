# QA Smoke Findings (historisch)

Status: historisch, niet de huidige kwaliteitsstatus. Dit document is opgesteld op 2025-11-23 en is op 2026-11-14 gemarkeerd als historisch. Het is sindsdien niet opnieuw uitgevoerd.

## Wat hier stond

Op 2025-11-23 meldde de eerste smoke-run een blocker bij het installeren van de dependencies tegen Laravel 12: `pestphp/pest-plugin-laravel` en `barryvdh/laravel-dompdf` ondersteunden toen alleen Laravel ^10/^11, en er was nog geen `composer.lock`. Daardoor zijn de smoke-scenario's uit `docs/QA_PLAN.md` toen niet uitgevoerd.

## Wat daarvan nog geldt

Niets. Alle drie de punten zijn opgelost, te controleren in de repo zelf:

- `composer.lock` staat in de repo.
- `composer.lock` bevat `laravel/framework` v12, `pestphp/pest-plugin-laravel` v4 en `barryvdh/laravel-dompdf` v3, dus de install slaagt tegen Laravel 12.
- De CI-workflow (`.github/workflows/ci.yml`) draait lint, audit, test en een build van de productie-image zonder push.

## Wat er niet is gedaan

De handmatige smoke-test uit `docs/QA_PLAN.md` (gebruiker, wapen, sessie, AI, export, bijlagen, queue) is niet opnieuw doorlopen. Er is dus geen vastgelegde uitkomst van een handmatige run na 2025-11-23. Wil je er een, voer het plan dan uit en leg het resultaat met datum vast in een nieuw document of in dit bestand.
