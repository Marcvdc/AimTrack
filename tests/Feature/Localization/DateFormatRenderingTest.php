<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource\Pages\ListSessions;
use App\Filament\Resources\SessionResource\Pages\ViewSession;
use App\Models\Session;
use App\Models\User;
use App\Support\DateFormat;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Livewire\Livewire;

test('een kale ->date()-kolom rendert d-m-Y en niet de Amerikaanse Filament-default', function (): void {
    $user = User::factory()->create();
    Session::factory()->for($user)->create(['date' => '2026-08-09']);

    Livewire::actingAs($user)
        ->test(ListSessions::class)
        ->assertOk()
        ->assertSee('09-08-2026')
        ->assertDontSee('Aug 9, 2026');
});

test('de sessie-detailpagina toont de datum in de vastgelegde notatie', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create(['date' => '2026-08-09']);

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $session->id])
        ->assertOk()
        ->assertSee('09-08-2026');
});

test('de Filament-defaults staan centraal op de vastgelegde notatie', function (): void {
    $user = User::factory()->create();
    Session::factory()->for($user)->create();

    $livewire = Livewire::actingAs($user)->test(ListSessions::class)->instance();

    $table = Table::make($livewire);
    $schema = Schema::make($livewire);

    expect($table->getDefaultDateDisplayFormat())->toBe(DateFormat::DATE)
        ->and($table->getDefaultDateTimeDisplayFormat())->toBe(DateFormat::DATE_TIME)
        ->and($table->getDefaultTimeDisplayFormat())->toBe(DateFormat::TIME)
        ->and($schema->getDefaultDateDisplayFormat())->toBe(DateFormat::DATE)
        ->and($schema->getDefaultDateTimeDisplayFormat())->toBe(DateFormat::DATE_TIME)
        ->and($schema->getDefaultTimeDisplayFormat())->toBe(DateFormat::TIME);
});
