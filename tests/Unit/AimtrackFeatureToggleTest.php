<?php

use App\Models\User;
use App\Services\Ai\AiKeyResolver;
use App\Support\Features\AimtrackFeatureToggle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Feature as FeatureFacade;

it('uses Laravel Pennant when the features table exists', function (): void {
    Schema::shouldReceive('hasTable')
        ->once()
        ->with('features')
        ->andReturnTrue();

    FeatureFacade::shouldReceive('active')
        ->once()
        ->with('aimtrack-ai')
        ->andReturnTrue();

    // Key-check stubben zodat deze unit-test los blijft van auth/DB.
    $this->instance(AiKeyResolver::class, new class extends AiKeyResolver
    {
        public function forCurrentUser(): ?string
        {
            return 'sk-ant-x';
        }
    });

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
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Pennant features table ontbreekt; val terug op env default.'
                && ($context['feature'] ?? null) === 'aimtrack-ai'
                && ($context['default'] ?? null) === false;
        });

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

it('is aiEnabled wanneer de flag actief is én de user een key heeft', function (): void {
    $this->actingAs(User::factory()->create(['anthropic_api_key' => 'sk-ant-x']));
    Feature::activate('aimtrack-ai');

    expect(app(AimtrackFeatureToggle::class)->aiEnabled())->toBeTrue();
});

it('is niet aiEnabled wanneer de user geen key heeft', function (): void {
    $this->actingAs(User::factory()->create(['anthropic_api_key' => null]));
    Feature::activate('aimtrack-ai');

    $toggle = app(AimtrackFeatureToggle::class);

    expect($toggle->aiEnabled())->toBeFalse()
        ->and($toggle->aiDisabled())->toBeTrue();
});

it('is niet aiEnabled wanneer de flag uit staat ook al heeft de user een key', function (): void {
    $this->actingAs(User::factory()->create(['anthropic_api_key' => 'sk-ant-x']));
    Feature::deactivate('aimtrack-ai');

    expect(app(AimtrackFeatureToggle::class)->aiEnabled())->toBeFalse();
});
