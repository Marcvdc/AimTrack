# Contributing

Bedankt voor je interesse in AimTrack! Volg onderstaande richtlijnen om bij te dragen.

## Gedragscode
Door bij te dragen ga je akkoord met de [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md).

## Issues
- Gebruik issues voor bugs of feature requests. Beschrijf duidelijk het probleem, verwachte gedrag en stappen om te reproduceren.
- Voeg relevante logs, screenshots en omgeving (OS, PHP-versie, DB) toe.

## Workflow
1. Fork de repo en maak een feature branch (`feature/onderwerp` of `fix/bug-xyz`).
2. Houd je aan het PLAN-FIRST principe: beschrijf kort de aanpak (bijv. update `docs/PLAN.md` of een issue-comment) voordat je code wijzigt.
3. Schrijf compacte, leesbare code met NL-labels in Filament.
4. Zorg dat alle tests en linters slagen.
5. Maak een heldere PR met samenvatting, testresultaten en referentie naar het issue.

## Coding standards
- Lint met `composer lint` (Laravel Pint). Gebruik `composer lint:fix` voor automatische fixes.
- Tests draaien met `composer test` (Pest/phpunit).
- Geen secrets committen; gebruik `.env`/secrets store.
- AI-calls altijd via de queue (geen directe HTTP-call in request lifecycles) behalve expliciet synchrone coachvragen.

## Commits en PR’s
- Gebruik betekenisvolle commit messages (imperatief: "Add...", "Fix...").
- Kleine, gefocuste PR’s zijn makkelijker te reviewen.

## Documentatie
- Update relevante docs bij nieuwe features (technisch en gebruikersdocumentatie).
- Voeg NL-disclaimers toe bij AI/exports waar nodig.
