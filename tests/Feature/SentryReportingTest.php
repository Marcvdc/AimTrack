<?php

use App\Support\Sentry\ScrubRequestData;
use Illuminate\Support\Facades\Route;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Laravel\Http\SetRequestIpMiddleware;
use Sentry\Laravel\Http\SetRequestMiddleware;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

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
    config(['app.debug' => false]);

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

it('zet de opties die persoonsgegevens tegenhouden', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.max_request_body_size'))->toBe('never')
        ->and(config('sentry.before_send'))->toBe([ScrubRequestData::class, 'beforeSend']);
});

it('stuurt geen body, query string of IP-adres mee met een echte client', function (): void {
    // Met een DSN zet de service provider deze twee middleware globaal; hier
    // was er bij het booten geen DSN, dus hangen ze aan de testroute.
    $transport = new class implements TransportInterface
    {
        /** @var list<Event> */
        public array $events = [];

        public function send(Event $event): Result
        {
            $this->events[] = $event;

            return new Result(ResultStatus::success(), $event);
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };

    config(['sentry.dsn' => 'https://public@sentry.example.invalid/1', 'app.debug' => false]);
    app()->extend(ClientBuilder::class, fn (ClientBuilder $builder): ClientBuilder => $builder->setTransport($transport));
    app()->forgetInstance(HubInterface::class);
    app(HubInterface::class);

    Route::post('/__test/sentry-boom', fn () => throw new RuntimeException('kapot met persoonsgegevens'))
        ->middleware([SetRequestMiddleware::class, SetRequestIpMiddleware::class]);

    $this->post('/__test/sentry-boom?email=schutter%40example.test&token=geheim', [
        'email' => 'schutter@example.test',
        'password' => 'geheim-wachtwoord',
    ], [
        'REMOTE_ADDR' => '203.0.113.7',
        'HTTP_REFERER' => 'https://aimtrack.test/wachtwoord/reset?email=schutter%40example.test',
    ])->assertServerError();

    expect($transport->events)->toHaveCount(1);

    $event = $transport->events[0];
    $request = $event->getRequest();
    $payload = json_encode([$request, $event->getUser()?->getIpAddress()]);

    expect($request)->not->toHaveKeys(['query_string', 'data', 'env', 'cookies'])
        ->and($request['url'] ?? null)->toEndWith('/__test/sentry-boom')
        ->and($event->getUser()?->getIpAddress())->toBeNull()
        ->and($payload)->not->toContain('schutter')
        ->and($payload)->not->toContain('geheim')
        ->and($payload)->not->toContain('203.0.113.7');
});

it('haalt de query string en body weg in de before_send-hook', function (): void {
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://aimtrack.test/wachtwoord/reset/abc?email=schutter%40example.test#top',
        'method' => 'GET',
        'query_string' => 'email=schutter%40example.test',
        'data' => ['email' => 'schutter@example.test'],
        'headers' => [
            'Referer' => ['https://aimtrack.test/login?email=schutter%40example.test'],
            'User-Agent' => ['Pest'],
        ],
    ]);

    $request = ScrubRequestData::beforeSend($event)->getRequest();

    expect($request)->toBe([
        'url' => 'https://aimtrack.test/wachtwoord/reset/abc',
        'method' => 'GET',
        'headers' => [
            'Referer' => ['https://aimtrack.test/login'],
            'User-Agent' => ['Pest'],
        ],
    ]);
});

it('laat een event zonder request ongemoeid in de before_send-hook', function (): void {
    $event = Event::createEvent();

    expect(ScrubRequestData::beforeSend($event)->getRequest())->toBe([]);
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
