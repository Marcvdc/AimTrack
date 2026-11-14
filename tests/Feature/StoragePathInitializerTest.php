<?php

use App\Providers\AppServiceProvider;
use App\Support\StoragePathInitializer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Issue #159: StoragePathInitializer maakte alleen de framework-mappen aan. De
 * roots van de filesystem-disks (storage/app/private en storage/app/public)
 * ontbraken, waardoor ze buiten de Docker-entrypoint pas bij de eerste write
 * ontstonden, en dat faalt zodra de parent niet schrijfbaar is.
 *
 * De disk-roots wijzen hier naar een tijdelijke map, zodat de test aantoont dat
 * de initializer ze zelf aanmaakt in plaats van dat ze toevallig al bestonden.
 */
beforeEach(function () {
    $this->sandbox = sys_get_temp_dir().'/aimtrack-storage-'.bin2hex(random_bytes(6));
});

afterEach(function () {
    if (is_dir($this->sandbox)) {
        chmod($this->sandbox, 0755);
    }

    (new Filesystem)->deleteDirectory($this->sandbox);
});

test('de root van de default local-disk wordt aangemaakt', function () {
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $this->sandbox.'/app/private');

    StoragePathInitializer::ensure();

    expect(is_dir($this->sandbox.'/app/private'))->toBeTrue();
});

test('de root van de publieke disk wordt aangemaakt', function () {
    config()->set('filesystems.disks.public.root', $this->sandbox.'/app/public');

    StoragePathInitializer::ensure();

    expect(is_dir($this->sandbox.'/app/public'))->toBeTrue();
});

test('de root volgt de configuratie van de default disk in plaats van een vast pad', function () {
    config()->set('filesystems.disks.uploads', [
        'driver' => 'local',
        'root' => $this->sandbox.'/uploads',
    ]);
    config()->set('filesystems.default', 'uploads');

    StoragePathInitializer::ensure();

    expect(is_dir($this->sandbox.'/uploads'))->toBeTrue();
});

test('een default disk die niet lokaal is levert geen map op en geen fout', function () {
    config()->set('filesystems.disks.s3.root', $this->sandbox.'/s3');
    config()->set('filesystems.default', 's3');

    StoragePathInitializer::ensure();

    expect(is_dir($this->sandbox.'/s3'))->toBeFalse();
});

test('een bestaande disk-root blijft met inhoud en al staan', function () {
    mkdir($this->sandbox.'/app/private/bijlagen', 0755, true);
    file_put_contents($this->sandbox.'/app/private/bijlagen/schietkaart.jpg', 'bestaand');
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $this->sandbox.'/app/private');

    StoragePathInitializer::ensure();

    expect(file_get_contents($this->sandbox.'/app/private/bijlagen/schietkaart.jpg'))->toBe('bestaand');
});

test('AppServiceProvider::boot() maakt de disk-roots aan, zonder Docker-entrypoint', function () {
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $this->sandbox.'/app/private');
    config()->set('filesystems.disks.public.root', $this->sandbox.'/app/public');

    app()->getProvider(AppServiceProvider::class)->boot();

    expect(is_dir($this->sandbox.'/app/private'))->toBeTrue()
        ->and(is_dir($this->sandbox.'/app/public'))->toBeTrue();
});

/*
 * ensure() draait bij elke boot. Een exception bij een niet-schrijfbare disk-root
 * zou elke request een 500 geven, ook /health, dat juist storage_unwritable met
 * een 503 moet melden. De fout wordt dus gelogd en de boot gaat door.
 */
test('een niet-schrijfbare disk-root geeft geen exception maar een waarschuwing', function () {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        test()->markTestSkipped('Als root negeert het bestandssysteem de schrijfrechten.');
    }

    mkdir($this->sandbox, 0755);
    chmod($this->sandbox, 0555);
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $this->sandbox.'/private');
    Log::spy();

    app()->getProvider(AppServiceProvider::class)->boot();

    expect(is_dir($this->sandbox.'/private'))->toBeFalse();
    Log::shouldHaveReceived('warning')
        ->with('Storage-map kon niet worden aangemaakt', ['path' => $this->sandbox.'/private'])
        ->once();
});

test('met een niet-schrijfbare disk-root meldt /health storage_unwritable in plaats van een 500', function (string $route) {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        test()->markTestSkipped('Als root negeert het bestandssysteem de schrijfrechten.');
    }

    mkdir($this->sandbox, 0755);
    chmod($this->sandbox, 0555);
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $this->sandbox.'/private');
    Storage::forgetDisk('local');

    StoragePathInitializer::ensure();

    $this->getJson(route($route))
        ->assertServiceUnavailable()
        ->assertJsonPath('checks.storage.error', 'storage_unwritable');
})->with(['health', 'api.health']);
