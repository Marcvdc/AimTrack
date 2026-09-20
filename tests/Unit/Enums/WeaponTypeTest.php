<?php

use App\Enums\WeaponType;
use Filament\Support\Contracts\HasLabel;

it('kent luchtpistool en luchtgeweer als eigen type', function (): void {
    expect(WeaponType::AIR_PISTOL->value)->toBe('luchtpistool')
        ->and(WeaponType::AIR_RIFLE->value)->toBe('luchtgeweer');
});

it('heeft een nederlands label per type', function (WeaponType $type, string $verwacht): void {
    expect($type->label())->toBe($verwacht);
})->with([
    'luchtpistool' => [WeaponType::AIR_PISTOL, 'Luchtpistool'],
    'luchtgeweer' => [WeaponType::AIR_RIFLE, 'Luchtgeweer'],
    'pistool' => [WeaponType::PISTOL, 'Pistool'],
    'geweer' => [WeaponType::RIFLE, 'Geweer'],
    'karabijn' => [WeaponType::CARBINE, 'Karabijn'],
    'revolver' => [WeaponType::REVOLVER, 'Revolver'],
    'hagelgeweer' => [WeaponType::SHOTGUN, 'Hagelgeweer'],
    'overig' => [WeaponType::OTHER, 'Overig'],
]);

it('laat de bestaande waarden ongemoeid zodat opgeslagen rijen blijven casten', function (): void {
    $waarden = array_map(fn (WeaponType $type): string => $type->value, WeaponType::cases());

    expect($waarden)->toContain('pistool', 'geweer', 'karabijn', 'revolver', 'hagelgeweer', 'overig')
        ->and($waarden)->toHaveCount(8);
});

it('geeft geen lege label terug voor welk type dan ook', function (): void {
    foreach (WeaponType::cases() as $type) {
        expect($type->label())->not->toBe('');
    }
});

it('geeft filament hetzelfde label via de haslabel-interface', function (): void {
    expect(WeaponType::AIR_PISTOL)->toBeInstanceOf(HasLabel::class);

    foreach (WeaponType::cases() as $type) {
        expect($type->getLabel())->toBe($type->label());
    }
});
