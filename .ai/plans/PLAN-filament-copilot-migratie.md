---
status: APPROVED
created: 2026-04-25
approved: 2026-04-25
author: Claude
jira: n/a
---

# PLAN: Migratie naar Filament Copilot Plugin

## Status: APPROVED

## Samenvatting

Migreer de bestaande AI-coach chat functionaliteit (`AiCoachPage`, `CoachQuestion`, `CoachSession`) naar de `eslam-reda-div/filament-copilot` plugin. Behoud de bestaande async reflectie- en inzichtengeneratie (`AiReflection`, `AiWeaponInsight`) via de eigen `ShooterCoach` service.

## Motivatie

De huidige `AiCoachPage` is een zelfgebouwde chat-UI zonder streaming, met handmatige history-opslag en beperkte UX. De Copilot plugin biedt:
- SSE streaming responses (token-voor-token)
- Ingebouwde conversation persistence en history sidebar
- Agent memory systeem (feiten onthouden over sessies heen)
- Configureerbare rate limiting en token budgets
- Uitbreidbaar via custom tools en `CopilotPage` interface

## Acceptatiecriteria

1. Gebruiker kan AI-coachvragen stellen via Copilot modal in het Filament panel
2. Schuttercontext (recente sessies, wapen, datumrange) wordt automatisch geïnjecteerd via `CopilotPage`
3. Conversation history is beschikbaar en laadbaar via Copilot sidebar
4. Rate limiting is geconfigureerd (minimaal gelijkwaardig aan huidige 3/uur, 10/dag)
5. Gebruiker kan reflectie voor een sessie triggeren via chat ("genereer reflectie")
6. `AiReflection` en `AiWeaponInsight` generatie blijft ongewijzigd functioneren
7. Feature flag `aimtrack-ai` blijft werken via Copilot `authorizeUsing` closure
8. Alle bestaande tests blijven groen; nieuwe tests dekken Copilot integratie

## Buiten scope

- Migratie van bestaande `CoachQuestion` data naar Copilot conversation history
- Vervanging van `ShooterCoach` service voor reflectie/insight generatie
- Verandering van AI provider (blijft OpenAI)
- Management dashboard van Copilot plugin activeren

## Afhankelijkheden & risico's

| Risico | Impact | Mitigatie |
|---|---|---|
| `laravel/ai` is v0 (breaking changes mogelijk) | Hoog | Pin op exacte versie, monitor releases |
| Temperature (0.3) en MaxTokens (4096) hardcoded in plugin | Medium | Accepteren of fork voor eigen configuratie |
| Community plugin, geen first-party onderhoud | Medium | 81 tests aanwezig, actieve repo |

## Architectuur

### Wat verdwijnt
- `app/Filament/Pages/AiCoachPage.php`
- `resources/views/filament/pages/ai-coach-page.blade.php`
- `resources/views/filament/modals/ai-coach-question.blade.php`
- `app/Models/CoachQuestion.php`
- `app/Models/CoachSession.php`
- Migratie `create_coach_questions_table` (data archiveren voor verwijdering)

### Wat blijft
- `app/Services/Ai/ShooterCoach.php` (alleen reflectie/insight methoden)
- `app/Models/AiReflection.php`
- `app/Models/AiWeaponInsight.php`
- `app/Jobs/GenerateSessionReflectionJob.php`
- `app/Notifications/AiCoachFailureNotification.php`
- `config/ai.php`

### Wat nieuw wordt gebouwd
- `app/Filament/Copilot/Tools/ShooterContextTool.php` — injecteert sessie/wapen context
- `app/Filament/Copilot/Tools/GenerateReflectionTool.php` — triggert `GenerateSessionReflectionJob`
- Implementatie van `CopilotPage` op de AI-coach pagina (of verwijderd indien overbodig)

## Fasering

### Fase 1 — Installatie & basis chat (MEDIUM)
- [ ] Voeg `eslam-reda-div/filament-copilot` en `laravel/ai` toe via Composer
- [ ] Publiceer config, assets en migraties van de plugin
- [ ] Configureer provider (OpenAI), model en systeem prompt in `FilamentCopilotPlugin`
- [ ] Koppel feature flag via `authorizeUsing(fn() => AimtrackFeatureToggle::aiEnabled())`
- [ ] Stel rate limiting in (3/uur, 10/dag)
- [ ] Verifieer basiswerking van chat modal in panel

### Fase 2 — Schuttercontext & tools (MEDIUM)
- [ ] Implementeer `ShooterContextTool` met wapen + sessie-data als context
- [ ] Implementeer `GenerateReflectionTool` als wrapper rond `GenerateSessionReflectionJob`
- [ ] Voeg `CopilotResource` interface toe aan `SessionResource` (optioneel)
- [ ] Schrijf systeemprompt specifiek voor schutterscoaching (gebaseerd op huidige `ShooterCoach` prompt)

### Fase 3 — Opruimen (SIMPLE)
- [ ] Verwijder `AiCoachPage` en bijhorende views
- [ ] Verwijder `CoachQuestion` en `CoachSession` models (na datamigratie/-archivering)
- [ ] Vereenvoudig `ShooterCoach` service (verwijder `answerCoachQuestion`)
- [ ] Update navigatie en feature flag checks

### Fase 4 — Tests & documentatie (verplicht per fase)
- [ ] Feature tests voor Copilot tool integratie
- [ ] Verifieer bestaande reflectie/insight tests nog groen
- [ ] Update `docs/AimTrack/` met nieuwe architectuur

## Goedkeuring

- [ ] Plan reviewed door gebruiker
- [ ] Status gewijzigd naar APPROVED voor start BUILD MODE
