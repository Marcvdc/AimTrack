<?php

use App\Http\Middleware\InjectUserAnthropicKey;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;

it('injecteert de user-key in de laravel/ai config op de copilot-stream-route', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);

    $request = Request::create('/copilot/stream', 'POST');
    $request->setUserResolver(fn () => $user);

    $captured = null;
    app(InjectUserAnthropicKey::class)->handle($request, function () use (&$captured) {
        $captured = config('ai.providers.anthropic.key');

        return response('ok');
    });

    expect($captured)->toBe('sk-ant-user-key');
});

it('laat de config ongemoeid voor een user zonder key', function (): void {
    config(['ai.providers.anthropic.key' => 'global-fallback']);
    $user = User::factory()->create(['anthropic_api_key' => null]);

    $request = Request::create('/copilot/stream', 'POST');
    $request->setUserResolver(fn () => $user);

    $captured = null;
    app(InjectUserAnthropicKey::class)->handle($request, function () use (&$captured) {
        $captured = config('ai.providers.anthropic.key');

        return response('ok');
    });

    expect($captured)->toBe('global-fallback');
});

it('doet niets op andere routes', function (): void {
    config(['ai.providers.anthropic.key' => 'global-fallback']);
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $captured = null;
    app(InjectUserAnthropicKey::class)->handle($request, function () use (&$captured) {
        $captured = config('ai.providers.anthropic.key');

        return response('ok');
    });

    expect($captured)->toBe('global-fallback');
});

/*
 * De tests hierboven roepen de middleware rechtstreeks aan en bewijzen dus niet
 * dat hij in de stack hangt. Deze test gaat door de volledige HTTP-stack en legt
 * vast welke sleutel er werkelijk op de uitgaande call naar Anthropic staat.
 */
it('stuurt de key van de gebruiker mee op de uitgaande copilot-call, niet de serverkey', function (): void {
    config(['ai.providers.anthropic.key' => 'sk-SERVER-ENV-KEY']);
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-USER-PROBE']);
    Feature::for($user)->activate('aimtrack-ai');

    Http::fake([
        'api.anthropic.com/*' => Http::response(implode("\n\n", [
            'event: message_start'."\n".'data: {"type":"message_start","message":{"id":"msg_1","type":"message","role":"assistant","model":"claude-haiku-4-5-20251001","content":[],"usage":{"input_tokens":1,"output_tokens":0}}}',
            'event: content_block_start'."\n".'data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}',
            'event: content_block_delta'."\n".'data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"ok"}}',
            'event: content_block_stop'."\n".'data: {"type":"content_block_stop","index":0}',
            'event: message_delta'."\n".'data: {"type":"message_delta","delta":{"stop_reason":"end_turn"},"usage":{"output_tokens":1}}',
            'event: message_stop'."\n".'data: {"type":"message_stop"}',
        ])."\n\n", 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $response = $this->actingAs($user)
        ->post('/copilot/stream', ['message' => 'Hoe ging mijn laatste sessie?', 'panel_id' => 'admin'])
        ->assertOk();

    /**
     * De copilot-controller sluit bij het streamen zelf een outputbuffer af en
     * spoelt elk event door met ob_flush(). Een buffer met een eigen handler vangt
     * alles op wat doorgespoeld wordt, zodat de buffer van PHPUnit heel blijft.
     */
    $stream = '';
    $niveau = ob_get_level();
    ob_start(function (string $stuk) use (&$stream): string {
        $stream .= $stuk;

        return '';
    });
    ob_start();
    try {
        ($response->baseResponse->getCallback())();
    } finally {
        while (ob_get_level() > $niveau) {
            ob_end_flush();
        }
    }

    expect($stream)->toContain('event: chunk')->toContain('"text":"ok"');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'api.anthropic.com')
        && $request->header('x-api-key') === ['sk-ant-USER-PROBE']);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->header('x-api-key') === ['sk-SERVER-ENV-KEY']);
});
