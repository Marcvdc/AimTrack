---
status: APPROVED
created: 2026-06-06
updated: 2026-06-06
approved: 2026-06-06 (Marcvdc — ontwerp; implementatieplan ter review)
author: Claude (Opus 4.8, 1M context)
jira: GH#95
worktree: aimtrack-ai-byo-key (feature/ai-byo-key)
basis: 128186e (main)
---

# Fase 1 — BYO Claude API-key per user — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Elke gebruiker vult een eigen Anthropic (Claude) API-key in; *alle* AI in de app (Copilot-chat + sessie-reflecties + wapen-insights) loopt via die key. Geen key = AI uit voor die gebruiker. Inclusief migratie van `ShooterCoach` van OpenAI naar de Anthropic Messages API.

**Architecture:** Eén centrale `AiKeyResolver` bepaalt de actieve key voor een gebruiker (Fase 1: `user → null`). Copilot draait al via `laravel/ai`; we injecteren de user-key per request met een middleware die `config(['ai.providers.anthropic.key' => $key])` zet vóór `laravel/ai` de provider resolved. `ShooterCoach` (queued jobs) resolvet de key op `handle()`-tijd uit de eigenaar van de Session/Weapon en stuurt zelf een raw `Http`-call naar `/v1/messages`. De gate `AimtrackFeatureToggle::aiEnabled()` wordt key-aware zodat UI-knoppen/pagina's verdwijnen zonder key.

**Tech Stack:** Laravel 12 (Laravel-10 mappenstructuur), Filament v5, Livewire v4, Laravel Pennant, `laravel/ai` (Anthropic driver, al aanwezig), `eslam-reda-div/filament-copilot`, Pest v4. Raw `Illuminate\Support\Facades\Http` voor de ShooterCoach-call (geen SDK). Model: `claude-haiku-4-5-20251001`.

---

## Belangrijke feiten uit codebase-onderzoek (lees vóór je begint)

- **Copilot key-bron:** `eslam-reda-div/filament-copilot` delegeert de AI-call naar `laravel/ai`. De key = `config('ai.providers.anthropic.key')` (default `env('ANTHROPIC_API_KEY')`), *lazy* gelezen in `vendor/laravel/ai/src/AiManager.php::getInstanceConfig()` bij de eerste provider-resolve in de request (`StreamController` regels 123-133, route `POST /copilot/stream`). `provider()`/`model()` op de plugin zijn statische strings; de plugin kent géén key-concept. Een per-request `config([...])`-override vóór die resolve werkt; veiligheidsnet: `app(\Laravel\Ai\AiManager::class)->forgetInstance('anthropic')`.
- **`AiManager` is `->scoped()`** (vers per request, ook onder Octane) → een per-request override lekt niet tussen users mits gezet in request-scoped middleware (niet in een boot-only provider).
- **ShooterCoach** (`app/Services/Ai/ShooterCoach.php`) is het ENIGE bestand dat met OpenAI praat (raw `Http`). De twee jobs (`GenerateSessionReflectionJob`, `GenerateWeaponInsightJob`) serialiseren alleen het model-id en roepen `ShooterCoach::make()->generate...()` aan → **geen job-wijziging nodig** als de key op `handle()`-tijd uit `$session->user` / `$weapon->user` wordt geresolved.
- **User-model** gebruikt de `$casts`-**property** (NIET de `casts()`-method). Encrypted kolom moet `text()` zijn (niet `string()`), payload is lang. `'encrypted'` bestaat nog nergens in de codebase. User heeft al `is_admin` (laatst toegevoegde kolom).
- **Tests:** `pest-plugin-livewire` is **NIET** geïnstalleerd → gebruik `Livewire\Livewire::actingAs($u)->test(Page::class)` (facade), niet `livewire()`. `TestCase` heeft `LazilyRefreshDatabase` + `withoutVite()` (geen handmatige `RefreshDatabase` nodig). Feature-toggle-test staat op `tests/Unit/AimtrackFeatureToggleTest.php` (géén `/Aimtrack/`-submap).
- **Bestaande AI-tests die de flag activeren maar geen key zetten** breken zodra `aiEnabled()` ook een key eist: `tests/Feature/Filament/Pages/CoachPageTest.php` en `tests/Feature/EmptyStates/AiCoachThresholdTest.php`. Die moeten de actende user een key geven. (`tests/Feature/RangeConsole/AiCoachScreenTest.php` mockt `aiEnabled()` direct → veilig.)
- **`claude-haiku-4-5-20251001`**: Haiku ondersteunt géén `effort`/adaptive-thinking params (400). Geen `temperature`/`top_p`/`budget_tokens` toevoegen. JSON afdwingen via prompt-instructie ("alleen JSON, geen tekst eromheen") + bestaande `json_decode`-of-fallback.

---

## File Structure

