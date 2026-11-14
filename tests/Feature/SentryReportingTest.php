<?php

use Illuminate\Support\Facades\Route;
use Sentry\SentrySdk;
use Sentry\State\Hub;

it('rapporteert een onbehandelde exception uit een route aan de Sentry-hub', function (): void {
    $hub = fakeSentryHub();
    $hub->shouldReceive('captureException')
        ->once()
        ->with(Mockery::on(fn (Throwable $e): bool => $e->getMessage() === 'kapot in de route'), Mockery::any());

    Route::get('/__test/sentry-boom', fn () => throw new RuntimeException('kapot in de route'));

    $this->get('/__test/sentry-boom')->assertServerError();
});

it('rapporteert dezelfde exception maar één keer, ook als hij twee keer gemeld wordt', function (): void {
    $hub = fakeSentryHub();
    $hub->shouldReceive('captureException')->once();

    $exception = new RuntimeException('twee keer gemeld');

    report($exception);
    report($exception);
});

it('faalt niet zonder DSN en heeft dan geen adres om naar te sturen', function (): void {
    expect(config('sentry.dsn'))->toBeNull();

    $hub = SentrySdk::getCurrentHub();
    $options = $hub->getClient()?->getOptions();

    expect($hub)->toBeInstanceOf(Hub::class)
        ->and($options?->getDsn())->toBeNull()
        ->and($options?->isSpotlightEnabled())->toBeFalse();

    Route::get('/__test/sentry-boom', fn () => throw new RuntimeException('kapot zonder dsn'));

    $this->get('/__test/sentry-boom')
        ->assertServerError()
        ->assertSee('Server Error');
});

it('stuurt geen persoonsgegevens mee', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse();
});

it('laat het sentry-logkanaal buiten de stack, ook als LOG_STACK het noemt', function (): void {
    $original = $_SERVER['LOG_STACK'] ?? null;
    $_SERVER['LOG_STACK'] = 'single,sentry,stderr';

    try {
        $config = require config_path('logging.php');
    } finally {
        if ($original === null) {
            unset($_SERVER['LOG_STACK']);
        } else {
            $_SERVER['LOG_STACK'] = $original;
        }
    }

    expect($config['channels']['stack']['channels'])->toBe(['daily', 'single', 'stderr']);
});
