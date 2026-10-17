<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

test('de applicatie draait op de Nederlandse locale', function (): void {
    expect(app()->getLocale())->toBe('nl')
        ->and(config('app.fallback_locale'))->toBe('en');
});

test('een verplicht veld levert een Nederlandse melding op', function (): void {
    $validator = Validator::make(['bestand' => null], ['bestand' => 'required']);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('bestand'))
        ->not->toContain('field is required')
        ->toContain('is verplicht');
});

test('Filament vult :attribute met het veldlabel, dus het label komt terug in de melding', function (): void {
    // Dit is precies het geval uit het issue: het bijlage-veld heet in de UI
    // "Bestand" en leverde eerder "The bestand field is required."
    $validator = Validator::make([], ['path' => 'required'], [], ['path' => 'Bestand']);

    expect($validator->errors()->first('path'))->toBe('Bestand is verplicht.');
});

test('de overige veelgebruikte validatieregels zijn vertaald', function (string $rule, array $data, array $rules): void {
    $validator = Validator::make($data, $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first())->not->toStartWith('The ')
        ->and($validator->errors()->first())->not->toBe("validation.{$rule}");
})->with([
    'email' => ['email', ['email' => 'geen-adres'], ['email' => 'email']],
    'numeric' => ['numeric', ['aantal' => 'abc'], ['aantal' => 'numeric']],
    'max' => ['max', ['naam' => str_repeat('a', 30)], ['naam' => 'max:10']],
    'confirmed' => ['confirmed', ['wachtwoord' => 'x'], ['wachtwoord' => 'confirmed']],
    'in' => ['in', ['keuze' => 'z'], ['keuze' => 'in:a,b']],
]);

test('de Nederlandse sleutelset is compleet ten opzichte van de Engelse', function (): void {
    $dutch = require base_path('lang/nl/validation.php');
    $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

    expect(array_diff(array_keys($english), array_keys($dutch)))->toBe([]);
});

test('de Filament-vertalingen staan op Nederlands', function (): void {
    $label = Lang::get('filament-actions::modal.actions.confirm.label');

    expect($label)->toBe('Bevestigen')
        ->and($label)->not->toBe('Confirm');
});

test('een ontbrekende sleutel valt terug op Engels in plaats van op de kale sleutelnaam', function (): void {
    // 'nl' kent geen lang/nl/http.php, dus deze sleutel moet via de fallback komen.
    expect(Lang::get('pagination.previous'))->toContain('Vorige')
        ->and(Lang::get('auth.failed'))->not->toBe('auth.failed');
});