| Bestand | Verantwoordelijkheid | Actie |
|---|---|---|
| `database/migrations/<ts>_add_anthropic_api_key_to_users_table.php` | `users.anthropic_api_key` (text, nullable, encrypted) + `ai_key_verified_at` (timestamp, nullable) | Create |
| `app/Models/User.php` | `$casts` (encrypted + datetime), `$hidden` (key) | Modify |
| `database/factories/UserFactory.php` | `withAnthropicKey()` state | Modify |
| `app/Services/Ai/AiKeyResolver.php` | Centrale key-resolutie (`forUser`, `forCurrentUser`) | Create |
| `app/Support/Features/AimtrackFeatureToggle.php` | `aiEnabled()` = flag **én** key resolved | Modify |
| `config/ai.php` | Anthropic-defaults (model/base_url/version/max_tokens) | Modify |
| `app/Services/Ai/ShooterCoach.php` | Anthropic Messages API + per-user key | Modify |
| `app/Http/Middleware/InjectUserAnthropicKey.php` | Per-request user-key in `laravel/ai` config voor Copilot | Create |
| `app/Http/Kernel.php` | Middleware registreren in `web`-groep | Modify |
| `app/Filament/Pages/AiSettingsPage.php` | Per-user key invoeren/testen/wissen | Create |
| `resources/views/filament/pages/ai-settings-page.blade.php` | View van de pagina | Create |
| Tests (zie taken) | Unit + feature dekking | Create/Modify |

---

## Task 1: Migratie + User-cast/hidden + factory-state

**Files:**
- Create: `database/migrations/<ts>_add_anthropic_api_key_to_users_table.php`
- Modify: `app/Models/User.php` (`$casts` regels 26-31, `$hidden`)
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Auth/UserAnthropicKeyTest.php`

- [ ] **Step 1: Failing test voor encrypted opslag + hidden**

Maak `tests/Feature/Auth/UserAnthropicKeyTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('slaat de anthropic key encrypted op en geeft hem decrypted terug', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-secret-123']);

    expect($user->fresh()->anthropic_api_key)->toBe('sk-ant-secret-123');

    $rawColumn = DB::table('users')->where('id', $user->id)->value('anthropic_api_key');
    expect($rawColumn)->not->toBe('sk-ant-secret-123')
        ->and($rawColumn)->not->toBeNull();
});

it('verbergt de anthropic key in serialisatie', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-secret-123']);

    expect($user->toArray())->not->toHaveKey('anthropic_api_key');
});

