<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource;
use App\Filament\Resources\SessionResource\Pages\ManageSessionShots;
use App\Jobs\GenerateSessionReflectionJob;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

it('queues an AI reflection when finishing with the preference on and AI enabled', function (): void {
    Bus::fake();

    $user = User::factory()->withAnthropicKey()->create();
    $user->setPreference('auto_ai_reflection', true);
    Feature::for($user)->activate('aimtrack-ai');
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ManageSessionShots::class, ['record' => $session->id])
        ->call('finishSession')
        ->assertRedirect(SessionResource::getUrl('view', ['record' => $session->id]));

    Bus::assertDispatched(GenerateSessionReflectionJob::class);
});

it('does not queue a reflection when the auto preference is off', function (): void {
    Bus::fake();

    $user = User::factory()->withAnthropicKey()->create();
    Feature::for($user)->activate('aimtrack-ai');
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ManageSessionShots::class, ['record' => $session->id])
        ->call('finishSession')
        ->assertRedirect(SessionResource::getUrl('view', ['record' => $session->id]));

    Bus::assertNotDispatched(GenerateSessionReflectionJob::class);
});

it('does not queue a reflection when AI is disabled even if the preference is on', function (): void {
    Bus::fake();

    $user = User::factory()->withAnthropicKey()->create();
    $user->setPreference('auto_ai_reflection', true);
    Feature::for($user)->deactivate('aimtrack-ai');
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ManageSessionShots::class, ['record' => $session->id])
        ->call('finishSession');

    Bus::assertNotDispatched(GenerateSessionReflectionJob::class);
});
