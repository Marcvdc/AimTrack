<?php

use App\Support\Features\AimtrackFeatureToggle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature as FeatureFacade;
use Mockery;

it('uses Laravel Pennant when the features table exists', function (): void {
    Schema::shouldReceive('hasTable')
        ->once()
        ->with('features')
        ->andReturnTrue();

    FeatureFacade::shouldReceive('active')
        ->once()
        ->with('aimtrack-ai')
        ->andReturnTrue();

    $toggle = new AimtrackFeatureToggle;

    expect($toggle->aiEnabled())->toBeTrue();
});

it('falls back to the configured default and logs once when the table is missing', function (): void {
    config()->set('features.defaults.aimtrack-ai', false);

    Schema::shouldReceive('hasTable')
        ->once()
        ->with('features')
        ->andReturnFalse();

    FeatureFacade::shouldReceive('active')->never();

    Log::shouldReceive('warning')
        ->once()
        ->with(
            'Pennant features table ontbreekt; val terug op env default.',
            Mockery::on(function (array $context): bool {
                return $context['feature'] === 'aimtrack-ai'
                    && $context['default'] === false;
            })
        );

    $toggle = new AimtrackFeatureToggle;

    expect($toggle->aiEnabled())->toBeFalse();
    expect($toggle->aiDisabled())->toBeTrue();
});

it('only checks for the features table once per instance', function (): void {
    Schema::shouldReceive('hasTable')
        ->once()
        ->with('features')
        ->andReturnTrue();

    FeatureFacade::shouldReceive('active')
        ->twice()
        ->with('aimtrack-ai')
        ->andReturnFalse();

    $toggle = new AimtrackFeatureToggle;

    expect($toggle->aiEnabled())->toBeFalse();
    expect($toggle->aiEnabled())->toBeFalse();
});
