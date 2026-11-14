# AI

Laatst bijgewerkt: 2026-11-14

Dit document beschrijft hoe de AI-functionaliteit is ingericht en hoe je deze veilig en betrouwbaar gebruikt.

## Componenten
- **Service:** `App\Services\Ai\ShooterCoach` – centraal entrypoint voor alle LLM-calls.
- **Provider en key:** Anthropic (Claude). De key is per gebruiker (**AI-instellingen**, versleuteld opgeslagen); `App\Services\Ai\AiKeyResolver` kiest eerst de key van de gebruiker en dan de gedeelde key van de actieve vereniging. Zonder key staat AI uit (`AiKeyMissingException`). `ANTHROPIC_API_KEY` in `.env.example` wordt door de code niet gelezen.
- **Config:** `config/ai.php` leest `AI_MODEL`, `ANTHROPIC_BASE_URL`, `AI_MAX_TOKENS`, `ANTHROPIC_VERSION` en de alert-instellingen uit `.env`. `OPENAI_API_KEY` en `AI_DRIVER=openai` zijn vervallen. Geen secrets in code of repo.
- **Feature flag:** `aimtrack-ai` (Pennant, standaard via `FEATURE_AIMTRACK_AI`, alleen in `local` aan) plus een beschikbare key bepalen of de AI-functies zichtbaar zijn (`AimtrackFeatureToggle::aiEnabled`).
- **Queue-jobs:**
  - `GenerateSessionReflectionJob` – bouwt prompt met sessiecontext (datum, locatie, wapens, notities) en vraagt om samenvatting/went_well/needs_improvement/next_focus.
  - `GenerateWeaponInsightJob` – bouwt prompt met historische sessies per wapen en vraagt om trends/typical_issues/recommendations.
- **AI-coach:** de pagina `App\Filament\Pages\CoachPage` draait op `eslam-reda-div/filament-copilot`. Gesprekken worden door het Copilot-pakket bewaard (tabellen `copilot_conversations` en `copilot_messages`). De coach heeft tools om sessies, wapens, scoredrift en schutterscontext op te zoeken en om een trainingsdoel vast te leggen (`app/Filament/Copilot/Tools/`). De tabel `coach_questions` is verwijderd (`database/migrations/2026_04_26_120000_drop_coach_questions_table.php`). De middleware `InjectUserAnthropicKey` zet bij `copilot/stream` de key van de gebruiker neer.

## Flow per use-case
1. **Sessie-reflectie**: Filament-actie → queue-job → service-call → resultaat in `ai_reflections` → statuskolom in tabel/detail.
2. **Wapen-trends**: Filament-actie → queue-job → service-call → resultaat in `ai_weapon_insights` → zichtbaar bij het wapen.
3. **AI-coach**: Filament page met Copilot-chat → tools lezen alleen data van de ingelogde gebruiker → antwoord, het gesprek blijft in `copilot_conversations` staan.

## Prompts & guardrails
- Prompts zijn Nederlandstalig en leggen uit dat de AI **geen vervanging** is voor veiligheidsregels, wetgeving of instructeurs.
- Output is kort en puntsgewijs (~150 woorden per sectie), met focus op concrete acties.
- Fouten/timeouts worden gelogd. `ShooterCoach` gooit dan `AiUnavailableException` (geen key: `AiKeyMissingException`) en schrijft niets weg, zodat een bestaande reflectie of een bestaand wapeninzicht blijft staan.
- Het model is instelbaar met `AI_MODEL` en de basis-URL met `ANTHROPIC_BASE_URL`; de aanroep volgt de Anthropic Messages API, dus een andere provider vraagt een codewijziging.

## Limits & performance
- Queue zorgt dat UI niet blokkeert.
- De HTTP-call probeert een netwerkfout, 429 of 5xx nog twee keer (na 1 s en 3 s); een 401/403 of andere 4xx niet.
- De jobs hebben `$tries = 3`, `$backoff = [60, 300]` en `$timeout = 80` (langste poging is 3 x 20 s HTTP-timeout plus 1 s en 3 s backoff = 64 s; 80 blijft onder `retry_after` van 90 s). Een tijdelijke fout gaat terug de queue in; geen key of een 401/403 faalt de job direct. Een mislukte job komt in `failed_jobs` en loopt via de `Queue::failing`-hook in `AppServiceProvider` (log en Sentry).
- Rijen waarin vóór #186 een foutstring als samenvatting is opgeslagen, toont de UI als nette melding ("kon niet gemaakt worden").
- Max tokens via `AI_MAX_TOKENS`; de HTTP-timeout van 20 s staat in `ShooterCoach`.
- Houd de context klein (laatste N sessies) om tokens te beperken.

## Privacy en dataretentie
- AI krijgt alleen user-eigen data; queries filteren op `user_id`.
- Geen data wordt gedeeld met derde partijen buiten Anthropic, de provider van de AI-aanroepen.
- Bewaar AI-output in de database voor audit; verwijder op verzoek via standaard CRUD.
