<?php

use App\Enums\Deviation;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon;

it('streams csv export with shared dataset and shots summary', function (): void {
    Carbon::setTestNow('2026-01-21');

    /** @var User $user */
    $user = User::factory()->create();

    $session = Session::factory()->for($user)->create([
        'date' => Carbon::parse('2026-01-20'),
        'range_name' => 'SSV Scherpschutters',
        'location' => 'Scherpschutters',
        'notes_raw' => 'Focussen op houding.',
    ]);

    $weapon = Weapon::factory()->for($user)->create([
        'name' => 'CZ Shadow 2',
        'caliber' => '9mm',
    ]);

    SessionWeapon::factory()->for($session)->for($weapon)->create([
        'distance_m' => 25,
        'rounds_fired' => 50,
        'group_quality_text' => 'Strakke groep',
        'deviation' => Deviation::LEFT->value,
        'ammo_type' => '9mm FMG',
        'flyers_count' => 2,
    ]);

    SessionShot::factory()->count(3)->for($session)->state(new Sequence(
        ['score' => 8, 'ring' => 8],
        ['score' => 9, 'ring' => 9],
        ['score' => 10, 'ring' => 10],
    ))->create();

    $this->actingAs($user);

    $response = $this->get(route('exports.sessions.download', [
        'from' => '2026-01-01',
        'to' => '2026-01-31',
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $content = collect(explode("\n", trim($response->streamedContent())));
    $header = str_getcsv($content->first());
    expect($header)->toBe([
        'Datum',
        'Baan',
        'Locatie',
        'Wapen',
        'Kaliber',
        'Afstand (m)',
        'Rondes',
        'Munitietype',
        'Groepering',
        'Afwijking',
        'Flyers',
        'Notities',
        'Shot samenvatting',
    ]);

    $row = str_getcsv($content->get(1));
    expect($row)->toBe([
        '2026-01-20',
        'SSV Scherpschutters',
        'Scherpschutters',
        'CZ Shadow 2',
        '9mm',
        '25',
        '50',
        '9mm FMG',
        'Strakke groep',
        Deviation::LEFT->value,
        '2',
        'Focussen op houding.',
        '3 schoten | gem. score 9,0 | beste score 10 | gem. ring 9,0',
    ]);
});

it('renders pdf view with paginated session blocks', function (): void {
    Carbon::setTestNow('2026-01-21');

    /** @var User $user */
    $user = User::factory()->create();

    $sessions = Session::factory()->count(2)->for($user)->sequence(
        ['date' => Carbon::parse('2026-01-18'), 'range_name' => 'Baan 1', 'location' => 'Locatie 1'],
        ['date' => Carbon::parse('2026-01-19'), 'range_name' => 'Baan 2', 'location' => 'Locatie 2'],
    )->create();

    foreach ($sessions as $session) {
        SessionWeapon::factory()->for($session)->create([
            'distance_m' => 50,
            'rounds_fired' => 30,
            'group_quality_text' => 'Moet beter',
            'deviation' => Deviation::NONE->value,
            'ammo_type' => '9mm FMG',
            'flyers_count' => 1,
        ]);
    }

    $this->actingAs($user);

    $response = $this->get(route('exports.sessions.download', [
        'from' => '2026-01-01',
        'to' => '2026-01-31',
        'format' => 'pdf',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
