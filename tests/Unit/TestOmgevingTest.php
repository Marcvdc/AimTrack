<?php

/**
 * Bewaakt dat de testsuite in de dev-container dezelfde omgeving krijgt als in CI.
 *
 * De dev-container laadt .env.local via env_file. Die waarden staan dan als echte
 * omgevingsvariabelen klaar en winnen van een <env> in phpunit.xml zonder force. Zo
 * draaide de suite in de container met APP_ENV=local, een lege APP_KEY en file-cache,
 * en kreeg een nieuwe worktree een lege VIEW_COMPILED_PATH waarmee artisan niet opstart.
 */

/**
 * @return array<string, string>
 */
function envVoorbeeldWaarden(): array
{
    $waarden = [];

    foreach (file(base_path('.env.local.example'), FILE_IGNORE_NEW_LINES) as $regel) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $regel, $match) === 1) {
            $waarden[$match[1]] = trim($match[2]);
        }
    }

    return $waarden;
}

/**
 * Namen van de variabelen die phpunit.xml met force="true" zet, per soort (env of server).
 *
 * @return array<int, string>
 */
function phpunitGeforceerd(string $soort): array
{
    $xml = simplexml_load_file(base_path('phpunit.xml'));
    $namen = [];

    foreach ($xml->php->{$soort} as $variabele) {
        if ((string) $variabele['force'] === 'true') {
            $namen[] = (string) $variabele['name'];
        }
    }

    return $namen;
}

/**
 * @return array<int, string>
 */
function phpunitTestwaarden(): array
{
    $xml = simplexml_load_file(base_path('phpunit.xml'));
    $namen = [];

    foreach ($xml->php->env as $variabele) {
        $namen[] = (string) $variabele['name'];
    }

    return $namen;
}

it('draait de tests in de testing-omgeving met array-drivers', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('app.key'))->not->toBeEmpty()
        ->and(config('view.compiled'))->not->toBeEmpty();
});

it('forceert elke testwaarde die de dev-container via .env.local ook zet', function (string $soort) {
    $nietGeforceerd = collect(phpunitTestwaarden())
        ->filter(fn (string $naam): bool => array_key_exists($naam, envVoorbeeldWaarden()))
        ->reject(fn (string $naam): bool => in_array($naam, phpunitGeforceerd($soort), true))
        ->values()
        ->all();

    expect($nietGeforceerd)->toBe([]);
})->with(['env', 'server']);

it('zet in .env.local.example geen lege pad-variabele', function () {
    $legePaden = collect(envVoorbeeldWaarden())
        ->filter(fn (string $waarde, string $naam): bool => str_ends_with($naam, '_PATH') && $waarde === '')
        ->keys()
        ->all();

    expect($legePaden)->toBe([]);
});
