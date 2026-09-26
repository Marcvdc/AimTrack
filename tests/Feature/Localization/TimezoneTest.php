<?php

declare(strict_types=1);

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Support\DateFormat;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('de opslag-tijdzone blijft UTC', function (): void {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(date_default_timezone_get())->toBe('UTC');
});

test('de weergave-tijdzone staat op Europe/Amsterdam', function (): void {
    expect(config('app.display_timezone'))->toBe('Europe/Amsterdam')
        ->and(FilamentTimezone::get())->toBe('Europe/Amsterdam');
});

test('een bestaande UTC-rij verschuift niet bij het teruglezen', function (): void {
    // Dit is de regressie die deze PR bewust NIET wil veroorzaken: de
    // timestamp-kolommen dragen geen zone mee, dus als app.timezone naar
    // Europe/Amsterdam zou gaan, zou deze rij als 09:30 terugkomen.
    $session = Session::factory()->for(User::factory())->create();

    DB::table('session_shots')->insert([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
        'distance_from_center' => 0.0,
        'ring' => 10,
        'score' => 10,
        'created_at' => '2026-08-09 09:30:00',
        'updated_at' => '2026-08-09 09:30:00',
    ]);

    $shot = SessionShot::query()->where('session_id', $session->id)->sole();

    expect($shot->created_at->timezone->getName())->toBe('UTC')
        ->and($shot->created_at->toDateTimeString())->toBe('2026-08-09 09:30:00');
});

test('dezelfde rij wordt wel in Amsterdamse tijd getoond', function (): void {
    $stored = Carbon::parse('2026-08-09 09:30:00', 'UTC');

    expect(DateFormat::dateTime($stored))->toBe('09-08-2026 11:30')
        ->and(DateFormat::time($stored))->toBe('11:30');
});

test('de weergave houdt rekening met zomer- en wintertijd', function (string $utc, string $expected): void {
    expect(DateFormat::dateTime(Carbon::parse($utc, 'UTC')))->toBe($expected);
})->with([
    'wintertijd is +1' => ['2026-01-15 09:30:00', '15-01-2026 10:30'],
    'zomertijd is +2' => ['2026-07-15 09:30:00', '15-07-2026 11:30'],
]);

test('een model schrijft nieuwe timestamps in UTC weg', function (): void {
    $session = Session::factory()->for(User::factory())->create();

    Carbon::setTestNow(Carbon::parse('2026-08-09 09:30:00', 'UTC'));

    $shot = SessionShot::query()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
        'distance_from_center' => 0.0,
        'ring' => 10,
        'score' => 10,
    ]);

    $raw = DB::table('session_shots')->where('id', $shot->id)->value('created_at');

    Carbon::setTestNow();

    expect((string) $raw)->toStartWith('2026-08-09 09:30:00');
});
