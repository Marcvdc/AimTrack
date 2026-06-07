<?php

use EslamRedaDiv\FilamentCopilot\Agent\ContextBuilder;
use Filament\Facades\Filament;

it('configures the copilot to introduce itself as the AimTrack AI-Coach', function () {
    $prompt = config('filament-copilot.system_prompt');

    expect($prompt)->toBeString()
        ->toContain('AimTrack AI-Coach')
        ->not->toContain('admin panel assistent')
        ->not->toContain('Filament admin panel assistant');
});

it('uses the configured coach prompt as the copilot base prompt', function () {
    $builder = app(ContextBuilder::class);

    $buildBasePrompt = (new ReflectionMethod($builder, 'buildBasePrompt'));
    $buildBasePrompt->setAccessible(true);

    expect($buildBasePrompt->invoke($builder))
        ->toBe(config('filament-copilot.system_prompt'));
});

it('does not append the config prompt a second time as additional instructions', function () {
    $plugin = Filament::getPanel('admin')->getPlugin('filament-copilot');

    expect($plugin->getSystemPrompt())->toBe('');
});
