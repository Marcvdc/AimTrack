<?php

declare(strict_types=1);

use App\Filament\Copilot\Tools\ScoreDriftTool;
use App\Filament\Copilot\Tools\SessionLookupTool;
use App\Filament\Copilot\Tools\ShooterContextTool;
use App\Filament\Copilot\Tools\WeaponLookupTool;
use App\Models\AiReflection;
use App\Models\AiWeaponInsight;
use App\Models\AmmoType;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use App\Support\Ai\AiPrivacyNotice;
use EslamRedaDiv\FilamentCopilot\Agent\ContextBuilder;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAgentMemory;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

// #132: de privacytekst moet precies noemen wat er naar Anthropic gaat, niet
// minder en niet meer. Deze test bepaalt per AI-pad welke velden de uitvoer
// beïnvloeden door elk veld afzonderlijk te wijzigen en te kijken of wat er
// naar buiten gaat verandert. Komt er een veld bij in een prompt of tool, of
// een nieuwe kolom op een model die ergens wordt meegestuurd, dan wordt dit
// rood tot AiPrivacyNotice (en daarmee de tekst) is bijgewerkt.

/**
 * @return array{user: User, weapon: Weapon, session: Session, models: list<Model>}
 */
function aiDataFlowFixture(): array
{
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-probe']);

    $ammo = AmmoType::query()->create([
        'user_id' => $user->id,
        'name' => 'Probe Match',
        'caliber' => '.22 Probe',
        'notes' => 'Probe-munitienotitie',
    ]);

    $weapon = Weapon::factory()->create([
        'user_id' => $user->id,
        'name' => 'Probe Pistool',
        'weapon_type' => \App\Enums\WeaponType::PISTOL,
        'caliber' => '9mm Probe',
        'korrel_correction' => 'korrel-probe',
        'vizier_correction' => 'vizier-probe',
        'trigger_weight_g' => 1350,
        'grip_size' => 'grip-probe',
        'serial_number' => 'SN-PROBE-1',
        'storage_location' => 'Kluis Probe',
        'owned_since' => '2021-03-04',
        'is_active' => true,
        'notes' => 'Wapennotitie probe',
    ]);

    AiWeaponInsight::query()->create([
        'weapon_id' => $weapon->id,
        'summary' => 'Inzicht probe',
        'patterns' => ['Patroon probe'],
        'suggestions' => ['Suggestie probe'],
    ]);

    $session = Session::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-05-06',
        'range_name' => 'Baan Probe',
        'location' => 'Locatie Probe',
        'notes_raw' => 'Sessienotitie probe',
        'manual_reflection' => 'Reflectie probe',
    ]);

    $entry = SessionWeapon::factory()->create([
        'session_id' => $session->id,
        'weapon_id' => $weapon->id,
        'distance_m' => 25,
        'rounds_fired' => 40,
        'ammo_type' => 'Munitie probe',
        'ammo_type_id' => $ammo->id,
        'group_quality_text' => 'Groepering probe',
        'deviation' => \App\Enums\Deviation::LEFT,
        'flyers_count' => 2,
    ]);

    $shots = collect(range(0, 9))->map(fn (int $i): SessionShot => SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => intdiv($i, 5),
        'shot_index' => $i % 5,
        'x_normalized' => 0.5 + $i / 100,
        'y_normalized' => 0.5 - $i / 100,
        'distance_from_center' => $i / 50,
        'ring' => 10 - intdiv($i, 3),
        'score' => 10 - intdiv($i, 3),
        'metadata' => ['bron' => 'probe'],
    ]));

    $reflection = AiReflection::factory()->create([
        'session_id' => $session->id,
        'summary' => 'AI-reflectie probe',
        'positives' => ['Positief probe'],
        'improvements' => ['Verbeter probe'],
        'next_focus' => 'Focus probe',
        'acknowledged_at' => '2026-05-07 10:00:00',
    ]);

    return [
        'user' => $user,
        'weapon' => $weapon,
        'session' => $session,
        'models' => [$weapon, $weapon->aiWeaponInsight()->first(), $session, $entry, $shots->first(), $reflection, $ammo],
    ];
}

/**
 * Een andere geldige waarde voor een attribuut, afgeleid van de cast.
 */
function aiDataFlowAlternative(Model $model, string $attribute): mixed
{
    $cast = $model->getCasts()[$attribute] ?? null;
    $current = $model->getAttribute($attribute);

    if (is_string($cast) && enum_exists($cast)) {
        return collect($cast::cases())->first(fn ($case): bool => $case !== $current);
    }

    return match (true) {
        $cast === 'boolean' => ! $current,
        in_array($cast, ['integer', 'int'], true) => ((int) $current) + 100,
        in_array($cast, ['float', 'double', 'real'], true) => ((float) $current) + 0.137,
        in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime'], true) => Carbon::parse($current)->addDays(3),
        in_array($cast, ['array', 'json'], true) => ['Anders '.$attribute],
        default => 'Anders '.class_basename($model).' '.$attribute,
    };
}

/**
 * Welke modelattributen de uitvoer van een pad beïnvloeden, als "tabel.kolom".
 *
 * Foreign keys blijven buiten beschouwing: die verwijzen naar een ander model,
 * en de velden daarvan worden via dat model zelf getoetst.
 *
 * @param  list<Model>  $models
 * @return list<string>
 */
