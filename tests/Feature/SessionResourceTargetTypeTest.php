<?php

use App\Enums\TargetType;
use App\Filament\Resources\SessionResource\Pages\EditSession;
use App\Models\Session;
use App\Models\User;
use Livewire\Livewire;

test('session edit form saves target_type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::test(EditSession::class, ['record' => $session->id])
        ->fillForm(['target_type' => TargetType::Kkg50m->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($session->refresh()->target_type)->toBe(TargetType::Kkg50m);
});
