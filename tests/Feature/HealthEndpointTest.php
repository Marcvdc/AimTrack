<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('health endpoint reports ok when database, queue and storage all work', function () {
    Storage::fake(config('filesystems.default'));

    $response = $this->getJson(route('health'));

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.queue.status', 'ok')
        ->assertJsonPath('checks.storage.status', 'ok');
});

test('health endpoint reports the default disk it probed', function () {
    Storage::fake(config('filesystems.default'));

    $this->getJson(route('health'))
        ->assertJsonPath('checks.storage.disk', config('filesystems.default'));
});

test('health endpoint leaves no probe file behind', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $this->getJson(route('health'))->assertOk();

    Storage::disk($disk)->assertMissing('healthchecks/storage-check.txt');
});

test('health endpoint degrades to 503 when the storage disk is not writable', function () {
    Storage::shouldReceive('disk')
        ->andThrow(new RuntimeException('Unable to create directory at storage/app/private'));

    $response = $this->getJson(route('health'));

    $response->assertServiceUnavailable()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.storage.status', 'failed')
        ->assertJsonPath('checks.storage.error', 'storage_unwritable')
        ->assertJsonPath('checks.database.status', 'ok');
});

test('health endpoint degrades to 503 when the database is unreachable', function () {
    Storage::fake(config('filesystems.default'));

    DB::shouldReceive('connection')
        ->andThrow(new RuntimeException('could not connect to server'));

    $this->getJson(route('health'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.database.status', 'failed')
        ->assertJsonPath('checks.database.error', 'database_unreachable');
});

test('health endpoint does not leak exception details', function () {
    Storage::shouldReceive('disk')
        ->andThrow(new RuntimeException('/var/www/html/storage/app/private is owned by root'));

    $response = $this->getJson(route('health'));

    $response->assertServiceUnavailable();
    expect($response->getContent())
        ->not->toContain('owned by root')
        ->not->toContain('/var/www/html');
});