function aiDataFlowDetect(array $models, Closure $render): array
{
    $baseline = $render();
    $detected = [];

    foreach ($models as $model) {
        foreach ($model->getFillable() as $attribute) {
            if (str_ends_with($attribute, '_id')) {
                continue;
            }

            $original = $model->getAttributes()[$attribute] ?? null;

            $model->setAttribute($attribute, aiDataFlowAlternative($model, $attribute));
            $model->saveQuietly();

            if ($render() !== $baseline) {
                $detected[] = $model->getTable().'.'.$attribute;
            }

            $model->setRawAttributes(array_merge($model->getAttributes(), [$attribute => $original]));
            $model->saveQuietly();
        }
    }

    sort($detected);

    return $detected;
}

function aiDataFlowTool(BaseTool $tool, User $user): BaseTool
{
    return $tool->forPanel('admin')->forUser($user)->forTenant(null)->forConversation(null);
}

/**
 * Wat ShooterCoach feitelijk naar de API stuurt: system-prompt plus berichten.
 */
function aiDataFlowCapture(Closure $call): string
{
    $sent = '';

    Http::fake(function (HttpRequest $request) use (&$sent) {
        $sent = json_encode([$request['system'] ?? null, $request['messages'] ?? null]);

        return Http::response(['content' => [['type' => 'text', 'text' => '{}']]], 200);
    });

    $call();

    return $sent;
}

dataset('ai-paden', [
    'reflectie' => [
        'reflectie',
        fn (array $f): Closure => fn (): string => aiDataFlowCapture(
            fn () => ShooterCoach::make()->generateSessionReflection(Session::query()->findOrFail($f['session']->id))
        ),
    ],
    'wapeninzicht' => [
        'wapeninzicht',
        fn (array $f): Closure => fn (): string => aiDataFlowCapture(
            fn () => ShooterCoach::make()->generateWeaponInsight(Weapon::query()->findOrFail($f['weapon']->id))
        ),
    ],
    'chat: schietcontext' => [
        'chat_schietcontext',
        fn (array $f): Closure => fn (): string => (string) aiDataFlowTool(new ShooterContextTool, $f['user'])->handle(new Request([])),
    ],
    'chat: sessie opzoeken' => [
        'chat_sessie',
        fn (array $f): Closure => fn (): string => (string) aiDataFlowTool(new SessionLookupTool, $f['user'])->handle(new Request(['session_id' => $f['session']->id])),
    ],
    'chat: wapen opzoeken' => [
        'chat_wapen',
        fn (array $f): Closure => fn (): string => (string) aiDataFlowTool(new WeaponLookupTool, $f['user'])->handle(new Request(['weapon_id' => $f['weapon']->id])),
    ],
    'chat: score-drift' => [
        'chat_scoredrift',
        fn (array $f): Closure => fn (): string => (string) aiDataFlowTool(new ScoreDriftTool, $f['user'])->handle(new Request([])),
    ],
]);

test('elk AI-pad stuurt precies de velden die AiPrivacyNotice voor dat pad opgeeft', function (string $path, Closure $renderer): void {
    $fixture = aiDataFlowFixture();

    $detected = aiDataFlowDetect($fixture['models'], $renderer($fixture));
    $declared = AiPrivacyNotice::sentAttributes($path);
    sort($declared);

    expect($detected)->toBe($declared);
})->with('ai-paden');

test('elke tool die de chat kan aanroepen staat in AiPrivacyNotice', function (): void {
    $registered = collect([
        \App\Filament\Pages\CoachPage::copilotTools(),
        \App\Filament\Resources\SessionResource::copilotTools(),
        \App\Filament\Resources\WeaponResource::copilotTools(),
    ])
        ->flatten()
        ->map(fn (object $tool): string => $tool::class)
        ->merge(config('filament-copilot.global_tools'))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($registered)->toBe(collect(AiPrivacyNotice::CHAT_TOOLS)->keys()->sort()->values()->all());

    foreach (AiPrivacyNotice::CHAT_TOOLS as $path) {
        expect($path === null || array_key_exists($path, AiPrivacyNotice::PATHS))->toBeTrue();
    }
});

test('de opgeslagen herinneringen van de chat gaan mee in de systeemprompt', function (): void {
    $user = User::factory()->create();
    CopilotAgentMemory::remember($user, 'admin', 'voorkeur', 'Herinnering probe');

    $prompt = app(ContextBuilder::class)->forPanel('admin')->forUser($user)->build();

    expect($prompt)->toContain('Herinnering probe')
        ->and(AiPrivacyNotice::panel())->toContain('herinneringen');
});

test('elk meegestuurd veld heeft een label in de tekst, en elk label staat in de docs', function (): void {
    $docs = [
        'docs/user/README.md' => file_get_contents(base_path('docs/user/README.md')),
        'docs/user/ai-coach.md' => file_get_contents(base_path('docs/user/ai-coach.md')),
    ];

    foreach (AiPrivacyNotice::allSentAttributes() as $attribute) {
        expect(AiPrivacyNotice::LABELS)->toHaveKey($attribute);
    }

    foreach (AiPrivacyNotice::labels() as $label) {
        expect(AiPrivacyNotice::panel())->toContain($label);

        foreach ($docs as $name => $content) {
            expect(str_contains(preg_replace('/\s+/', ' ', $content), $label))
                ->toBeTrue("{$name} noemt '{$label}' niet");
        }
    }
});
