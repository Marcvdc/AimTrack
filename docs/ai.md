# AI

Dit document beschrijft hoe de AI-functionaliteit is ingericht en hoe je deze veilig en betrouwbaar gebruikt.

## Componenten
- **Service:** `App\Services\Ai\ShooterCoach` – centraal entrypoint voor alle LLM-calls.
- **Config:** `config/ai.php` leest provider/model/base_url/timeout/retries uit `.env` (bijv. `AI_DRIVER`, `AI_MODEL`, `OPENAI_BASE_URL`, `OPENAI_API_KEY`). Geen secrets in code of repo.
- **Queue-jobs:**
  - `GenerateSessionReflectionJob` – bouwt prompt met sessiecontext (datum, locatie, wapens, notities) en vraagt om samenvatting/went_well/needs_improvement/next_focus.
  - `GenerateWeaponInsightJob` – bouwt prompt met historische sessies per wapen en vraagt om trends/typical_issues/recommendations.
- **AI-coach:** `ShooterCoach::answerCoachQuestion` ondersteunt vrije vragen (optioneel wapen/periode) en bewaart vraag/antwoord in `coach_questions` wanneer geactiveerd.

## Flow per use-case
1. **Sessie-reflectie**: Filament-actie → queue-job → service-call → resultaat in `ai_reflections` → statuskolom in tabel/detail.
2. **Wapen-trends**: Filament-actie → queue-job → service-call → resultaat in `ai_weapon_insights` → zichtbaar bij het wapen.
3. **AI-coach**: Filament page → directe service-call (of queue bij zware context) → antwoord + waarschuwing → optioneel opslag in `coach_questions` voor audit/historie.

## Prompts & guardrails
- Prompts zijn Nederlandstalig en leggen uit dat de AI **geen vervanging** is voor veiligheidsregels, wetgeving of instructeurs.
- Output is kort en puntsgewijs (~150 woorden per sectie), met focus op concrete acties.
- Fouten/timeouts worden gelogd; jobs schrijven status `failed` + `error` veld bij mislukking.
- Provider/model is configureerbaar zodat self-hosted of cloud LLM’s kunnen worden gebruikt.

## Limits & performance
- Queue zorgt dat UI niet blokkeert. Job-retries staan aan via queue-config.
- Timeouts/max tokens via `config/ai.php`; in `.env` kunnen strengere waarden worden gezet voor self-hosting.
- Bij grote datasets kan coach-vraag worden gequeue’d; structureer context (laatste N sessies) om tokens te beperken.

## Privacy en dataretentie
- AI krijgt alleen user-eigen data; queries filteren op `user_id`.
- Geen data wordt gedeeld met derde partijen buiten de gekozen LLM-provider; kies self-hosted/provider met DPA indien nodig.
- Bewaar AI-output in de database voor audit; verwijder op verzoek via standaard CRUD.
