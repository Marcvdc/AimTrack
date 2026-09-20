<?php

declare(strict_types=1);

use App\Support\DateFormat;
use Illuminate\Support\Carbon;

test('date renders the vastgelegde notatie d-m-Y', function (): void {
    expect(DateFormat::date(Carbon::parse('2026-08-09')))->toBe('09-08-2026');
});

test('dateTime renders d-m-Y H:i in de weergave-tijdzone', function (): void {
    // 09:30 UTC in augustus is 11:30 in Amsterdam (zomertijd, +2).
    expect(DateFormat::dateTime(Carbon::parse('2026-08-09 09:30:00', 'UTC')))
        ->toBe('09-08-2026 11:30');
});

test('time rendert alleen het tijdstip, in de weergave-tijdzone', function (): void {
    expect(DateFormat::time(Carbon::parse('2026-08-09 09:30:00', 'UTC')))->toBe('11:30');
});

test('month rendert maand-granulariteit voor trendassen, in het Nederlands', function (): void {
    expect(DateFormat::month(Carbon::parse('2026-08-01')))->toBe('aug. 2026');
});

test('machineDate blijft ISO 8601 voor CSV en AI-payloads', function (): void {
    expect(DateFormat::machineDate(Carbon::parse('2026-08-09')))->toBe('2026-08-09');
});

test('elke helper is null-veilig', function (string $method): void {
    expect(DateFormat::{$method}(null))->toBeNull();
})->with(['date', 'dateTime', 'time', 'month', 'machineDate']);

test('dateTime verschuift de dag mee bij een tijdstip vlak voor middernacht UTC', function (): void {
    // 31 december 23:30 UTC is in Amsterdam al 1 januari 00:30 (wintertijd, +1).
    expect(DateFormat::dateTime(Carbon::parse('2026-12-31 23:30:00', 'UTC')))
        ->toBe('01-01-2027 00:30');
});

test('date converteert juist NIET: een datumkolom draagt geen tijdstip', function (): void {
    // Zou date() naar Europe/Amsterdam schuiven, dan zou een sessie van
    // 1 januari op 31 december kunnen landen.
    expect(DateFormat::date(Carbon::parse('2027-01-01 00:00:00', 'UTC')))->toBe('01-01-2027');
});

test('displayTimezone volgt de configuratie', function (): void {
    expect(DateFormat::displayTimezone())->toBe('Europe/Amsterdam');
});
