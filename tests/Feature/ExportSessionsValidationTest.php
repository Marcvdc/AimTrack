<?php

use App\Filament\Pages\ExportSessionsPage;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Carbon::setTestNow('2026-01-21');
});

function sessionExportUrl(array $query = []): string
{
    return route('exports.sessions.download', $query + [
        'from' => '2026-01-01',
        'to' => '2026-01-31',
        'format' => 'csv',
    ]);
}

it('exporteert met een wapenfilter van de ingelogde gebruiker', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $session = Session::factory()->for($user)->create([
        'date' => Carbon::parse('2026-01-20'),
    ]);

    $weapon = Weapon::factory()->for($user)->create();

    SessionWeapon::factory()->for($session)->for($weapon)->create();

    $this->actingAs($user);

    $response = $this->get(sessionExportUrl(['weapon_ids' => (string) $weapon->getKey()]));

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'text/csv');
});

it('stuurt een onparseerbare datum terug naar de exportpagina in plaats van een fout', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get(sessionExportUrl(['from' => 'geen-datum']));

    $response->assertRedirect(ExportSessionsPage::getUrl());
    $response->assertSessionHasErrors(['from' => 'De startdatum is geen geldige datum.']);

    expect(ExportSessionsPage::getUrl())->toContain('/admin/');
});

it('weigert een export zonder periode in plaats van een leeg bestand', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('exports.sessions.download', ['format' => 'csv']));

    $response->assertRedirect(ExportSessionsPage::getUrl());
    $response->assertSessionHasErrors([
        'from' => 'Kies een startdatum voor de export.',
        'to' => 'Kies een einddatum voor de export.',
    ]);

    Notification::assertNotified('Export niet gestart');
});

it('weigert een einddatum die voor de startdatum ligt', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get(sessionExportUrl(['from' => '2026-01-31', 'to' => '2026-01-01']));

    $response->assertRedirect(ExportSessionsPage::getUrl());
    $response->assertSessionHasErrors(['to' => 'De einddatum moet op of na de startdatum liggen.']);
});

it('weigert een onbekend formaat', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get(sessionExportUrl(['format' => 'xml']));

    $response->assertRedirect(ExportSessionsPage::getUrl());
    $response->assertSessionHasErrors(['format' => 'Kies csv of pdf als formaat.']);
});

it('houdt een niet-numeriek wapenfilter buiten de query', function (): void {
    $this->actingAs(User::factory()->create());

    DB::enableQueryLog();

    $response = $this->get(sessionExportUrl(['weapon_ids' => 'abc']));

    $weaponQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'weapons'));

    DB::disableQueryLog();

    $response->assertRedirect(ExportSessionsPage::getUrl());

    // De `bail`-regel moet de keten stoppen voordat `exists` draait. Gebeurt dat niet, dan legt de
    // bestaanscontrole `abc` op de bigint-kolom, en dat is de HTTP 500 uit issue #139 op PostgreSQL.
    // SQLite slikt die query wel, dus de uitgebleven query is hier het bewijs, niet de melding.
    expect($weaponQueries)->toBeEmpty();

    expect(session('errors')->getBag('default')->get('weapon_ids.0'))
        ->toBe(['Het wapenfilter mag alleen wapennummers bevatten.']);
});

it('weigert een wapen van een andere gebruiker', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $weaponOfSomeoneElse = Weapon::factory()->for(User::factory()->create())->create();

    $this->actingAs($user);

    $response = $this->get(sessionExportUrl(['weapon_ids' => (string) $weaponOfSomeoneElse->getKey()]));

    $response->assertRedirect(ExportSessionsPage::getUrl());
    $response->assertSessionHasErrors([
        'weapon_ids.0' => 'Een van de gekozen wapens bestaat niet of is niet van jou.',
    ]);
});

it('negeert een leeg wapenfilter', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get(sessionExportUrl(['weapon_ids' => '']));

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'text/csv');
});

it('beperkt het aantal exports tot tien per minuut', function (): void {
    $this->actingAs(User::factory()->create());

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->get(sessionExportUrl())->assertOk();
    }

    $this->get(sessionExportUrl())->assertTooManyRequests();

    // De teller hangt aan de gebruiker, niet aan het adres: achter een gedeeld NAT-adres mag de
    // ene schutter de andere niet blokkeren.
    $this->actingAs(User::factory()->create());

    $this->get(sessionExportUrl())->assertOk();
});
