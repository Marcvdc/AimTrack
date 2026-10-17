<?php

declare(strict_types=1);

use App\Exceptions\Ai\AiKeyMissingException;
use App\Exceptions\Ai\AiUnavailableException;
use App\Filament\Resources\SessionResource\Pages\ViewSession;
use App\Filament\Resources\WeaponResource\Pages\ViewWeapon;
use App\Jobs\GenerateSessionReflectionJob;
use App\Jobs\GenerateWeaponInsightJob;
use App\Models\AiReflection;
use App\Models\AiWeaponInsight;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Sleep::fake();
});

function failurePathCoach(): ShooterCoach
{
    return new ShooterCoach(baseUrl: 'https://api.anthropic.com');
}

function failurePathUser(?string $key = 'sk-ant-user-key'): User
{
    return User::factory()->create(['anthropic_api_key' => $key]);
}

/**
 * @return array{summary: string, positives: array<int, string>, improvements: array<int, string>, next_focus: string}
 */
function goodReflectionContent(): array
{
    return [
        'summary' => 'Goede reflectie die moet blijven staan.',
        'positives' => ['Rustige afdruk'],
        'improvements' => ['Ademhaling serie 3'],
        'next_focus' => 'Natuurlijk richtpunt',
    ];
}

/**
 * @return array{summary: string, patterns: array<int, string>, suggestions: array<int, string>}
 */
function goodInsightContent(): array
{
    return [
        'summary' => 'Goed wapeninzicht dat moet blijven staan.',
        'patterns' => ['Lichte afwijking links'],
        'suggestions' => ['Controleer de grip'],
    ];
}

it('gooit een herkenbare AiKeyMissingException zonder call als er geen key is', function (): void {
    Http::fake();

    $session = Session::factory()->for(failurePathUser(null))->create();

    expect(fn () => failurePathCoach()->generateSessionReflection($session))
        ->toThrow(AiKeyMissingException::class);

    Http::assertNothingSent();
    expect(AiReflection::query()->count())->toBe(0);
});

it('gooit een AiUnavailableException in plaats van een foutstring terug te geven', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 500)]);

    $session = Session::factory()->for(failurePathUser())->create();

    expect(fn () => failurePathCoach()->generateSessionReflection($session))
        ->toThrow(AiUnavailableException::class);

    expect(AiReflection::query()->count())->toBe(0);
});

it('laat een bestaande reflectie ongewijzigd als de call faalt', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'kapot'], 500)]);

    $session = Session::factory()->for(failurePathUser())->create();
    $reflection = AiReflection::factory()->for($session)->create(goodReflectionContent());

    try {
        (new GenerateSessionReflectionJob($session))->handle();
    } catch (AiUnavailableException) {
        // De job laat de fout door zodat de queue hem opnieuw probeert.
    }

    expect($reflection->fresh()->only(array_keys(goodReflectionContent())))
        ->toBe(goodReflectionContent());
});

it('laat een bestaand wapeninzicht ongewijzigd als de call faalt', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'kapot'], 500)]);

    $weapon = Weapon::factory()->for(failurePathUser())->create();
    $insight = $weapon->aiWeaponInsight()->create(goodInsightContent());

    try {
        (new GenerateWeaponInsightJob($weapon))->handle();
    } catch (AiUnavailableException) {
        // De job laat de fout door zodat de queue hem opnieuw probeert.
    }

    expect($insight->fresh()->only(array_keys(goodInsightContent())))
        ->toBe(goodInsightContent());
});

it('retryt een 401 of 403 niet', function (int $status): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'auth'], $status)]);

    $session = Session::factory()->for(failurePathUser())->create();

    try {
        failurePathCoach()->generateSessionReflection($session);
    } catch (AiUnavailableException $exception) {
        expect($exception->status)->toBe($status)
            ->and($exception->isRetryable())->toBeFalse();
    }

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
})->with([401, 403]);

it('retryt een 429 en een 5xx met oplopende backoff', function (int $status): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'tijdelijk'], $status)]);

    $session = Session::factory()->for(failurePathUser())->create();

    try {
        failurePathCoach()->generateSessionReflection($session);
    } catch (AiUnavailableException $exception) {
        expect($exception->status)->toBe($status)
            ->and($exception->isRetryable())->toBeTrue();
    }

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
    Sleep::assertSequence([
        Sleep::for(1000)->milliseconds(),
        Sleep::for(3000)->milliseconds(),
    ]);
})->with([429, 500, 503]);

it('herstelt na een tijdelijke 5xx en slaat dan de reflectie op', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => 'tijdelijk'], 503)
            ->push(['content' => [['type' => 'text', 'text' => json_encode(goodReflectionContent())]]], 200),
    ]);

    $session = Session::factory()->for(failurePathUser())->create();

    $reflection = failurePathCoach()->generateSessionReflection($session);

    Http::assertSentCount(2);
    expect($reflection->summary)->toBe('Goede reflectie die moet blijven staan.');
});

it('geeft beide jobs tries en backoff', function (string $jobClass): void {
    $job = $jobClass === GenerateSessionReflectionJob::class
        ? new GenerateSessionReflectionJob(Session::factory()->create())
        : new GenerateWeaponInsightJob(Weapon::factory()->create());

    expect($job->tries)->toBeGreaterThan(1)
        ->and($job->backoff)->toBeArray()->not->toBeEmpty()
        ->and(method_exists($job, 'failed'))->toBeTrue();
})->with([GenerateSessionReflectionJob::class, GenerateWeaponInsightJob::class]);

