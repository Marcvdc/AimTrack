# AimTrack Project Context

## Domain
AimTrack is een shooting training en coaching applicatie voor schutters en coaches.
Gebruikers kunnen trainingssessies bijhouden met verschillende wapens en ontvangen AI-gebaseerde coaching feedback.

## Key Models
- **Session**: Trainingssessies met schoten en statistieken
- **Weapon**: Verschillende wapentypes (pistool, geweer, etc.)
- **User**: Schutters en coaches
- **SessionShot**: Individuele schoten binnen een sessie
- **AiReflection**: AI coaching feedback en analyses

## Important Conventions
- Gebruik Nederlands voor user-facing tekst
- Volg bestaande Filament resource patterns
- AI features gebruiken feature flags via Laravel Pennant
- Logging voor feature flags blijft in applicatie logs (geen Sentry)
- Optionele mail notificaties zijn toegestaan

## Architecture Notes
- Type A (Eloquent) architectuur - voornamelijk database-gebaseerd
- Filament voor admin interface
- Livewire voor interactive components
- Laravel Pennant voor feature management
- Pest voor testing

## Business Rules
- Elke sessie moet minimaal één schot bevatten
- AI coaching is optioneel en via feature flag
- Weapons hebben specifieke validatie per type
- Users kunnen zowel schutter als coach zijn

## File Structure Patterns
- Models: `app/Models/`
- Filament Resources: `app/Filament/Resources/`
- Services: `app/Services/`
- Tests: `tests/Feature/` en `tests/Unit/`
- Documentation: `docs/AimTrack/`