it('casts ai_key_verified_at naar een datetime', function (): void {
    $user = User::factory()->create(['ai_key_verified_at' => now()]);

    expect($user->fresh()->ai_key_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
```

- [ ] **Step 2: Run → faalt (kolom/cast bestaat nog niet)**

Run: `php artisan test --compact tests/Feature/Auth/UserAnthropicKeyTest.php`
Expected: FAIL (kolom `anthropic_api_key` ontbreekt / mass-assignment geblokkeerd).

- [ ] **Step 3: Migratie genereren en invullen**

Run: `php artisan make:migration add_anthropic_api_key_to_users_table --no-interaction`

Vul het gegenereerde bestand:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('anthropic_api_key')->nullable()->after('is_admin');
            $table->timestamp('ai_key_verified_at')->nullable()->after('anthropic_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['ai_key_verified_at', 'anthropic_api_key']);
        });
    }
};
```

- [ ] **Step 4: User-model uitbreiden**

In `app/Models/User.php`, breid de bestaande `$casts`-**property** uit (NIET overstappen op de `casts()`-method):

```php
protected $casts = [
    'email_verified_at' => 'datetime',
    'demo_data_seeded_at' => 'datetime',
    'password' => 'hashed',
    'is_admin' => 'boolean',
    'anthropic_api_key' => 'encrypted',
    'ai_key_verified_at' => 'datetime',
];
```

Voeg `'anthropic_api_key'` toe aan `$fillable` (nodig voor de factory/settings-save) én aan `$hidden`:

```php
// in $hidden:
'anthropic_api_key',
```

- [ ] **Step 5: Factory-state toevoegen**

In `database/factories/UserFactory.php` (volg het state-patroon uit `TrainingGoalFactory`):

```php
public function withAnthropicKey(string $key = 'sk-ant-test-key'): static
{
    return $this->state(fn (): array => [
        'anthropic_api_key' => $key,
        'ai_key_verified_at' => now(),
    ]);
}
```

- [ ] **Step 6: Run → groen**

Run: `php artisan test --compact tests/Feature/Auth/UserAnthropicKeyTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations app/Models/User.php database/factories/UserFactory.php tests/Feature/Auth/UserAnthropicKeyTest.php
git commit -m "feat(ai): users.anthropic_api_key (encrypted) + hidden + factory-state (#95)"
```

---

## Task 2: AiKeyResolver

**Files:**
- Create: `app/Services/Ai/AiKeyResolver.php`
- Test: `tests/Unit/Services/AiKeyResolverTest.php`

- [ ] **Step 1: Failing test**

Maak `tests/Unit/Services/AiKeyResolverTest.php`:

```php
<?php

use App\Models\User;
use App\Services\Ai\AiKeyResolver;

it('geeft de key van een user terug', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-abc']);

    expect(app(AiKeyResolver::class)->forUser($user))->toBe('sk-ant-abc');
});

it('geeft null als de user geen key heeft', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => null]);

    expect(app(AiKeyResolver::class)->forUser($user))->toBeNull();
});

it('geeft null voor een null-user', function (): void {
    expect(app(AiKeyResolver::class)->forUser(null))->toBeNull();
});

it('resolvet de ingelogde user via forCurrentUser', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-xyz']);
    $this->actingAs($user);

    expect(app(AiKeyResolver::class)->forCurrentUser())->toBe('sk-ant-xyz');
});

it('geeft null bij forCurrentUser zonder ingelogde user', function (): void {
    expect(app(AiKeyResolver::class)->forCurrentUser())->toBeNull();
});
```

- [ ] **Step 2: Run → faalt (class bestaat niet)**

Run: `php artisan test --compact tests/Unit/Services/AiKeyResolverTest.php`
Expected: FAIL ("Class App\Services\Ai\AiKeyResolver not found").

- [ ] **Step 3: Implementeer de resolver**

Maak `app/Services/Ai/AiKeyResolver.php`:

```php
<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class AiKeyResolver
{
    /**
     * De actieve Claude-key voor een gebruiker.
     * Fase 1: alleen de eigen key. Fase 2 (#95) breidt dit uit met een
     * verenigings-key fallback: user-key → vereniging-key → null.
     */
    public function forUser(?User $user): ?string
    {
        $key = $user?->anthropic_api_key;

        return filled($key) ? $key : null;
    }

    public function forCurrentUser(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $this->forUser($user) : null;
    }
}
```

> `Authenticatable` is geïmporteerd voor het geval een toekomstige guard een ander type teruggeeft; de `instanceof User`-check houdt het type-safe.

- [ ] **Step 4: Run → groen**

Run: `php artisan test --compact tests/Unit/Services/AiKeyResolverTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Ai/AiKeyResolver.php tests/Unit/Services/AiKeyResolverTest.php
git commit -m "feat(ai): AiKeyResolver — centrale per-user key-resolutie (#95)"
```

---

## Task 3: Key-aware feature gate

**Files:**
- Modify: `app/Support/Features/AimtrackFeatureToggle.php` (`aiEnabled()` regels 15-17)
- Modify: `tests/Unit/AimtrackFeatureToggleTest.php`
- Modify: `tests/Feature/Filament/Pages/CoachPageTest.php`, `tests/Feature/EmptyStates/AiCoachThresholdTest.php` (key meegeven)

- [ ] **Step 1: Failing tests in AimtrackFeatureToggleTest**

Vervang/uitbreiden in `tests/Unit/AimtrackFeatureToggleTest.php`. Bestaande cases die `aiEnabled()` `true` verwachten moeten nu een ingelogde user-mét-key hebben. Voeg toe:

```php
use App\Models\User;
use App\Support\Features\AimtrackFeatureToggle;
use Laravel\Pennant\Feature;

it('is aiEnabled wanneer de flag actief is én de user een key heeft', function (): void {
    Feature::activate('aimtrack-ai');
    $this->actingAs(User::factory()->create(['anthropic_api_key' => 'sk-ant-x']));

    expect(app(AimtrackFeatureToggle::class)->aiEnabled())->toBeTrue();
});

it('is niet aiEnabled wanneer de user geen key heeft', function (): void {
    Feature::activate('aimtrack-ai');
    $this->actingAs(User::factory()->create(['anthropic_api_key' => null]));

    $toggle = app(AimtrackFeatureToggle::class);
    expect($toggle->aiEnabled())->toBeFalse()
        ->and($toggle->aiDisabled())->toBeTrue();
});

it('is niet aiEnabled wanneer de flag uit staat ook al heeft de user een key', function (): void {
    Feature::deactivate('aimtrack-ai');
    $this->actingAs(User::factory()->create(['anthropic_api_key' => 'sk-ant-x']));

    expect(app(AimtrackFeatureToggle::class)->aiEnabled())->toBeFalse();
});
```

> Behoud de bestaande `Schema::hasTable`/`FeatureFacade::active`-mock-test; pas die aan zodat er een ingelogde user-mét-key is voordat `aiEnabled()` `true` mag opleveren (anders faalt-ie door de key-check).

- [ ] **Step 2: Run → faalt**

Run: `php artisan test --compact tests/Unit/AimtrackFeatureToggleTest.php`
Expected: FAIL (key-check bestaat nog niet → "no key"-case faalt, of method ontbreekt).

- [ ] **Step 3: Gate key-aware maken**

In `app/Support/Features/AimtrackFeatureToggle.php`, wijzig `aiEnabled()` en voeg een helper toe. Laat `isFeatureActive()` ongewijzigd (blijft de generieke flag-helper):

```php
use App\Services\Ai\AiKeyResolver;

public function aiEnabled(): bool
{
    return $this->isFeatureActive('aimtrack-ai') && $this->aiKeyResolved();
}

protected function aiKeyResolved(): bool
{
    return filled(app(AiKeyResolver::class)->forCurrentUser());
}
```

- [ ] **Step 4: Run → groen**

Run: `php artisan test --compact tests/Unit/AimtrackFeatureToggleTest.php`
Expected: PASS.

- [ ] **Step 5: Bestaande AI-flag-tests repareren**

In `tests/Feature/Filament/Pages/CoachPageTest.php` en `tests/Feature/EmptyStates/AiCoachThresholdTest.php`: geef de actende user een key zodat `aiEnabled()` nog `true` is. Gebruik de factory-state, bv.:

```php
$user = User::factory()->withAnthropicKey()->create();
$this->actingAs($user);
```

(Pas elke plek aan waar `aimtrack-ai` wordt geactiveerd en daarna AI-UI verwacht wordt.)

- [ ] **Step 6: Run de geraakte tests → groen**

Run: `php artisan test --compact tests/Feature/Filament/Pages/CoachPageTest.php tests/Feature/EmptyStates/AiCoachThresholdTest.php tests/Feature/RangeConsole/AiCoachScreenTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Support/Features/AimtrackFeatureToggle.php tests/
git commit -m "feat(ai): aiEnabled() vereist nu ook een resolvebare key (#95)"
```

---

## Task 4: ShooterCoach → Anthropic Messages API + per-user key

**Files:**
- Modify: `config/ai.php`
- Modify: `app/Services/Ai/ShooterCoach.php` (constructor/`make()` regels 22-39, `callModel()` regels 59-105, de twee publieke generate-methods)
- Modify: `tests/Feature/Ai/SessionReflectionPromptTest.php`
- Test (nieuw): `tests/Feature/Ai/WeaponInsightPromptTest.php`

- [ ] **Step 1: config/ai.php op Anthropic zetten**

In `config/ai.php`, pas de defaults aan (key komt niet meer uit config — per-user via resolver):

```php
'driver' => env('AI_DRIVER', 'anthropic'), // informational; ShooterCoach praat raw met Anthropic
'model' => env('AI_MODEL', 'claude-haiku-4-5-20251001'),
'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
'anthropic_version' => env('ANTHROPIC_VERSION', '2023-06-01'),
// 'api_key' (OPENAI_API_KEY) is deprecated — ShooterCoach gebruikt de per-user key via AiKeyResolver.
```

> Laat `alert_*`-keys ongewijzigd. Laat `api_key` desgewenst staan met een `// @deprecated`-comment; hij wordt niet meer gelezen door ShooterCoach.

- [ ] **Step 2: Failing test — Anthropic wire format + per-user key (sessie-reflectie)**

Herschrijf `tests/Feature/Ai/SessionReflectionPromptTest.php` naar de Anthropic-vorm. Kern (pas bestaande helper/asserts aan; verwijder OpenAI-envelope):

```php
<?php

use App\Models\Session;
use App\Models\User;
use App\Services\Ai\ShooterCoach;
use Illuminate\Support\Facades\Http;

it('genereert een sessie-reflectie via de Anthropic Messages API met de user-key', function (): void {
    config(['ai.model' => 'claude-haiku-4-5-20251001', 'ai.base_url' => 'https://api.anthropic.com']);

    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    $session = Session::factory()->for($user)->create();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Goede groepering.',
                    'positives' => ['Stabiele houding'],
                    'improvements' => ['Adembeheersing'],
                    'next_focus' => 'Trigger control',
                ]),
            ]],
        ], 200),
    ]);

    $reflection = ShooterCoach::make()->generateSessionReflection($session);

    expect($reflection->summary)->toBe('Goede groepering.');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return str_contains($request->url(), '/v1/messages')
            && $request->hasHeader('x-api-key', 'sk-ant-user-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-haiku-4-5-20251001'
            && isset($request['max_tokens'])
            && is_string($request['system'])
            && $request['messages'][0]['role'] === 'user';
    });
});

it('valt terug op een nette tekst zonder user-key', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => null]);
    $session = Session::factory()->for($user)->create();

    Http::fake();

    $reflection = ShooterCoach::make()->generateSessionReflection($session);

    Http::assertNothingSent();
    expect($reflection->summary)->not->toBeEmpty();
});
```

> Controleer de exacte `Session`-factory-relatie (`->for($user)` veronderstelt een `user()`-BelongsTo op `Session`; pas aan naar de bestaande factory-conventie indien nodig). Verwijder de oude OpenAI-`choices.0.message.content`-fake en de `messages[1]`-asserts.

- [ ] **Step 3: Run → faalt**

Run: `php artisan test --compact tests/Feature/Ai/SessionReflectionPromptTest.php`
Expected: FAIL (nog OpenAI-call / `withToken` / verkeerde response-parse).

- [ ] **Step 4: ShooterCoach migreren**

In `app/Services/Ai/ShooterCoach.php`:

**(a) Constructor + `make()` (vervang regels 22-39)** — key uit constructor halen:

```php
public function __construct(
    private readonly string $model = 'claude-haiku-4-5-20251001',
    private readonly string $baseUrl = 'https://api.anthropic.com',
    private readonly int $maxTokens = 1024,
    private readonly string $anthropicVersion = '2023-06-01',
) {}

public static function make(): self
{
    $config = config('ai');

    return new self(
        model: $config['model'] ?? 'claude-haiku-4-5-20251001',
        baseUrl: $config['base_url'] ?? 'https://api.anthropic.com',
        maxTokens: (int) ($config['max_tokens'] ?? 1024),
        anthropicVersion: $config['anthropic_version'] ?? '2023-06-01',
    );
}
```

**(b) `callModel()` (vervang regels 59-105)** — nieuwe signature met `$apiKey`, Anthropic-wire-format:

```php
private function callModel(string $prompt, ?string $apiKey): string
{
    if (blank($apiKey)) {
        Log::error('AI: geen Claude API key voor deze gebruiker.');

        return 'AI-configuratie ontbreekt. Voeg je Claude API-key toe bij AI-instellingen om antwoorden te genereren.';
    }

    $payload = [
        'model' => $this->model,
        'max_tokens' => $this->maxTokens,
        'system' => $this->systemContext(),
        'messages' => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ];

    try {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $this->anthropicVersion,
            ])
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2, 500, throw: false)
            ->post('/v1/messages', $payload);

        if (! $response->successful()) {
            $this->maybeAlert('ai_http_error', ['status' => $response->status()]);

            return 'Er ging iets mis bij het genereren. Probeer het later opnieuw.';
        }

        $content = data_get($response->json(), 'content.0.text');

        return is_string($content) ? $content : (json_encode($content) ?: '');
    } catch (\Throwable $e) {
        $this->maybeAlert('ai_exception', ['message' => $e->getMessage()]);

        return 'Er ging iets mis bij het genereren. Probeer het later opnieuw.';
    }
}
```

> Behoud de bestaande `maybeAlert()`-aanroepen-logica (provider-agnostisch). Verwijder `withToken()`, `response_format`, en de OpenAI-`choices.0.message.content`-parse. **Geen** `temperature`/`thinking`/`budget_tokens` toevoegen (400 op Haiku).
>
> ⚠️ **Aanroepers verifiëren:** ik laat de oude `bool $expectsJson`-param vallen. Run eerst `grep -n 'callModel' app/Services/Ai/ShooterCoach.php` — als ALLEEN `generateSessionReflection` en `generateWeaponInsight` `callModel` aanroepen (zoals verwacht), is de nieuwe signature `($prompt, ?string $apiKey)` veilig. Roept iets anders `callModel($prompt, false)` aan, behoud dan `$expectsJson` als 3e param zodat de positionele args niet verschuiven.

**(c) De twee publieke methods** — resolve de key uit de eigenaar en geef hem mee aan `callModel`:

```php
// generateSessionReflection(Session $session): de callModel-aanroep wordt:
$raw = $this->callModel(
    $this->buildSessionPrompt($session),
    app(AiKeyResolver::class)->forUser($session->user),
);

// generateWeaponInsight(Weapon $weapon): de callModel-aanroep wordt:
$raw = $this->callModel(
    $this->buildWeaponPrompt($weapon),
    app(AiKeyResolver::class)->forUser($weapon->user),
);
```

Voeg bovenin `use App\Services\Ai\AiKeyResolver;` toe. Controleer dat `Session` een `user`-relatie en `Weapon` een `user`-relatie heeft (beide hebben `user_id`).

**(d) Prompts (regels 141-155 en 223-237)** — voeg aan beide prompts één regel toe om losse prose vóór de JSON te voorkomen:

```
Antwoord uitsluitend met het JSON-object, zonder enige tekst eromheen.
```

- [ ] **Step 5: Run → groen (sessie-reflectie)**

Run: `php artisan test --compact tests/Feature/Ai/SessionReflectionPromptTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Wapen-insight test toevoegen**

Maak `tests/Feature/Ai/WeaponInsightPromptTest.php` (spiegel van de sessie-test, op `generateWeaponInsight`):

```php
<?php

use App\Models\User;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use Illuminate\Support\Facades\Http;

it('genereert een wapen-insight via Anthropic met de user-key', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    $weapon = Weapon::factory()->for($user)->create();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Consistente prestaties.',
                    'patterns' => ['Lichte links-afwijking'],
                    'suggestions' => ['Controleer grip'],
                ]),
            ]],
        ], 200),
    ]);

    $insight = ShooterCoach::make()->generateWeaponInsight($weapon);

    expect($insight->summary)->toBe('Consistente prestaties.');
    Http::assertSent(fn (\Illuminate\Http\Client\Request $r): bool =>
        str_contains($r->url(), '/v1/messages') && $r->hasHeader('x-api-key', 'sk-ant-user-key'));
});
```

> Pas de `Weapon`-factory-relatie aan op de bestaande conventie indien `->for($user)` niet matcht.

- [ ] **Step 7: Run → groen**

Run: `php artisan test --compact tests/Feature/Ai/WeaponInsightPromptTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add config/ai.php app/Services/Ai/ShooterCoach.php tests/Feature/Ai/
git commit -m "feat(ai): ShooterCoach naar Anthropic Messages API + per-user key (#95)"
```

---

## Task 5: Copilot per-user key-injectie (middleware)

**Files:**
- Create: `app/Http/Middleware/InjectUserAnthropicKey.php`
- Modify: `app/Http/Kernel.php` (middleware in `web`-groep)
- Test: `tests/Feature/Ai/InjectUserAnthropicKeyTest.php`

- [ ] **Step 1: Failing test (middleware in isolatie)**

Maak `tests/Feature/Ai/InjectUserAnthropicKeyTest.php`:

```php
<?php

