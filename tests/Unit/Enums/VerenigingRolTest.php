<?php

use App\Enums\VerenigingRol;

it('herkent coachrollen', function (VerenigingRol $rol, bool $verwacht): void {
    expect($rol->canCoach())->toBe($verwacht);
})->with([
    'member' => [VerenigingRol::Member, false],
    'coach' => [VerenigingRol::Coach, true],
    'admin' => [VerenigingRol::Admin, true],
]);

it('herkent beheerdersrollen', function (VerenigingRol $rol, bool $verwacht): void {
    expect($rol->canManage())->toBe($verwacht);
})->with([
    'member' => [VerenigingRol::Member, false],
    'coach' => [VerenigingRol::Coach, false],
    'admin' => [VerenigingRol::Admin, true],
]);

it('heeft een nederlands label', function (): void {
    expect(VerenigingRol::Admin->label())->toBe('Beheerder');
});
