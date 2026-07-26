<?php

use App\Enums\VerenigingRol;
use App\Models\User;
use App\Models\Vereniging;
use App\Services\Vereniging\VerenigingException;
use App\Services\Vereniging\VerenigingService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = app(VerenigingService::class);
});

it('voegt een bestaand lid toe op e-mailadres met een rol', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create(['email' => 'lid@example.test']);

    $this->service->voegLidToe($vereniging, 'lid@example.test', VerenigingRol::Coach);

    expect($user->fresh()->rolInVereniging($vereniging))->toBe(VerenigingRol::Coach)
        ->and($user->fresh()->active_vereniging_id)->toBe($vereniging->id);
});

it('faalt netjes bij een onbekend e-mailadres', function (): void {
    $vereniging = Vereniging::factory()->create();

    expect(fn () => $this->service->voegLidToe($vereniging, 'niemand@example.test', VerenigingRol::Member))
        ->toThrow(VerenigingException::class);
});

it('voegt een lid niet dubbel toe', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create(['email' => 'lid@example.test']);
    $this->service->voegLidToe($vereniging, 'lid@example.test', VerenigingRol::Member);

    expect(fn () => $this->service->voegLidToe($vereniging, 'lid@example.test', VerenigingRol::Member))
        ->toThrow(VerenigingException::class);
});

it('wijzigt de rol van een lid', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create();
    $vereniging->members()->attach($user, ['role' => VerenigingRol::Member->value]);

    $this->service->wijzigRol($vereniging, $user, VerenigingRol::Admin);

    expect($user->fresh()->rolInVereniging($vereniging))->toBe(VerenigingRol::Admin);
});

it('beschermt de laatste beheerder tegen degradatie', function (): void {
    $vereniging = Vereniging::factory()->create();
    $admin = User::factory()->create();
    $vereniging->members()->attach($admin, ['role' => VerenigingRol::Admin->value]);

    expect(fn () => $this->service->wijzigRol($vereniging, $admin, VerenigingRol::Member))
        ->toThrow(VerenigingException::class);
});

it('laat degradatie toe als er nog een andere beheerder is', function (): void {
    $vereniging = Vereniging::factory()->create();
    $adminA = User::factory()->create();
    $adminB = User::factory()->create();
    $vereniging->members()->attach($adminA, ['role' => VerenigingRol::Admin->value]);
    $vereniging->members()->attach($adminB, ['role' => VerenigingRol::Admin->value]);

    $this->service->wijzigRol($vereniging, $adminA, VerenigingRol::Member);

    expect($adminA->fresh()->rolInVereniging($vereniging))->toBe(VerenigingRol::Member);
});

it('verwijdert een lid en reset diens actieve vereniging', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create(['active_vereniging_id' => null]);
    $vereniging->members()->attach($user, ['role' => VerenigingRol::Member->value]);
    $user->update(['active_vereniging_id' => $vereniging->id]);

    $this->service->verwijderLid($vereniging, $user);

    expect($vereniging->members()->whereKey($user->id)->exists())->toBeFalse()
        ->and($user->fresh()->active_vereniging_id)->toBeNull();
});

it('beschermt de laatste beheerder tegen verwijdering', function (): void {
    $vereniging = Vereniging::factory()->create();
    $admin = User::factory()->create();
    $vereniging->members()->attach($admin, ['role' => VerenigingRol::Admin->value]);

    expect(fn () => $this->service->verwijderLid($vereniging, $admin))
        ->toThrow(VerenigingException::class);
});

it('bewaart en wist de gedeelde key', function (): void {
    $vereniging = Vereniging::factory()->create();

    $this->service->bewaarKey($vereniging, 'sk-ant-club');
    expect($vereniging->fresh()->anthropic_api_key)->toBe('sk-ant-club');

    $this->service->wisKey($vereniging);
    expect($vereniging->fresh()->anthropic_api_key)->toBeNull();
});

it('valideert een geldige key tegen Anthropic', function (): void {
    Http::fake(['*/v1/models' => Http::response(['data' => []], 200)]);
    $vereniging = Vereniging::factory()->withKey('sk-ant-club')->create();

    expect($this->service->testKey($vereniging))->toBeTrue()
        ->and($vereniging->fresh()->ai_key_verified_at)->not->toBeNull();
});

it('meldt een ongeldige key', function (): void {
    Http::fake(['*/v1/models' => Http::response([], 401)]);
    $vereniging = Vereniging::factory()->withKey('sk-ant-fout')->create();

    expect($this->service->testKey($vereniging))->toBeFalse();
});