use App\Http\Middleware\InjectUserAnthropicKey;
use App\Models\User;
use Illuminate\Http\Request;

it('injecteert de user-key in de laravel/ai config op de copilot-stream-route', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    $this->actingAs($user);

    $request = Request::create('/copilot/stream', 'POST');
    $request->setUserResolver(fn () => $user);

    $captured = null;
    app(InjectUserAnthropicKey::class)->handle($request, function () use (&$captured) {
        $captured = config('ai.providers.anthropic.key');

        return response('ok');
    });

    expect($captured)->toBe('sk-ant-user-key');
});

it('laat de config ongemoeid voor een user zonder key', function (): void {
    config(['ai.providers.anthropic.key' => 'global-fallback']);
    $user = User::factory()->create(['anthropic_api_key' => null]);
    $this->actingAs($user);

    $request = Request::create('/copilot/stream', 'POST');
    $request->setUserResolver(fn () => $user);

    $captured = null;
    app(InjectUserAnthropicKey::class)->handle($request, function () use (&$captured) {
        $captured = config('ai.providers.anthropic.key');

        return response('ok');
    });

    expect($captured)->toBe('global-fallback');
});

it('doet niets op andere routes', function (): void {
    config(['ai.providers.anthropic.key' => 'global-fallback']);
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    $this->actingAs($user);

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $captured = null;
    app(InjectUserAnthropicKey::class)->handle($request, function () use (&$captured) {
        $captured = config('ai.providers.anthropic.key');

        return response('ok');
    });

    expect($captured)->toBe('global-fallback');
});
```

- [ ] **Step 2: Run → faalt (middleware bestaat niet)**

Run: `php artisan test --compact tests/Feature/Ai/InjectUserAnthropicKeyTest.php`
Expected: FAIL ("Class ... InjectUserAnthropicKey not found").

- [ ] **Step 3: Middleware implementeren**

Maak `app/Http/Middleware/InjectUserAnthropicKey.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Ai\AiKeyResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectUserAnthropicKey
{
    public function __construct(private readonly AiKeyResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('copilot/stream')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User) {
            $key = $this->resolver->forUser($user);

            if (filled($key)) {
                config(['ai.providers.anthropic.key' => $key]);
                // Veiligheidsnet: forceer herresolve mocht de provider al opgebouwd zijn.
                app(\Laravel\Ai\AiManager::class)->forgetInstance('anthropic');
            }
        }

        return $next($request);
    }
}
```

> `$request->user()` gebruikt de default web-guard, die de admin-panel-auth deelt. Mocht het panel een aparte guard hebben, vervang door de controller-aanpak (`Filament::setCurrentPanel($request->input('panel_id'))` + `Filament::auth()->user()`); de huidige panel-config gebruikt de default guard, dus `$request->user()` volstaat.

- [ ] **Step 4: Middleware registreren in de web-groep**

In `app/Http/Kernel.php`, voeg de class toe aan de `'web'`-middlewaregroep (de middleware self-guard't op `copilot/stream`, dus elders is het een no-op):

```php
// in protected $middlewareGroups['web'], onderaan toevoegen:
\App\Http\Middleware\InjectUserAnthropicKey::class,
```

- [ ] **Step 5: Run → groen**

Run: `php artisan test --compact tests/Feature/Ai/InjectUserAnthropicKeyTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Middleware/InjectUserAnthropicKey.php app/Http/Kernel.php tests/Feature/Ai/InjectUserAnthropicKeyTest.php
git commit -m "feat(ai): per-user Claude-key in laravel/ai config voor Copilot via middleware (#95)"
```

---

## Task 6: AI-instellingen-pagina (per-user key)

**Files:**
- Create: `app/Filament/Pages/AiSettingsPage.php`
- Create: `resources/views/filament/pages/ai-settings-page.blade.php`
- Test: `tests/Feature/Filament/Pages/AiSettingsPageTest.php`

> Spiegel de chrome (`content()`, `getFormActions()`, property-types) van `app/Filament/Pages/ExportSessionsPage.php` — dat is de geverifieerde v5-template in deze repo. Behoud exacte property-union-types: `protected static string|\BackedEnum|null $navigationIcon`, `protected static string|\UnitEnum|null $navigationGroup`, `protected string $view` (niet static).
>
> ⚠️ **API-exactheid (kopieer, raad niet):** de onderstaande klasse is een **logica-skelet**. Neem de exacte `use`-imports en schema-API **letterlijk over uit `ExportSessionsPage`/`FailedJobsPage`** — met name: `HasForms` (waarschijnlijk `Filament\Forms\Contracts\HasForms`, NIET `Filament\Contracts\HasForms`), `InteractsWithForms`, `Filament\Schemas\Schema`, en of de Schema `->components([...])` óf `->schema([...])` gebruikt. Run `php artisan test --filter=AiSettings` tot de chrome klopt.
>
> ⚠️ **NIET gaten op `aiEnabled()`:** deze pagina mag GÉÉN `canAccess()`/`shouldRegisterNavigation()` op `aiEnabled()` krijgen. Zonder key is `aiEnabled()` `false`, en dan moet een user juist hier nog wél kunnen komen om een key toe te voegen (kip-ei). Laat de pagina bereikbaar voor elke ingelogde user.

- [ ] **Step 1: Failing test**

Maak `tests/Feature/Filament/Pages/AiSettingsPageTest.php`:

```php
<?php

