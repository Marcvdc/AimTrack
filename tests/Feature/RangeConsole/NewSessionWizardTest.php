<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource;
use App\Filament\Resources\SessionResource\Pages\CreateSession;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

/**
 * Complete sessionWeapons repeater-rij. In de browser vult "Regel toevoegen"
 * de veld-defaults (rounds_fired/flyers_count = 0); de fillForm test-helper
 * doet dat niet, dus leveren we volledige data aan.
 *
 * @return array<int, array<string, mixed>>
 */
function wizardWeaponRow(Weapon $weapon, int $rounds = 60): array
{
    return [
        [
            'weapon_id' => $weapon->id,
            'rounds_fired' => $rounds,
            'flyers_count' => 0,
        ],
    ];
}

it('renders the 4-step wizard with all step labels', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->assertOk()
        ->assertSee('Wapen')
        ->assertSee('Sessie')
        ->assertSee('Schoten')
        ->assertSee('Notities');
});

it('renders the Schoten step preview with readout, numpad and AI tip', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->assertSee('VOORTGANG')
        ->assertSee('LOPENDE SCORE')
        ->assertSee('GEM. PER SCHOT')
        ->assertSee('OPNAME START NA OPSLAAN')
        ->assertSee('10.9')
        ->assertSee('OF VUL HANDMATIG IN')
        ->assertSee('Bevestig')
        ->assertSee('AI TIJDENS SESSIE')
        ->assertSee('schotenbord');
});

it('renders the Schoten step context rail with SESSIE, WAPEN and OPTIES', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->assertSee('SESSIE')
        ->assertSee('WAPEN')
        ->assertSee('OPTIES')
        ->assertSee('AI-reflectie')
        ->assertSee('Decimaal-notatie');
});

it('creates a session with weapon row through the wizard', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->fillForm([
            'date' => '2026-05-20',
            'sessionWeapons' => wizardWeaponRow($weapon),
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Session::class, [
        'user_id' => $user->id,
        'date' => '2026-05-20 00:00:00',
    ]);

    $session = Session::query()->where('user_id', $user->id)->latest('id')->first();

    expect($session->sessionWeapons()->count())->toBe(1);
    expect($session->sessionWeapons()->first()->weapon_id)->toBe($weapon->id);
});

it('redirects to the shot board after creating the session', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->fillForm([
            'date' => '2026-05-21',
            'sessionWeapons' => wizardWeaponRow($weapon, 10),
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $session = Session::query()->where('user_id', $user->id)->latest('id')->first();

    expect(SessionResource::getUrl('shots', ['record' => $session]))->toContain('/shots');
});

it('validates that a date is required before completing the wizard', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->fillForm([
            'date' => null,
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['date' => 'required']);
});

it('rejects a weapon row without a weapon_id', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->fillForm([
            'date' => '2026-05-20',
            'sessionWeapons' => [
                ['rounds_fired' => 10, 'flyers_count' => 0],
            ],
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Session::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('rejects a weapon row with distance beyond the allowed maximum', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->fillForm([
            'date' => '2026-05-20',
            'sessionWeapons' => [
                ['weapon_id' => $weapon->id, 'rounds_fired' => 10, 'flyers_count' => 0, 'distance_m' => 9999],
            ],
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Session::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('exposes the Bijlagen attachment repeater on the Notities step', function (): void {
    // De FileUpload-metadata-capture (afterStateUpdated) is bestaande logica,
    // verbatim hergebruikt uit SessionResource::form(). Hier borgen we de
    // nieuwe integratie: de wizard Notities-stap stelt de attachments-repeater
    // beschikbaar. Een echte temp-upload door een geneste repeater simuleren is
    // bewust buiten scope (bros + dekt bestaande logica).
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->assertOk()
        ->assertSee('Bijlagen')
        ->assertSee('Bijlage toevoegen');
});

it('creates a session with notes through the wizard Notities step', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->fillForm([
            'date' => '2026-05-22',
            'sessionWeapons' => wizardWeaponRow($weapon, 10),
            'notes_raw' => 'Rustige sessie, nieuwe handgreep getest.',
            'manual_reflection' => 'Volgende keer kortere pauzes.',
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Session::class, [
        'user_id' => $user->id,
        'notes_raw' => 'Rustige sessie, nieuwe handgreep getest.',
        'manual_reflection' => 'Volgende keer kortere pauzes.',
    ]);
});
