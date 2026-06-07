<?php

use App\Http\Middleware\InjectUserAnthropicKey;
use App\Models\User;
use Illuminate\Http\Request;

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