/**
 * Draait één poging van de eerstvolgende job op de database-queue,
 * zoals de worker in productie dat doet.
 */
function runDatabaseWorkerOnce(): void
{
    test()->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--sleep' => 0,
    ])->assertSuccessful();
}

it('faalt de job direct zonder retry bij een ontbrekende key en raakt de failing-hook', function (string $jobClass): void {
    config(['queue.default' => 'database']);
    Http::fake();

    $sentry = Mockery::mock();
    $sentry->shouldReceive('captureException')
        ->once()
        ->with(Mockery::type(AiKeyMissingException::class));
    app()->instance('sentry', $sentry);

    $user = failurePathUser(null);

    $jobClass === GenerateSessionReflectionJob::class
        ? GenerateSessionReflectionJob::dispatch(Session::factory()->for($user)->create())
        : GenerateWeaponInsightJob::dispatch(Weapon::factory()->for($user)->create());

    runDatabaseWorkerOnce();

    Http::assertNothingSent();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->value('exception'))->toContain(AiKeyMissingException::class);
})->with([GenerateSessionReflectionJob::class, GenerateWeaponInsightJob::class]);

it('faalt de job direct bij een 401 zonder hem opnieuw in te plannen', function (): void {
    config(['queue.default' => 'database']);
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'invalid x-api-key'], 401)]);
    app()->instance('sentry', Mockery::spy());

    GenerateSessionReflectionJob::dispatch(Session::factory()->for(failurePathUser())->create());

    runDatabaseWorkerOnce();

    Http::assertSentCount(1);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});

it('plant de job opnieuw in bij een 5xx en zet hem na de laatste poging in failed_jobs', function (): void {
    config(['queue.default' => 'database']);
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'kapot'], 500)]);

    $sentry = Mockery::mock();
    $sentry->shouldReceive('captureException')->once()->with(Mockery::type(AiUnavailableException::class));
    app()->instance('sentry', $sentry);

    $session = Session::factory()->for(failurePathUser())->create();
    $reflection = AiReflection::factory()->for($session)->create(goodReflectionContent());

    GenerateSessionReflectionJob::dispatch($session);
    $tries = (new GenerateSessionReflectionJob($session))->tries;

    foreach (range(1, $tries) as $attempt) {
        DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
        runDatabaseWorkerOnce();

        if ($attempt < $tries) {
            expect(DB::table('jobs')->count())->toBe(1)
                ->and(DB::table('failed_jobs')->count())->toBe(0);
        }
    }

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and($reflection->fresh()->summary)->toBe('Goede reflectie die moet blijven staan.');
});

it('toont een nette melding in plaats van een opgeslagen foutstring', function (string $legacy): void {
    $reflection = AiReflection::factory()->create(['summary' => $legacy]);
    $insight = Weapon::factory()->create()->aiWeaponInsight()->create(['summary' => $legacy]);

    expect($reflection->summary)->toBe(AiReflection::FAILED_SUMMARY)
        ->and($reflection->hasFailedSummary())->toBeTrue()
        ->and($insight->summary)->toBe(AiWeaponInsight::FAILED_SUMMARY)
        ->and($insight->hasFailedSummary())->toBeTrue();
})->with([
    'AI-configuratie ontbreekt. Voeg je Claude API-key toe bij AI-instellingen om antwoorden te genereren.',
    'Geen AI-antwoord beschikbaar (API-fout of timeout). Probeer het later opnieuw.',
    'Geen AI-antwoord beschikbaar (timeout of netwerkfout). Probeer het opnieuw of controleer de provider.',
]);

it('laat een gewone samenvatting met rust', function (): void {
    $reflection = AiReflection::factory()->create(['summary' => 'Sterke serie 2.']);

    expect($reflection->summary)->toBe('Sterke serie 2.')
        ->and($reflection->hasFailedSummary())->toBeFalse();
});

it('toont op de sessiepagina de nette melding en niet de foutstring', function (): void {
    $user = failurePathUser();
    $session = Session::factory()->for($user)->create();
    AiReflection::factory()->for($session)->create([
        'summary' => 'Geen AI-antwoord beschikbaar (API-fout of timeout). Probeer het later opnieuw.',
    ]);

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $session->id])
        ->assertOk()
        ->assertSee(AiReflection::FAILED_SUMMARY)
        ->assertDontSee('Geen AI-antwoord beschikbaar');
});

it('toont op de wapenpagina de nette melding en niet de foutstring', function (): void {
    $user = failurePathUser();
    $weapon = Weapon::factory()->for($user)->create();
    $weapon->aiWeaponInsight()->create([
        'summary' => 'Geen AI-antwoord beschikbaar (timeout of netwerkfout). Probeer het opnieuw of controleer de provider.',
    ]);

    Livewire::actingAs($user)
        ->test(ViewWeapon::class, ['record' => $weapon->id])
        ->assertOk()
        ->assertSee(AiWeaponInsight::FAILED_SUMMARY)
        ->assertDontSee('Geen AI-antwoord beschikbaar');
});