use App\Filament\Pages\AiSettingsPage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('slaat een ingevoerde key op het user-record op', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => null]);

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->fillForm(['anthropic_api_key' => 'sk-ant-new-key'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->anthropic_api_key)->toBe('sk-ant-new-key');
});

it('test-actie meldt succes bij een geldige key (200)', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['data' => []], 200)]);

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->callAction('test')
        ->assertNotified();

    expect($user->fresh()->ai_key_verified_at)->not->toBeNull();
});

it('test-actie meldt fout bij een ongeldige key (401)', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-bad']);
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error'], 401)]);

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->callAction('test')
        ->assertNotified();

    expect($user->fresh()->ai_key_verified_at)->toBeNull();
});
```

> `callAction('test')` werkt voor page-acties in `getHeaderActions()`. Pas aan als je de test-knop als form-action plaatst (`callAction` met de juiste naam).

- [ ] **Step 2: Run → faalt (pagina bestaat niet)**

Run: `php artisan test --compact tests/Feature/Filament/Pages/AiSettingsPageTest.php`
Expected: FAIL.

- [ ] **Step 3: Pagina implementeren**

Maak `app/Filament/Pages/AiSettingsPage.php` (gebruik de juiste v5-namespaces; kruisverwijs `ExportSessionsPage` voor `content()`/`getFormActions()`):

```php
<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

class AiSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'BEHEER';

    protected static ?string $navigationLabel = 'AI-instellingen';

    protected static ?string $title = 'AI-instellingen';

    protected string $view = 'filament.pages.ai-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        // Toon de bestaande key niet; alleen of er een key is.
        $this->form->fill(['anthropic_api_key' => null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('anthropic_api_key')
                    ->label('Claude API-key')
                    ->password()
                    ->revealable()
                    ->placeholder($this->keyPlaceholder())
                    ->helperText('Je eigen Anthropic (Claude) API-key. Wordt versleuteld opgeslagen en nooit volledig getoond.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $key = $this->form->getState()['anthropic_api_key'] ?? null;

        if (blank($key)) {
            Notification::make()->title('Geen key ingevoerd')->warning()->send();

            return;
        }

        $user = auth()->user();
        $user->update(['anthropic_api_key' => $key, 'ai_key_verified_at' => null]);

        $this->form->fill(['anthropic_api_key' => null]);

        Notification::make()->title('Key opgeslagen')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test key')
                ->action(fn () => $this->testKey()),
            Action::make('clear')
                ->label('Wis key')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->clearKey()),
        ];
    }

    protected function testKey(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $key = $user->anthropic_api_key;

        if (blank($key)) {
            Notification::make()->title('Geen key om te testen')->warning()->send();

            return;
        }

        $response = Http::baseUrl(config('ai.base_url', 'https://api.anthropic.com'))
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => config('ai.anthropic_version', '2023-06-01'),
            ])
            ->connectTimeout(5)
            ->timeout(20)
            ->get('/v1/models');

        if ($response->successful()) {
            $user->update(['ai_key_verified_at' => now()]);
            Notification::make()->title('Key is geldig')->success()->send();

            return;
        }

        Notification::make()
            ->title('Key ongeldig')
            ->body('Anthropic gaf status '.$response->status().' terug.')
            ->danger()
            ->send();
    }

    protected function clearKey(): void
    {
        auth()->user()->update(['anthropic_api_key' => null, 'ai_key_verified_at' => null]);
        Notification::make()->title('Key gewist')->success()->send();
    }

    protected function keyPlaceholder(): string
    {
        $key = auth()->user()?->anthropic_api_key;

        return filled($key)
            ? 'Huidige key: ••••'.substr($key, -4)
            : 'sk-ant-…';
    }
}
```

> Cross-check tegen `ExportSessionsPage`: als die `content(Schema $schema)` gebruikt i.p.v. een los blade-`{{ $this->form }}`, volg dezelfde aanpak. Pas `getHeaderActions()` aan naar de exacte v5-signature die `ExportSessionsPage` gebruikt indien afwijkend.

- [ ] **Step 4: View toevoegen**

Maak `resources/views/filament/pages/ai-settings-page.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Opslaan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 5: Run → groen**

