<?php

use App\Enums\TargetType;

test('target type has the five supported disciplines', function () {
    $values = array_map(fn (TargetType $t) => $t->value, TargetType::cases());

    expect($values)->toBe(['kkp_25m', 'gkp_25m', 'kkg_50m', 'kkg_100m', 'gkg_100m']);
});

test('target type values match the python TARGET_SPECS keys', function () {
    expect(TargetType::Kkp25m->value)->toBe('kkp_25m');
    expect(TargetType::Gkg100m->value)->toBe('gkg_100m');
});
