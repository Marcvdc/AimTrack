<?php

declare(strict_types=1);

use App\Filament\Pages\ExportSessionsPage;
use App\Filament\Resources\SessionResource\Pages\EditSession;
use App\Filament\Resources\SessionResource\Pages\ListSessions;
use App\Filament\Resources\SessionResource\Pages\ViewSession;
use App\Filament\Resources\WeaponResource\Pages\EditWeapon;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use App\Support\DateFormat;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Livewire\Livewire;

/**
 * Deze tests asserten op de gerenderde pagina, niet op een getter. Een eerdere
 * versie van dit bestand las Table::getDefaultDateDisplayFormat() en
 * Schema::getDefaultDateDisplayFormat() uit. Die stonden goed terwijl de
 * formulierlaag er niet op aangesloten was, dus de suite was groen terwijl de
 * gebruiker in het belangrijkste invoerveld van de app "Aug 9, 2026" zag.
 *
 * De blade van de datumkiezer geeft het format in day.js-notatie aan de
 * Alpine-component mee: d-m-Y wordt DD-MM-YYYY. Staat de centrale default niet
 * aan, dan rendert daar de eigen default van DateTimePicker ("M j, Y"), oftewel
 * MMM D, YYYY. Daarom assert elke formuliertest op beide kanten.
 */
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

test('het sessieformulier rendert de datumkiezer in de vastgelegde notatie', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create(['date' => '2026-08-09']);

    $html = Livewire::actingAs($user)
        ->test(EditSession::class, ['record' => $session->id])
        ->assertOk()
        ->html();

    expect($html)->toContain('DD-MM-YYYY')
        ->and($html)->not->toContain('MMM D, YYYY');
});

test('het wapenformulier rendert de datumkiezer in de vastgelegde notatie', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create(['owned_since' => '2026-08-09']);

    $html = Livewire::actingAs($user)
        ->test(EditWeapon::class, ['record' => $weapon->id])
        ->assertOk()
        ->html();

    expect($html)->toContain('DD-MM-YYYY')
        ->and($html)->not->toContain('MMM D, YYYY');
});

test('de van- en tot-velden op de exportpagina renderen de vastgelegde notatie', function (): void {
    $user = User::factory()->create();

    $html = Livewire::actingAs($user)
        ->test(ExportSessionsPage::class)
        ->assertOk()
        ->html();

    expect($html)->toContain('DD-MM-YYYY')
        ->and($html)->not->toContain('MMM D, YYYY');
});

test('de datumkiezers vallen terug op de vastgelegde notatie en niet op hun eigen default', function (string $component, string $expected): void {
    // Aanvullende borging voor DateTimePicker en TimePicker: die staan op geen
    // enkele pagina, dus voor die twee valt er niets te renderen. Dit is wel de
    // waarde die de blade letterlijk uitschrijft, zie getDisplayFormat().
    expect($component::make('veld')->getDisplayFormat())->toBe($expected);
})->with([
    'DatePicker' => [DatePicker::class, DateFormat::DATE],
    'DateTimePicker' => [DateTimePicker::class, DateFormat::DATE_TIME_SECONDS],
    'TimePicker' => [TimePicker::class, DateFormat::TIME_SECONDS],
]);

test('een datumkiezer zonder seconden houdt ook de Nederlandse notatie aan', function (): void {
    // Filament zet seconden standaard aan op DateTimePicker en TimePicker, dus
    // beide varianten moeten op de vastgelegde notatie uitkomen.
    expect(DateTimePicker::make('veld')->seconds(false)->getDisplayFormat())->toBe(DateFormat::DATE_TIME)
        ->and(TimePicker::make('veld')->seconds(false)->getDisplayFormat())->toBe(DateFormat::TIME);
});