Run: `php artisan test --compact tests/Feature/Filament/Pages/AiSettingsPageTest.php`
Expected: PASS (3 tests). Bij falen van de action-tests: controleer of `test`/`clear` als header-action vs form-action geregistreerd zijn en gebruik de juiste `callAction`-naam.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Pages/AiSettingsPage.php resources/views/filament/pages/ai-settings-page.blade.php tests/Feature/Filament/Pages/AiSettingsPageTest.php
git commit -m "feat(ai): AI-instellingen-pagina — per-user Claude-key invoeren/testen/wissen (#95)"
```

---

## Task 7: Integratie-check + opruimen + volledige suite

**Files:**
- Modify: `.env.example` / `.env.local.example` (documenteer `ANTHROPIC_*`, markeer `OPENAI_*` deprecated) — alleen voorbeeldbestanden, geen secrets.

- [ ] **Step 1: Env-voorbeelden bijwerken**

Voeg in `.env.example` (en `.env.local.example` indien aanwezig) toe en documenteer:

```
# AI (Claude) — BYO-key per gebruiker (zie AI-instellingen in de app).
# ANTHROPIC_API_KEY blijft optioneel als globale fallback; default leeg (puur BYO).
ANTHROPIC_API_KEY=
ANTHROPIC_BASE_URL=https://api.anthropic.com
AI_MODEL=claude-haiku-4-5-20251001
AI_MAX_TOKENS=1024
# OPENAI_API_KEY / AI_DRIVER=openai — DEPRECATED na migratie naar Claude.
```

- [ ] **Step 2: Verifieer dat de key nergens lekt**

Run: `git grep -n "anthropic_api_key" app/ | grep -i "log\|dump\|dd\|toArray"`
Expected: geen treffers die de ruwe key loggen/dumpen. (`$hidden` dekt serialisatie al.)

- [ ] **Step 3: Geraakte tests draaien**

Run: `php artisan test --compact tests/Feature/Ai tests/Unit/Services tests/Unit/AimtrackFeatureToggleTest.php tests/Feature/Filament/Pages tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 4: Pint over de hele diff**

