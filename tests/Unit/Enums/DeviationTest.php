<?php

use App\Enums\Deviation;

it('heeft een nederlands label per afwijking', function (Deviation $deviation, string $verwacht): void {
    expect($deviation->label())->toBe($verwacht);
})->with([
    'links' => [Deviation::LEFT, 'Links'],
    'rechts' => [Deviation::RIGHT, 'Rechts'],
    'hoog' => [Deviation::HIGH, 'Hoog'],
    'laag' => [Deviation::LOW, 'Laag'],
    'geen' => [Deviation::NONE, 'Geen'],
]);

it('houdt de opgeslagen waarden engels zodat bestaande rijen blijven werken', function (): void {
    expect(Deviation::LEFT->value)->toBe('left')
        ->and(Deviation::from('none'))->toBe(Deviation::NONE);
});
