<?php

use App\Filament\Pages\AiCoachPage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('renders the AI-coach page when the features table is unavailable', function (): void {
    config()->set('features.defaults.aimtrack-ai', true);

    $user = User::factory()->create();

    $this->actingAs($user);

    Schema::shouldReceive('hasTable')
        ->with('features')
        ->andReturnFalse();

    Schema::shouldReceive('hasTable')
        ->andReturnTrue()
        ->byDefault();

    Livewire::test(AiCoachPage::class)
        ->assertSet('answer', null)
        ->assertOk();
});
