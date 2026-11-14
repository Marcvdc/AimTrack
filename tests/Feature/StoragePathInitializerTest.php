<?php

use App\Providers\AppServiceProvider;
use App\Support\StoragePathInitializer;
use Illuminate\Filesystem\Filesystem;

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

test('na de boot van de applicatie bestaan de disk-roots, zonder Docker-entrypoint', function () {
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $this->sandbox.'/app/private');
    config()->set('filesystems.disks.public.root', $this->sandbox.'/app/public');

    app()->getProvider(AppServiceProvider::class)->boot();

    expect(is_dir($this->sandbox.'/app/private'))->toBeTrue()
        ->and(is_dir($this->sandbox.'/app/public'))->toBeTrue();
});