Run: `vendor/bin/pint --dirty`
Expected: geen resterende stijl-issues.

- [ ] **Step 5: Volledige suite (vraag de gebruiker)**

Vraag de gebruiker of de volledige suite mag draaien:
Run: `php artisan test --compact`
Expected: groen (let op de eerder gerepareerde flag-tests).

- [ ] **Step 6: Commit**

```bash
git add .env.example .env.local.example
git commit -m "docs(ai): env-voorbeelden voor Claude BYO-key + OpenAI als deprecated (#95)"
```

---

## Definition of Done (Fase 1)

- [ ] Gebruiker kan in AI-instellingen een eigen Claude-key opslaan (encrypted, gemaskeerd) en testen (200/401-feedback) en wissen.
- [ ] `AiKeyResolver` geeft de user-key of `null`.
- [ ] `aiEnabled()` is alleen `true` bij flag-actief **én** een resolvebare key; alle ~6 call-sites volgen automatisch.
- [ ] Copilot gebruikt per request de key van de ingelogde gebruiker (middleware), geen centrale env-key.
- [ ] Sessie-reflecties en wapen-insights lopen via de Anthropic Messages API met de key van de eigenaar; jobs serialiseren de key niet.
- [ ] Zonder key: AI-UI verborgen + ShooterCoach geeft nette fallback-tekst i.p.v. een fout.
- [ ] Pest-tests dekken: encrypted opslag/hidden, resolver, key-aware gate, ShooterCoach-reflectie+insight (Anthropic gemockt) + no-key-fallback, middleware-injectie (incl. geen-lek/andere-route), settings-pagina (opslaan/test-ok/test-fail).
- [ ] Pint clean; bestaande AI-flag-tests gerepareerd; volledige suite groen.

## Niet in deze fase (Fase 2 — prio 3)
Verenigingen, rollen, coach-inzage, gedeelde verenigings-key (resolver-uitbreiding `user → vereniging → null`), ledenbeheer. Krijgt een eigen worktree + plan.
