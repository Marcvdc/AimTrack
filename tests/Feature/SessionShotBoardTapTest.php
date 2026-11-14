<?php

use App\Livewire\SessionShotBoard;
use App\Models\Session;
use App\Models\User;
use Livewire\Livewire;

test('twee taps vlak naast elkaar leveren twee schoten op', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->call('recordShot', 0.500, 0.500)
        ->call('recordShot', 0.512, 0.505)
        ->call('recordShot', 0.508, 0.495)
        ->assertSet('pendingDeleteShotId', null);

    expect($session->shots()->count())->toBe(3);
});

test('het bord heeft een tik-handler en geen lang-indrukken-timer meer', function () {
    $view = file_get_contents(resource_path('views/livewire/session-shot-board.blade.php'));

    expect($view)
        ->toContain('@pointerup="handlePointerUp($event)"')
        ->toContain('@pointercancel="cancelPointer()"')
        ->not->toContain('@click="handleCanvasClick')
        ->not->toContain('longPress')
        ->not->toContain('console.log');
});

test('targetBoard wordt maar een keer geinstantieerd', function () {
    $view = file_get_contents(resource_path('views/livewire/session-shot-board.blade.php'));

    expect(substr_count($view, 'x-data="targetBoard('))->toBe(1)
        ->and(substr_count($view, '...targetBoard('))->toBe(0);
});

test('de verwijdermodal krijgt zijn tekst via een event naar de buitenste scope', function () {
    $view = file_get_contents(resource_path('views/livewire/session-shot-board.blade.php'));

    expect($view)
        ->toContain('x-on:shot-delete-requested="deleteConfirmationMessage = $event.detail.message"')
        ->toContain("this.\$dispatch('shot-delete-requested'");
});
